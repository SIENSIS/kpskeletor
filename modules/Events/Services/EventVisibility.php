<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\Audience;

/**
 * Who sees an event, as Query Builder conditions: the only place the rule lives (calendar view,
 * JSON feeds, event details, widget, reminders and the administration list of users without
 * `events-events.all`; docs/events.md, "Visibility").
 *
 * - guests and the public site: only `public` events;
 * - signed-in users: `public`, `private`, and `restricted` events whose audience
 *   (`event_audience`) lists one of the user's groups or the user themself.
 *
 * Administrators and superadmins follow the same rule (an event "only for John" is not in every
 * admin's calendar); `events-events.all` only widens the administration list. Deleted events are
 * never visible. No raw SQL: every value is bound or escaped by the Query Builder.
 */
final class EventVisibility
{
    public const VISIBILITIES = ['public', 'private', 'restricted'];

    public const STATUSES = ['confirmed', 'tentative', 'cancelled'];

    /**
     * Adds the "visible to this user" conditions to a query on `events` (or an alias of it).
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     * @param bool $publicOnly only `public` events, even for a signed-in user (public site)
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function apply(BaseBuilder $builder, ?User $user, string $alias = 'events', bool $publicOnly = false): BaseBuilder
    {
        $builder->where($alias . '.deleted_at', null);

        return self::audience($builder, $user, $alias, $publicOnly);
    }

    /**
     * Only the audience conditions (in brackets), without the deleted filter: kpcrudy's
     * `where(Closure)` scope already handles the trash.
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function audience(BaseBuilder $builder, ?User $user, string $alias = 'events', bool $publicOnly = false, ?int $orCreatedBy = null): BaseBuilder
    {
        if ($publicOnly || $user === null) {
            return $builder->where($alias . '.visibility', 'public');
        }

        $groups = Audience::groupIds($user);
        $userId = (int) $user->id;

        $builder->groupStart()
            ->whereIn($alias . '.visibility', ['public', 'private'])
            ->orGroupStart()
            ->where($alias . '.visibility', 'restricted')
            ->whereIn($alias . '.id', static function (BaseBuilder $sub) use ($groups, $userId): BaseBuilder {
                $sub->select('event_id')->from('event_audience')->groupStart()->where('user_id', $userId);

                if ($groups !== []) {
                    $sub->orWhereIn('group_id', $groups);
                }

                return $sub->groupEnd();
            })
            ->groupEnd();

        if ($orCreatedBy !== null) {
            $builder->orWhere($alias . '.created_by', $orCreatedBy);
        }

        return $builder->groupEnd();
    }

    /**
     * Whether one event (by id) is visible to the user.
     */
    public static function canSee(int $id, ?User $user, bool $publicOnly = false): bool
    {
        return $id > 0 && self::apply(db_connect()->table('events'), $user, 'events', $publicOnly)
            ->where('events.id', $id)
            ->countAllResults() === 1;
    }
}
