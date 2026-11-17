<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Closure;
use Modules\Core\Config\Version;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Modules\SemVer;
use Modules\Core\Modules\SyncFailedException;
use Modules\Core\Modules\SyncReport;
use Modules\Core\Theme\ThemeRegistry;
use Throwable;

/**
 * Installs or upgrades a validated module or theme package (`modules:install`, `themes:install`,
 * staged installs; docs/installing-modules.md):
 *
 * 1. checks dependencies, the Core version, the author of an installed module of the same name
 *    and the version (a downgrade or a reinstall needs `force`);
 * 2. makes a backup (Safeguards, the Backup module) and enters maintenance mode;
 * 3. extracts to a private folder and runs `php -l` on every PHP file (PackageValidator);
 * 4. moves it into place: the new folder is copied next to the target as a hidden folder and
 *    renamed (atomic), the previous version is renamed aside;
 * 5. runs `modules:sync` in a new PHP process (SparkSync); a new module stays disabled unless `enable` (or `Packages.autoEnable`),
 *    an upgraded one keeps its state;
 * 6. on any failure, the previous folder is put back and `modules:sync` runs again.
 *
 * Only the console runs it: the web server user never writes into `modules/` or `themes/`.
 */
final class PackageInstaller
{
    private readonly string $modulesRoot;

    private readonly string $themesRoot;

    private readonly string $work;

    /** @var Closure(string, string): void */
    private Closure $say;

    /**
     * @param (Closure(): SyncReport)|null $sync Runs `modules:sync` (default: SparkSync, a new process)
     */
    public function __construct(
        private readonly PackageValidator $validator = new PackageValidator(),
        private readonly Safeguards $safeguards = new Safeguards(),
        private readonly ?Closure $sync = null,
        private readonly ?ModuleToggler $toggler = null,
        ?string $modulesRoot = null,
        ?string $themesRoot = null,
        ?string $work = null,
        private readonly ?DemoDataManager $demo = null,
    ) {
        $this->modulesRoot = rtrim($modulesRoot ?? ROOTPATH . 'modules', '/');
        $this->themesRoot  = rtrim($themesRoot ?? (new ThemeRegistry())->root(), '/');
        $this->work        = rtrim($work ?? WRITEPATH . 'packages', '/');
        $this->say         = static function (string $line, string $color = ''): void {
        };
    }

    /**
     * Where the progress lines go (the console command prints them; staged installs log them).
     *
     * @param Closure(string, string): void $say
     */
    public function onProgress(Closure $say): self
    {
        $this->say = $say;

        return $this;
    }

    public function targetOf(PackageInfo $info): string
    {
        return ($info->isModule() ? $this->modulesRoot : $this->themesRoot) . '/' . $info->name;
    }

    /**
     * What installing would do, and why it cannot be done.
     *
     * @return array{action: 'install'|'upgrade'|'reinstall'|'downgrade', from: string|null, problems: list<string>}
     */
    public function plan(PackageInfo $info, bool $force = false, bool $checkWritable = true): array
    {
        $problems = [];
        $target   = $this->targetOf($info);
        $from     = null;
        $action   = 'install';

        if (is_dir($target)) {
            $installed = $this->installedManifest($info, $target);
            $from      = $installed['version'];

            if ($installed['author'] !== $info->author) {
                $problems[] = lang('Core.packages.otherAuthor', [$info->name, $installed['author'] !== '' ? $installed['author'] : '—', $info->author !== '' ? $info->author : '—']);
            }

            if ($from !== null && SemVer::isVersion($from)) {
                $cmp    = SemVer::compare($info->version, $from);
                $action = $cmp > 0 ? 'upgrade' : ($cmp === 0 ? 'reinstall' : 'downgrade');

                if ($action === 'downgrade' && ! $force) {
                    $problems[] = lang('Core.packages.downgrade', [$info->name, $from, $info->version]);
                } elseif ($action === 'reinstall' && ! $force) {
                    $problems[] = lang('Core.packages.sameVersion', [$info->name, $from]);
                }
            } else {
                $action = 'upgrade';
            }
        }

        if ($info->isModule()) {
            $problems = [...$problems, ...$this->dependencyProblems($info)];
        } elseif ($info->parent !== null && ! is_file($this->themesRoot . '/' . $info->parent . '/Theme.php')) {
            $problems[] = lang('Core.packages.parentMissing', [$info->parent]);
        }

        $root = $info->isModule() ? $this->modulesRoot : $this->themesRoot;

        if ($checkWritable && (! is_dir($root) || ! is_writable($root))) {
            $problems[] = lang('Core.packages.notWritable', [$root]);
        }

        return ['action' => $action, 'from' => $from, 'problems' => $problems];
    }

