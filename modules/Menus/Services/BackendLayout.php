<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuDefinitions;
use RuntimeException;

/**
 * The back-office menu as the customisation editor sees it (every entry, permissions not
 * applied, hidden ones included) for one scope (everyone or a group), and the writes of the
 * editor: the order and place of the entries (tree editor), the retouches of one entry, resets.
 *
 * What the editor stores is a difference: against the generated menu for the scope "everyone",
 * against the menu of everyone for a group. A field equal to that base is stored as null, so an
 * entry nobody touched follows its module (and a new module's entries appear in their default
 * place).
 */
final class BackendLayout
{
    public const ICON = '/^bi bi-[a-z0-9-]{1,50}$/';

    public function __construct(
        private readonly Customisations $store = new Customisations(),
        private readonly CustomisationApplier $applier = new CustomisationApplier(),
        private readonly MenuBuilder $builder = new MenuBuilder(),
    ) {
    }

    public function store(): Customisations
    {
        return $this->store;
    }

    /**
     * Every entry for a scope (key => definition), hidden and broken ones included.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(?string $scope): array
    {
        return $this->applier->apply($this->builder->definitions(), $scope === null ? [null] : [null, $scope], true);
    }

    /**
     * What the scope's customisations are compared with.
     *
     * @return array<string, array<string, mixed>>
     */
    public function base(?string $scope): array
    {
        return $this->applier->apply($this->builder->definitions(), $scope === null ? [] : [null], true);
    }

    /**
     * The keys of every entry that exists (generated entries of enabled modules, custom ones).
     *
     * @return list<string>
     */
    public function knownKeys(): array
    {
        return array_map('strval', array_keys($this->builder->definitions() + $this->applier->customEntries(true)));
    }

    /**
     * The tree of a scope for the editor: zone => list of nodes (key, text, icon, module, kind,
     * hidden, customised, permission, broken, children).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function tree(?string $scope): array
    {
        $definitions = $this->definitions($scope);
        $stored      = $this->store->all()[$scope ?? ''] ?? [];
        $nodes       = self::nodes($definitions);
        $tree        = array_fill_keys(Customisations::ZONES, []);

        $build = function (string $key, array $seen) use (&$build, $definitions, $nodes, $stored): array {
            $definition = $definitions[$key];
            $children   = [];

            foreach (TreeMoves::siblings($nodes, $key, $nodes[$key]['zone']) as $child) {
                if (! in_array($child, $seen, true)) {
                    $children[] = $build($child, [...$seen, $key]);
                }
            }

            return [
                'key'        => $key,
                'text'       => self::text($definition),
                'icon'       => (string) ($definition['icon'] ?? MenuDefinitions::DEFAULT_ICON),
                'module'     => (string) ($definition['module'] ?? ''),
                'kind'       => (string) ($definition['kind'] ?? 'route'),
                'hidden'     => ($definition['hidden'] ?? false) === true,
                'customised' => isset($stored[$key]),
                'permission' => (string) ($definition['permission'] ?? implode(', ', (array) ($definition['permissions'] ?? []))),
                'broken'     => ($definition['broken'] ?? false) === true,
                'children'   => $children,
            ];
        };

        foreach (Customisations::ZONES as $zone) {
            foreach (TreeMoves::siblings($nodes, null, $zone) as $key) {
                $tree[$zone][] = $build($key, []);
            }
        }

        // Entries of another zone (a theme's) are still listed, under their own zone.
        foreach ($nodes as $key => $node) {
            if ($node['parent'] === null && ! in_array($node['zone'], Customisations::ZONES, true)) {
                $tree[$node['zone']][] = $build($key, []);
            }
        }

        return $tree;
    }

    /**
     * Saves the place of every entry: key => [parent (null = root), zone, order]. Siblings whose
     * sequence did not change keep their stored positions.
     *
     * @param array<string, array{parent: ?string, zone: string, order: int}> $places
     *
     * @return int how many entries changed
     *
     * @throws RuntimeException with a translated message
     */
    public function saveTree(?string $scope, array $places, ?int $userId): int
    {
        $current = self::nodes($this->definitions($scope));
        $base    = self::nodes($this->base($scope));
        $stored  = $this->store->all()[$scope ?? ''] ?? [];

        foreach ($places as $key => $place) {
            if (! isset($current[$key]) || ($place['parent'] !== null && ! isset($current[$place['parent']])) || ! in_array($place['zone'], Customisations::ZONES, true)) {
                throw new RuntimeException(lang('Menus.errors.unknownEntry'));
            }
        }

        $nodes = array_replace($current, $places);

        if (TreeMoves::hasCycle(array_map(static fn (array $n): ?string => $n['parent'], $nodes))) {
            throw new RuntimeException(lang('Menus.errors.cycle'));
        }

        // Positions: entries that stay in a list keep theirs when their relative order is the
        // same; newcomers slot in between; otherwise the list is renumbered 10, 20, 30…
        $positions = [];

        foreach (self::groups($nodes) as $siblings) {
            foreach (self::placeSiblings($siblings, $current, $nodes) as $key => $position) {
                $positions[$key] = $position;
            }
        }

        $changed = 0;

        foreach ($nodes as $key => $node) {
            $fields = [
                'parent' => $node['parent'] === $base[$key]['parent'] ? null : ($node['parent'] ?? ''),
                'zone'   => $node['zone'] === $base[$key]['zone'] ? null : $node['zone'],
            ];

            if (isset($positions[$key])) {
                $fields['position'] = $positions[$key] === $base[$key]['order'] ? null : $positions[$key];
            }

            $old = $stored[$key] ?? array_fill_keys(Customisations::FIELDS, null);

            if (array_intersect_key($old, $fields) == $fields) {
                continue;
            }

            $this->store->put($key, $scope, $fields, $userId, false);
            $changed++;
        }

        $this->store->changed();

        return $changed;
    }

