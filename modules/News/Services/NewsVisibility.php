<?php

declare(strict_types=1);

namespace Modules\News\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\RawSql;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;

/**
 * Who may read a post, as Query Builder conditions shared by the reader screen, the widgets, the
 * public site, the RSS feed and the API (docs/news.md, "Visibility").
 *
 * A post is **readable** when it is not deleted, its status is `published` or `scheduled`, its
 * `published_at` has passed and its `expires_at` (if any) has not. Then:
 * - guests (and the public site, the feed): only `public`;
 * - signed-in users: `public`, `private` and `groups` posts linked (`news_groups`) to one of the
 *   user's groups. Superadmins follow the same rule: the back office (`news-posts.list`) shows
 *   everything to authors.
 *
 * No raw SQL: every value is bound or escaped by the Query Builder.
 */
final class NewsVisibility
{
    public const TYPES        = ['article', 'notice'];
    public const PRIORITIES   = ['info', 'warning', 'critical'];
    public const VISIBILITIES = ['public', 'private', 'groups'];
    public const STATUSES     = ['draft', 'scheduled', 'published', 'expired'];

    /**
     * Adds the "readable by this user" conditions to a query on `news` (or an alias of it).
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     * @param bool $publicOnly only `public` posts, even for a signed-in user (public site)
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function apply(BaseBuilder $builder, ?User $user, string $alias = 'news', bool $publicOnly = false, ?Time $now = null): BaseBuilder
    {
        $now = ($now ?? Time::now())->toDateTimeString();

        $builder->where($alias . '.deleted_at', null)
            ->whereIn($alias . '.status', ['published', 'scheduled'])
            ->where($alias . '.published_at IS NOT NULL')
            ->where($alias . '.published_at <=', $now)
            ->groupStart()
            ->where($alias . '.expires_at', null)
            ->orWhere($alias . '.expires_at >', $now)
            ->groupEnd();

        if ($publicOnly || $user === null) {
            return $builder->where($alias . '.visibility', 'public');
        }

        $groups = self::groupIds($user);

        $builder->groupStart()
            ->whereIn($alias . '.visibility', ['public', 'private']);

        if ($groups !== []) {
            $builder->orGroupStart()
                ->where($alias . '.visibility', 'groups')
                ->whereIn($alias . '.id', static fn (BaseBuilder $sub): BaseBuilder => $sub->select('news_id')->from('news_groups')->whereIn('group_id', $groups))
                ->groupEnd();
        }

        return $builder->groupEnd();
    }

    /**
     * The column and value of a kpapy/kpcrudy `where()` that limits a resource on `news` to the
     * posts the user may read: `<news>.id = (SELECT v.id FROM news v WHERE v.id = <news>.id AND
     * <readable>)`, a correlated subquery returning the post's own id only when it is readable, so
     * list, details, search and operations of the library are all limited.
     *
     * The subquery is compiled by the Query Builder (every value escaped by it); only table names
     * (with the connection's prefix) are written here.
     *
     * @return array{0: string, 1: RawSql}
     */
    public static function condition(?User $user): array
    {
        $db    = db_connect();
        $outer = $db->prefixTable('news');
        // The alias carries the prefix too, so the Query Builder never prefixes it twice.
        $alias = $db->prefixTable('news_visible');
        $sub   = self::apply($db->table('news ' . $alias)->select($alias . '.id'), $user, $alias)
            ->where(new RawSql($alias . '.id = ' . $outer . '.id'));

        return [$outer . '.id', new RawSql('(' . $sub->getCompiledSelect() . ')')];
    }

    /**
     * Whether one post row (columns of `news`) is readable by the user.
     *
     * @param array<string, mixed> $row
     */
    public static function canRead(array $row, ?User $user, bool $publicOnly = false): bool
    {
        $id = (int) ($row['id'] ?? 0);

        return $id > 0 && self::apply(db_connect()->table('news'), $user, 'news', $publicOnly)
            ->where('news.id', $id)
            ->countAllResults() === 1;
    }

    /**
     * Ids of the user's groups (`groups.id` of the Shield group names).
     *
     * @return list<int>
     */
    public static function groupIds(User $user): array
    {
        $names = $user->getGroups() ?? [];

        if ($names === []) {
            return [];
        }

        $rows = db_connect()->table('groups')->select('id')->whereIn('name', $names)->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
