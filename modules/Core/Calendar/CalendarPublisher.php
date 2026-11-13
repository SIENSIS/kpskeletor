<?php

declare(strict_types=1);

namespace Modules\Core\Calendar;

/**
 * The calendar's publishing API (`service('calendar')`, docs/calendar-api.md). Both calls are
 * idempotent per module and key: call `publish()` on every change of the source record and
 * `unpublish()` when it is deleted or should no longer be in the calendar.
 *
 * Without the Events module the service is a `NullCalendar` (nothing happens).
 */
interface CalendarPublisher
{
    /**
     * Stores or updates the event `$key` of `$module` (e.g. `'News'`, `'notice-12'`).
     *
     * @return bool whether the calendar changed
     */
    public function publish(string $module, string $key, CalendarEvent $event): bool;

    /**
     * Removes the event `$key` of `$module` (clients of the iCal feeds see it cancelled).
     *
     * @return bool whether the calendar changed
     */
    public function unpublish(string $module, string $key): bool;
}
