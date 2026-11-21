<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionForm;
use Modules\Core\Sections\SectionFormView;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionRegistry;
use Modules\Core\Sections\SectionType;
use Modules\Core\Sections\SectionValidationException;
use Modules\Pages\Services\PageRepository;
use Modules\Pages\Services\PageSections;
use Modules\Pages\Services\Revisions;
use RuntimeException;

/**
 * The section editor of a page (`backend/pages/<id>/sections`, D-45-01; docs/pages.md, "Pages
 * built from sections"): `pages.edit` on the routes and the page's own record rules
 * (`$crud->isAllowed('edit', $page)`, the same resolver as the CRUD) on every request.
 *
 * - the list: drag and drop or keyboard reordering (`pages/section-list`; the up/down buttons
 *   also work without JavaScript), edit, duplicate, hide/show, delete, "Add section", "Preview";
 * - the gallery of the section types of the enabled modules (thumbnails, descriptions);
 * - the form of a section, generated from its type's schema and validated on the server
 *   (`SectionForm`; forged posts are refused with the field's message);
 * - the page's layout (`article` | `sections`); switching keeps the other content.
 *
 * Every change records a page revision (sections included) and an activity entry.
 */
final class Sections extends BackendController
{
    public function index(int $pageId): string
    {
        $page     = $this->page($pageId);
        $registry = new SectionRegistry();
        $locale   = $this->request->getLocale();
        $rows     = [];

        foreach ((new PageSections())->all($pageId) as $section) {
            $type   = $registry->get($section->type);
            $rows[] = [
                'section' => $section,
                'type'    => $type,
                'summary'  => $type?->summary($section, $locale) ?? '',
                'warnings' => $type?->warnings($section, auth()->user()) ?? [],
            ];
        }

        $this->crumbs($page);

        return $this->render('Modules\Pages\Views\editor\list', [
            'title'      => lang('Pages.sections.titleOf', [$page['title']]),
            'icon'       => 'bi bi-layout-text-window-reverse',
            'page'       => $page,
            'rows'       => $rows,
            'baseUrl'    => backend_url('pages/' . $pageId . '/sections'),
            'previewUrl' => $this->crudUrl('preview', $pageId),
            'editUrl'    => $this->crudUrl('edit', $pageId),
            'revisionsUrl' => $this->crudUrl('revisions', $pageId),
            'full'       => count($rows) >= PageSections::MAX_SECTIONS,
        ]);
    }

    public function gallery(int $pageId): string
    {
        $page = $this->page($pageId);
        $this->crumbs($page, lang('Pages.sections.add'));

        return $this->render('Modules\Pages\Views\editor\gallery', [
            'title'   => lang('Pages.sections.addTo', [$page['title']]),
            'icon'    => 'bi bi-plus-square',
            'types'   => (new SectionRegistry())->all(),
            'newUrl'  => backend_url('pages/' . $pageId . '/sections/new'),
            'backUrl' => backend_url('pages/' . $pageId . '/sections'),
        ]);
    }

    public function create(int $pageId, string $key): string
    {
        $page = $this->page($pageId);
        $type = $this->type($key);

        return $this->form($page, $type, null, (new SectionForm($type, FieldContext::current('Pages')))->formValues(null), SectionOptions::defaults($type), false);
    }

    public function edit(int $pageId, int $sectionId): string
    {
        $page    = $this->page($pageId);
        $section = (new PageSections())->find($pageId, $sectionId) ?? throw PageNotFoundException::forPageNotFound();
        $type    = $this->type($section->type);
        $form    = new SectionForm($type, FieldContext::current('Pages', $type->fileKeys($section)));

        return $this->form($page, $type, $sectionId, $form->formValues($section), $section->options, $section->hidden, null, $type->warnings($section, auth()->user()));
    }

