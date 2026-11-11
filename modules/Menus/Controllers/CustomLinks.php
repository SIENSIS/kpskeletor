<?php

declare(strict_types=1);

namespace Modules\Menus\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Theme\ErrorPage;
use Modules\Menus\Services\BackendLayout;
use Modules\Menus\Services\Customisations;
use Modules\Menus\Services\LinkTarget;
use Modules\Menus\Services\TreeMoves;

/**
 * Custom groups (`menus-backend.customise`) and custom links (`menus-backend.links`) of the
 * back-office menu (docs/menus.md):
 *
 * - a group is a heading that gathers entries (e.g. "Content" with Pages, News and Events); it is
 *   hidden while it has no visible entry;
 * - a link leads to a back-office path or route, an external URL, a public page or a module link
 *   type, with a label per language, an icon, the target window (external links always get
 *   `rel="noopener noreferrer"`), its place and who sees it: groups and/or a permission from the
 *   catalogue. A link to an internal path or route also requires that route's `can:`
 *   permissions, so nobody is shown a link that answers 403.
 *
 * Every write is written to the activity log.
 */
final class CustomLinks extends BackendController
{
    public function createGroup(): string
    {
        return $this->form(null, self::blank('group'));
    }

    public function createLink(): string
    {
        return $this->form(null, self::blank('link'));
    }

