<?php

declare(strict_types=1);

namespace Modules\Backup\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Backup\Services\BackupPassword;
use Modules\Backup\Services\BackupSecurityCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * The Backup module's part of `security:check`: `backup.password` when scheduled backups are on,
 * backup files never readable by others nor inside `public/` (D-27-05).
 *
 * @internal
 */
final class BackupSecurityCheckTest extends CIUnitTestCase
{
    public function testPasswordAndFolder(): void
    {
        $dir           = sys_get_temp_dir() . '/kp-backup-check-' . bin2hex(random_bytes(4));
        $backups       = new BackupConfig();
        $backups->path = $dir . '/backups';
        mkdir($backups->path, 0750, true);
        file_put_contents($backups->path . '/backup-1.kpbackup', 'x');
        file_put_contents($backups->path . '/index.html', 'x');
        chmod($backups->path . '/backup-1.kpbackup', 0644);
        chmod($backups->path . '/index.html', 0644);
        $password = new BackupPassword(); // no backup.password in the test environment

        try {
            $report = new SecurityReport();
            (new BackupSecurityCheck($backups, true, $password, $dir . '/public'))->run($report);
            $this->assertSame(['secrets.backupPassword', 'installation.backups'], $report->errorIds());

            chmod($backups->path . '/backup-1.kpbackup', 0640);
            $report = new SecurityReport();
            (new BackupSecurityCheck($backups, false, $password, $dir . '/public'))->run($report);
            $this->assertSame([], $report->errorIds(), 'Not scheduled: no password needed; index.html is not a backup.');

            $report = new SecurityReport();
            (new BackupSecurityCheck($backups, false, $password, $dir))->run($report);
            $this->assertSame(['installation.backups'], $report->errorIds(), 'A backup folder inside public/ is an error.');
        } finally {
            array_map('unlink', glob($backups->path . '/*') ?: []);
            rmdir($backups->path);
            rmdir($dir);
        }
    }

}
