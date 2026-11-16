<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Closure;
use Config\Outbound;
use FilesystemIterator;
use Modules\Core\Archive\ArchiveRefused;
use Modules\Core\Archive\SafeZip;
use Modules\Core\Config\Version;
use Modules\Core\Http\OutboundClient;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Updates\Config\Updates as UpdatesConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * The update procedure of `php spark app:update` (docs/updates.md). Console only.
 *
 * 1. Checks: a newer release, no maintenance mode already on, no local changes (git: tracked
 *    files changed; archive: files changed since the last archive update), a trusted public key
 *    and a signed archive (archive method).
 * 2. Gets the code and **verifies it before anything is applied**: archive → download the
 *    archive, its `.sha256` and `.minisig`, check the SHA-256 and the Ed25519 signature
 *    (SignatureVerifier); git → `git fetch --tags` and `git verify-tag`. A release that does not
 *    verify is refused and nothing changes.
 * 3. Maintenance mode on (`MaintenanceMode`, reason `updates.update`, no bypass), a full backup
 *    (Backup module), the new code (archive: files copied over the installation except the
 *    protected paths, replaced and removed files kept in `writable/updates/previous/<version>`;
 *    git: `git merge --ff-only <tag>`), `composer install --no-dev`, `php spark modules:sync`
 *    and `php spark app:cache-clear` in new processes (they load the new code), maintenance off.
 * 4. On a failure after maintenance mode was turned on, it stays on and the message names the
 *    backup and how to restore it.
 */
final class Updater
{
    public const MAINTENANCE_REASON = 'updates.update';

    private readonly UpdatesConfig $config;

    private readonly CommandRunner $runner;

    private readonly MaintenanceMode $maintenance;

    /** @var Closure(string, string|null): void */
    private readonly Closure $output;

    /**
     * @param (Closure(string): string)|null       $backup takes the backup password, returns the backup file name
     * @param (Closure(string, string|null): void)|null $output a line and its CLI colour
     */
    public function __construct(
        ?UpdatesConfig $config = null,
        private readonly ?ReleaseSource $source = null,
        ?CommandRunner $runner = null,
        ?MaintenanceMode $maintenance = null,
        private readonly ?Closure $backup = null,
        ?Closure $output = null,
        private readonly ?OutboundClient $downloads = null,
    ) {
        $this->config      = $config ?? config(UpdatesConfig::class);
        $this->runner      = $runner ?? new ProcessRunner();
        $this->maintenance = $maintenance ?? new MaintenanceMode();
        $this->output      = $output ?? static function (string $line, ?string $color): void {
        };
    }

    /**
     * `git` when the installation is a git checkout (method `auto`), else `archive`.
     */
    public function method(): string
    {
        if (in_array($this->config->method, ['git', 'archive'], true)) {
            return $this->config->method;
        }

        return is_dir($this->root() . '.git') ? 'git' : 'archive';
    }

    /**
     * The newest release when it is newer than the installed version.
     *
     * @throws UpdateException
     */
    public function available(): ?Release
    {
        $latest = $this->source()->latest($this->config->prereleases);

        return $latest !== null && VersionComparator::isNewer($latest->version, Version::CORE) ? $latest : null;
    }

    /**
     * What would stop the update (each a translated message); empty = it can run.
     *
     * @return list<string>
     */
    public function problems(Release $release): array
    {
        $problems = [];

        if ($this->maintenance->isOn()) {
            $problems[] = lang('Updates.error.maintenanceOn');
        }

        if ($this->method() === 'archive') {
            if ((new SignatureVerifier($this->config->keys()))->keys() === []) {
                $problems[] = lang('Updates.error.noKey');
            }

            if ($release->archive() === null) {
                $problems[] = lang('Updates.error.unsigned', [$release->tag]);
            }
        }

        $changes = $this->localChanges();

        if ($changes !== null && $changes !== []) {
            $problems[] = lang('Updates.error.localChanges', [implode(', ', array_slice($changes, 0, 10)) . (count($changes) > 10 ? ', …' : '')]);
        }

        return $problems;
    }

