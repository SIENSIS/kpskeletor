<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

use Modules\Core\Config\ModuleManifest;

/**
 * Turns the `$menu` entries of a manifest into normalised definitions, stored by `modules:sync`
 * in `module_declarations` (kind `menu`) so regular requests never load the manifests.
 *
 * - An entry with `resource` takes the route named after the resource (its permission prefix,
 *   e.g. `news-posts`) and the permission `.list` (crud) or `.view` (page).
 * - Other entries give `route` (a route name) and `permission`.
 * - The stable key is `<Module>.<resource>` or the route name; `parent` names a resource of the
 *   same module or the key of any other entry.
 */
final class MenuDefinitions
{
    public const DEFAULT_ZONE  = 'sidebar';
    public const DEFAULT_ORDER = 100;
    public const DEFAULT_ICON  = 'bi bi-circle';

    /**
     * @return array<string, array{key: string, module: string, label: string, icon: string, order: int, zone: string, parent: string|null, route: string, permission: string}>
     */
    public static function of(ModuleManifest $manifest): array
    {
        $definitions = [];

        foreach ($manifest->menu as $entry) {
            $resource = $entry['resource'] ?? null;

            if (is_string($resource) && isset($manifest->resources[$resource])) {
                $prefix     = $manifest->permissionPrefix($resource);
                $type       = $manifest->resources[$resource]['type'] ?? 'crud';
                $key        = $manifest->name . '.' . $resource;
                $route      = $prefix;
                $permission = $prefix . ($type === 'page' ? '.view' : '.list');
            } else {
                $route      = (string) ($entry['route'] ?? '');
                $permission = (string) ($entry['permission'] ?? '');
                $key        = $route;
            }

            if ($route === '' || $permission === '') {
                continue;
            }

            $zone = is_string($entry['zone'] ?? null) && $entry['zone'] !== '' ? $entry['zone'] : self::DEFAULT_ZONE;

            if (isset($definitions[$key])) {
                $key .= '@' . $zone; // the same resource in a second zone
            }

            $parent = $entry['parent'] ?? null;

            if (is_string($parent) && $parent !== '') {
                $parent = isset($manifest->resources[$parent]) ? $manifest->name . '.' . $parent : $parent;
            } else {
                $parent = null;
            }

            $definitions[$key] = [
                'key'        => $key,
                'module'     => $manifest->name,
                'label'      => (string) ($entry['label'] ?? ''),
                'icon'       => is_string($entry['icon'] ?? null) && $entry['icon'] !== '' ? $entry['icon'] : self::DEFAULT_ICON,
                'order'      => is_int($entry['order'] ?? null) ? $entry['order'] : self::DEFAULT_ORDER,
                'zone'       => $zone,
                'parent'     => $parent,
                'route'      => $route,
                'permission' => $permission,
            ];
        }

        return $definitions;
    }
}
