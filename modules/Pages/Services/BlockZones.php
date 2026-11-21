<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * Where reusable blocks are shown outside pages (D-47-01; table `page_block_zones`): a zone of
 * the public theme (`public.before_footer`) and a visibility rule:
 *
 * - `all`: every public page of the site (also the pages of other modules);
 * - `home`: the home page only;
 * - `only`: only the pages listed (Pages module pages);
 * - `except`: every page but those listed.
 *
 * `position` orders the blocks of one zone.
 */
final class BlockZones
{
    public const RULES = ['all', 'home', 'only', 'except'];

    public const MAX_PAGES = 200;

    /**
     * The assignments of a block, by zone and position.
     *
     * @return list<array{id: int, block_id: int, zone: string, rule: string, pages: list<int>, position: int}>
     */
    public function forBlock(int $blockId): array
    {
        $rows = db_connect()->table('page_block_zones')->where('block_id', $blockId)->orderBy('zone')->orderBy('position')->orderBy('id')->get()->getResultArray();

        return array_map(self::decode(...), $rows);
    }

    /**
     * @return array{id: int, block_id: int, zone: string, rule: string, pages: list<int>, position: int}|null
     */
    public function find(int $blockId, int $id): ?array
    {
        $row = db_connect()->table('page_block_zones')->where('block_id', $blockId)->where('id', $id)->get(1)->getRowArray();

        return is_array($row) ? self::decode($row) : null;
    }

    /**
     * The published blocks placed in a zone that apply to the current page, in order.
     *
     * @return list<int> block ids
     */
    public function blocksFor(string $zone, ?int $pageId, bool $home): array
    {
        $rows = db_connect()->table('page_block_zones')
            ->select('page_block_zones.*')
            ->join('page_blocks', 'page_blocks.id = page_block_zones.block_id')
            ->where('page_block_zones.zone', $zone)
            ->where('page_blocks.status', 'published')
            ->orderBy('page_block_zones.position')->orderBy('page_block_zones.id')
            ->get()->getResultArray();
        $ids = [];

        foreach ($rows as $row) {
            $assignment = self::decode($row);

            if (self::applies($assignment['rule'], $assignment['pages'], $pageId, $home)) {
                $ids[] = $assignment['block_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether a rule shows a block on a page (`$pageId` null: a page of another module).
     *
     * @param list<int> $pages
     */
    public static function applies(string $rule, array $pages, ?int $pageId, bool $home): bool
    {
        return match ($rule) {
            'all'    => true,
            'home'   => $home,
            'only'   => $pageId !== null && in_array($pageId, $pages, true),
            'except' => $pageId === null || ! in_array($pageId, $pages, true),
            default  => false,
        };
    }

    /**
     * Adds (`$id` null) or changes an assignment. Returns its id.
     *
     * @param list<int> $pages
     *
     * @throws RuntimeException when the zone, rule or pages are not valid
     */
    public function save(int $blockId, string $zone, string $rule, array $pages, int $position, ?int $id = null): int
    {
        if (! array_key_exists($zone, service('theme')->zones('public'))) {
            throw new RuntimeException(lang('Pages.blocks.errors.zone'));
        }

        if (! in_array($rule, self::RULES, true)) {
            throw new RuntimeException(lang('Pages.blocks.errors.rule'));
        }

        $pages = array_values(array_unique(array_filter(array_map('intval', $pages), static fn (int $page): bool => $page > 0)));

        if (in_array($rule, ['only', 'except'], true) && $pages === []) {
            throw new RuntimeException(lang('Pages.blocks.errors.pages'));
        }

        if (count($pages) > self::MAX_PAGES || ($pages !== [] && db_connect()->table('pages')->whereIn('id', $pages)->countAllResults() !== count($pages))) {
            throw new RuntimeException(lang('Pages.blocks.errors.pages'));
        }

        $now = Time::now()->toDateTimeString();
        $row = [
            'zone'       => $zone,
            'rule'       => $rule,
            'pages'      => json_encode(in_array($rule, ['only', 'except'], true) ? $pages : []),
            'position'   => max(0, min(9999, $position)),
            'updated_at' => $now,
        ];

        if ($id === null) {
            db_connect()->table('page_block_zones')->insert($row + ['block_id' => $blockId, 'created_at' => $now]);

            return (int) db_connect()->insertID();
        }

        if ($this->find($blockId, $id) === null) {
            throw new RuntimeException(lang('Pages.blocks.errors.placement'));
        }

        db_connect()->table('page_block_zones')->where('id', $id)->where('block_id', $blockId)->update($row);

        return $id;
    }

    /**
     * @throws RuntimeException
     */
    public function delete(int $blockId, int $id): void
    {
        if ($this->find($blockId, $id) === null) {
            throw new RuntimeException(lang('Pages.blocks.errors.placement'));
        }

        db_connect()->table('page_block_zones')->where('id', $id)->where('block_id', $blockId)->delete();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, block_id: int, zone: string, rule: string, pages: list<int>, position: int}
     */
    private static function decode(array $row): array
    {
        $pages = json_decode((string) ($row['pages'] ?? '[]'), true);

        return [
            'id'       => (int) $row['id'],
            'block_id' => (int) $row['block_id'],
            'zone'     => (string) $row['zone'],
            'rule'     => (string) $row['rule'],
            'pages'    => is_array($pages) ? array_values(array_map('intval', array_filter($pages, 'is_numeric'))) : [],
            'position' => (int) $row['position'],
        ];
    }
}
