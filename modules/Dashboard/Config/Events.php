<?php

declare(strict_types=1);

/**
 * Events of the Dashboard module (discovered by CodeIgniter, also while the module is disabled):
 *
 * - `moduleUninstalling` (the Core's Uninstaller, docs/installing-modules.md): the uninstalled module's
 *   widgets leave the group layouts (users' own layouts drop unknown widgets when read).
 */

use CodeIgniter\Events\Events;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Packages\ModuleUninstalling;

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
