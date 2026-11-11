<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Modules\CacheInvalidator;
use RuntimeException;

/**
 * Public menus and their items (tables `menus`, `menu_items`, `menu_item_translations`,
 * `menu_item_groups`). Every write invalidates the `menus` cache. Query Builder only.
 */
final class MenuRepository
{
    public const VISIBILITIES = ['all', 'guests', 'users', 'groups'];
    public const TARGETS      = ['_self', '_blank'];

    /** Deepest nesting of public items (a root item is depth 1). */
    public const MAX_DEPTH = 3;

    /**
     * @return array{id: int, name: string, location: string|null}|null
     */
    public function find(int $id): ?array
    {
        $row = db_connect()->table('menus')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'location' => $row['location'] === null ? null : (string) $row['location']] : null;
    }

    /** The id of the menu placed in a location, or null. */
    public function forLocation(string $location): ?int
    {
        $row = db_connect()->table('menus')->select('id')->where('location', $location)->get()->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /**
     * The items of a menu, flat, in tree order of their siblings, with every label and group id.
     *
     * @return array<int, array{id: int, parent_id: int|null, position: int, type: string, value: string, icon: string, target: string, css_class: string, visibility: string, labels: array<string, string>, groups: list<int>}>
     */
    public function items(int $menuId): array
    {
        $db    = db_connect();
        $rows  = $db->table('menu_items')->where('menu_id', $menuId)->orderBy('position')->orderBy('id')->get()->getResultArray();
        $items = [];

        foreach ($rows as $row) {
            $items[(int) $row['id']] = [
                'id'         => (int) $row['id'],
                'parent_id'  => $row['parent_id'] === null ? null : (int) $row['parent_id'],
                'position'   => (int) $row['position'],
                'type'       => (string) $row['type'],
                'value'      => (string) $row['value'],
                'icon'       => (string) $row['icon'],
                'target'     => (string) $row['target'],
                'css_class'  => (string) $row['css_class'],
                'visibility' => (string) $row['visibility'],
                'labels'     => [],
                'groups'     => [],
            ];
        }

        if ($items === []) {
            return [];
        }

        foreach ($db->table('menu_item_translations')->whereIn('item_id', array_keys($items))->get()->getResultArray() as $row) {
            $items[(int) $row['item_id']]['labels'][(string) $row['locale']] = (string) $row['label'];
        }

        foreach ($db->table('menu_item_groups')->whereIn('item_id', array_keys($items))->get()->getResultArray() as $row) {
            $items[(int) $row['item_id']]['groups'][] = (int) $row['group_id'];
        }

        return $items;
    }

    /**
     * The label of an item in a language (else Catalan, else the first one).
     *
     * @param array<string, string> $labels
     */
    public static function label(array $labels, string $locale): string
    {
        return ContentTranslations::pick(array_map(static fn (string $l): array => ['label' => $l], $labels), ['label'], $locale)['label'];
    }

    /**
     * Inserts or updates an item. `$data`: parent_id, type, value, icon, target, css_class,
     * visibility (already validated); `$labels`: locale => text; `$groups`: group ids.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $labels
     * @param list<int>             $groups
     */
    public function saveItem(int $menuId, ?int $itemId, array $data, array $labels, array $groups): int
    {
        $db  = db_connect();
        $now = Time::now()->toDateTimeString();

        $db->transStart();

        if ($itemId === null) {
            $siblings = $db->table('menu_items')->selectMax('position', 'top')->where('menu_id', $menuId)->where('parent_id', $data['parent_id'] ?? null)->get()->getRowArray();
            $db->table('menu_items')->insert($data + ['menu_id' => $menuId, 'position' => ((int) ($siblings['top'] ?? 0)) + 10, 'created_at' => $now, 'updated_at' => $now]);
            $itemId = (int) $db->insertID();
        } else {
            $db->table('menu_items')->where('id', $itemId)->where('menu_id', $menuId)->update($data + ['updated_at' => $now]);
        }

        $db->table('menu_item_translations')->where('item_id', $itemId)->delete();

        foreach ($labels as $locale => $label) {
            if ($label !== '') {
                $db->table('menu_item_translations')->insert(['item_id' => $itemId, 'locale' => $locale, 'label' => $label]);
            }
        }

        $db->table('menu_item_groups')->where('item_id', $itemId)->delete();

        foreach (array_unique($groups) as $group) {
            $db->table('menu_item_groups')->insert(['item_id' => $itemId, 'group_id' => $group]);
        }

        $db->transComplete();
        $this->changed();

        return $itemId;
    }

    /**
     * Deletes an item and its descendants.
     *
     * @return int how many items were deleted
     */
    public function deleteItem(int $menuId, int $itemId): int
    {
        $items = $this->items($menuId);

        if (! isset($items[$itemId])) {
            return 0;
        }

        $parents = array_map(static fn (array $i): ?string => $i['parent_id'] === null ? null : (string) $i['parent_id'], $items);
        $doomed  = array_values(array_filter(array_keys($items), static fn (int $id): bool => TreeMoves::isDescendant($parents, (string) $itemId, (string) $id)));

        $this->deleteRows($doomed);
        $this->changed();

        return count($doomed);
    }

    /** Deletes every item of a menu (before the menu itself is deleted). */
    public function deleteMenuItems(int $menuId): void
    {
        $this->deleteRows(array_keys($this->items($menuId)));
        $this->changed();
    }

    /**
     * Saves the whole order of a menu: id => [parent id|null, position]. Refuses unknown ids,
     * parents of another menu, cycles and nesting deeper than `MAX_DEPTH`.
     *
     * @param array<int, array{parent: int|null, position: int}> $order
     *
     * @throws RuntimeException with a translated message
     */
    public function reorder(int $menuId, array $order): void
    {
        $items   = $this->items($menuId);
        $parents = [];

        foreach ($items as $id => $item) {
            $parent = array_key_exists($id, $order) ? $order[$id]['parent'] : $item['parent_id'];

            if ($parent !== null && ! isset($items[$parent])) {
                throw new RuntimeException(lang('Menus.errors.parent'));
            }

            $parents[(string) $id] = $parent === null ? null : (string) $parent;
        }

        if (array_diff(array_keys($order), array_keys($items)) !== []) {
            throw new RuntimeException(lang('Menus.errors.unknownItem'));
        }

        self::checkTree($parents);

        $db = db_connect();
        $db->transStart();

        foreach ($order as $id => $place) {
            $db->table('menu_items')->where('id', $id)->where('menu_id', $menuId)->update(['parent_id' => $place['parent'], 'position' => $place['position']]);
        }

        $db->transComplete();
        $this->changed();
    }

    /**
     * Applies one move of the tree editor (`up`, `down`, `indent`, `outdent`).
     *
     * @throws RuntimeException with a translated message
     */
    public function move(int $menuId, int $itemId, string $move): void
    {
        $items = $this->items($menuId);

        if (! isset($items[$itemId])) {
            throw new RuntimeException(lang('Menus.errors.unknownItem'));
        }

        $nodes = [];

        foreach ($items as $id => $item) {
            $nodes[(string) $id] = ['parent' => $item['parent_id'] === null ? null : (string) $item['parent_id'], 'zone' => 'menu', 'order' => $item['position']];
        }

        $order = [];

        foreach (TreeMoves::move($nodes, (string) $itemId, $move) as $id => $node) {
            $order[(int) $id] = ['parent' => $node['parent'] === null ? null : (int) $node['parent'], 'position' => $node['order']];
        }

        $this->reorder($menuId, $order);
    }

    /**
     * The choices of an item's parent: every item but the item itself and its descendants, indented.
     *
     * @return array<int, string>
     */
    public function parentOptions(int $menuId, ?int $itemId, string $locale): array
    {
        $items   = $this->items($menuId);
        $parents = array_map(static fn (array $i): ?string => $i['parent_id'] === null ? null : (string) $i['parent_id'], $items);
        $options = [];

        $walk = static function (?int $parent, int $depth) use (&$walk, &$options, $items, $parents, $itemId, $locale): void {
            foreach ($items as $id => $item) {
                if ($item['parent_id'] !== $parent) {
                    continue;
                }

                if ($itemId !== null && TreeMoves::isDescendant($parents, (string) $itemId, (string) $id)) {
                    continue;
                }

                if ($depth < self::MAX_DEPTH - 1) {
                    $options[$id] = str_repeat('— ', $depth) . self::label($item['labels'], $locale);
                    $walk($id, $depth + 1);
                }
            }
        };
        $walk(null, 0);

        return $options;
    }

    /**
     * Refuses a parent that would put an item under itself or too deep.
     *
     * @throws RuntimeException with a translated message
     */
    public function checkParent(int $menuId, ?int $itemId, ?int $parent): void
    {
        $items   = $this->items($menuId);
        $parents = array_map(static fn (array $i): ?string => $i['parent_id'] === null ? null : (string) $i['parent_id'], $items);

        if ($parent !== null && ! isset($items[$parent])) {
            throw new RuntimeException(lang('Menus.errors.parent'));
        }

        $parents[$itemId === null ? 'new' : (string) $itemId] = $parent === null ? null : (string) $parent;
        self::checkTree($parents);
    }

    /**
     * @param array<array-key, string|null> $parents
     *
     * @throws RuntimeException
     */
    public static function checkTree(array $parents): void
    {
        if (TreeMoves::hasCycle($parents)) {
            throw new RuntimeException(lang('Menus.errors.cycle'));
        }

        foreach (array_keys($parents) as $id) {
            $depth = 0;

            for ($node = (string) $id; $node !== null; $node = $parents[$node] ?? null) {
                if (++$depth > self::MAX_DEPTH) {
                    throw new RuntimeException(lang('Menus.errors.depth', [self::MAX_DEPTH]));
                }
            }
        }
    }

    /**
     * @param list<int> $ids
     */
    private function deleteRows(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        // Portable clean-up (foreign keys are not enforced on every database).
        $db = db_connect();
        $db->transStart();
        $db->table('menu_item_translations')->whereIn('item_id', $ids)->delete();
        $db->table('menu_item_groups')->whereIn('item_id', $ids)->delete();
        $db->table('menu_items')->whereIn('id', $ids)->delete();
        $db->transComplete();
    }

    private function changed(): void
    {
        (new CacheInvalidator())->invalidate(['menus']);
    }
}
