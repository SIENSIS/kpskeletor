<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use Kpcrudy\Crud;
use Modules\Core\Config\ModuleManifest;

/**
 * Expands a manifest's `defaultGrants` with the permissions they presuppose
 * (`Kpcrudy\Crud::impliedPermissions()`: edit → list, show; restore → trash…), keeping only
 * operations that exist in the resource's catalogue. Wildcards are kept as they are.
 */
final class DefaultGrants
{
    /**
     * @return array<string, list<string>> group => permissions (sorted, unique)
     */
    public function expand(ModuleManifest $manifest): array
    {
        $byPrefix = [];

        foreach ($manifest->resources as $resource => $definition) {
            $byPrefix[$manifest->permissionPrefix((string) $resource)] = array_keys(CatalogueBuilder::operations($definition));
        }

        $result = [];

        foreach ($manifest->defaultGrants as $group => $permissions) {
            $expanded = [];
            $atomic   = [];

            foreach ($permissions as $permission) {
                [$prefix, $operation] = array_pad(explode('.', $permission, 2), 2, '');

                if ($operation === '*' || ! isset($byPrefix[$prefix])) {
                    $expanded[] = $permission;

                    continue;
                }

                $atomic[$prefix][] = $operation;
            }

            foreach ($atomic as $prefix => $operations) {
                foreach (Crud::impliedPermissions($operations) as $operation) {
                    if (in_array($operation, $byPrefix[$prefix], true)) {
                        $expanded[] = $prefix . '.' . $operation;
                    }
                }
            }

            $expanded = array_values(array_unique($expanded));
            sort($expanded);
            $result[(string) $group] = $expanded;
        }

        return $result;
    }
}