    public function edit(int $id): ResponseInterface|string
    {
        $link = (new Customisations())->links()[$id] ?? null;

        if ($link === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->refused($link['kind']) ?? $this->form($id, $link);
    }

    public function save(): ResponseInterface
    {
        $store = new Customisations();
        $raw   = (string) $this->request->getPost('id');
        $id    = ctype_digit($raw) && $raw !== '0' ? (int) $raw : null;
        $old   = $id === null ? null : ($store->links()[$id] ?? null);

        if ($id !== null && $old === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $kind    = $old['kind'] ?? ((string) $this->request->getPost('kind') === 'group' ? 'group' : 'link');
        $refused = $this->refused($kind);

        if ($refused !== null) {
            return $refused;
        }

        $errors = [];
        $link   = $this->validated($kind, $id, $errors);
        $back   = $id === null ? site_url('backend/menus/backend/' . $kind . '/new') : site_url('backend/menus/backend/custom/' . $id);

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('error', implode(' ', $errors));
        }

        $saved = $store->saveLink($id, $link, $this->userId());

        activity_log('Menus', $id === null ? 'backend_custom_added' : 'backend_custom_edited', [
            'resource'  => 'menus-backend',
            'record_id' => Customisations::PREFIX . $saved,
            'summary'   => $kind . ': ' . ($link['labels']['ca'] ?? ''),
            'before'    => $old === null ? null : self::summary($old),
            'after'     => self::summary($link),
        ]);

        return redirect()->to(site_url('backend/menus/backend'))->with('success', lang('Menus.custom.saved'));
    }

    public function delete(int $id): ResponseInterface
    {
        $store = new Customisations();
        $link  = $store->links()[$id] ?? null;

        if ($link === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $refused = $this->refused($link['kind']);

        if ($refused !== null) {
            return $refused;
        }

        $store->deleteLink($id);

        activity_log('Menus', 'backend_custom_deleted', [
            'resource'  => 'menus-backend',
            'record_id' => Customisations::PREFIX . $id,
            'summary'   => $link['kind'] . ': ' . ($link['labels']['ca'] ?? ''),
            'before'    => self::summary($link),
        ]);

        return redirect()->to(site_url('backend/menus/backend'))->with('success', lang('Menus.custom.deleted'));
    }

    /**
     * Groups need `customise`, links need `links` (the routes accept either): the themed 403
     * page when the user lacks the one this kind needs.
     */
    private function refused(string $kind): ?ResponseInterface
    {
        $permission = $kind === 'group' ? 'menus-backend.customise' : 'menus-backend.links';

        if ($this->can($permission)) {
            return null;
        }

        security_log('Menus', 'custom_forbidden', ['summary' => $permission . ' ' . uri_string()]);

        return service('response')->setStatusCode(403)->setContentType('text/html')->setBody(ErrorPage::render(403, lang('Core.forbidden')));
    }

    /**
     * @param array<string, mixed> $link
     */
    private function form(?int $id, array $link): string
    {
        $target  = new LinkTarget();
        $layout  = new BackendLayout();
        $parents = [];

        foreach ($layout->definitions(null) as $key => $definition) {
            if ($key !== Customisations::PREFIX . $id) {
                $parents[(string) $key] = BackendLayout::text($definition) . ' (' . ((string) ($definition['module'] ?? '') !== '' ? $definition['module'] : lang('Menus.backend.custom')) . ')';
            }
        }

        asort($parents, SORT_NATURAL | SORT_FLAG_CASE);
        $choices = [];

        foreach ($target->types()->all() as $key => $type) {
            $choices[$key] = array_map('strval', $type->options());
        }

        $groups = [];

        foreach ((new GroupRegistry())->all() as $name => $group) {
            $groups[$name] = $group['title'];
        }

        $title = lang($link['kind'] === 'group' ? ($id === null ? 'Menus.custom.addGroup' : 'Menus.custom.editGroup') : ($id === null ? 'Menus.custom.addLink' : 'Menus.custom.editLink'));
        $this->breadcrumb(lang('Menus.backend.title'), site_url('backend/menus/backend'))->breadcrumb($title);

        return $this->render('Modules\Menus\Views\custom_form', [
            'title'       => $title,
            'icon'        => $link['kind'] === 'group' ? 'bi bi-folder-plus' : 'bi bi-link-45deg',
            'id'          => $id,
            'link'        => $link,
            'types'       => $target->typeLabels(),
            'choices'     => $choices,
            'parents'     => $parents,
            'zones'       => BackendMenu::zoneLabels(),
            'groups'      => $groups,
            'permissions' => (new PermissionCatalogue())->enabledNames(),
            'locales'     => ContentTranslations::LOCALES,
            'action'      => site_url('backend/menus/backend/custom/save'),
            'deleteUrl'   => $id === null ? null : site_url('backend/menus/backend/custom/' . $id . '/delete'),
            'backUrl'     => site_url('backend/menus/backend'),
        ]);
    }

    /**
     * The posted group or link, checked; errors are appended to `$errors`.
     *
     * @param list<list<string>|string> $errors lang() texts
     *
     * @return array{kind: string, type: string, value: string, labels: array<string, string>, icon: string, target: string, permission: string, groups: list<string>, parent: ?string, zone: string, position: int}
     */
    private function validated(string $kind, ?int $id, array &$errors): array
    {
        $post   = fn (string $key): string => trim((string) (is_scalar($this->request->getPost($key)) ? $this->request->getPost($key) : ''));
        $labels = [];
        $posted = $this->request->getPost('labels');

        foreach (array_keys(ContentTranslations::LOCALES) as $locale) {
            $label = trim((string) (is_array($posted) && is_scalar($posted[$locale] ?? null) ? $posted[$locale] : ''));

            if (mb_strlen($label) > 100) {
                $errors[] = lang('Menus.errors.labelLength');
            }

            if ($label !== '') {
                $labels[$locale] = mb_substr($label, 0, 100);
            }
        }

        if (($labels['ca'] ?? '') === '') {
            $errors[] = lang('Menus.errors.labelRequired');
        }

        $icon = $post('icon');

        if ($icon !== '' && preg_match(BackendLayout::ICON, $icon) !== 1) {
            $errors[] = lang('Menus.errors.icon');
        }

        $zone = $post('zone');

        if (! in_array($zone, Customisations::ZONES, true)) {
            $errors[] = lang('Menus.errors.zone');
        }

        $parent      = $post('parent');
        $definitions = (new BackendLayout())->definitions(null);

        if ($parent !== '' && (! isset($definitions[$parent]) || $parent === Customisations::PREFIX . $id || ($id !== null && TreeMoves::isDescendant(array_map(static fn (array $n): ?string => $n['parent'], BackendLayout::nodes($definitions)), Customisations::PREFIX . $id, $parent)))) {
            $errors[] = lang('Menus.errors.cycle');
        }

        $position = $post('position');

        if (preg_match('/^\d{1,4}$/', $position) !== 1) {
            $errors[] = lang('Menus.errors.position');
        }

        $link = [
            'kind'       => $kind,
            'type'       => '',
            'value'      => '',
            'labels'     => $labels,
            'icon'       => $icon,
            'target'     => '_self',
            'permission' => '',
            'groups'     => [],
            'parent'     => $parent === '' ? null : $parent,
            'zone'       => $zone,
            'position'   => (int) $position,
        ];

        if ($kind === 'group') {
            return $link;
        }

        $type  = $post('type');
        $value = $post('value');

        if (! in_array($type, [LinkTarget::URL, LinkTarget::ROUTE], true)) {
            $choices = $this->request->getPost('choice');
            $value   = is_array($choices) && is_scalar($choices[$type] ?? null) ? trim((string) $choices[$type]) : '';
        }

        $error = (new LinkTarget())->error($type, $value);

        if ($error !== null) {
            $errors[] = $error;
        }

        $target = $post('target');

        if (! in_array($target, ['_self', '_blank'], true)) {
            $errors[] = lang('Menus.errors.target');
        }

        $permission = $post('permission');

        if ($permission !== '' && ! in_array($permission, (new PermissionCatalogue())->enabledNames(), true)) {
            $errors[] = lang('Menus.errors.permission');
        }

        $known  = array_keys((new GroupRegistry())->all());
        $groups = [];

        foreach ((array) $this->request->getPost('groups') as $group) {
            if (is_string($group) && in_array($group, $known, true)) {
                $groups[] = $group;
            }
        }

        return ['type' => $type, 'value' => $value, 'target' => $target, 'permission' => $permission, 'groups' => array_values(array_unique($groups))] + $link;
    }

    /**
     * @return array<string, mixed>
     */
    private static function blank(string $kind): array
    {
        return [
            'kind' => $kind, 'type' => LinkTarget::URL, 'value' => '', 'labels' => [], 'icon' => '', 'target' => '_self',
            'permission' => '', 'groups' => [], 'parent' => null, 'zone' => 'sidebar', 'position' => 100,
        ];
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string, string>
     */
    private static function summary(array $link): array
    {
        return [
            'label'      => implode(' / ', (array) ($link['labels'] ?? [])),
            'target'     => ($link['kind'] ?? '') === 'group' ? '' : (string) ($link['type'] ?? '') . ': ' . (string) ($link['value'] ?? ''),
            'place'      => (string) ($link['zone'] ?? '') . ' / ' . (string) ($link['parent'] ?? '') . ' / ' . (string) ($link['position'] ?? ''),
            'visibility' => trim((string) ($link['permission'] ?? '') . ' ' . implode(',', (array) ($link['groups'] ?? []))),
        ];
    }
}
