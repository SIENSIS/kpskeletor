<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use Modules\Core\Config\ModuleManifest;

/**
 * Expands a manifest's `defaultGrants` with the permissions they presuppose
 * (`ImpliedPermissions`), keeping only operations that exist in the resource's catalogue.
 * Wildcards are kept as they are.
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
            $result[(string) $group] = ImpliedPermissions::complete($permissions, $byPrefix);
        }

        return $result;
    }
}
