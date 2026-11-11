<?php

declare(strict_types=1);

namespace Modules\News\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\ContentTranslations;

/**
 * Read queries of the reader screen, the widgets, the public site and the feed. Every query
 * starts from `NewsVisibility::apply()`, so nothing a user may not read is ever loaded.
 *
 * Rows are `news` columns plus `title`, `summary`, `body` in the current language (falling back
 * to Catalan, then any language: demo data is Catalan only) and `category` (name) /
 * `category_slug`.
 */
final class NewsRepository
{
    /** Language used when a post has no text in the current one (the default content language). */
    public const FALLBACK_LOCALE = ContentTranslations::FALLBACK_LOCALE;

    /**
     * A page of readable posts, pinned notices first, newest first.
     *
     * @param array{type?: string, category?: int, unread?: bool} $filters
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function page(?User $user, int $page, int $perPage, array $filters = [], bool $publicOnly = false): array
    {
        $builder = $this->readable($user, $publicOnly);
        $this->filter($builder, $user, $filters);
        $total = $builder->countAllResults(false);

        $rows = $builder->orderBy('news.pinned', 'desc')
            ->orderBy('news.published_at', 'desc')
            ->orderBy('news.id', 'desc')
            ->limit($perPage, max(0, ($page - 1) * $perPage))
            ->get()->getResultArray();

        return ['rows' => $this->withTexts($rows), 'total' => $total];
    }

    /**
     * Readable notices: pinned first, then by priority (critical, warning, info) and date.
     *
     * @return list<array<string, mixed>>
     */
    public function notices(User $user, int $limit, bool $unreadOnly = false): array
    {
        $builder = $this->readable($user)->where('news.type', 'notice');

        if ($unreadOnly) {
            $this->filter($builder, $user, ['unread' => true]);
        }

        // Readable notices are few (they expire): sort by pin, priority and date in PHP, portable.
        $rows = $builder->orderBy('news.published_at', 'desc')->limit(500)->get()->getResultArray();
        $rank = array_flip(['critical', 'warning', 'info']);

        usort($rows, static fn (array $a, array $b): int => [(int) $b['pinned'], $rank[$a['priority']] ?? 3, (string) $b['published_at']]
            <=> [(int) $a['pinned'], $rank[$b['priority']] ?? 3, (string) $a['published_at']]);

        $rows = array_slice($rows, 0, $limit);

        return $this->withTexts($rows);
    }

    /**
     * One readable post by id or slug, or null (not found, not published, not visible).
     *
     * @return array<string, mixed>|null
     */
    public function find(?User $user, int|string $idOrSlug, bool $publicOnly = false): ?array
    {
        $builder = $this->readable($user, $publicOnly);
        is_int($idOrSlug) ? $builder->where('news.id', $idOrSlug) : $builder->where('news.slug', $idOrSlug);

        $row = $builder->get(1)->getRowArray();

        return is_array($row) ? $this->withTexts([$row])[0] : null;
    }

    /**
     * Active categories with their name in the current language.
     *
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function categories(): array
    {
        $rows = db_connect()->table('news_categories')->where('active', 1)
            ->orderBy('position')->orderBy('id')->get()->getResultArray();
        $names = $this->texts('news_category_translations', 'category_id', array_column($rows, 'id'), ['name']);

        return array_map(static fn (array $row): array => [
            'id'   => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) ($names[(int) $row['id']]['name'] ?? $row['slug']),
        ], $rows);
    }

    /**
     * @return array{id: int, slug: string, name: string}|null
     */
    public function categoryBySlug(string $slug): ?array
    {
        foreach ($this->categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    private function readable(?User $user, bool $publicOnly = false): BaseBuilder
    {
        return NewsVisibility::apply(db_connect()->table('news')->select('news.*'), $user, 'news', $publicOnly);
    }

    /**
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     * @param array{type?: string, category?: int, unread?: bool} $filters
     */
    private function filter(BaseBuilder $builder, ?User $user, array $filters): void
    {
        if (in_array($filters['type'] ?? '', NewsVisibility::TYPES, true)) {
            $builder->where('news.type', $filters['type']);
        }

        if (($filters['category'] ?? 0) > 0) {
            $builder->where('news.category_id', $filters['category']);
        }

        if (($filters['unread'] ?? false) && $user !== null) {
            $builder->whereNotIn('news.id', static fn (BaseBuilder $sub): BaseBuilder => $sub->select('news_id')->from('news_reads')->where('user_id', (int) $user->id));
        }
    }

    /**
     * Adds the texts in the current language and the category name to each row.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withTexts(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $texts      = $this->texts('news_translations', 'news_id', array_column($rows, 'id'), ['title', 'summary', 'body']);
        $categories = [];

        foreach ($this->categories() as $category) {
            $categories[$category['id']] = $category;
        }

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row += ['title' => '', 'summary' => '', 'body' => ''];
            $row = array_merge($row, $texts[$id] ?? []);

            $category             = $categories[(int) ($row['category_id'] ?? 0)] ?? null;
            $row['category']      = $category['name'] ?? null;
            $row['category_slug'] = $category['slug'] ?? null;
        }

        return $rows;
    }

    /**
     * Texts of several rows in the current language, else Catalan, else the first language found.
     *
     * @param list<mixed>  $ids
     * @param list<string> $fields
     *
     * @return array<int, array<string, string>>
     */
    private function texts(string $table, string $key, array $ids, array $fields): array
    {
        return ContentTranslations::texts($table, $key, $ids, $fields);
    }
}
