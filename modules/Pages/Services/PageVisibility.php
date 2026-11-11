<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\Audience;
use Modules\Core\Content\PublishWorkflow;

/**
 * Who may read a page, as Query Builder conditions shared by the public routes, the home page,
 * the sitemap, the menu link type and the sidebar of subpages (docs/pages.md, "Visibility").
 *
 * A page is **published** when it is not deleted, its status is `published` or `scheduled` and
 * its `publish_at` has passed (the Core's PublishWorkflow). Then:
 * - guests (and the sitemap): only `public` pages;
 * - signed-in users: `public`, `private` and `groups` pages linked (`page_groups`) to one of
 *   their groups (the Core's Audience). Superadmins follow the same rule; authors use the
 *   back office's preview.
 */
final class PageVisibility
{
    public const STATUSES     = PublishWorkflow::STATUSES;
    public const VISIBILITIES = Audience::VISIBILITIES;

    /**
     * Adds "published and readable by this user" to a query on `pages` (or an alias of it).
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function apply(BaseBuilder $builder, ?User $user, string $alias = 'pages', bool $publicOnly = false, ?Time $now = null): BaseBuilder
    {
        PublishWorkflow::applyPublished($builder, $alias, 'publish_at', $now);

        return Audience::apply($builder, $user, $alias, 'page_groups', 'page_id', $publicOnly);
    }

    /**
     * Whether a page is published now (whoever reads it).
     */
    public static function isPublished(int $id): bool
    {
        return $id > 0 && PublishWorkflow::applyPublished(db_connect()->table('pages'), 'pages', 'publish_at')
            ->where('pages.id', $id)
            ->countAllResults() === 1;
    }

    /**
     * Whether a page is published and readable by the user.
     */
    public static function canRead(int $id, ?User $user, bool $publicOnly = false): bool
    {
        return $id > 0 && self::apply(db_connect()->table('pages'), $user, 'pages', $publicOnly)
            ->where('pages.id', $id)
            ->countAllResults() === 1;
    }

    /**
     * The status shown to authors: a `scheduled` page whose date has passed is `published`.
     *
     * @param array<string, mixed> $row
     */
    public static function displayStatus(array $row, ?Time $now = null): string
    {
        $status = (string) ($row['status'] ?? 'draft');
        $date   = $row['publish_at'] ?? null;

        if ($status === 'scheduled' && is_string($date) && $date !== '' && ! Time::parse($date)->isAfter($now ?? Time::now())) {
            return 'published';
        }

        return $status;
    }
}