    public function save(int $pageId): RedirectResponse|ResponseInterface|string
    {
        $page     = $this->page($pageId);
        $sections = new PageSections();
        $id       = (string) $this->request->getPost('id');
        $id       = ctype_digit($id) && $id !== '0' ? (int) $id : null;
        $current  = $id === null ? null : ($sections->find($pageId, $id) ?? throw PageNotFoundException::forPageNotFound());
        $type     = $this->type($current !== null ? $current->type : (string) $this->request->getPost('type'));
        $context  = FieldContext::current('Pages', $current === null ? [] : $type->fileKeys($current));
        $hidden   = $this->request->getPost('hidden') === '1';

        try {
            $section = (new SectionForm($type, $context))->validate($this->request->getPost('f'), $this->request->getPost('o'), $hidden, $id);
            $saved   = $sections->save($pageId, $section, $id);
        } catch (SectionValidationException $e) {
            $posted = $this->request->getPost('f');
            $posted = is_array($posted) ? $posted : [];
            $options = $this->request->getPost('o');

            $this->response->setStatusCode(422);

            return $this->form($page, $type, $id, $posted, is_array($options) ? $options : [], $hidden, $e->toArray());
        } catch (RuntimeException $e) {
            return redirect()->to(backend_url('pages/' . $pageId . '/sections'))->with('error', $e->getMessage());
        }

        $this->changed($pageId, $id === null ? 'section_added' : 'section_edited', $page, $type->label());

        return redirect()->to(backend_url('pages/' . $pageId . '/sections') . '#section-' . $saved)
            ->with('success', lang($id === null ? 'Pages.sections.added' : 'Pages.sections.saved'));
    }

