<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\ContentTranslations;

/**
 * Read queries of the public site: one page with its texts in the current language (Catalan
 * fallback) and the readable subpages of a page.
 */
final class PageRepository
{
    /** Translated fields of a page. */
    public const TEXTS = ['title', 'slug', 'summary', 'body', 'meta_title', 'meta_description', 'canonical'];

    /**
     * A page (any status or visibility; trashed ones excluded) with its texts, or null.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id, ?string $locale = null): ?array
    {
        $row = db_connect()->table('pages')->where('id', $id)->where('deleted_at', null)->get(1)->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $texts = ContentTranslations::texts('page_translations', 'page_id', [$id], self::TEXTS, $locale);

        return array_merge($row, array_fill_keys(self::TEXTS, ''), $texts[$id] ?? []);
    }

    /**
     * The readable children of a page with their title and summary, in order.
     *
     * @return list<array<string, mixed>>
     */
    public function children(int $id, ?User $user): array
    {
        $rows = PageVisibility::apply(db_connect()->table('pages')->select('pages.*'), $user)
            ->where('pages.parent_id', $id)
            ->orderBy('pages.position')->orderBy('pages.id')
            ->get()->getResultArray();

        if ($rows === []) {
            return [];
        }

        $texts = ContentTranslations::texts('page_translations', 'page_id', array_column($rows, 'id'), ['title', 'summary']);

        return array_map(static fn (array $row): array => array_merge($row, ['title' => '', 'summary' => ''], $texts[(int) $row['id']] ?? []), $rows);
    }
}
