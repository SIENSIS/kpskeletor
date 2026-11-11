<?php

declare(strict_types=1);

namespace Modules\Menus\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;
use Modules\Menus\Services\BackendLayout;
use Modules\Menus\Services\LinkTarget;
use Modules\Menus\Services\MenuRepository;
use Modules\Menus\Services\TreeMoves;
use RuntimeException;

/**
 * The items of a public menu (`backend/menus/items/<menu>`, `menus-public.edit`):
 *
 * - the tree editor: drag and drop, keyboard and up/down/indent/outdent buttons (the buttons also
 *   work without JavaScript, one move per request); "Save order" posts the whole tree;
 * - the item form: label per language, type (URL, route or a module's link type) and target,
 *   parent, icon, target window, CSS class, visibility (everyone, guests, signed-in users,
 *   groups). Addresses are checked by `UrlValidator` (never `javascript:`, `data:` or `//`).
 *
 * Every write is written to the activity log.
 */
final class MenuItems extends BackendController
{
    public function index(int $menuId): string
    {
        $menu   = $this->menu($menuId);
        $items  = new MenuRepository();
        $rows   = $items->items($menuId);
        $target = new LinkTarget();
        $locale = $this->request->getLocale();

        $this->breadcrumb(lang('Menus.public.title'), site_url('backend/menus/public'))->breadcrumb($menu['name']);

        return $this->render('Modules\Menus\Views\items', [
            'title'     => lang('Menus.items.title', [$menu['name']]),
            'subtitle'  => $menu['location'] === null ? lang('Menus.items.notPlaced') : lang('Menus.items.placed', [lang((string) (service('theme')->menuLocations('public')[$menu['location']] ?? $menu['location']))]),
            'icon'      => 'bi bi-diagram-3',
            'menu'      => $menu,
            'tree'      => self::tree($rows, null, $locale),
            'types'     => $target->typeLabels(),
            'broken'    => array_keys(array_filter($rows, static fn (array $row): bool => $target->resolve($row['type'], $row['value']) === null)),
            'orderUrl'  => site_url('backend/menus/items/' . $menuId . '/order'),
            'newUrl'    => site_url('backend/menus/items/' . $menuId . '/new'),
            'baseUrl'   => site_url('backend/menus/items/' . $menuId),
            'maxDepth'  => MenuRepository::MAX_DEPTH,
        ]);
    }

