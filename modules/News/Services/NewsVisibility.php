<?php

declare(strict_types=1);

namespace Modules\News\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\Audience;
use Modules\Core\Content\PublishWorkflow;

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
    public const VISIBILITIES = Audience::VISIBILITIES;
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
        $now ??= Time::now();

        PublishWorkflow::applyPublished($builder, $alias, 'published_at', $now)
            ->groupStart()
            ->where($alias . '.expires_at', null)
            ->orWhere($alias . '.expires_at >', $now->toDateTimeString())
            ->groupEnd();

        return Audience::apply($builder, $user, $alias, 'news_groups', 'news_id', $publicOnly);
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
        return Audience::groupIds($user);
    }
}