    /**
     * New positions for a list of siblings in their new order (only the ones that change).
     *
     * @param list<string>                                                    $siblings
     * @param array<string, array{parent: ?string, zone: string, order: int}> $current
     * @param array<string, array{parent: ?string, zone: string, order: int}> $nodes
     *
     * @return array<string, int>
     */
    private static function placeSiblings(array $siblings, array $current, array $nodes): array
    {
        $stays = static fn (string $k): bool => $current[$k]['parent'] === $nodes[$k]['parent'] && ($nodes[$k]['parent'] !== null || $current[$k]['zone'] === $nodes[$k]['zone']);
        $kept  = array_values(array_filter($siblings, $stays));
        $old   = $kept;
        usort($old, static fn (string $a, string $b): int => [$current[$a]['order'], $a] <=> [$current[$b]['order'], $b]);

        if ($old === $kept) {
            $positions = [];
            $previous  = null;
            $fits      = true;

            foreach ($siblings as $i => $key) {
                if ($stays($key)) {
                    $previous = $current[$key]['order'];

                    continue;
                }

                $next = null;

                foreach (array_slice($siblings, $i + 1) as $after) {
                    if ($stays($after)) {
                        $next = $current[$after]['order'];

                        break;
                    }
                }

                $position = $previous === null ? ($next === null ? 10 : $next - 1) : $previous + 1;

                if ($position < 0 || ($next !== null && $position >= $next)) {
                    $fits = false;

                    break;
                }

                $positions[$key] = $position;
                $previous        = $position;
            }

            if ($fits) {
                return $positions;
            }
        }

        $positions = [];

        foreach ($siblings as $i => $key) {
            $positions[$key] = ($i + 1) * 10;
        }

        return $positions;
    }

    /**
     * One move of the tree editor without JavaScript (`up`, `down`, `indent`, `outdent`).
     *
     * @throws RuntimeException
     */
    public function move(?string $scope, string $key, string $move, ?int $userId): int
    {
        $nodes = self::nodes($this->definitions($scope));

        if (! isset($nodes[$key])) {
            throw new RuntimeException(lang('Menus.errors.unknownEntry'));
        }

        return $this->saveTree($scope, TreeMoves::move($nodes, $key, $move), $userId);
    }

