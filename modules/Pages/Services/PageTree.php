<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use Modules\Core\Content\ContentTranslations;

/**
 * The tree of pages (parent → children, ordered by `position` then id) with their slugs and
 * titles per language, read with two queries and kept for the request. Used for the parent
 * field, the paths of the public URLs, the sitemap and the menu link type. Trashed pages are left
 * out.
 */
final class PageTree
{
    /** Deepest level followed (a deeper page is treated as unreachable). */
    public const MAX_DEPTH = 20;

    /** @var array<int, array{id: int, parent_id: int|null, position: int}>|null */
    private ?array $pages = null;

    /** @var array<int, array<string, array{title: string, slug: string}>> */
    private array $texts = [];

    /** @var array<int, list<int>> parent id (0 = root) => children ids */
    private array $children = [];

    public function reset(): void
    {
        $this->pages    = null;
        $this->texts    = [];
        $this->children = [];
    }

    /**
     * @return array{id: int, parent_id: int|null, position: int}|null
     */
    public function page(int $id): ?array
    {
        return $this->load()[$id] ?? null;
    }

    /**
     * Ids of the children of a page (null = root pages), in order.
     *
     * @return list<int>
     */
    public function childrenOf(?int $id): array
    {
        $this->load();

        return $this->children[$id ?? 0] ?? [];
    }

    /**
     * Every page under one page (children, grandchildren…).
     *
     * @return list<int>
     */
    public function descendants(int $id): array
    {
        $found = [];
        $queue = $this->childrenOf($id);

        while ($queue !== [] && count($found) < 100000) {
            $child = array_shift($queue);

            if (in_array($child, $found, true) || $child === $id) {
                continue;
            }

            $found[] = $child;
            $queue   = array_merge($queue, $this->childrenOf($child));
        }

        return $found;
    }

    /**
     * The page and its ancestors, root first; empty when the chain is broken or too deep.
     *
     * @return list<int>
     */
    public function lineage(int $id): array
    {
        $pages = $this->load();
        $chain = [];

        for ($current = $id; $current !== null; $current = $pages[$current]['parent_id']) {
            if (! isset($pages[$current]) || in_array($current, $chain, true) || count($chain) >= self::MAX_DEPTH) {
                return [];
            }

            array_unshift($chain, $current);
        }

        return $chain;
    }

    /**
     * Title of a page in a language (else Catalan, else any).
     */
    public function title(int $id, string $locale): string
    {
        return $this->text($id, $locale, 'title');
    }

    /**
     * The public path of a page in a language (`about/team`): each level's slug in that language,
     * else in Catalan, else any; null when the page is not in the tree.
     */
    public function path(int $id, string $locale): ?string
    {
        $lineage = $this->lineage($id);

        if ($lineage === []) {
            return null;
        }

        $slugs = array_map(fn (int $page): string => $this->text($page, $locale, 'slug'), $lineage);

        return in_array('', $slugs, true) ? null : implode('/', $slugs);
    }

    /**
     * The distinct paths of a page, one per language it has a translation in (locale => path).
     *
     * @return array<string, string>
     */
    public function paths(int $id): array
    {
        $this->load();
        $paths = [];

        foreach (array_keys($this->texts[$id] ?? []) as $locale) {
            $path = $this->path($id, $locale);

            if ($path !== null && ! in_array($path, $paths, true)) {
                $paths[$locale] = $path;
            }
        }

        return $paths;
    }

    /**
     * Options of the parent field in tree order ("— — Child"), without a page and its descendants
     * (a page cannot become its own ancestor).
     *
     * @return array<int, string>
     */
    public function options(?int $except, string $locale): array
    {
        $skip    = $except === null ? [] : [$except, ...$this->descendants($except)];
        $options = [];
        $walk    = function (?int $parent, int $depth) use (&$walk, &$options, $skip, $locale): void {
            if ($depth >= self::MAX_DEPTH) {
                return;
            }

            foreach ($this->childrenOf($parent) as $id) {
                if (in_array($id, $skip, true)) {
                    continue;
                }

                $options[$id] = str_repeat('— ', $depth) . $this->title($id, $locale);
                $walk($id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $options;
    }

    private function text(int $id, string $locale, string $field): string
    {
        $this->load();
        $locales = $this->texts[$id] ?? [];

        return ContentTranslations::pick($locales, [$field], $locale)[$field];
    }

    /**
     * @return array<int, array{id: int, parent_id: int|null, position: int}>
     */
    private function load(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }

        $this->pages = [];
        $rows        = db_connect()->table('pages')->select('id, parent_id, position')->where('deleted_at', null)
            ->orderBy('position')->orderBy('id')->get()->getResultArray();

        foreach ($rows as $row) {
            $id                = (int) $row['id'];
            $this->pages[$id]  = ['id' => $id, 'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'], 'position' => (int) $row['position']];
        }

        foreach ($this->pages as $id => $page) {
            // A child of a trashed page is treated as unreachable (not shown as a root page).
            if ($page['parent_id'] !== null && ! isset($this->pages[$page['parent_id']])) {
                continue;
            }

            $this->children[$page['parent_id'] ?? 0][] = $id;
        }

        if ($this->pages !== []) {
            $texts = db_connect()->table('page_translations')->select('page_id, locale, title, slug')
                ->whereIn('page_id', array_keys($this->pages))->get()->getResultArray();

            foreach ($texts as $text) {
                $this->texts[(int) $text['page_id']][(string) $text['locale']] = ['title' => (string) $text['title'], 'slug' => (string) $text['slug']];
            }
        }

        return $this->pages;
    }
}
