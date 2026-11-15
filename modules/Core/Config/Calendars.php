<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseConfig;
use Modules\Core\Calendar\NullCalendar;

/**
 * The calendar API (docs/calendar-api.md): the class behind `service('calendar')`. The Core has
 * none (`NullCalendar`); the Events module sets its `CalendarService` with a registrar.
 */
class Calendars extends BaseConfig
{
    /** A class implementing `Modules\Core\Calendar\CalendarPublisher`. */
    public string $publisher = NullCalendar::class;
}
