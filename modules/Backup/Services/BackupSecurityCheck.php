<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Core\Security\Check\FileAccess;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use Throwable;

/**
 * The Backup module's part of `security:check` (event `securityChecks`):
 *
 * - `secrets.backupPassword`: `backup.password` is set (and strong enough) when scheduled backups
 *   are on;
 * - `installation.backups`: `writable/backups` is outside `public/` (uploaded and created backups
 *   are never served, D-27-05) and its files are not readable by other users (the folder's mode
 *   is part of the Core's `writable/` check).
 */
final class BackupSecurityCheck implements SecurityCheck
{
    public function __construct(
        private readonly ?BackupConfig $config = null,
        private readonly ?bool $scheduled = null,
        private readonly ?BackupPassword $password = null,
        private readonly string $public = FCPATH,
    ) {
    }

    public function groups(): array
    {
        return ['secrets', 'installation'];
    }

    public function run(SecurityReport $report): void
    {
        $this->password($report);
        $this->folder($report);
    }

    private function password(SecurityReport $report): void
    {
        try {
            $scheduled = $this->scheduled ?? (setting('Backup.scheduled') === true);
        } catch (Throwable) {
            $scheduled = false;
        }

        $passwords = $this->password ?? new BackupPassword();
        $password  = $passwords->configured();
        $title     = lang('Backup.securityCheck.password');

        if (! $scheduled) {
            $report->ok('secrets', 'secrets.backupPassword', $title, lang('Backup.securityCheck.notScheduled'));
        } elseif ($password === null) {
            $report->error('secrets', 'secrets.backupPassword', $title, lang('Backup.securityCheck.passwordMissing'), lang('Backup.securityCheck.fixPassword'));
        } elseif (($problem = $passwords->problem($password)) !== null) {
            $report->warning('secrets', 'secrets.backupPassword', $title, $problem, lang('Backup.securityCheck.fixPassword'));
        } else {
            $report->ok('secrets', 'secrets.backupPassword', $title, lang('Backup.securityCheck.passwordOk'));
        }
    }

    private function folder(SecurityReport $report): void
    {
        $path   = rtrim(($this->config ?? config(BackupConfig::class))->path, '/');
        $title  = lang('Backup.securityCheck.folder');
        $real   = realpath($path) ?: $path;
        $public = realpath($this->public) ?: rtrim($this->public, '/');

        if (str_starts_with($real . '/', rtrim($public, '/') . '/')) {
            $report->error('installation', 'installation.backups', $title, lang('Backup.securityCheck.folderPublic', [$path]), lang('Backup.securityCheck.fixFolder'));

            return;
        }

        if (! is_dir($path)) {
            $report->ok('installation', 'installation.backups', $title, lang('Backup.securityCheck.folderOk'));

            return;
        }

        // The folder's own mode is part of the Core's `writable/` check; here, the backup files.
        $readable = [];

        foreach (glob($path . '/*') ?: [] as $file) {
            if (is_file($file) && ! in_array(basename($file), ['index.html', '.htaccess', '.gitkeep'], true) && FileAccess::readableByOthers($file)) {
                $readable[] = basename($file);
            }
        }

        if ($readable === []) {
            $report->ok('installation', 'installation.backups', $title, lang('Backup.securityCheck.folderOk'));
        } else {
            $report->error('installation', 'installation.backups', $title, lang('Backup.securityCheck.folderReadable', [implode(', ', array_slice($readable, 0, 5))]), lang('Backup.securityCheck.fixReadable'));
        }
    }
}
