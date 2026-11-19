<?php

declare(strict_types=1);

/**
 * Events of the Dashboard module (discovered by CodeIgniter, also while the module is disabled):
 *
 * - `moduleUninstalling` (the Core's Uninstaller, docs/installing-modules.md): the uninstalled module's
 *   widgets leave the group layouts (users' own layouts drop unknown widgets when read).
 * - `backendPathChanged` (`php spark app:backend-path`): quick links written with the old prefix.
 */

use CodeIgniter\Events\Events;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Packages\ModuleUninstalling;
use Modules\Core\Routing\BackendPathChanged;

Events::on(ModuleUninstalling::EVENT, static function (ModuleUninstalling $event): void {
    $db = db_connect();

    if ($event->widgets === [] || ! $db->tableExists('dashboard_group_widgets')) {
        return;
    }

    $db->table('dashboard_group_widgets')->whereIn('widget', $event->widgets)->delete();
    $removed = $db->affectedRows();

    if ($removed > 0) {
        (new CacheInvalidator())->invalidate(['widgets']);
        $event->note(lang('Dashboard.uninstall.removed', [$removed]));
    }
});

// `php spark app:backend-path` (D-40-01): quick links typed with the old back-office prefix.
Events::on('backendPathChanged', static function (BackendPathChanged $event): void {
    $rows = $event->rewriteColumn('dashboard_group_widgets', 'options', true)
        + $event->rewriteColumn('dashboard_user_layouts', 'layout', true, [], 'user_id');

    if ($rows > 0) {
        (new CacheInvalidator())->invalidate(['widgets']);
    }
});
