<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Permissions\CatalogueBuilder;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Permissions\DefaultGrants;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * For feature tests with a migrated database: does what `modules:sync` does for the RBAC
 * (registry rows, permission catalogue, default grants, Shield's matrix) without migrations,
 * namespaces or assets. Shield has no permissions otherwise (Config\AuthGroups is empty).
 */
trait RbacFixtureTrait
{
    protected function syncPermissions(): void
    {
        $db        = Database::connect();
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $now   = Time::now()->toDateTimeString();
        $known = array_column($db->table('modules')->get()->getResultArray(), null, 'name');

        foreach ($manifests as $name => $manifest) {
            if (! isset($known[$name])) {
                $db->table('modules')->insert([
                    'name' => $name, 'version' => $manifest->version, 'enabled' => 1,
                    'grants_applied' => 0, 'installed_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        (new CoreCache())->invalidate('permissions');
        $catalogue = new PermissionCatalogue();
        $catalogue->upsert((new CatalogueBuilder())->build($manifests));

        foreach ($db->table('modules')->where('grants_applied', 0)->get()->getResultArray() as $row) {
            if (isset($manifests[$row['name']])) {
                $catalogue->grant((new DefaultGrants())->expand($manifests[$row['name']]));
                $db->table('modules')->where('id', $row['id'])->update(['grants_applied' => 1]);
            }
        }

        (new CoreCache())->invalidate('permissions');
        (new ShieldMatrix($catalogue))->write();
        CrudPermissions::reset();
    }
}