    /**
     * Files changed in the installation since it was installed or last updated; null when this
     * cannot be known (an archive install never updated by `app:update`).
     *
     * @return list<string>|null
     */
    public function localChanges(): ?array
    {
        if ($this->method() === 'git') {
            $result = $this->runner->run([$this->config->gitBinary, 'status', '--porcelain', '--untracked-files=no'], $this->root());

            if ($result['code'] !== 0) {
                throw new UpdateException(lang('Updates.error.command', ['git status', trim($result['output'])]));
            }

            return array_values(array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', $result['output']) ?: [])));
        }

        $manifest = $this->installedFiles();

        if ($manifest === null) {
            return null;
        }

        $changed = [];

        foreach ($manifest as $path => $hash) {
            $file = $this->root() . $path;

            if (! is_file($file) || ! hash_equals($hash, (string) hash_file('sha256', $file))) {
                $changed[] = $path;
            }
        }

        return $changed;
    }

    /**
     * The steps `app:update` takes for a release (shown by `--dry-run`).
     *
     * @return list<string>
     */
    public function plan(Release $release): array
    {
        $git   = $this->method() === 'git';
        $steps = [lang('Updates.plan.checks')];

        if ($git) {
            $steps[] = lang('Updates.plan.gitFetch', [$this->config->gitRemote]);
            $steps[] = lang('Updates.plan.gitVerify', [$release->tag]);
        } else {
            $archive = $release->archive();
            $steps[] = lang('Updates.plan.download', [$archive['name'] ?? '—']);
            $steps[] = lang('Updates.plan.verify');
        }

        $steps[] = lang('Updates.plan.maintenance');
        $steps[] = lang('Updates.plan.backup');
        $steps[] = $git ? lang('Updates.plan.gitMerge', [$release->tag]) : lang('Updates.plan.copy', [implode(', ', $this->config->protectedPaths)]);
        $steps[] = lang('Updates.plan.composer');
        $steps[] = lang('Updates.plan.sync');
        $steps[] = lang('Updates.plan.caches');
        $steps[] = lang('Updates.plan.maintenanceOff');

        return $steps;
    }

    /**
     * Updates the installation to a release.
     *
     * @throws UpdateException
     */
    public function update(Release $release, string $password): void
    {
        $problems = $this->problems($release);

        if ($problems !== []) {
            throw new UpdateException(implode(' ', $problems));
        }

        $git     = $this->method() === 'git';
        $staging = null;

        // Verified before anything is applied.
        if ($git) {
            $this->say(lang('Updates.step.gitFetch'));
            $this->command([$this->config->gitBinary, 'fetch', '--tags', '--', $this->config->gitRemote]);
            $this->say(lang('Updates.step.gitVerify', [$release->tag]));
            $verify = $this->runner->run([$this->config->gitBinary, 'verify-tag', '--', $release->tag], $this->root());

            if ($verify['code'] !== 0) {
                $this->refused($release, trim($verify['output']));

                throw new UpdateException(lang('Updates.error.tagNotVerified', [$release->tag]));
            }
        } else {
            $staging = $this->downloadAndVerify($release);
        }

        $this->say(lang('Updates.step.maintenance'));
        $this->maintenance->enable(self::MAINTENANCE_REASON);
        $backupFile = null;

        try {
            $this->say(lang('Updates.step.backup'));
            $backupFile = ($this->backup ?? static function (string $password): string {
                return (string) (new \Modules\Backup\Services\BackupService())->create($password, 'full', 'update')['file'];
            })($password);
            $this->say(lang('Updates.step.backupDone', [$backupFile]), 'green');

            if ($git) {
                $this->say(lang('Updates.step.gitMerge', [$release->tag]));
                $this->command([$this->config->gitBinary, 'merge', '--ff-only', '--', 'refs/tags/' . $release->tag]);
            } else {
                $this->say(lang('Updates.step.copy'));
                $this->apply((string) $staging, $release);
            }

            $this->say(lang('Updates.step.composer'));
            $this->command([$this->config->composerBinary, 'install', '--no-dev', '--no-interaction', '--no-progress', '--optimize-autoloader']);
            $this->say(lang('Updates.step.sync'));
            $this->command([$this->config->phpBinary, 'spark', 'modules:sync']);
            $this->say(lang('Updates.step.caches'));
            $this->command([$this->config->phpBinary, 'spark', 'app:cache-clear']);
        } catch (Throwable $e) {
            security_log('Updates', 'update_failed', ['resource' => 'updates', 'summary' => $release->version . ': ' . $e->getMessage(), 'changes' => ['backup' => $backupFile]]);

            throw new UpdateException(lang('Updates.error.failed', [
                $e->getMessage(),
                $backupFile ?? lang('Updates.error.noBackup'),
                $this->previousFolder(),
            ]), 0, $e);
        } finally {
            if ($staging !== null) {
                self::removeFolder(dirname($staging));
            }
        }

        $this->maintenance->disable();
        activity_log('Updates', 'updated', ['resource' => 'updates', 'summary' => Version::CORE . ' → ' . $release->version, 'changes' => ['backup' => $backupFile, 'method' => $git ? 'git' : 'archive']]);
        $this->say(lang('Updates.step.done', [$release->version]), 'green');
    }

    /**
     * Downloads the archive with its checksum and signature, verifies both, and extracts it
     * into a staging folder; returns the folder that holds the application's files.
     *
     * @throws UpdateException
     */
    public function downloadAndVerify(Release $release): string
    {
        $archive = $release->archive();

        if ($archive === null) {
            throw new UpdateException(lang('Updates.error.unsigned', [$release->tag]));
        }

        $work = rtrim($this->config->path, '/') . '/staging-' . bin2hex(random_bytes(6));
        mkdir($work, 0700, true);
        $file = $work . '/release.zip';

        try {
            $this->say(lang('Updates.step.download', [$archive['name']]));
            $client = $this->downloadClient();
            file_put_contents($file, $this->source()->download($archive['url'], $client));
            $checksum  = $this->source()->download($archive['checksum']);
            $signature = $this->source()->download($archive['signature']);

            $this->say(lang('Updates.step.verify'));

            try {
                (new SignatureVerifier($this->config->keys()))->verifyFile($file, $signature, $checksum);
            } catch (UpdateException $e) {
                $this->refused($release, $e->getMessage());

                throw $e;
            }

            $this->say(lang('Updates.step.verified'), 'green');

            return $this->extract($file, $work . '/files');
        } catch (Throwable $e) {
            self::removeFolder($work);

            throw $e instanceof UpdateException ? $e : new UpdateException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Extracts a verified archive through SafeZip. The archive holds one top-level folder (the
     * application's root, e.g. `kpskeletor-1.2.0/`).
     *
     * @throws UpdateException
     */
    public function extract(string $file, string $target): string
    {
        $zip = new ZipArchive();

        if ($zip->open($file, ZipArchive::RDONLY) !== true || $zip->numFiles === 0) {
            throw new UpdateException(lang('Updates.error.archive'));
        }

        $first = (string) $zip->getNameIndex(0);
        $zip->close();
        $top = explode('/', $first)[0];

        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $top) !== 1 || ! str_contains($first, '/')) {
            throw new UpdateException(lang('Updates.error.archive'));
        }

        try {
            $safe = SafeZip::open($file, [$top . '/'], $this->config->maxArchiveBytes * 20);
            $safe->extract($top . '/', $target, 0644, 0755);
            $safe->close();
        } catch (ArchiveRefused $e) {
            throw new UpdateException($e->getMessage(), 0, $e);
        }

        if (! is_file($target . '/spark') || ! is_dir($target . '/modules/Core')) {
            throw new UpdateException(lang('Updates.error.archive'));
        }

        return $target;
    }

    /**
     * Copies the new files over the installation (protected paths are never written), keeps
     * the replaced and removed files under `previous/<installed version>` and records the
     * installed files for the next local-changes check.
     */
    public function apply(string $staging, Release $release): void
    {
        $root     = $this->root();
        $previous = $this->previousFolder();
        $old      = $this->installedFiles() ?? [];
        $new      = [];

        foreach (self::files($staging) as $relative) {
            if ($this->isProtected($relative)) {
                continue;
            }

            $source      = $staging . '/' . $relative;
            $destination = $root . $relative;
            $new[$relative] = (string) hash_file('sha256', $source);

            if (is_file($destination)) {
                if (hash_equals($new[$relative], (string) hash_file('sha256', $destination))) {
                    continue;
                }

                $this->keep($destination, $previous . '/' . $relative);
            }

            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }

            if (! copy($source, $destination)) {
                throw new UpdateException(lang('Updates.error.write', [$relative]));
            }

            @chmod($destination, fileperms($source) & 0777);
        }

        foreach (array_diff_key($old, $new) as $relative => $hash) {
            $file = $root . $relative;

            if (! $this->isProtected($relative) && is_file($file)) {
                $this->keep($file, $previous . '/' . $relative);
                @unlink($file);
            }
        }

        $this->writeInstalledFiles($new);
    }

    public function isProtected(string $relative): bool
    {
        foreach ($this->config->protectedPaths as $path) {
            if ($relative === rtrim($path, '/') || (str_ends_with($path, '/') && str_starts_with($relative, $path))) {
                return true;
            }
        }

        return false;
    }

    public function previousFolder(): string
    {
        return rtrim($this->config->path, '/') . '/previous/' . Version::CORE;
    }

    /**
     * @return array<string, string>|null relative path => SHA-256, null before the first archive update
     */
    public function installedFiles(): ?array
    {
        $file = rtrim($this->config->path, '/') . '/files.json';

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_filter($data, static fn ($hash, $path): bool => is_string($hash) && is_string($path), ARRAY_FILTER_USE_BOTH) : null;
    }

    /**
     * @param array<string, string> $files
     */
    private function writeInstalledFiles(array $files): void
    {
        ksort($files);
        $file = rtrim($this->config->path, '/') . '/files.json';
        file_put_contents($file, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($file, 0640);
    }

    private function keep(string $file, string $copy): void
    {
        if (is_file($copy)) {
            return;
        }

        if (! is_dir(dirname($copy))) {
            mkdir(dirname($copy), 0750, true);
        }

        copy($file, $copy);
    }

    /**
     * @param list<string> $command
     *
     * @throws UpdateException
     */
    private function command(array $command): void
    {
        $result = $this->runner->run($command, $this->root());

        if ($result['code'] !== 0) {
            throw new UpdateException(lang('Updates.error.command', [implode(' ', array_slice($command, 0, 3)), mb_substr(trim($result['output']), -2000)]));
        }
    }

    private function refused(Release $release, string $reason): void
    {
        security_log('Updates', 'release_refused', ['resource' => 'updates', 'summary' => $release->tag . ': ' . $reason]);
    }

    private function downloadClient(): OutboundClient
    {
        if ($this->downloads !== null) {
            return $this->downloads;
        }

        $outbound           = clone config(Outbound::class);
        $outbound->maxBytes = $this->config->maxArchiveBytes;
        $outbound->timeout  = $this->config->downloadTimeout;

        return new OutboundClient($outbound);
    }

    private function source(): ReleaseSource
    {
        return $this->source ?? new ReleaseSource($this->config->repository, $this->config->token);
    }

    private function root(): string
    {
        return rtrim($this->config->rootPath, '/') . '/';
    }

    private function say(string $line, ?string $color = null): void
    {
        ($this->output)($line, $color);
    }

    /**
     * @return list<string> relative paths of the files under a folder
     */
    private static function files(string $folder): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $files[] = substr($file->getPathname(), strlen(rtrim($folder, '/')) + 1);
            }
        }

        sort($files);

        return $files;
    }

    public static function removeFolder(string $folder): void
    {
        if (! is_dir($folder)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($folder);
    }
}
