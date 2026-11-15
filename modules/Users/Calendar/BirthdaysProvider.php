<?php

declare(strict_types=1);

namespace Modules\Users\Calendar;

use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Modules\Core\Calendar\CalendarProvider;
use Modules\Core\Content\Audience;
use Modules\Core\Permissions\Authorization;

/**
 * "Birthdays" (calendar API, pull; D-25-07, D-25-08): the shared calendar. A birthday is shown
 * only when **all** of these hold:
 *
 * 1. the setting `Users.birthdaysCalendar` is on (off by default);
 * 2. the person consented in their profile (`user_profiles.share_birthday`, off by default);
 * 3. the viewer holds `users-birthdays.view`;
 * 4. one of the viewer's groups may see one of the person's groups (`group_birthday_audiences`,
 *    edited in Users › Groups: "Sees the birthdays of").
 *
 * Nothing is stored. Management sees every date of birth in the administrative calendar
 * (`AdminBirthdaysProvider`) and in the user's record instead.
 */
final class BirthdaysProvider implements CalendarProvider
{
    public const CALENDAR = 'users.birthdays';

    public function calendar(): string
    {
        return self::CALENDAR;
    }

    public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable
    {
        if (! (bool) setting('Users.birthdaysCalendar') || ! Authorization::for($user)->can('users-birthdays.view')) {
            return [];
        }

        $targets = self::targetGroups($user);

        if ($targets === []) {
            return [];
        }

        $rows = Birthdays::people()
            ->where('user_profiles.share_birthday', 1)
            ->whereIn('user_profiles.user_id', static fn ($sub) => $sub->select('user_id')->from('auth_groups_users')->whereIn('group', $targets))
            ->get()->getResultArray();

        return Birthdays::events($rows, $from, $to, self::CALENDAR, 'user-');
    }

    /**
     * Names of the groups whose members' birthdays the user may see.
     *
     * @return list<string>
     */
    public static function targetGroups(User $user): array
    {
        $groups = Audience::groupIds($user);

        if ($groups === []) {
            return [];
        }

        $rows = db_connect()->table('group_birthday_audiences')
            ->select('groups.name')
            ->join('groups', 'groups.id = group_birthday_audiences.target_group_id')
            ->whereIn('group_birthday_audiences.group_id', $groups)
            ->distinct()
            ->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
