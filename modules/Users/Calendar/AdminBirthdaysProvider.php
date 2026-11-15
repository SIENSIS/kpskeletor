<?php

declare(strict_types=1);

namespace Modules\Users\Calendar;

use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Modules\Core\Calendar\CalendarProvider;
use Modules\Core\Permissions\Authorization;

/**
 * "Birthdays (administration)" (D-25-08): every date of birth of active users, whether or not they
 * share it, for administrative work (paperwork, communications). Only for holders of
 * `users-users.birthdate` (the calendar declares the same permission and is hidden by default in
 * the calendar view). Independent of the setting of the shared calendar.
 */
final class AdminBirthdaysProvider implements CalendarProvider
{
    public const CALENDAR = 'users.birthdaysadmin';

    public function calendar(): string
    {
        return self::CALENDAR;
    }

    public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable
    {
        if (! Authorization::for($user)->can('users-users.birthdate')) {
            return [];
        }

        return Birthdays::events(Birthdays::people()->get()->getResultArray(), $from, $to, self::CALENDAR, 'admin-user-');
    }
}
