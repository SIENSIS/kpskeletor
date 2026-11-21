<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Kpcrudy\Crud;
use Modules\Pages\Services\PageRepository;
use Modules\Pages\Services\PageSections;
use Modules\Pages\Services\Revisions;

/**
 * The section editor of a page (`backend/pages/<id>/sections`, D-45-01; docs/pages.md, "Pages
 * built from sections"): `pages.edit` on the routes and the page's own record rules
 * (`$crud->isAllowed('edit', $page)`, the same resolver as the CRUD) on every request. The list,
 * gallery and forms are shared with reusable blocks (`SectionEditor`); a page adds its layout
 * (`article` | `sections`; switching keeps the other content), preview, edit and revisions
 * links. Every change records a page revision (sections included) and an activity entry.
 */
final class Sections extends SectionEditor
{
    /**
     * Switches the page's layout (`article` | `sections`); the other content is kept.
     */
    public function switchLayout(int $pageId): RedirectResponse
    {
        $page   = $this->owner($pageId);
        $layout = (string) $this->request->getPost('layout');
        $back   = $this->baseUrl($pageId);

        if (! in_array($layout, PageRepository::LAYOUTS, true)) {
            return redirect()->to($back)->with('error', lang('Pages.sections.errors.layout'));
        }

        if ($layout !== ($page['layout'] ?? 'article')) {
            db_connect()->table('pages')->where('id', $pageId)->update(['layout' => $layout, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->changed($pageId, 'layout_changed', $page, lang('Pages.layouts.' . $layout));
        }

        return redirect()->to($back)->with('success', lang('Pages.sections.layoutSaved', [lang('Pages.layouts.' . $layout)]));
    }

    protected function owner(int $id): array
    {
        $page = (new PageRepository())->find($id);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->crud('pages')->setTable('pages')->isAllowed('edit', $page)) {
            security_log('Pages', 'sections_denied', ['resource' => 'pages', 'record_id' => (string) $id]);

            throw PageNotFoundException::forPageNotFound();
        }

        return $page;
    }

    protected function storage(): PageSections
    {
        return new PageSections();
    }

    protected function baseUrl(int $id): string
    {
        return backend_url('pages/' . $id . '/sections');
    }

    protected function ownerTitle(array $owner): string
    {
        return (string) $owner['title'];
    }

    protected function listExtras(array $owner): array
    {
        $id = (int) $owner['id'];

        return [
            'page'  => $owner,
            'links' => [
                ['url' => $this->crudUrl('preview', $id), 'label' => lang('Pages.preview'), 'icon' => 'bi bi-eye', 'newTab' => true],
                ['url' => $this->crudUrl('edit', $id), 'label' => lang('Pages.sections.editPage'), 'icon' => 'bi bi-pencil'],
                ['url' => $this->crudUrl('revisions', $id), 'label' => lang('Pages.revisions'), 'icon' => 'bi bi-clock-history'],
            ],
        ];
    }

    protected function changed(int $id, string $action, array $owner, string $detail = ''): void
    {
        (new Revisions())->record($id, (int) auth()->id());
        activity_log('Pages', $action, [
            'resource'  => 'pages',
            'record_id' => (string) $id,
            'summary'   => (string) $owner['title'] . ($detail !== '' ? ' › ' . $detail : ''),
        ]);
    }

    protected function crumbs(array $owner, ?string $last = null): void
    {
        $id = (int) $owner['id'];
        $this->breadcrumb(lang('Pages.pages'), backend_url('pages'))
            ->breadcrumb((string) $owner['title'], $this->crudUrl('edit', $id));

        if ($last === null) {
            $this->breadcrumb(lang('Pages.sections.title'));
        } else {
            $this->breadcrumb(lang('Pages.sections.title'), $this->baseUrl($id))->breadcrumb($last);
        }
    }

    /** A URL of the pages CRUD with the masked id (`edit`, `preview`, `revisions`). */
    private function crudUrl(string $operation, int $pageId): string
    {
        return backend_url('pages/' . $operation . '/' . rawurlencode(Crud::idForUrl($pageId, 'pages')));
    }
}
