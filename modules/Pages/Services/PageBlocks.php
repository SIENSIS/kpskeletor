<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Sections\SectionData;
use Modules\Pages\Sections\BlockSection;

/**
 * Reusable blocks (D-47-01; docs/sections.md, "Reusable blocks"): a named list of sections
 * (table `page_blocks`, its sections in `page_block_sections`), shown inside
 * pages through the section type `pages.block` and in the theme's zones (`BlockZones`). A
 * `draft` block is shown nowhere.
 *
 * Deleting a block removes its uses (D-47-01: the screen asks for confirmation first): its zone
 * assignments and the `pages.block` sections that show it (a revision of each page touched),
 * then its own sections and revisions.
 */
final class PageBlocks
{
    public const STATUSES = ['published', 'draft'];

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = db_connect()->table('page_blocks')->where('id', $id)->get(1)->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function isPublished(int $id): bool
    {
        return ($this->find($id)['status'] ?? null) === 'published';
    }

    /**
     * Every block by name (id => name), drafts marked, for the block section's select.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        try {
            $rows = db_connect()->table('page_blocks')->orderBy('name')->get()->getResultArray();
        } catch (\Throwable) {
            return []; // not migrated yet
        }

        foreach ($rows as $row) {
            $options[(string) $row['id']] = (string) $row['name'] . ($row['status'] === 'draft' ? ' (' . lang('Pages.blocks.statuses.draft') . ')' : '');
        }

        return $options;
    }

    /**
     * Where a block is used: its zone assignments and the pages whose sections show it.
     *
     * @return array{zones: list<array<string, mixed>>, pages: list<int>}
     */
    public function uses(int $id): array
    {
        return ['zones' => (new BlockZones())->forBlock($id), 'pages' => array_keys($this->pageSections($id))];
    }

    public function useCount(int $id): int
    {
        $uses = $this->uses($id);

        return count($uses['zones']) + count($uses['pages']);
    }

    /**
     * Removes a block's uses and its own rows (after the CRUD deleted the block, or before).
     */
    public function removeUses(int $id, ?int $userId): void
    {
        $db = db_connect();
        $db->table('page_block_zones')->where('block_id', $id)->delete();

        $pages = new PageSections();

        foreach ($this->pageSections($id) as $pageId => $sectionIds) {
            foreach ($sectionIds as $sectionId) {
                $pages->delete($pageId, $sectionId);
            }

            (new Revisions())->record($pageId, $userId);
        }

        PageSections::forBlocks()->deleteAll($id);
        $db->table('page_block_revisions')->where('block_id', $id)->delete();
    }

    /**
     * Creates a block with sections (demo data, tests). Returns its id.
     *
     * @param list<SectionData> $sections
     */
    public function create(string $name, array $sections = [], string $status = 'published'): int
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('page_blocks')->insert(['name' => mb_substr($name, 0, 120), 'status' => in_array($status, self::STATUSES, true) ? $status : 'draft', 'created_at' => $now, 'updated_at' => $now]);
        $id     = (int) db_connect()->insertID();
        $blocks = PageSections::forBlocks();

        foreach ($sections as $section) {
            $blocks->save($id, $section);
        }

        return $id;
    }

    /**
     * The block sections of pages that show a block: page id => section ids.
     *
     * @return array<int, list<int>>
     */
    private function pageSections(int $id): array
    {
        $found = [];
        $rows  = db_connect()->table('page_sections')->select('id, page_id, data')
            ->where('type', BlockSection::KEY)->where('page_id IS NOT NULL')->get()->getResultArray();

        foreach ($rows as $row) {
            $data = json_decode((string) $row['data'], true);

            if (is_array($data) && (string) ($data['block'] ?? '') === (string) $id) {
                $found[(int) $row['page_id']][] = (int) $row['id'];
            }
        }

        return $found;
    }
}