    /**
     * @param array{enable?: bool, force?: bool, demo?: bool, backup?: bool, password?: string|null, userId?: int|null} $options
     *
     * @return array{action: string, from: string|null, to: string, backup: string|null, enabled: list<string>}
     *
     * @throws PackageRefused nothing is changed (or the previous version was put back)
     */
    public function install(string $zip, PackageInfo $info, array $options = []): array
    {
        $plan = $this->plan($info, $options['force'] ?? false);

        if ($plan['problems'] !== []) {
            throw new PackageRefused(implode("\n", $plan['problems']));
        }

        $label = $info->name . ' ' . $info->version;
        $this->line(lang('Core.packages.step.backup'));
        $backup = $this->safeguards->backup($plan['action'] . ' ' . $label, $options['password'] ?? null, $options['backup'] ?? true, $options['userId'] ?? null);
        $this->line($backup !== null ? lang('Core.packages.step.backupDone', [$backup]) : lang('Core.packages.step.noBackup'), $backup !== null ? 'green' : 'yellow');

        $this->line(lang('Core.packages.step.maintenance'));
        $this->safeguards->enterMaintenance();

        $target   = $this->targetOf($info);
        $stage    = $this->work . '/stage-' . bin2hex(random_bytes(6));
        $incoming = dirname($target) . '/.' . $info->name . '.new-' . bin2hex(random_bytes(4));
        $previous = null;
        $moved    = false;
        $enabled  = [];

        try {
            if (! is_dir($this->work)) {
                mkdir($this->work, 0750, true);
            }

            $this->line(lang('Core.packages.step.extract'));
            $extracted = $this->validator->extract($zip, $info, $stage, $label);

            // Copied next to the target first (same file system), then renamed: atomic.
            self::copyFolder($extracted, $incoming);

            if (is_dir($target)) {
                $previous = dirname($target) . '/.' . $info->name . '.old-' . bin2hex(random_bytes(4));

                if (! @rename($target, $previous)) {
                    throw new PackageRefused(lang('Core.packages.notWritable', [$target]));
                }
            }

            if (! @rename($incoming, $target)) {
                throw new PackageRefused(lang('Core.packages.notWritable', [$target]));
            }

            $moved = true;

            $this->line(lang('Core.packages.step.sync'));
            $report = $this->runSync();

            foreach ($report->lines() as $line) {
                $this->line('  ' . $line);
            }

            if ($info->isModule() && ($options['enable'] ?? false)) {
                $enabled = $this->toggler()->enable($info->name, true);

                if ($enabled !== []) {
                    $this->line(lang('Core.toggle.enabled', [implode(', ', $enabled)]), 'green');
                }
            } elseif ($info->isModule()) {
                $row = model(ModuleModel::class, false)->where('name', $info->name)->first();

                if (is_array($row) && (int) $row['enabled'] === 0) {
                    $this->line(lang('Core.packages.step.disabled', [$info->name]), 'yellow');
                }
            }
        } catch (Throwable $e) {
            $this->line(lang('Core.packages.step.rollback'), 'yellow');
            PackageValidator::removeFolder($incoming);

            if ($moved) {
                PackageValidator::removeFolder($target);
            }

            if ($previous !== null) {
                @rename($previous, $target);
            }

            try {
                $this->runSync();
            } catch (Throwable) {
                // The previous state is back on disk; the operator sees the original error.
            }

            security_log('Core', 'package_failed', ['resource' => 'modules', 'summary' => mb_substr($label . ': ' . $e->getMessage(), 0, 250), 'changes' => ['backup' => $backup]]);

            $message = $e instanceof SyncFailedException ? implode("\n", $e->errors) : $e->getMessage();

            throw new PackageRefused(lang('Core.packages.installFailed', [$label, $message]), 0, $e);
        } finally {
            PackageValidator::removeFolder($stage);
            $this->safeguards->leaveMaintenance();
        }

        if ($previous !== null) {
            PackageValidator::removeFolder($previous);
        }

        activity_log('Core', $plan['action'] === 'install' ? 'package_installed' : 'package_upgraded', [
            'resource' => 'modules',
            'summary'  => $label,
            'changes'  => ['type' => $info->type, 'from' => $plan['from'], 'to' => $info->version, 'backup' => $backup, 'sha256' => $info->sha256, 'signed' => $info->signed],
        ]);

        if ($info->isModule() && ($options['demo'] ?? false)) {
            $manager = $this->demo ?? new DemoDataManager();

            try {
                $manager->assertAllowed();
                $manager->load($info->name);
                $this->line(lang('Core.demo.loaded', [$info->name]), 'green');
            } catch (Throwable $e) {
                $this->line($e->getMessage(), 'yellow');
            }
        }

        $this->line(lang('Core.packages.step.done', [$label]), 'green');

        return ['action' => $plan['action'], 'from' => $plan['from'], 'to' => $info->version, 'backup' => $backup, 'enabled' => $enabled];
    }

