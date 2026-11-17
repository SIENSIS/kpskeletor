<?php

declare(strict_types=1);

/**
 * Events of the Backup module (discovered by CodeIgniter, also while the module is disabled, so
 * the listener checks it):
 *
 * - `packageBackup` (the Core's PackageInstaller and Uninstaller, docs/packages.md): a full backup
 *   (origin `package`) before a module or theme is installed, upgraded or uninstalled, with the
 *   operator's password or the configured one (`backup.password`, `BACKUP_PASSWORD`).
 */

use CodeIgniter\Events\Events;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupPassword;
use Modules\Backup\Services\BackupService;
use Modules\Core\Packages\PackageBackup;
use Modules\Core\Registry\ModuleState;

Events::on(PackageBackup::EVENT, static function (PackageBackup $request): void {
    if (! (new ModuleState())->isEnabled('Backup')) {
        return;
    }

    $password = $request->password ?? (new BackupPassword())->configured();

    if ($password === null) {
        $request->failed(lang('Backup.cli.noPassword'));

        return;
    }

    try {
        $request->done((string) (new BackupService())->create($password, 'full', 'package', $request->userId)['file']);
    } catch (BackupException $e) {
        $request->failed($e->getMessage());
    }
});

// `securityChecks` (the Core's security:check and Security status page): `backup.password` when
// scheduled backups are on, `writable/backups` outside `public/` and not readable by others.
Events::on(\Modules\Core\Security\Check\SecurityChecks::EVENT, static function (\Modules\Core\Security\Check\SecurityChecks $checks): void {
    if ((new ModuleState())->isEnabled('Backup')) {
        $checks->add(new \Modules\Backup\Services\BackupSecurityCheck());
    }
});
