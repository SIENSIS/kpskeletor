<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Closure;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Core\Archive\ArchiveRefused;
use Modules\Core\Config\Version;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\SemVer;
use Throwable;

/**
 * Restores a backup (spec "Backup" › "Restore"; docs/backup-restore.md), console and back office:
 *
 * 1. decrypt, authenticate and check the checksums (nothing is touched before);
 * 2. compatibility: format, Core and module versions (a newer backup is refused on an older
 *    installation, a module the installation does not have too) and, for the database, every
 *    migration of the backup must exist in this code;
 * 3. an automatic safety backup (type `full`, same password);
 * 4. maintenance mode on (only the restore's progress stays reachable), activity log paused;
 * 5. database and/or files; 6. `modules:sync`; 7. maintenance mode off.
 *
 * The `.env` is never written here: the result carries the secrets that differ and the merged
 * content; the console writes it after a confirmation, the back office shows it to copy.
 * A failure after step 4 keeps maintenance mode on and names the safety backup to restore from
 * the console.
 */
final class RestoreService
{
    public const PARTS = ['database', 'files'];

    /** Paths still served while a restore runs (the progress endpoint). */
    public const MAINTENANCE_ALLOW = ['progress/backup/*'];

    private readonly BackupService $backups;

    private readonly EnvFile $env;

    /** @var Closure(): void */
    private readonly Closure $sync;

    private readonly BackupConfig $config;

    /**
     * @param (Closure(): void)|null $sync Runs `modules:sync` (default ModuleSynchronizer)
     */
    public function __construct(
        ?BackupConfig $config = null,
        ?BackupService $backups = null,
        private readonly MaintenanceMode $maintenance = new MaintenanceMode(),
        ?EnvFile $env = null,
        ?Closure $sync = null,
    ) {
        $this->config  = $config ?? config(BackupConfig::class);
        $this->env     = $env ?? new EnvFile($this->config->envPath);
        $this->backups = $backups ?? new BackupService($this->config, null, null, null, $this->env);
        $this->sync    = $sync ?? static function (): void {
            (new ModuleSynchronizer())->sync();
        };
    }