    /**
     * @return list<string>
     */
    private function dependencyProblems(PackageInfo $info): array
    {
        $problems  = [];
        $installed = $this->installedVersions();

        foreach ($info->requires as $module => $constraint) {
            $version = $module === 'Core' ? Version::CORE : ($installed[$module] ?? null);

            if ($version === null) {
                $problems[] = lang('Core.packages.dependencyMissing', [$info->name, $module, $constraint]);
            } elseif (SemVer::isVersion($version) && ! SemVer::satisfies($version, $constraint)) {
                $problems[] = lang('Core.packages.dependencyVersion', [$info->name, $module, $constraint, $version]);
            }
        }

        if (! isset($info->requires['Core'])) {
            $problems[] = lang('Core.packages.coreConstraint', [$info->name]);
        }

        return $problems;
    }

    /**
     * Versions of the modules whose folders exist (read from their manifests, not run).
     *
     * @return array<string, string>
     */
    private function installedVersions(): array
    {
        $versions = [];
        $reader   = new ManifestReader();

        foreach ((new ModuleDiscovery([$this->modulesRoot, ...$this->extraRoots()]))->folders() as $name => $path) {
            try {
                $version = ManifestReader::string($reader->readFile($path . '/Config/Module.php')['properties'], 'version');
            } catch (Throwable) {
                $version = '';
            }

            $versions[$name] = $version;
        }

        $versions['Core'] = Version::CORE;

        return $versions;
    }

    /**
     * @return list<string>
     */
    private function extraRoots(): array
    {
        $default = rtrim(ROOTPATH . 'modules', '/');

        return $this->modulesRoot === $default ? [] : [$default];
    }

    /**
     * @return array{version: string|null, author: string}
     */
    private function installedManifest(PackageInfo $info, string $target): array
    {
        $file = $target . ($info->isModule() ? '/Config/Module.php' : '/Theme.php');

        try {
            $properties = (new ManifestReader())->readFile($file)['properties'];
        } catch (Throwable) {
            return ['version' => null, 'author' => $info->author];
        }

        $version = ManifestReader::string($properties, 'version');

        if ($info->isModule() && $version === '') {
            $row     = model(ModuleModel::class, false)->where('name', $info->name)->first();
            $version = is_array($row) ? (string) $row['version'] : '';
        }

        return ['version' => $version !== '' ? $version : null, 'author' => ManifestReader::string($properties, 'author')];
    }

    /**
     * `modules:sync` in a new process by default (SparkSync): this process may hold the previous
     * version's classes.
     */
    private function runSync(): SyncReport
    {
        return $this->sync !== null ? ($this->sync)() : (new SparkSync($this->say))();
    }

    private function toggler(): ModuleToggler
    {
        return $this->toggler ?? new ModuleToggler();
    }

    private function line(string $line, string $color = ''): void
    {
        ($this->say)($line, $color);
    }

    /**
     * Copies a folder (files 0644, folders 0755).
     */
    public static function copyFolder(string $from, string $to): void
    {
        if (! is_dir($to) && ! @mkdir($to, 0755, true)) {
            throw new PackageRefused(lang('Core.packages.notWritable', [dirname($to)]));
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isLink()) {
                continue;
            }

            $path = $to . '/' . substr($item->getPathname(), strlen($from) + 1);

            if ($item->isDir()) {
                if (! is_dir($path) && ! @mkdir($path, 0755, true)) {
                    throw new PackageRefused(lang('Core.packages.notWritable', [$path]));
                }
            } elseif (! @copy($item->getPathname(), $path)) {
                throw new PackageRefused(lang('Core.packages.notWritable', [$path]));
            } else {
                @chmod($path, 0644);
            }
        }
    }
}
