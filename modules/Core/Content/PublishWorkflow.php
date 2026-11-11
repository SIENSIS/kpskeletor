<?php

declare(strict_types=1);

namespace Modules\Core\Content;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * The publish workflow shared by module content (news, pages…): `draft` → `scheduled` (published
 * with a future date) → `published`. Extracted from News in session 20.
 *
 * Readers never depend on a scheduled task: `applyPublished()` checks the date itself, so a
 * scheduled row becomes visible the moment its date passes; `releaseScheduled()` only updates the
 * stored status (lists, badges, notifications).
 */
final class PublishWorkflow
{
    public const STATUSES = ['draft', 'scheduled', 'published'];

    /**
     * The status and date of a row being published now: the given date (default now) and
     * `scheduled` when it is in the future, otherwise `published`.
     *
     * @return array{status: string, at: Time}
     */
    public static function statusFor(?string $date, ?Time $now = null): array
    {
        $now ??= Time::now();
        $at = $date !== null && $date !== '' ? Time::parse($date) : $now;

        return ['status' => $at->isAfter($now) ? 'scheduled' : 'published', 'at' => $at];
    }

    /**
     * Adds "published and its date has come, not deleted" to a query on a content table.
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     * @param string $column the publication date column (`published_at`, `publish_at`)
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function applyPublished(BaseBuilder $builder, string $alias, string $column, ?Time $now = null): BaseBuilder
    {
        $now = ($now ?? Time::now())->toDateTimeString();

        return $builder->where($alias . '.deleted_at', null)
            ->whereIn($alias . '.status', ['published', 'scheduled'])
            ->where($alias . '.' . $column . ' IS NOT NULL')
            ->where($alias . '.' . $column . ' <=', $now);
    }

    /**
     * Scheduled rows whose date has come become `published`. Returns their ids.
     *
     * @return list<int>
     */
    public static function releaseScheduled(string $table, string $column): array
    {
        $now = Time::now()->toDateTimeString();
        $ids = array_map(static fn (array $row): int => (int) $row['id'], db_connect()->table($table)->select('id')
            ->where('status', 'scheduled')->where('deleted_at', null)->where($column . ' <=', $now)
            ->get()->getResultArray());

        if ($ids !== []) {
            db_connect()->table($table)->whereIn('id', $ids)->update(['status' => 'published', 'updated_at' => $now]);
        }

        return $ids;
    }
}
