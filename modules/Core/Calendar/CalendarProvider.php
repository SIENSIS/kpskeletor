<?php

declare(strict_types=1);

namespace Modules\Core\Calendar;

use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;

/**
 * A source of calendar events computed on the fly (pull mode; nothing is stored), declared in the
 * manifest's `$calendarProviders` (docs/calendar-api.md). Examples: task deadlines, holidays,
 * birthdays (`Modules\Users\Calendar\BirthdaysProvider`).
 *
 * Providers must return **only the events the user may see**, each with a stable `key` (its iCal
 * `UID`) and a `calendar` of their own module (or none: `calendar()` is used). The calendar caches
 * the results per user and range for a few minutes.
 */
interface CalendarProvider
{
    /**
     * The calendar of the events (`<module>.<key>`, declared in the manifest's `$calendars`).
     */
    public function calendar(): string;

    /**
     * The events that overlap `[$from, $to)` for this user.
     *
     * @return iterable<CalendarEvent>
     */
    public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable;
}
