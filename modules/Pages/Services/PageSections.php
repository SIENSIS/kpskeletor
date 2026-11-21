<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Sections\SectionData;
use RuntimeException;

/**
 * The sections of pages in `sections` layout (D-45-01; docs/pages.md, "Pages built from
 * sections"): tables `page_sections` (type, position, options and non-translated values as JSON,
 * hidden) and `page_section_translations` (one row per section and language with the translated
 * values as JSON).
 *
 * Owners (D-47-01, D-47-02): the sections of a page (the default) or of a reusable block
 * (`PageSections::forBlocks()`: tables `page_block_sections` and
 * `page_block_section_translations`, the same columns with `block_id`); the same code, editor,
 * section types and translations. Every method takes the owner's id and touches only that owner's sections. Translations of a
 * language the site no longer offers are kept when a section is saved (neither shown nor edited).
 * The sections are part of the page's revisions (`snapshot()` / `replace()`).
 */
final class PageSections
{
    public const MAX_SECTIONS = 60;

    /** Owner type => [sections table, translations table, owner column] (D-47-02). */
    public const OWNERS = [
        'page'  => ['page_sections', 'page_section_translations', 'page_id'],
        'block' => ['page_block_sections', 'page_block_section_translations', 'block_id'],
    ];

    private readonly string $table;

    private readonly string $texts;

    private readonly string $column;

    public function __construct(public readonly string $owner = 'page')
    {
        if (! isset(self::OWNERS[$owner])) {
            throw new RuntimeException('Unknown section owner: ' . $owner);
        }

        [$this->table, $this->texts, $this->column] = self::OWNERS[$owner];
    }

    /** The sections of reusable blocks. */
    public static function forBlocks(): self
    {
        return new self('block');
    }

    /**
     * The sections of a page in order (hidden ones included).
     *
     * @return list<SectionData>
     */
    public function all(int $pageId): array
    {
        $rows = db_connect()->table($this->table)->where($this->column, $pageId)
            ->orderBy('position')->orderBy('id')->get()->getResultArray();

        if ($rows === []) {
            return [];
        }

        $texts = $this->translations(array_map('intval', array_column($rows, 'id')));

        return array_map(static fn (array $row): SectionData => SectionData::fromRows($row, $texts[(int) $row['id']] ?? []), $rows);
    }

    public function find(int $pageId, int $sectionId): ?SectionData
    {
        $row = db_connect()->table($this->table)->where($this->column, $pageId)->where('id', $sectionId)->get(1)->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        return SectionData::fromRows($row, $this->translations([$sectionId])[$sectionId] ?? []);
    }

    public function count(int $pageId): int
    {
        return db_connect()->table($this->table)->where($this->column, $pageId)->countAllResults();
    }

