<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;
use Modules\Pages\Services\BlockRevisions;
use Modules\Pages\Services\PageBlocks;
use RuntimeException;

/**
 * Reusable blocks (`backend/pages/blocks`, resource `pages-blocks`, D-47-01; docs/sections.md,
 * "Reusable blocks"), a kpcrudy CRUD: name and status (`published` | `draft`: a draft is shown
 * nowhere), where it is used, and operations to edit its sections (`edit`), place it in the
 * theme's zones and see or restore its revisions (all `edit`).
 *
 * Deleting a block that is used somewhere (zones or pages) is refused by the plain delete; the
 * operation "Delete with its uses" asks for confirmation, removes its zone assignments and the
 * block sections of the pages that show it (a page revision each) and deletes it.
 */
final class Blocks extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $blocks = new PageBlocks();
        $me     = (int) auth()->id();
        $crud   = $this->crud('pages-blocks');

        $crud->setTable('page_blocks')
            ->setSubject(lang('Pages.blocks.block'), lang('Pages.blocks.title'))
            ->columns(['name', 'status', 'uses', 'updated_at'])
            ->addFields(['name', 'status'])
            ->editFields(['name', 'status'])
            ->searchFields(['name'])
            ->filters(['status'])
            ->unsortable(['uses'])
            ->displayAs([
                'name'       => lang('Pages.blocks.fields.name'),
                'status'     => lang('Pages.blocks.fields.status'),
                'uses'       => lang('Pages.blocks.fields.uses'),
                'updated_at' => lang('Pages.fields.updated_at'),
            ])
            ->fieldType('status', 'select', ['options' => [
                'published' => lang('Pages.blocks.statuses.published'),
                'draft'     => lang('Pages.blocks.statuses.draft'),
            ]])
            ->setDefault('status', 'published')
            ->setRules([
                'name'   => 'required|max_length[120]',
                'status' => 'required|in_list[' . implode(',', PageBlocks::STATUSES) . ']',
            ])
            ->setHelp('name', lang('Pages.blocks.help.name'))
            ->setHelp('status', lang('Pages.blocks.help.status'))
            ->callbackColumn('status', static fn ($value): string => ui_badge(lang('Pages.blocks.statuses.' . ($value === 'draft' ? 'draft' : 'published')), $value === 'draft' ? 'secondary' : 'success'))
            ->callbackColumn('uses', static function ($value, array $row) use ($blocks): string {
                $uses = $blocks->uses((int) $row['id']);

                return esc(lang('Pages.blocks.usesCount', [count($uses['zones']), count($uses['pages'])]));
            })
            ->callbackColumn('updated_at', static fn ($value): string => ui_date(is_string($value) ? $value : null, 'relative'))
            ->afterInsert(static function (string $id) use ($me): void {
                (new BlockRevisions())->record((int) $id, $me, 'create');
            })
            ->afterUpdate(static function (string $id) use ($me): void {
                (new BlockRevisions())->record((int) $id, $me);
            })
            ->beforeDelete(static fn (string $id): ?string => $blocks->useCount((int) $id) > 0 ? lang('Pages.blocks.errors.inUse') : null)
            ->afterDelete(static function (string $id) use ($blocks, $me): void {
                $blocks->removeUses((int) $id, $me);
            })
            ->rowActionsMenu()
            ->unsetShow()
            ->unsetExport()
            ->defaultOrder('name', 'asc');

        $crud->addOperation('sections', static fn (string $id): ResponseInterface => redirect()->to(backend_url('pages/blocks/' . (int) $id . '/sections')), [
            'method'     => 'get',
            'permission' => 'edit',
            'label'      => lang('Pages.sections.title'),
            'icon'       => 'bi bi-layout-text-window-reverse',
            'class'      => 'btn-outline-primary',
        ]);

        $crud->addOperation('zones', static fn (string $id): ResponseInterface => redirect()->to(backend_url('pages/blocks/' . (int) $id . '/zones')), [
            'method'     => 'get',
            'permission' => 'edit',
            'label'      => lang('Pages.blocks.placements'),
            'icon'       => 'bi bi-pin-map',
        ]);

        $crud->addOperation('revisions', fn (string $id, array $row, Crud $crud): ResponseInterface => $this->revisions((int) $id, (string) $row['name'], $crud), [
            'method'     => 'get',
            'permission' => 'edit',
            'label'      => lang('Pages.revisions'),
            'icon'       => 'bi bi-clock-history',
        ]);

        $crud->addOperation('restorerevision', static function (string $id, array $row, Crud $crud) use ($me): ResponseInterface|string {
            try {
                (new BlockRevisions())->restore((int) $id, (int) service('request')->getPost('revision'), $me);
            } catch (RuntimeException $e) {
                return $crud->backToList('danger', $e->getMessage());
            }

            return lang('Pages.restoredOk');
        }, [
            'permission' => 'edit',
            'button'     => false,
            'label'      => lang('Pages.restore'),
        ]);

        $crud->addOperation('deletewithuses', static function (string $id, array $row) use ($blocks, $me): string {
            $blocks->removeUses((int) $id, $me);
            db_connect()->table('page_blocks')->where('id', (int) $id)->delete();

            return lang('Pages.blocks.deletedWithUses', [(string) $row['name']]);
        }, [
            'permission' => 'delete',
            'label'      => lang('Pages.blocks.deleteWithUses'),
            'icon'       => 'bi bi-trash',
            'class'      => 'btn-outline-danger',
            'confirm'    => lang('Pages.blocks.deleteWithUsesConfirm'),
            'visible'    => static fn (?array $row): bool => $row === null || $blocks->useCount((int) $row['id']) > 0,
        ]);

        return $this->renderCrud($crud);
    }

    private function revisions(int $id, string $name, Crud $crud): ResponseInterface
    {
        $revisions = new BlockRevisions();
        $history   = $revisions->history($id);
        $selected  = (int) $this->request->getGet('revision');
        $selected  = in_array($selected, array_column($history, 'id'), true) ? $selected : ($history[0]['id'] ?? 0);

        $html = theme_view('Modules\Pages\Views\revisions', [
            'history'    => $history,
            'selected'   => $selected,
            'diff'       => $selected > 0 ? $revisions->diff($id, $selected) : [],
            'canRestore' => $crud->isAllowed('edit', ['id' => $id]),
            'restoreUrl' => $crud->url('restorerevision', (string) $id),
            'pageUrl'    => $crud->url('revisions', (string) $id),
            'backUrl'    => $crud->url('list'),
            'labels'     => [
                'name'     => lang('Pages.blocks.fields.name'),
                'status'   => lang('Pages.blocks.fields.status'),
                'sections' => lang('Pages.fields.sections'),
            ],
        ]);

        return service('response')->setBody($crud->page($html, lang('Pages.revisionsOf', [$name])));
    }
}
