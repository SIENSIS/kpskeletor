<?php

declare(strict_types=1);

namespace Modules\Users\Calendar;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\Core\Calendar\CalendarEvent;

/**
 * What both birthdays providers share (D-25-07, D-25-08): the active, non-banned users with a date
 * of birth (`user_profiles`, Auth), turned into all-day events with the day and month only
 * (never the year or the age; 29 February on 28 February in common years).
 */
final class Birthdays
{
    /**
     * Profiles of active, non-banned users with a date of birth.
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function people(): BaseBuilder
    {
        return db_connect()->table('user_profiles')
            ->select('user_profiles.user_id, user_profiles.birth_date, user_profiles.name, users.username')
            ->join('users', 'users.id = user_profiles.user_id')
            ->where('user_profiles.birth_date IS NOT NULL')
            ->where('users.active', 1)
            ->where('users.deleted_at', null)
            ->groupStart()->where('users.status', null)->orWhere('users.status !=', 'banned')->groupEnd();
    }

    /**
     * The birthday events of these people in `[$from, $to)`.
     *
     * @param list<array<string, mixed>> $rows rows of `people()`
     *
     * @return list<CalendarEvent>
     */
    public static function events(array $rows, DateTimeInterface $from, DateTimeInterface $to, string $calendar, string $keyPrefix): array
    {
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
                    title: CalendarEvent::translated('Users.birthdays.title', [$name]),
                    start: $date,
                    allDay: true,
                    calendar: $calendar,
                    visibility: 'private',
                    key: $keyPrefix . (int) $row['user_id'] . '-' . $year,
                );
            }
        }

        return $events;
    }
}