    public function order(int $pageId): RedirectResponse
    {
        $page = $this->page($pageId);
        $back = backend_url('pages/' . $pageId . '/sections');
        $move = (string) $this->request->getPost('move');

        try {
            if ($move !== '') {
                [$id, $direction] = array_pad(explode(':', $move, 2), 2, '');

                if (! ctype_digit($id)) {
                    throw new RuntimeException(lang('Pages.sections.errors.notFound'));
                }

                (new PageSections())->move($pageId, (int) $id, $direction);
            } else {
                $order = json_decode((string) $this->request->getPost('order'), true);

                if (! is_array($order) || array_filter($order, static fn (mixed $id): bool => ! is_int($id)) !== []) {
                    throw new RuntimeException(lang('Pages.sections.errors.order'));
                }

                (new PageSections())->reorder($pageId, array_values($order));
            }
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($pageId, 'sections_reordered', $page);

        return redirect()->to($back . ($move !== '' ? '#section-' . (int) $move : ''))->with('success', lang('Pages.sections.orderSaved'));
    }

    public function duplicate(int $pageId, int $sectionId): RedirectResponse
    {
        $page = $this->page($pageId);
        $back = backend_url('pages/' . $pageId . '/sections');

        try {
            $copy = (new PageSections())->duplicate($pageId, $sectionId);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($pageId, 'section_duplicated', $page);

        return redirect()->to($back . '#section-' . $copy)->with('success', lang('Pages.sections.duplicated'));
    }

    public function hide(int $pageId, int $sectionId): RedirectResponse
    {
        $page   = $this->page($pageId);
        $back   = backend_url('pages/' . $pageId . '/sections');
        $hidden = $this->request->getPost('hidden') === '1';

        try {
            (new PageSections())->setHidden($pageId, $sectionId, $hidden);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($pageId, $hidden ? 'section_hidden' : 'section_shown', $page);

        return redirect()->to($back . '#section-' . $sectionId)->with('success', lang($hidden ? 'Pages.sections.hiddenOk' : 'Pages.sections.shownOk'));
    }

    public function delete(int $pageId, int $sectionId): RedirectResponse
    {
        $page = $this->page($pageId);
        $back = backend_url('pages/' . $pageId . '/sections');

        try {
            (new PageSections())->delete($pageId, $sectionId);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($pageId, 'section_deleted', $page);

        return redirect()->to($back)->with('success', lang('Pages.sections.deleted'));
    }

    /**
     * Switches the page's layout (`article` | `sections`); the other content is kept.
     */
    public function switchLayout(int $pageId): RedirectResponse
    {
        $page   = $this->page($pageId);
        $layout = (string) $this->request->getPost('layout');
        $back   = backend_url('pages/' . $pageId . '/sections');

        if (! in_array($layout, PageRepository::LAYOUTS, true)) {
            return redirect()->to($back)->with('error', lang('Pages.sections.errors.layout'));
        }

        if ($layout !== ($page['layout'] ?? 'article')) {
            db_connect()->table('pages')->where('id', $pageId)->update(['layout' => $layout, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->changed($pageId, 'layout_changed', $page, lang('Pages.layouts.' . $layout));
        }

        return redirect()->to($back)->with('success', lang('Pages.sections.layoutSaved', [lang('Pages.layouts.' . $layout)]));
    }

    /**
     * @param array<string, mixed>                       $page
     * @param array<string, mixed>                       $values  field => posted shape
     * @param array<string, mixed>                       $options
     * @param array{path: string, message: string, link?: array{url: string, label: string}}|null $error
     * @param list<array{message: string, link?: array{url: string, label: string}}> $warnings
     */
    private function form(array $page, SectionType $type, ?int $sectionId, array $values, array $options, bool $hidden, ?array $error = null, array $warnings = []): string
    {
        $pageId  = (int) $page['id'];
        $context = FieldContext::current('Pages');
        $this->crumbs($page, $type->label());

        return $this->render('Modules\Pages\Views\editor\form', [
            'title'     => lang($sectionId === null ? 'Pages.sections.new' : 'Pages.sections.edit', [$type->label()]),
            'icon'      => $type->icon(),
            'type'      => $type,
            'sectionId' => $sectionId,
            'fields'    => (new SectionFormView($type, $context, $values, $options, $hidden, $error, $warnings))->render(),
            'action'    => backend_url('pages/' . $pageId . '/sections/save'),
            'backUrl'   => backend_url('pages/' . $pageId . '/sections'),
        ]);
    }

    /**
     * The page (not trashed) when the user may edit it (the CRUD's resolver and record rules).
     *
     * @return array<string, mixed>
     */
    private function page(int $pageId): array
    {
        $page = (new PageRepository())->find($pageId);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->crud('pages')->setTable('pages')->isAllowed('edit', $page)) {
            security_log('Pages', 'sections_denied', ['resource' => 'pages', 'record_id' => (string) $pageId]);

            throw PageNotFoundException::forPageNotFound();
        }

        return $page;
    }

    private function type(string $key): SectionType
    {
        $type = (new SectionRegistry())->get($key);

        if ($type === null) {
            throw PageNotFoundException::forPageNotFound(lang('Sections.errors.unknownType'));
        }

        return $type;
    }

    /**
     * After a change: a page revision (sections included) and an activity entry.
     *
     * @param array<string, mixed> $page
     */
    private function changed(int $pageId, string $action, array $page, string $detail = ''): void
    {
        (new Revisions())->record($pageId, (int) auth()->id());
        activity_log('Pages', $action, [
            'resource'  => 'pages',
            'record_id' => (string) $pageId,
            'summary'   => (string) $page['title'] . ($detail !== '' ? ' › ' . $detail : ''),
        ]);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function crumbs(array $page, ?string $last = null): void
    {
        $pageId = (int) $page['id'];
        $this->breadcrumb(lang('Pages.pages'), backend_url('pages'))
            ->breadcrumb((string) $page['title'], $this->crudUrl('edit', $pageId));

        if ($last === null) {
            $this->breadcrumb(lang('Pages.sections.title'));
        } else {
            $this->breadcrumb(lang('Pages.sections.title'), backend_url('pages/' . $pageId . '/sections'))->breadcrumb($last);
        }
    }

    /** A URL of the pages CRUD with the masked id (`edit`, `preview`, `revisions`). */
    private function crudUrl(string $operation, int $pageId): string
    {
        return backend_url('pages/' . $operation . '/' . rawurlencode(Crud::idForUrl($pageId, 'pages')));
    }
}
