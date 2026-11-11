<?php

declare(strict_types=1);

namespace Modules\Menus\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;
use Modules\Menus\Services\BackendLayout;
use Modules\Menus\Services\Customisations;
use Modules\Menus\Services\TreeMoves;
use RuntimeException;

/**
 * Customisation of the back-office menu (`backend/menus/backend`; docs/menus.md): the generated
 * menu in a tree editor (entries marked with their module, custom groups and links), for everyone
 * or for one group (`?group=<name>`, the most specific scope wins):
 *
 * - reorder and relocate entries (drag and drop, keyboard, buttons; `menus-backend.customise`);
 * - rename, change the icon, hide, move to another parent or zone (`customise`);
 * - reset an entry, a scope or everything, and delete orphaned customisations (`reset`).
 *
 * Custom groups and links are edited in `CustomLinks`. Permissions always win: the layer never
 * changes an entry's route or permission. Every write is written to the activity log.
 */
final class BackendMenu extends BackendController
{
    public function index(): string
    {
        $scope  = $this->scope((string) $this->request->getGet('group'));
        $layout = new BackendLayout();
        $groups = (new GroupRegistry())->all();
        $known  = $layout->knownKeys();

        $this->breadcrumb(lang('Menus.backend.title'), site_url('backend/menus/backend'));

        if ($scope !== null) {
            $this->breadcrumb($groups[$scope]['title']);
        }

        return $this->render('Modules\Menus\Views\backend', [
            'title'        => lang('Menus.backend.title'),
            'subtitle'     => lang('Menus.backend.subtitle'),
            'icon'         => 'bi bi-layout-sidebar',
            'scope'        => $scope,
            'groups'       => $groups,
            'tree'         => $layout->tree($scope),
            'scopeCount'   => count($layout->store()->all()[$scope ?? ''] ?? []),
            'orphans'      => $this->can('menus-backend.reset') ? $layout->store()->orphans($known) : [],
            'canCustomise' => $this->can('menus-backend.customise'),
            'canLinks'     => $this->can('menus-backend.links'),
            'canReset'     => $this->can('menus-backend.reset'),
            'zones'        => self::zoneLabels(),
        ]);
    }