    public function order(int $menuId): RedirectResponse
    {
        $menu  = $this->menu($menuId);
        $items = new MenuRepository();
        $back  = site_url('backend/menus/items/' . $menuId);
        $move  = (string) $this->request->getPost('move');

        try {
            if ($move !== '') {
                [$id, $direction] = array_pad(explode(':', $move, 2), 2, '');

                if (! ctype_digit($id) || ! in_array($direction, TreeMoves::MOVES, true)) {
                    throw new RuntimeException(lang('Menus.errors.unknownItem'));
                }

                $items->move($menuId, (int) $id, $direction);
            } else {
                $items->reorder($menuId, self::postedOrder((string) $this->request->getPost('tree')));
            }
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        activity_log('Menus', 'items_reordered', ['resource' => 'menus-public', 'summary' => $menu['name']]);

        return redirect()->to($back . ($move !== '' ? '#item-' . (int) $move : ''))->with('success', lang('Menus.items.orderSaved'));
    }

    public function create(int $menuId): string
    {
        $parent = $this->request->getGet('parent');

        return $this->form($this->menu($menuId), null, [
            'parent_id' => is_string($parent) && ctype_digit($parent) ? (int) $parent : null,
            'type'      => LinkTarget::URL, 'value' => '', 'icon' => '', 'target' => '_self',
            'css_class' => '', 'visibility' => 'all', 'labels' => [], 'groups' => [],
        ]);
    }

    public function edit(int $menuId, int $itemId): string
    {
        $menu = $this->menu($menuId);
        $item = (new MenuRepository())->items($menuId)[$itemId] ?? null;

        if ($item === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($menu, $itemId, $item);
    }

    public function save(int $menuId): RedirectResponse
    {
        $menu   = $this->menu($menuId);
        $repo   = new MenuRepository();
        $items  = $repo->items($menuId);
        $id     = (string) $this->request->getPost('id');
        $itemId = ctype_digit($id) && $id !== '0' ? (int) $id : null;
        $back   = site_url('backend/menus/items/' . $menuId . ($itemId === null ? '/new' : '/edit/' . $itemId));

        if ($itemId !== null && ! isset($items[$itemId])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $errors = [];
        $data   = $this->validated($menuId, $itemId, $errors);

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('error', implode(' ', $errors));
        }

        $before = $itemId === null ? null : $items[$itemId];
        $saved  = $repo->saveItem($menuId, $itemId, $data['row'], $data['labels'], $data['groups']);

        activity_log('Menus', $itemId === null ? 'item_added' : 'item_edited', [
            'resource'  => 'menus-public',
            'record_id' => (string) $saved,
            'summary'   => $menu['name'] . ' › ' . ($data['labels']['ca'] ?? ''),
            'before'    => $before === null ? null : self::summary($before),
            'after'     => self::summary($data['row'] + ['labels' => $data['labels']]),
        ]);

        return redirect()->to(site_url('backend/menus/items/' . $menuId))->with('success', lang('Menus.items.saved'));
    }

    public function delete(int $menuId, int $itemId): RedirectResponse
    {
        $menu  = $this->menu($menuId);
        $repo  = new MenuRepository();
        $item  = $repo->items($menuId)[$itemId] ?? null;

        if ($item === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $count = $repo->deleteItem($menuId, $itemId);

        activity_log('Menus', 'item_deleted', [
            'resource'  => 'menus-public',
            'record_id' => (string) $itemId,
            'summary'   => $menu['name'] . ' › ' . MenuRepository::label($item['labels'], 'ca') . ($count > 1 ? ' (+' . ($count - 1) . ')' : ''),
            'before'    => self::summary($item),
        ]);

        return redirect()->to(site_url('backend/menus/items/' . $menuId))->with('success', lang('Menus.items.deleted', [$count]));
    }

    /**
     * @return array{id: int, name: string, location: string|null}
     */
    private function menu(int $menuId): array
    {
        $menu = (new MenuRepository())->find($menuId);

        if ($menu === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $menu;
    }

    /**
     * @param array{id: int, name: string, location: string|null} $menu
     * @param array<string, mixed>                                $item
     */
    private function form(array $menu, ?int $itemId, array $item): string
    {
        $target = new LinkTarget();
        $locale = $this->request->getLocale();
        $groups = [];

        foreach ((new GroupRegistry())->all() as $group) {
            $groups[$group['id']] = $group['title'];
        }

        $choices = [];

        foreach ($target->types()->all() as $key => $type) {
            $choices[$key] = array_map('strval', $type->options());
        }

        $this->breadcrumb(lang('Menus.public.title'), site_url('backend/menus/public'))
            ->breadcrumb($menu['name'], site_url('backend/menus/items/' . $menu['id']))
            ->breadcrumb(lang($itemId === null ? 'Menus.items.add' : 'Menus.items.edit'));

        return $this->render('Modules\Menus\Views\item_form', [
            'title'        => lang($itemId === null ? 'Menus.items.add' : 'Menus.items.edit'),
            'icon'         => 'bi bi-link-45deg',
            'menu'         => $menu,
            'itemId'       => $itemId,
            'item'         => $item,
            'types'        => $target->typeLabels(),
            'choices'      => $choices,
            'parents'      => (new MenuRepository())->parentOptions($menu['id'], $itemId, $locale),
            'groups'       => $groups,
            'locales'      => ContentTranslations::LOCALES,
            'visibilities' => array_combine(MenuRepository::VISIBILITIES, array_map(static fn (string $v): string => lang('Menus.visibility.' . $v), MenuRepository::VISIBILITIES)),
            'action'       => site_url('backend/menus/items/' . $menu['id'] . '/save'),
            'backUrl'      => site_url('backend/menus/items/' . $menu['id']),
        ]);
    }

    /**
     * The posted item, checked; errors are appended to `$errors`.
     *
     * @param list<list<string>|string> $errors lang() texts
     *
     * @return array{row: array<string, mixed>, labels: array<string, string>, groups: list<int>}
     */
    private function validated(int $menuId, ?int $itemId, array &$errors): array
    {
        $post   = fn (string $key): string => trim((string) (is_scalar($this->request->getPost($key)) ? $this->request->getPost($key) : ''));
        $labels = [];
        $posted = $this->request->getPost('labels');

        foreach (array_keys(ContentTranslations::LOCALES) as $locale) {
            $label = trim((string) (is_array($posted) && is_scalar($posted[$locale] ?? null) ? $posted[$locale] : ''));

            if (mb_strlen($label) > 100) {
                $errors[] = lang('Menus.errors.labelLength');
            }

            $labels[$locale] = mb_substr($label, 0, 100);
        }

        if ($labels['ca'] === '') {
            $errors[] = lang('Menus.errors.labelRequired');
        }

        $type    = $post('type');
        $value   = $post('value');
        $choices = $this->request->getPost('choice');

        if (! in_array($type, [LinkTarget::URL, LinkTarget::ROUTE], true)) {
            $value = is_array($choices) && is_scalar($choices[$type] ?? null) ? trim((string) $choices[$type]) : '';
        }

        $error = (new LinkTarget())->error($type, $value);

        if ($error !== null) {
            $errors[] = $error;
        }

        $parent   = $post('parent_id');
        $parentId = ctype_digit($parent) && $parent !== '0' ? (int) $parent : null;

        try {
            (new MenuRepository())->checkParent($menuId, $itemId, $parentId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        $icon = $post('icon');

        if ($icon !== '' && preg_match(BackendLayout::ICON, $icon) !== 1) {
            $errors[] = lang('Menus.errors.icon');
        }

        $class = $post('css_class');

        if (preg_match('/^[A-Za-z0-9_ -]{0,100}$/', $class) !== 1) {
            $errors[] = lang('Menus.errors.cssClass');
        }

        $target     = $post('target');
        $visibility = $post('visibility');

        if (! in_array($target, MenuRepository::TARGETS, true)) {
            $errors[] = lang('Menus.errors.target');
        }

        if (! in_array($visibility, MenuRepository::VISIBILITIES, true)) {
            $errors[] = lang('Menus.errors.visibility');
        }

        $known  = array_column((new GroupRegistry())->all(), 'id');
        $groups = [];

        foreach ((array) $this->request->getPost('groups') as $group) {
            if (is_scalar($group) && ctype_digit((string) $group) && in_array((int) $group, $known, true)) {
                $groups[] = (int) $group;
            }
        }

        if ($visibility === 'groups' && $groups === []) {
            $errors[] = lang('Menus.errors.groups');
        }

        return [
            'row' => [
                'parent_id'  => $parentId,
                'type'       => $type,
                'value'      => $value,
                'icon'       => $icon,
                'target'     => $target,
                'css_class'  => trim(preg_replace('/\s+/', ' ', $class) ?? ''),
                'visibility' => $visibility,
            ],
            'labels' => $labels,
            'groups' => $visibility === 'groups' ? $groups : [],
        ];
    }

    /**
     * The order posted by the tree editor: a JSON list of {id, parent, position}.
     *
     * @return array<int, array{parent: int|null, position: int}>
     */
    private static function postedOrder(string $json): array
    {
        $rows = json_decode($json, true);

        if (! is_array($rows) || count($rows) > 1000) {
            throw new RuntimeException(lang('Menus.errors.tree'));
        }

        $order = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_int($row['id'] ?? null) || ! is_int($row['position'] ?? null) || ! (is_int($row['parent'] ?? null) || ($row['parent'] ?? null) === null)) {
                throw new RuntimeException(lang('Menus.errors.tree'));
            }

            $order[$row['id']] = ['parent' => $row['parent'], 'position' => max(0, min(1_000_000, $row['position']))];
        }

        return $order;
    }

    /**
     * Nested items for the editor.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private static function tree(array $rows, ?int $parent, string $locale): array
    {
        $tree = [];

        foreach ($rows as $id => $row) {
            if ($row['parent_id'] === $parent) {
                $tree[] = $row + ['text' => MenuRepository::label($row['labels'], $locale), 'children' => self::tree($rows, $id, $locale)];
            }
        }

        return $tree;
    }

    /**
     * What the activity log keeps of an item.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, string>
     */
    private static function summary(array $item): array
    {
        return [
            'label'      => is_array($item['labels'] ?? null) ? implode(' / ', array_filter($item['labels'])) : '',
            'target'     => (string) ($item['type'] ?? '') . ': ' . (string) ($item['value'] ?? ''),
            'parent'     => (string) ($item['parent_id'] ?? ''),
            'visibility' => (string) ($item['visibility'] ?? ''),
        ];
    }
}
