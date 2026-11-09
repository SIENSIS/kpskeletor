<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

use Closure;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The generated back-office menu (docs/specification.md, "Menus").
 *
 * Built from the menu entries of the **enabled** modules (`DeclarationRegistry`, kind `menu`),
 * filtered by permission (`Authorization`), nested by `parent`, sorted by `order` and split into
 * zones (`sidebar`, `settings`, `user`…). An entry is visible when its route exists and the user
 * holds its permission; a parent the user cannot open (or without a route) is kept as a group
 * only while it has visible children. The filtered tree is cached per group set (plus the user's
 * individual permissions) in the `menus` scope, which `modules:sync`, enabling or disabling a
 * module and every grant change invalidate.
 *
 * The active entry and the breadcrumbs depend on the request and are computed on each call.
 */
final class MenuBuilder
{
    /** @var Closure(string): ?string */
    private readonly Closure $routes;

    /**
     * @param (Closure(string): ?string)|null $routes route name => path (null when it does not exist)
     */
    public function __construct(
        private readonly DeclarationRegistry $registry = new DeclarationRegistry(),
        private readonly CoreCache $cache = new CoreCache(),
        ?Closure $routes = null,
    ) {
        $this->routes = $routes ?? static function (string $name): ?string {
            try {
                $path = route_to($name);
            } catch (Throwable) {
                return null; // the route needs parameters: it cannot be a menu entry
            }

            return is_string($path) ? trim($path, '/') : null;
        };
    }

    /**
     * The visible tree of every zone for a user (null = guest: nothing).
     *
     * @return array<string, list<MenuEntry>> zone => root entries
     */
    public function tree(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $data = $this->cache->remember('menus', 'tree.' . self::cacheKey($user), fn (): array => $this->build($user));
        $tree = [];

        foreach ($data as $zone => $entries) {
            foreach ($entries as $entry) {
                $tree[$zone][] = MenuEntry::fromArray($entry);
            }
        }

        return $tree;
    }

    /**
     * The visible entries of one zone, with the active entry (and its open ancestors) marked.
     *
     * @return list<MenuEntry>
     */
    public function zone(string $zone, ?User $user, ?string $currentPath = null, ?string $currentRoute = null): array
    {
        $entries = $this->tree($user)[$zone] ?? [];

        if ($currentPath !== null || $currentRoute !== null) {
            $trail = self::findTrail($entries, trim((string) $currentPath, '/'), $currentRoute);

            foreach ($trail as $i => $entry) {
                $entry->open = true;
                $entry->active = $i === count($trail) - 1;
            }
        }

        return $entries;
    }

    /**
     * The entries from the root to the active one, in any zone (the breadcrumbs).
     *
     * @return list<MenuEntry>
     */
    public function trail(?User $user, string $currentPath, ?string $currentRoute = null): array
    {
        $best = [];

        foreach ($this->tree($user) as $entries) {
            $trail = self::findTrail($entries, trim($currentPath, '/'), $currentRoute);

            if ($trail !== [] && ($best === [] || self::score(end($trail), trim($currentPath, '/'), $currentRoute) > self::score(end($best), trim($currentPath, '/'), $currentRoute))) {
                $best = $trail;
            }
        }

        return $best;
    }

    /**
     * The cache key of a user's menu: their groups and individual permissions.
     */
    public static function cacheKey(User $user): string
    {
        $groups      = $user->getGroups() ?? [];
        $permissions = $user->getPermissions() ?? [];
        sort($groups);
        sort($permissions);

        return md5(implode(',', $groups) . '|' . implode(',', $permissions));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function build(User $user): array
    {
        $auth        = Authorization::for($user);
        $definitions = [];

        foreach ($this->registry->all('menu') as $key => $declaration) {
            $definition = $declaration['definition'];

            if (! isset($definition['route'], $definition['permission'], $definition['label'])) {
                continue;
            }

            $definitions[(string) $key] = $definition;
        }

        $children = [];
        $roots    = [];

        foreach ($definitions as $key => $definition) {
            $parent = $definition['parent'] ?? null;

            if (is_string($parent) && isset($definitions[$parent]) && $parent !== $key) {
                $children[$parent][] = $key;
            } else {
                $roots[] = $key; // no parent, or its module is disabled: shown at the root
            }
        }

        $tree = [];

        foreach ($roots as $key) {
            $entry = $this->node($key, $definitions, $children, $auth, []);

            if ($entry !== null) {
                $tree[$entry->zone][] = $entry;
            }
        }

        $result = [];

        foreach ($tree as $zone => $entries) {
            $result[$zone] = array_map(static fn (MenuEntry $e): array => $e->toArray(), self::sorted($entries));
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, list<string>>         $children
     * @param list<string>                        $seen        ancestors (cycles are cut)
     */
    private function node(string $key, array $definitions, array $children, Authorization $auth, array $seen): ?MenuEntry
    {
        $definition = $definitions[$key];
        $visible    = [];

        foreach ($children[$key] ?? [] as $childKey) {
            if (in_array($childKey, $seen, true)) {
                continue;
            }

            $child = $this->node($childKey, $definitions, $children, $auth, [...$seen, $key]);

            if ($child !== null) {
                $visible[] = $child;
            }
        }

        $path = $auth->can((string) $definition['permission']) ? ($this->routes)((string) $definition['route']) : null;

        if ($path === null && $visible === []) {
            return null;
        }

        return new MenuEntry(
            $key,
            (string) ($definition['module'] ?? ''),
            (string) $definition['label'],
            (string) ($definition['icon'] ?? MenuDefinitions::DEFAULT_ICON),
            $path,
            $path === null ? null : (string) $definition['route'],
            (int) ($definition['order'] ?? MenuDefinitions::DEFAULT_ORDER),
            (string) ($definition['zone'] ?? MenuDefinitions::DEFAULT_ZONE),
            self::sorted($visible),
        );
    }

    /**
     * @param list<MenuEntry> $entries
     *
     * @return list<MenuEntry>
     */
    private static function sorted(array $entries): array
    {
        usort($entries, static fn (MenuEntry $a, MenuEntry $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $entries;
    }

    /**
     * The path from a root to the entry that matches the request best: the current route name,
     * else the longest path that is the current path or one of its parents.
     *
     * @param list<MenuEntry> $entries
     *
     * @return list<MenuEntry>
     */
    private static function findTrail(array $entries, string $path, ?string $route): array
    {
        $best      = [];
        $bestScore = 0;

        $walk = static function (array $entries, array $ancestors) use (&$walk, &$best, &$bestScore, $path, $route): void {
            foreach ($entries as $entry) {
                $trail = [...$ancestors, $entry];
                $score = self::score($entry, $path, $route);

                if ($score > $bestScore) {
                    $best      = $trail;
                    $bestScore = $score;
                }

                $walk($entry->children, $trail);
            }
        };
        $walk($entries, []);

        return $best;
    }

    /**
     * How well an entry matches the request: 0 = not at all; a route name match beats any path.
     */
    private static function score(MenuEntry $entry, string $path, ?string $route): int
    {
        if ($entry->path === null) {
            return 0;
        }

        if ($route !== null && $route !== '' && $entry->route === $route) {
            return 100000;
        }

        if ($entry->path === $path) {
            return 50000;
        }

        // Prefixes count from two segments: the back-office home (`backend`) is only active on itself.
        if (str_contains($entry->path, '/') && str_starts_with($path, $entry->path . '/')) {
            return strlen($entry->path) + 1;
        }

        return 0;
    }
}