    /**
     * Inserts a section at the end of the page (no `$sectionId`) or updates one of its sections.
     * Returns the section id.
     *
     * @throws RuntimeException when the section does not belong to the page, or the page is full
     */
    public function save(int $pageId, SectionData $section, ?int $sectionId = null): int
    {
        if (! $this->accepts($section->type)) {
            throw new RuntimeException(lang('Pages.blocks.errors.nested'));
        }

        $db  = db_connect();
        $now = Time::now()->toDateTimeString();
        $row = [
            'type'       => $section->type,
            'options'    => json_encode($section->options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'data'       => json_encode($section->values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'hidden'     => $section->hidden ? 1 : 0,
            'updated_at' => $now,
        ];

        $db->transException(true)->transStart();

        try {
            if ($sectionId === null) {
                if ($this->count($pageId) >= self::MAX_SECTIONS) {
                    throw new RuntimeException(lang('Pages.sections.errors.full', [self::MAX_SECTIONS]));
                }

                $last = $db->table($this->table)->selectMax('position')->where($this->column, $pageId)->get()->getRowArray();
                $db->table($this->table)->insert($row + [
                    $this->column => $pageId,
                    'position'   => (int) ($last['position'] ?? 0) + 10,
                    'created_at' => $now,
                ]);
                $sectionId = (int) $db->insertID();
            } else {
                if ($this->find($pageId, $sectionId) === null) {
                    throw new RuntimeException(lang('Pages.sections.errors.notFound'));
                }

                $db->table($this->table)->where('id', $sectionId)->where($this->column, $pageId)->update($row);
            }

            $this->writeTexts($sectionId, $section->texts);
            $db->transComplete();
        } finally {
            $db->transException(false);
        }

        return $sectionId;
    }

    /**
     * Puts the page's sections in the given order: every section of the page exactly once.
     *
     * @param list<int> $ids
     *
     * @throws RuntimeException when the list does not match the page's sections
     */
    public function reorder(int $pageId, array $ids): void
    {
        $current = $this->ids($pageId);
        $ids     = array_map('intval', $ids);
        $sorted  = $ids;
        sort($sorted);
        $expected = $current;
        sort($expected);

        if ($sorted !== $expected || count(array_unique($ids)) !== count($ids)) {
            throw new RuntimeException(lang('Pages.sections.errors.order'));
        }

        $this->writePositions($pageId, $ids);
    }

    /**
     * Moves one section up or down (the buttons without JavaScript).
     *
     * @throws RuntimeException
     */
    public function move(int $pageId, int $sectionId, string $direction): void
    {
        $ids   = $this->ids($pageId);
        $index = array_search($sectionId, $ids, true);

        if ($index === false || ! in_array($direction, ['up', 'down'], true)) {
            throw new RuntimeException(lang('Pages.sections.errors.notFound'));
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $this->writePositions($pageId, $ids);
    }

    /**
     * Copies a section right after it (hidden state, options and texts included). Returns the
     * copy's id.
     *
     * @throws RuntimeException
     */
    public function duplicate(int $pageId, int $sectionId): int
    {
        $section = $this->find($pageId, $sectionId);

        if ($section === null) {
            throw new RuntimeException(lang('Pages.sections.errors.notFound'));
        }

        $copy = $this->save($pageId, new SectionData($section->type, $section->values, $section->texts, $section->options, $section->hidden));
        $ids  = array_values(array_diff($this->ids($pageId), [$copy]));
        array_splice($ids, (int) array_search($sectionId, $ids, true) + 1, 0, [$copy]);
        $this->writePositions($pageId, $ids);

        // Rows of languages no longer offered are copied too.
        $this->copyUnavailableTexts($sectionId, $copy);

        return $copy;
    }

    /**
     * @throws RuntimeException
     */
    public function setHidden(int $pageId, int $sectionId, bool $hidden): void
    {
        if ($this->find($pageId, $sectionId) === null) {
            throw new RuntimeException(lang('Pages.sections.errors.notFound'));
        }

        db_connect()->table($this->table)->where('id', $sectionId)->where($this->column, $pageId)
            ->update(['hidden' => $hidden ? 1 : 0, 'updated_at' => Time::now()->toDateTimeString()]);
    }

    /**
     * @throws RuntimeException
     */
    public function delete(int $pageId, int $sectionId): void
    {
        if ($this->find($pageId, $sectionId) === null) {
            throw new RuntimeException(lang('Pages.sections.errors.notFound'));
        }

        $db = db_connect();
        $db->table($this->texts)->where('section_id', $sectionId)->delete();
        $db->table($this->table)->where('id', $sectionId)->where($this->column, $pageId)->delete();
    }

    /** Deletes every section of a page (the page is purged). */
    public function deleteAll(int $pageId): void
    {
        $ids = $this->ids($pageId);

        if ($ids !== []) {
            db_connect()->table($this->texts)->whereIn('section_id', $ids)->delete();
            db_connect()->table($this->table)->where($this->column, $pageId)->delete();
        }
    }

    /**
     * The sections of a page for its revisions: type, hidden, options, values and the texts of
     * every language (as stored), in order.
     *
     * @return list<array{type: string, hidden: bool, options: array<string, mixed>, data: array<string, mixed>, texts: array<string, array<string, mixed>>}>
     */
    public function snapshot(int $pageId): array
    {
        $rows = db_connect()->table($this->table)->where($this->column, $pageId)
            ->orderBy('position')->orderBy('id')->get()->getResultArray();
        $all  = $this->translations(array_map('intval', array_column($rows, 'id')), false);
        $list = [];

        foreach ($rows as $row) {
            $section = SectionData::fromRows($row, $all[(int) $row['id']] ?? []);
            $texts   = $section->texts;
            ksort($texts);

            $list[] = ['type' => $section->type, 'hidden' => $section->hidden, 'options' => $section->options, 'data' => $section->values, 'texts' => $texts];
        }

        return $list;
    }

    /**
     * Replaces the sections of a page with those of a snapshot (restoring a revision, copying a
     * page). Texts of languages the site no longer offers are written as they were.
     *
     * @param list<mixed> $snapshot
     */
    public function replace(int $pageId, array $snapshot): void
    {
        $db = db_connect();
        $db->transException(true)->transStart();

        try {
            $this->deleteAll($pageId);
            $now      = Time::now()->toDateTimeString();
            $position = 0;

            foreach ($snapshot as $entry) {
                if (! is_array($entry) || ! is_string($entry['type'] ?? null) || ! $this->accepts($entry['type']) || $position >= self::MAX_SECTIONS * 10) {
                    continue;
                }

                $position += 10;
                $db->table($this->table)->insert([
                    $this->column => $pageId,
                    'position'   => $position,
                    'type'       => substr($entry['type'], 0, 101),
                    'options'    => json_encode(is_array($entry['options'] ?? null) ? $entry['options'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'data'       => json_encode(is_array($entry['data'] ?? null) ? $entry['data'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'hidden'     => ($entry['hidden'] ?? false) === true ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $db->insertID();

                foreach (is_array($entry['texts'] ?? null) ? $entry['texts'] : [] as $locale => $data) {
                    if (is_string($locale) && preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/', $locale) === 1 && is_array($data)) {
                        $db->table($this->texts)->insert([
                            'section_id' => $id,
                            'locale'     => $locale,
                            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            }

            $db->transComplete();
        } finally {
            $db->transException(false);
        }
    }

    /**
     * Whether a section type may belong to this owner: a block never contains a block section
     * (no nesting, no loops).
     */
    public function accepts(string $type): bool
    {
        return $this->owner !== 'block' || $type !== \Modules\Pages\Sections\BlockSection::KEY;
    }

    /**
     * @return list<int> the page's section ids in order
     */
    private function ids(int $pageId): array
    {
        return array_map('intval', array_column(db_connect()->table($this->table)->select('id')->where($this->column, $pageId)
            ->orderBy('position')->orderBy('id')->get()->getResultArray(), 'id'));
    }

    /**
     * @param list<int> $ids
     */
    private function writePositions(int $pageId, array $ids): void
    {
        $db = db_connect();

        foreach ($ids as $index => $id) {
            $db->table($this->table)->where('id', $id)->where($this->column, $pageId)->update(['position' => ($index + 1) * 10]);
        }
    }

    /**
     * The translations of sections: id => locale => JSON (only the site's languages unless
     * `$availableOnly` is false).
     *
     * @param list<int> $ids
     *
     * @return array<int, array<string, string>>
     */
    private function translations(array $ids, bool $availableOnly = true): array
    {
        if ($ids === []) {
            return [];
        }

        $codes = ContentTranslations::codes();
        $texts = [];

        foreach (db_connect()->table($this->texts)->whereIn('section_id', $ids)->get()->getResultArray() as $row) {
            if ($availableOnly && ! in_array((string) $row['locale'], $codes, true)) {
                continue;
            }

            $texts[(int) $row['section_id']][(string) $row['locale']] = (string) $row['data'];
        }

        return $texts;
    }

    /**
     * Writes the texts of the site's languages (rows of other languages are kept).
     *
     * @param array<string, array<string, mixed>> $texts locale => field => part
     */
    private function writeTexts(int $sectionId, array $texts): void
    {
        $db    = db_connect();
        $codes = ContentTranslations::codes();
        $db->table($this->texts)->where('section_id', $sectionId)->whereIn('locale', $codes)->delete();

        foreach ($texts as $locale => $data) {
            if (! in_array((string) $locale, $codes, true)) {
                continue;
            }

            $db->table($this->texts)->insert([
                'section_id' => $sectionId,
                'locale'     => (string) $locale,
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private function copyUnavailableTexts(int $from, int $to): void
    {
        $db    = db_connect();
        $codes = ContentTranslations::codes();

        foreach ($db->table($this->texts)->where('section_id', $from)->whereNotIn('locale', $codes)->get()->getResultArray() as $row) {
            $db->table($this->texts)->insert(['section_id' => $to, 'locale' => (string) $row['locale'], 'data' => (string) $row['data']]);
        }
    }
}
