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
 * - An entry with `group` (its key, e.g. `group.system`) has no route and no permission: a
 *   heading shown only while one of its children is visible (kind `group`). The Core declares
 *   the groups of the `settings` zone (`GROUPS`); modules point their entries to them with
 *   `parent`.
 * - The stable key is `<Module>.<resource>` or the route name; `parent` names a resource of the
 *   same module or the key of any other entry.
 */
final class MenuDefinitions
{
    public const DEFAULT_ZONE  = 'sidebar';
    public const DEFAULT_ORDER = 100;
    public const DEFAULT_ICON  = 'bi bi-circle';

    /**
     * The groups of the `settings` zone declared by the Core (D-38-01), a public convention for
     * modules: `'parent' => MenuDefinitions::GROUP_SYSTEM` (or the key itself).
     */
    public const GROUP_ACCESS        = 'group.access';
    public const GROUP_SECURITY      = 'group.security';
    public const GROUP_API           = 'group.api';
    public const GROUP_CUSTOMISATION = 'group.customisation';
    public const GROUP_SYSTEM        = 'group.system';

    /** Keys of group entries: `group.<name>`. */
    public const GROUP_KEY = '/^group\.[a-z][a-z0-9-]{0,40}$/';

    /**
     * @return array<string, array{key: string, module: string, label: string, icon: string, order: int, zone: string, parent: string|null, route: string, permission: string, kind?: string}>
     */
    public static function of(ModuleManifest $manifest): array
    {
        $definitions = [];

        foreach ($manifest->menu as $entry) {
            $resource = $entry['resource'] ?? null;
            $group    = $entry['group'] ?? null;

            if (is_string($group)) {
                if (preg_match(self::GROUP_KEY, $group) !== 1) {
                    continue;
                }

                $key        = $group;
                $route      = '';
                $permission = '';
            } elseif (is_string($resource) && isset($manifest->resources[$resource])) {
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

            if (! is_string($group) && ($route === '' || $permission === '')) {
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

            if (is_string($group)) {
                $definitions[$key]['kind'] = 'group';
            }
        }

        return $definitions;
    }
}
