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
 * never visible, and neither are events of calendars the user may not see (a disabled module, a
 * calendar permission) or that an `EventAccessPolicy` refuses. No raw SQL: every value is bound or escaped by the Query Builder.
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
        self::calendars($builder, $user, $alias);

        return self::audience($builder, $user, $alias, $publicOnly);
    }

    /**
     * Only events of calendars the user may see (docs/calendar-api.md): events created by hand
     * (no calendar) and events of the calendars of **enabled** modules whose permission, if they
     * declare one, the user holds. Events of a disabled module are therefore hidden.
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function calendars(BaseBuilder $builder, ?User $user, string $alias = 'events'): BaseBuilder
    {
        $ids = array_values(array_diff(array_keys((new Calendars())->visibleTo($user)), [Calendars::OWN]));

        $builder->groupStart()->where($alias . '.calendar', null);

        if ($ids !== []) {
            $builder->orWhereIn($alias . '.calendar', $ids);
        }

        return $builder->groupEnd();
    }

    /**
     * Whether the access policy of the event's calendar (if any) lets the user see it; the policy
     * only narrows what the visibility allows. Guests never pass a policy.
     *
     * @param array<string, mixed> $row a row of `events`
     */
    public static function allowedByPolicy(array $row, ?User $user, ?Calendars $calendars = null): bool
    {
        $calendar = $row['calendar'] ?? null;

        if (! is_string($calendar) || $calendar === '') {
            return true;
        }

        $policy = ($calendars ?? new Calendars())->policy($calendar);

        if ($policy === null) {
            return true;
        }

        return $user !== null && $policy->canSee($user, (string) ($row['source_key'] ?? ''));
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
        if ($id <= 0) {
            return false;
        }

        $row = self::apply(db_connect()->table('events'), $user, 'events', $publicOnly)
            ->select('events.id, events.calendar, events.source_key')
            ->where('events.id', $id)
            ->get(1)->getRowArray();

        return is_array($row) && self::allowedByPolicy($row, $user);
    }
}
