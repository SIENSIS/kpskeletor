<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

/**
 * Applies the stored layer to the generated back-office menu (docs/menus.md, "Customisation").
 *
 * 1. Custom groups and links become entries `custom.<id>` (a link whose target is gone is
 *    dropped; a link to an internal path or route requires that route's `can:` permissions, plus
 *    the permission and groups chosen in the editor).
 * 2. The customisations of the scopes are merged field by field, least specific first: everyone,
 *    then each of the user's groups, so the most specific one wins (a group listed earlier in
 *    Users › Groups beats one listed later).
 * 3. A relocation that would put an entry under itself or its descendants is ignored (the entry
 *    keeps its previous parent), so cycles are impossible whatever is stored.
 *
 * Only `parent`, `zone`, `order`, `labels`, `icon` and `hidden` change: the route and the
 * permission of a generated entry never do, so permissions always win.
 */
final class CustomisationApplier
{
    public function __construct(
        private readonly Customisations $store = new Customisations(),
        private readonly LinkTarget $targets = new LinkTarget(),
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $definitions generated entries (key => definition)
     * @param list<string|null>                    $scopes      null = everyone, then group names, least specific first
     * @param bool                                 $all         keep custom links whose target is gone (the editor shows them)
     *
     * @return array<string, array<string, mixed>>
     */
    public function apply(array $definitions, array $scopes, bool $all = false): array
    {
        $definitions += $this->customEntries($all);
        $stored = $this->store->all();

        foreach ($scopes as $scope) {
            foreach ($stored[$scope ?? ''] ?? [] as $key => $fields) {
                if (! isset($definitions[$key])) {
                    continue; // orphaned: kept but inactive
                }

                $definitions[$key] = self::merge($definitions, $key, $fields);
            }
        }

        return $definitions;
    }

    /**
     * The scopes that apply to a user: everyone, then their groups from the least specific (the
     * last in the order of Users › Groups) to the most specific.
     *
     * @param list<string> $userGroups
     * @param list<string> $groupOrder every group name, most specific first
     *
     * @return list<string|null>
     */
    public static function scopesFor(array $userGroups, array $groupOrder): array
    {
        $ordered = array_values(array_filter($groupOrder, static fn (string $g): bool => in_array($g, $userGroups, true)));

        return [null, ...array_reverse($ordered)];
    }

    /**
     * Custom groups and links as definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function customEntries(bool $all = false): array
    {
        $entries = [];

        foreach ($this->store->links() as $id => $link) {
            $key        = Customisations::PREFIX . $id;
            $definition = [
                'key'    => $key,
                'module' => '',
                'label'  => $link['kind'] === 'group' ? 'Menus.custom.group' : 'Menus.custom.link',
                'labels' => $link['labels'],
                'icon'   => $link['icon'] !== '' ? $link['icon'] : ($link['kind'] === 'group' ? 'bi bi-folder' : 'bi bi-link-45deg'),
                'order'  => $link['position'],
                'zone'   => $link['zone'],
                'parent' => $link['parent'],
                'kind'   => $link['kind'],
                'custom' => true,
            ];

            if ($link['kind'] === 'link') {
                $target = $this->targets->resolve($link['type'], $link['value'], true);

                if ($target === null) {
                    if (! $all) {
                        continue;
                    }

                    $target = ['href' => '', 'all' => [], 'any' => [], 'internal' => false];
                    $definition['broken'] = true;
                }

                $definition['href']           = $target['href'];
                $definition['target']         = $link['target'];
                $definition['permissions']    = array_values(array_unique(array_filter([...$target['all'], $link['permission']], static fn (string $p): bool => $p !== '')));
                $definition['anyPermissions'] = $target['any'];
                $definition['groups']         = $link['groups'];
            }

            $entries[$key] = $definition;
        }

        return $entries;
    }

    /**
     * @param array<string, array<string, mixed>>                                                                               $definitions
     * @param array{parent: ?string, zone: ?string, position: ?int, labels: ?array<string, string>, icon: ?string, hidden: ?bool} $fields
     *
     * @return array<string, mixed>
     */
    private static function merge(array $definitions, string $key, array $fields): array
    {
        $definition = $definitions[$key];

        if ($fields['parent'] !== null) {
            $parent = $fields['parent'] === '' ? null : $fields['parent'];

            if ($parent === null || (isset($definitions[$parent]) && ! self::wouldCycle($definitions, $key, $parent))) {
                $definition['parent'] = $parent;
            }
        }

        if ($fields['zone'] !== null && in_array($fields['zone'], Customisations::ZONES, true)) {
            $definition['zone'] = $fields['zone'];
        }

        if ($fields['position'] !== null) {
            $definition['order'] = $fields['position'];
        }

        if ($fields['labels'] !== null) {
            $definition['labels'] = $fields['labels'];
        }

        if ($fields['icon'] !== null && $fields['icon'] !== '') {
            $definition['icon'] = $fields['icon'];
        }

        if ($fields['hidden'] !== null) {
            $definition['hidden'] = $fields['hidden'];
        }

        return $definition;
    }

    /**
     * True when `$parent` is `$key` or one of its descendants.
     *
     * @param array<string, array<string, mixed>> $definitions
     */
    private static function wouldCycle(array $definitions, string $key, string $parent): bool
    {
        $parents = array_map(static fn (array $d): ?string => is_string($d['parent'] ?? null) ? $d['parent'] : null, $definitions);

        return TreeMoves::isDescendant($parents, $key, $parent);
    }
}
