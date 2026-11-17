<?php

declare(strict_types=1);

/**
 * Events of the Updates module (discovered by CodeIgniter, also while the module is disabled, so
 * the listener checks it):
 *
 * - `moduleVersions` (the Core's Modules screen): the latest versions found by the last check of
 *   the modules that declare a `$repository` (no outbound request: the stored result only).
 */

use CodeIgniter\Events\Events;
use Modules\Core\Packages\ModuleVersions;
use Modules\Core\Registry\ModuleState;
use Modules\Updates\Services\UpdateStatus;

Events::on(ModuleVersions::EVENT, static function (ModuleVersions $versions): void {
    if (! (new ModuleState())->isEnabled('Updates')) {
        return;
    }

    $status = (new UpdateStatus())->read();

    foreach (is_array($status['modules'] ?? null) ? $status['modules'] : [] as $name => $module) {
        $version = is_array($module) && is_array($module['latest'] ?? null) ? ($module['latest']['version'] ?? null) : null;

        if (is_string($version) && $version !== '') {
            $versions->set((string) $name, $version);
        }
    }
});