    /**
     * Saves the retouches of one entry: labels (locale => text, empty = the generated one), icon
     * (empty = the generated one), hidden, parent (null = root) and zone.
     *
     * @param array<string, string> $labels
     *
     * @throws RuntimeException
     */
    public function saveEntry(?string $scope, string $key, array $labels, string $icon, bool $hidden, ?string $parent, string $zone, ?int $userId): void
    {
        $current = $this->definitions($scope);
        $base    = $this->base($scope);

        if (! isset($current[$key])) {
            throw new RuntimeException(lang('Menus.errors.unknownEntry'));
        }

        if ($icon !== '' && preg_match(self::ICON, $icon) !== 1) {
            throw new RuntimeException(lang('Menus.errors.icon'));
        }

        $parents = array_map(static fn (array $d): ?string => is_string($d['parent'] ?? null) && isset($current[$d['parent']]) ? $d['parent'] : null, $current);

        if ($parent !== null && (! isset($current[$parent]) || TreeMoves::isDescendant($parents, $key, $parent))) {
            throw new RuntimeException(lang('Menus.errors.cycle'));
        }

        if (! in_array($zone, Customisations::ZONES, true)) {
            throw new RuntimeException(lang('Menus.errors.zone'));
        }

        $labels     = array_filter(array_map('trim', $labels), static fn (string $l): bool => $l !== '');
        $baseLabels = is_array($base[$key]['labels'] ?? null) ? $base[$key]['labels'] : [];
        $baseParent = is_string($base[$key]['parent'] ?? null) && isset($base[$base[$key]['parent']]) ? $base[$key]['parent'] : null;
        $fields     = [
            'labels' => $labels === [] || $labels == $baseLabels ? null : $labels,
            'icon'   => $icon === '' || $icon === ($base[$key]['icon'] ?? '') ? null : $icon,
            'hidden' => $hidden === (($base[$key]['hidden'] ?? false) === true) ? null : $hidden,
            'parent' => $parent === $baseParent ? null : ($parent ?? ''),
            'zone'   => $zone === ($base[$key]['zone'] ?? MenuDefinitions::DEFAULT_ZONE) ? null : $zone,
        ];

        if ($parent !== $parents[$key] || ($parent === null && $zone !== ($current[$key]['zone'] ?? ''))) {
            $nodes              = self::nodes($current);
            $last               = TreeMoves::siblings($nodes, $parent, $zone, $key);
            $top                = $last === [] ? 0 : $nodes[end($last)]['order'];
            $position           = $top + 10;
            $fields['position'] = $position === (int) ($base[$key]['order'] ?? MenuDefinitions::DEFAULT_ORDER) ? null : $position;
        }

        $this->store->put($key, $scope, $fields, $userId);
    }

    /**
     * The tree nodes of definitions: key => [parent (an existing key or null), zone, order].
     *
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, array{parent: ?string, zone: string, order: int}>
     */
    public static function nodes(array $definitions): array
    {
        $nodes = [];

        foreach ($definitions as $key => $definition) {
            $parent      = $definition['parent'] ?? null;
            $nodes[$key] = [
                'parent' => is_string($parent) && $parent !== $key && isset($definitions[$parent]) ? $parent : null,
                'zone'   => (string) ($definition['zone'] ?? MenuDefinitions::DEFAULT_ZONE),
                'order'  => (int) ($definition['order'] ?? MenuDefinitions::DEFAULT_ORDER),
            ];
        }

        return $nodes;
    }

    /**
     * The translated label of a definition (its renamed text in the current language, else its
     * language key's).
     *
     * @param array<string, mixed> $definition
     */
    public static function text(array $definition): string
    {
        $labels = is_array($definition['labels'] ?? null) ? $definition['labels'] : [];

        if ($labels !== []) {
            return MenuRepository::label(array_map('strval', $labels), service('request')->getLocale());
        }

        return lang((string) ($definition['label'] ?? ''));
    }

    /**
     * Sibling lists: "<parent>|<zone>" => keys in order.
     *
     * @param array<string, array{parent: ?string, zone: string, order: int}> $nodes
     *
     * @return array<string, list<string>>
     */
    private static function groups(array $nodes): array
    {
        $groups = [];

        foreach ($nodes as $key => $node) {
            $groups[($node['parent'] ?? '') . '|' . ($node['parent'] === null ? $node['zone'] : '')][] = (string) $key;
        }

        foreach ($groups as $id => $keys) {
            usort($keys, static fn (string $a, string $b): int => [$nodes[$a]['order'], $a] <=> [$nodes[$b]['order'], $b]);
            $groups[$id] = $keys;
        }

        return $groups;
    }
}
