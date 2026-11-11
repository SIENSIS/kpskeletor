<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use Modules\Core\Content\ContentTranslations;

/**
 * Finds the page of a public path (`about/team`): segment by segment, a child of the previous
 * page (a root page first) whose slug in some language matches. Slugs are unique per parent and
 * language, so when two languages share a slug the current language wins, then Catalan, then
 * the lowest page id. Trashed pages never match (nor their children). Visibility is checked by
 * the caller (PageVisibility).
 */
final class PageResolver
{
    /** A valid slug (also the rule of the slug field). */
    public const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Most levels in a path. */
    public const MAX_SEGMENTS = PageTree::MAX_DEPTH;

    /**
     * The id of the page at the path, or null.
     */
    public function resolve(string $path, string $locale): ?int
    {
        $segments = $this->segments($path);

        if ($segments === null) {
            return null;
        }

        $parent = null;

        foreach ($segments as $segment) {
            $parent = $this->child($parent, $segment, $locale);

            if ($parent === null) {
                return null;
            }
        }

        return $parent;
    }

    /**
     * The segments of a path, or null when one is not a valid slug (or there are too many).
     *
     * @return list<string>|null
     */
    public function segments(string $path): ?array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);

        if (count($segments) > self::MAX_SEGMENTS) {
            return null;
        }

        foreach ($segments as $segment) {
            if (strlen($segment) > 190 || preg_match(self::SLUG, $segment) !== 1) {
                return null;
            }
        }

        return $segments;
    }

    private function child(?int $parent, string $slug, string $locale): ?int
    {
        $builder = db_connect()->table('page_translations')
            ->select('page_translations.page_id, page_translations.locale')
            ->join('pages', 'pages.id = page_translations.page_id')
            ->where('page_translations.slug', $slug)
            ->where('pages.deleted_at', null);

        $parent === null ? $builder->where('pages.parent_id', null) : $builder->where('pages.parent_id', $parent);

        $rows = $builder->orderBy('page_translations.page_id')->get()->getResultArray();

        if ($rows === []) {
            return null;
        }

        foreach ([$locale, ContentTranslations::FALLBACK_LOCALE] as $preferred) {
            foreach ($rows as $row) {
                if ($row['locale'] === $preferred) {
                    return (int) $row['page_id'];
                }
            }
        }

        return (int) $rows[0]['page_id'];
    }
}