    /**
     * @param string|null                        $only     `db`, `files` or null (everything the backup has)
     * @param (Closure(string, int): void)|null  $progress Called with (step, percent)
     *
     * @throws BackupException
     */
    public function restore(string $file, string $password, ?string $only = null, ?int $userId = null, string $source = 'console', ?Closure $progress = null): RestoreResult
    {
        $progress ??= static function (string $step, int $percent): void {
        };

        if ($only !== null && ! in_array($only, ['db', 'files'], true)) {
            throw new BackupException(lang('Backup.error.type'));
        }

        $progress('verify', 5);

        try {
            $opened = $this->backups->open($file, $password);
        } catch (BackupException $e) {
            security_log('Backup', 'restore_refused', ['resource' => 'backup', 'summary' => $file . ': ' . $e->getMessage()]);

            throw $e;
        }

        $started = false;
        $safety  = null;

        try {
            $this->checkVersions($opened->manifest);

            $parts = [];

            if ($opened->hasDatabase() && $only !== 'files') {
                $parts[] = 'database';
            }

            if ($opened->fileAreas() !== [] && $only !== 'db') {
                $parts[] = 'files';
            }

            if ($parts === []) {
                throw new BackupException(lang('Backup.error.nothingToRestore'));
            }

            $staging = $opened->workFolder . '/restore';
            $plan    = [];

            if (in_array('database', $parts, true)) {
                $progress('prepare', 15);
                $opened->zip->extract('database/', $staging . '/database', 0600, 0700);
                $plan = (new DatabaseDumper(null, $this->config))->plan($staging . '/database');
            }

            $areas = $this->backups->areas();

            if (in_array('files', $parts, true)) {
                foreach ($opened->fileAreas() as $area) {
                    if (! isset($areas[$area])) {
                        throw new BackupException(lang('Backup.error.unknownArea', [$area]));
                    }

                    $opened->zip->extract('files/' . $area . '/', $staging . '/files/' . $area, 0600, 0700, $areas[$area]['public'] ? $this->config->neverRestorePublic : []);
                }
            }

            // 3. Safety backup of the current installation (same password).
            $progress('safety', 30);
            $safety = (string) $this->backups->create($password, 'full', 'safety', $userId)['file'];

            // 4. Maintenance mode; the log is paused while the database is replaced.
            $this->maintenance->enable('backup.restore', self::MAINTENANCE_ALLOW);
            $started = true;
            service('activity')->enable(false);

            if (in_array('database', $parts, true)) {
                $progress('database', 45);
                (new DatabaseDumper(null, $this->config))->restore($staging . '/database', $plan, static function (int $done, int $total) use ($progress): void {
                    $progress('database', 45 + intdiv(30 * $done, max(1, $total)));
                });
            }

            if (in_array('files', $parts, true)) {
                $progress('files', 78);

                foreach ($opened->fileAreas() as $area) {
                    $this->replaceArea($staging . '/files/' . $area, $areas[$area]);
                }
            }

            $progress('sync', 90);
            ($this->sync)();
        } catch (Throwable $e) {
            $opened->close();
            service('activity')->enable(true);

            $message = $e instanceof BackupException || $e instanceof ArchiveRefused ? $e->getMessage() : lang('Backup.error.unexpected');

            if (! $e instanceof BackupException && ! $e instanceof ArchiveRefused) {
                log_message('error', 'Restore failed: {message}', ['message' => $e->getMessage()]);
            }

            if ($started) {
                // The installation may be half restored: stay in maintenance mode.
                $message = lang('Backup.error.restoreFailedSafety', [$message, (string) $safety]);
            }

            try {
                security_log('Backup', 'restore_failed', ['resource' => 'backup', 'summary' => $file . ': ' . $message]);
            } catch (Throwable) {
            }

            throw new BackupException($message, 0, $e);
        }

        $backupEnv = $opened->env();
        $opened->close();
        service('activity')->enable(true);
        $this->maintenance->disable();
        $this->backups->repository()->reconcile();

        $current   = $this->env->current() ?? '';
        $diff      = $backupEnv === null ? [] : $this->env->differences($current, $backupEnv);
        $merged    = $diff === [] ? null : $this->env->merge($current, (string) $backupEnv);

        activity_log('Backup', 'restore', ['resource' => 'backup', 'summary' => $file, 'changes' => [
            'parts' => $parts, 'safety' => $safety, 'source' => $source, 'secretsDiffer' => $diff,
        ]]);
        $progress('done', 100);

        return new RestoreResult($file, $parts, $safety, $diff, $merged, $backupEnv, $opened->manifest);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @throws BackupException
     */
    public function checkVersions(array $manifest): void
    {
        $core = (string) ($manifest['core'] ?? '');

        if (! SemVer::isVersion($core) || version_compare($core, Version::CORE, '>')) {
            throw new BackupException(lang('Backup.error.newer', ['Core', $core, Version::CORE]));
        }

        $installed = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $installed[$name] = $module->manifest->version;
            }
        }

        foreach ((array) ($manifest['modules'] ?? []) as $name => $version) {
            $name = (string) $name;

            if (! isset($installed[$name])) {
                throw new BackupException(lang('Backup.error.missingModule', [$name]));
            }

            if (! SemVer::isVersion((string) $version) || version_compare((string) $version, $installed[$name], '>')) {
                throw new BackupException(lang('Backup.error.newer', [$name, (string) $version, $installed[$name]]));
            }
        }
    }

    /**
     * Replaces the contents of an area with the staged files (the area's own `index.html` and
     * `.htaccess` stay).
     *
     * @param array{root: string, filePermission: int, dirPermission: int, public: bool} $area
     */
    private function replaceArea(string $staged, array $area): void
    {
        $root = $area['root'];

        if (! is_dir($root) && ! @mkdir($root, $area['dirPermission'], true) && ! is_dir($root)) {
            throw new BackupException(lang('Backup.error.write'));
        }

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, BackupService::PROTECTED_FILES, true)) {
                continue;
            }

            $path = $root . '/' . $entry;
            is_dir($path) && ! is_link($path) ? BackupService::removeFolder($path) : @unlink($path);
        }

        foreach (BackupService::filesOf($staged) as $relative) {
            $target = $root . '/' . $relative;
            $folder = dirname($target);

            if (! is_dir($folder) && ! @mkdir($folder, $area['dirPermission'], true) && ! is_dir($folder)) {
                throw new BackupException(lang('Backup.error.write'));
            }

            if (! @rename($staged . '/' . $relative, $target)) {
                throw new BackupException(lang('Backup.error.write'));
            }

            @chmod($target, $area['filePermission']);
        }
    }
}
