<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Controllers\BackendController;
use Modules\Pages\Services\BlockZones;
use Modules\Pages\Services\PageBlocks;
use Modules\Pages\Services\PageTree;
use RuntimeException;

/**
 * Where a reusable block is shown in the theme's zones (`backend/pages/blocks/<id>/zones`,
 * D-47-01): `pages-blocks.edit` on the routes and the CRUD's resolver on every request. A
 * placement is a zone of the public theme, a visibility rule (every page, the home page, only
 * the chosen pages, every page except the chosen ones) and an order; each change is logged.
 */
final class BlockPlacements extends BackendController
{
    public function index(int $blockId): string
    {
        $block  = $this->block($blockId);
        $locale = $this->request->getLocale();
        $edit   = (int) $this->request->getGet('edit');
        $zones  = new BlockZones();

        $this->breadcrumb(lang('Pages.blocks.title'), backend_url('pages/blocks'))
            ->breadcrumb((string) $block['name'], backend_url('pages/blocks/' . $blockId . '/sections'))
            ->breadcrumb(lang('Pages.blocks.placements'));

        return $this->render('Modules\Pages\Views\blocks\zones', [
            'title'       => lang('Pages.blocks.placementsOf', [(string) $block['name']]),
            'icon'        => 'bi bi-pin-map',
            'block'       => $block,
            'placements'  => $zones->forBlock($blockId),
            'editing'     => $edit > 0 ? $zones->find($blockId, $edit) : null,
            'zones'       => array_map(static fn (string $label): string => lang($label), service('theme')->zones('public')),
            'pages'       => (new PageTree())->options(null, $locale),
            'baseUrl'     => backend_url('pages/blocks/' . $blockId . '/zones'),
            'sectionsUrl' => backend_url('pages/blocks/' . $blockId . '/sections'),
            'listUrl'     => backend_url('pages/blocks'),
        ]);
    }

    public function save(int $blockId): RedirectResponse
    {
        $block = $this->block($blockId);
        $back  = backend_url('pages/blocks/' . $blockId . '/zones');
        $id    = (string) $this->request->getPost('id');
        $pages = $this->request->getPost('pages');

        try {
            (new BlockZones())->save(
                $blockId,
                (string) $this->request->getPost('zone'),
                (string) $this->request->getPost('rule'),
                is_array($pages) ? array_values(array_filter($pages, 'is_numeric')) : [],
                (int) $this->request->getPost('position'),
                ctype_digit($id) && $id !== '0' ? (int) $id : null,
            );
        } catch (RuntimeException $e) {
            return redirect()->to($back . (ctype_digit($id) && $id !== '0' ? '?edit=' . (int) $id : ''))->withInput()->with('error', $e->getMessage());
        }

        activity_log('Pages', 'block_placed', ['resource' => 'pages-blocks', 'record_id' => (string) $blockId, 'summary' => (string) $block['name'] . ' › ' . (string) $this->request->getPost('zone')]);

        return redirect()->to($back)->with('success', lang('Pages.blocks.placementSaved'));
    }

    public function delete(int $blockId, int $id): RedirectResponse
    {
        $block = $this->block($blockId);
        $back  = backend_url('pages/blocks/' . $blockId . '/zones');

        try {
            (new BlockZones())->delete($blockId, $id);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        activity_log('Pages', 'block_unplaced', ['resource' => 'pages-blocks', 'record_id' => (string) $blockId, 'summary' => (string) $block['name']]);

        return redirect()->to($back)->with('success', lang('Pages.blocks.placementDeleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function block(int $id): array
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
}