    public function order(): RedirectResponse
    {
        $scope  = $this->scope((string) $this->request->getPost('group'));
        $layout = new BackendLayout();
        $back   = self::url($scope);
        $move   = (string) $this->request->getPost('move');

        try {
            if ($move !== '') {
                $at  = strrpos($move, ':');
                $key = $at === false ? '' : substr($move, 0, $at);
                $dir = $at === false ? '' : substr($move, $at + 1);

                if (! in_array($dir, TreeMoves::MOVES, true)) {
                    throw new RuntimeException(lang('Menus.errors.unknownEntry'));
                }

                $changed = $layout->move($scope, $key, $dir, $this->userId());
            } else {
                $changed = $layout->saveTree($scope, self::postedTree((string) $this->request->getPost('tree')), $this->userId());
            }
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        activity_log('Menus', 'backend_reordered', ['resource' => 'menus-backend', 'summary' => self::scopeName($scope) . ': ' . $changed]);

        return redirect()->to($back)->with('success', lang('Menus.backend.orderSaved'));
    }

    public function entry(): string
    {
        $scope  = $this->scope((string) $this->request->getGet('group'));
        $key    = (string) $this->request->getGet('key');
        $layout = new BackendLayout();
        $all    = $layout->definitions($scope);
        $base   = $layout->base($scope);

        if (! isset($all[$key])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $nodes   = BackendLayout::nodes($all);
        $parents = array_map(static fn (array $n): ?string => $n['parent'], $nodes);
        $options = [];

        foreach ($all as $other => $definition) {
            if (! TreeMoves::isDescendant($parents, $key, (string) $other)) {
                $options[(string) $other] = BackendLayout::text($definition) . ' (' . ((string) ($definition['module'] ?? '') !== '' ? $definition['module'] : lang('Menus.backend.custom')) . ')';
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $stored = $layout->store()->get($key, $scope);

        $this->breadcrumb(lang('Menus.backend.title'), self::url($scope))->breadcrumb(BackendLayout::text($all[$key]));

        return $this->render('Modules\Menus\Views\entry_form', [
            'title'     => lang('Menus.backend.editEntry', [BackendLayout::text($all[$key])]),
            'icon'      => 'bi bi-pencil-square',
            'key'       => $key,
            'scope'     => $scope,
            'scopeName' => self::scopeName($scope),
            'entry'     => $all[$key],
            'base'      => $base[$key],
            'baseText'  => BackendLayout::text($base[$key]),
            'stored'    => $stored,
            'parent'    => $nodes[$key]['parent'],
            'parents'   => $options,
            'zones'     => self::zoneLabels(),
            'locales'   => ContentTranslations::LOCALES,
            'action'    => site_url('backend/menus/backend/entry'),
            'backUrl'   => self::url($scope),
            'canReset'  => $this->can('menus-backend.reset'),
        ]);
    }

    public function saveEntry(): RedirectResponse
    {
        $scope  = $this->scope((string) $this->request->getPost('group'));
        $key    = (string) $this->request->getPost('key');
        $layout = new BackendLayout();
        $labels = [];
        $posted = $this->request->getPost('labels');

        foreach (array_keys(ContentTranslations::LOCALES) as $locale) {
            $label = is_array($posted) && is_scalar($posted[$locale] ?? null) ? trim((string) $posted[$locale]) : '';

            if (mb_strlen($label) > 100) {
                return redirect()->back()->withInput()->with('error', lang('Menus.errors.labelLength'));
            }

            $labels[$locale] = $label;
        }

        $parent = (string) $this->request->getPost('parent');
        $before = $layout->store()->get($key, $scope);

        try {
            $layout->saveEntry(
                $scope,
                $key,
                $labels,
                trim((string) $this->request->getPost('icon')),
                $this->request->getPost('hidden') === '1',
                $parent === '' ? null : $parent,
                (string) $this->request->getPost('zone'),
                $this->userId(),
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        activity_log('Menus', 'backend_entry_customised', [
            'resource'  => 'menus-backend',
            'record_id' => $key,
            'summary'   => self::scopeName($scope) . ': ' . $key,
            'before'    => self::summary($before),
            'after'     => self::summary($layout->store()->get($key, $scope)),
        ]);

        return redirect()->to(self::url($scope))->with('success', lang('Menus.backend.entrySaved'));
    }

    public function reset(): RedirectResponse
    {
        $what  = (string) $this->request->getPost('reset');
        $scope = $this->scope((string) $this->request->getPost('group'));
        $store = new Customisations();
        $key   = (string) $this->request->getPost('key');

        $count = match ($what) {
            'entry'      => $key === '' ? 0 : $store->reset($key, $scope),
            'scope'      => $store->resetAll($scope),
            'everything' => $store->resetAll(null, true),
            default      => throw PageNotFoundException::forPageNotFound(),
        };

        activity_log('Menus', 'backend_reset', [
            'resource'  => 'menus-backend',
            'record_id' => $what === 'entry' ? $key : null,
            'summary'   => ($what === 'everything' ? lang('Menus.backend.everything') : self::scopeName($scope)) . ($what === 'entry' ? ': ' . $key : '') . ' (' . $count . ')',
        ]);

        return redirect()->to(self::url($what === 'everything' ? null : $scope))->with('success', lang('Menus.backend.resetDone', [$count]));
    }

    public function deleteOrphans(): RedirectResponse
    {
        $layout  = new BackendLayout();
        $orphans = $layout->store()->orphans($layout->knownKeys());
        $ids     = array_map('intval', array_filter((array) $this->request->getPost('ids'), static fn ($id): bool => is_scalar($id) && ctype_digit((string) $id)));
        $ids     = array_values(array_intersect($ids, array_column($orphans, 'id'))); // only orphans: never an active customisation
        $count   = $layout->store()->deleteRows($ids);

        activity_log('Menus', 'backend_orphans_deleted', [
            'resource' => 'menus-backend',
            'summary'  => implode(', ', array_column(array_filter($orphans, static fn (array $o): bool => in_array($o['id'], $ids, true)), 'key')),
        ]);

        return redirect()->to(self::url(null))->with('success', lang('Menus.backend.orphansDeleted', [$count]));
    }

    /**
     * The scope of a request: null (everyone) or an existing group's name.
     */
    private function scope(string $group): ?string
    {
        if ($group === '') {
            return null;
        }

        if ((new GroupRegistry())->find($group) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $group;
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }

    private static function url(?string $scope): string
    {
        return site_url('backend/menus/backend') . ($scope === null ? '' : '?group=' . rawurlencode($scope));
    }

    private static function scopeName(?string $scope): string
    {
        return $scope === null ? lang('Menus.backend.everyone') : $scope;
    }

    /**
     * @return array<string, string>
     */
    public static function zoneLabels(): array
    {
        $labels = [];

        foreach (Customisations::ZONES as $zone) {
            $labels[$zone] = lang('Menus.zones.' . $zone);
        }

        return $labels;
    }

    /**
     * The tree posted by the editor: a JSON list of {key, parent, zone} in display order.
     *
     * @return array<string, array{parent: ?string, zone: string, order: int}>
     */
    private static function postedTree(string $json): array
    {
        $rows = json_decode($json, true);

        if (! is_array($rows) || count($rows) > 1000) {
            throw new RuntimeException(lang('Menus.errors.tree'));
        }

        $places = [];

        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row) || ! is_string($row['key'] ?? null) || ! is_string($row['zone'] ?? null) || ! (is_string($row['parent'] ?? null) || ($row['parent'] ?? null) === null) || isset($places[$row['key']])) {
                throw new RuntimeException(lang('Menus.errors.tree'));
            }

            $places[$row['key']] = ['parent' => $row['parent'] === '' ? null : $row['parent'], 'zone' => $row['zone'], 'order' => ($i + 1) * 10];
        }

        return $places;
    }

    /**
     * What the activity log keeps of a customisation.
     *
     * @param array<string, mixed>|null $fields
     *
     * @return array<string, string>
     */
    private static function summary(?array $fields): array
    {
        if ($fields === null) {
            return [];
        }

        $summary = [];

        foreach ($fields as $field => $value) {
            if ($value !== null) {
                $summary[$field] = is_array($value) ? implode(' / ', array_map('strval', $value)) : (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
            }
        }

        return $summary;
    }
}
