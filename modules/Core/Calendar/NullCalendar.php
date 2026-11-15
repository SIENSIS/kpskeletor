<?php

declare(strict_types=1);

namespace Modules\Core\Calendar;

/**
 * The calendar service when no module provides one (the Events module is not installed):
 * publishing does nothing, so modules never need to check for the calendar themselves.
 */
final class NullCalendar implements CalendarPublisher
{
    public function publish(string $module, string $key, CalendarEvent $event): bool
    {
        return false;
    }

    public function unpublish(string $module, string $key): bool
    {
        return false;
    }
}
