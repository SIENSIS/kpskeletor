<?php

declare(strict_types=1);

namespace Modules\Users\Calendar;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarProvider;

/**
 * "Birthdays" (calendar API, pull; docs/calendar-api.md, "Providers"): an all-day event on the
 * birthday of every active user who entered a date of birth in their profile
 * (`user_profiles.birth_date`, Auth). Nothing is stored; only the day and month are shown (never
 * the year or the age); 29 February is shown on 28 February in other years.
 *
 * Off by default: setting `Users.birthdaysCalendar` (Settings › Users). Only signed-in users see
 * it (providers never reach the public site or public feeds).
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
        if (! (bool) setting('Users.birthdaysCalendar')) {
            return [];
        }

        $rows = db_connect()->table('user_profiles')
            ->select('user_profiles.user_id, user_profiles.birth_date, user_profiles.name, users.username')
            ->join('users', 'users.id = user_profiles.user_id')
            ->where('user_profiles.birth_date IS NOT NULL')
            ->where('users.active', 1)
            ->where('users.deleted_at', null)
            ->groupStart()->where('users.status', null)->orWhere('users.status !=', 'banned')->groupEnd()
            ->get()->getResultArray();

        $zone   = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');
        $start  = DateTimeImmutable::createFromInterface($from)->setTimezone($zone);
        $end    = DateTimeImmutable::createFromInterface($to)->setTimezone($zone);
        $events = [];

        foreach ($rows as $row) {
            if (preg_match('/^\d{4}-(\d{2})-(\d{2})/', (string) $row['birth_date'], $m) !== 1) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : (string) $row['username'];

            for ($year = (int) $start->format('Y'); $year <= (int) $end->format('Y'); $year++) {
                [$month, $day] = [(int) $m[1], (int) $m[2]];

                if (! checkdate($month, $day, $year)) {
                    $day = 28; // 29 February in a common year
                }

                $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $zone);

                if ($date < $start->setTime(0, 0) || $date >= $end) {
                    continue;
                }

                $events[] = new CalendarEvent(
                    title: [
                        'ca' => lang('Users.birthdays.title', [$name], 'ca'),
                        'es' => lang('Users.birthdays.title', [$name], 'es'),
                        'en' => lang('Users.birthdays.title', [$name], 'en'),
                    ],
                    start: $date,
                    allDay: true,
                    calendar: self::CALENDAR,
                    visibility: 'private',
                    key: 'user-' . (int) $row['user_id'] . '-' . $year,
                );
            }
        }

        return $events;
    }
}
