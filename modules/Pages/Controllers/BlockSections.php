<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use Kpcrudy\Crud;
use Modules\Pages\Services\BlockRevisions;
use Modules\Pages\Services\PageBlocks;
use Modules\Pages\Services\PageSections;

/**
 * The section editor of a reusable block (`backend/pages/blocks/<id>/sections`, D-47-01):
 * `pages-blocks.edit` on the routes and the CRUD's resolver on every request; the same editor
 * as pages (`SectionEditor`) without the block section (no nesting). Every change records a
 * block revision and an activity entry.
 */
final class BlockSections extends SectionEditor
{
    protected function owner(int $id): array
    {
        $block = (new PageBlocks())->find($id);

        if ($block === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->crud('pages-blocks')->setTable('page_blocks')->unsetShow()->unsetExport()->isAllowed('edit', $block)) {
            security_log('Pages', 'sections_denied', ['resource' => 'pages-blocks', 'record_id' => (string) $id]);

            throw PageNotFoundException::forPageNotFound();
        }

        return $block;
    }

    protected function storage(): PageSections
    {
        return PageSections::forBlocks();
    }

    protected function baseUrl(int $id): string
    {
        return backend_url('pages/blocks/' . $id . '/sections');
    }

    protected function ownerTitle(array $owner): string
    {
        return (string) $owner['name'];
    }

    protected function listExtras(array $owner): array
    {
        $id = (int) $owner['id'];

        return [
            'page'  => null,
            'notice' => lang('Pages.blocks.editorHelp'),
            'links' => [
                ['url' => backend_url('pages/blocks/' . $id . '/zones'), 'label' => lang('Pages.blocks.placements'), 'icon' => 'bi bi-pin-map'],
                ['url' => $this->crudUrl('edit', $id), 'label' => lang('Pages.blocks.editBlock'), 'icon' => 'bi bi-pencil'],
                ['url' => $this->crudUrl('revisions', $id), 'label' => lang('Pages.revisions'), 'icon' => 'bi bi-clock-history'],
            ],
        ];
    }

    protected function changed(int $id, string $action, array $owner, string $detail = ''): void
    {
        (new BlockRevisions())->record($id, (int) auth()->id());
        db_connect()->table('page_blocks')->where('id', $id)->update(['updated_at' => date('Y-m-d H:i:s')]);
        activity_log('Pages', 'block_' . $action, [
            'resource'  => 'pages-blocks',
            'record_id' => (string) $id,
            'summary'   => (string) $owner['name'] . ($detail !== '' ? ' › ' . $detail : ''),
        ]);
    }

    protected function crumbs(array $owner, ?string $last = null): void
    {
        $id = (int) $owner['id'];
        $this->breadcrumb(lang('Pages.blocks.title'), backend_url('pages/blocks'))
            ->breadcrumb((string) $owner['name'], $this->crudUrl('edit', $id));

        if ($last === null) {
            $this->breadcrumb(lang('Pages.sections.title'));
        } else {
            $this->breadcrumb(lang('Pages.sections.title'), $this->baseUrl($id))->breadcrumb($last);
        }
    }

    private function crudUrl(string $operation, int $id): string
    {
        return backend_url('pages/blocks/' . $operation . '/' . rawurlencode(Crud::idForUrl($id, 'page_blocks')));
    }
}
