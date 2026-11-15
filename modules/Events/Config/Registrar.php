<?php

declare(strict_types=1);

namespace Modules\Events\Config;

use Modules\Events\Services\CalendarService;

/**
 * Configuration the Events module contributes (CodeIgniter registrar): `service('calendar')`
 * becomes its `CalendarService` (the Core's `NullCalendar` otherwise; docs/calendar-api.md).
 */
final class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Calendars(): array
    {
        return ['publisher' => CalendarService::class];
    }
}
