<?php

declare(strict_types=1);

namespace Modules\Events\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration of the Events module (`config(\Modules\Events\Config\Calendar::class)`; a `Config/Events.php` would be loaded
 * by CodeIgniter as an event-listener file, D-00-04). Values can be overridden in `.env`
 * (`calendar.maxRangeDays = 200`) or stored with `setting('Calendar.<name>', …)` (D-24-05).
 */
class Calendar extends BaseConfig
{
    /** Whether the public site has a calendar (`calendar`, public events only). */
    public bool $publicCalendar = true;

    /** First view of the calendar: `dayGridMonth`, `timeGridWeek`, `timeGridDay` or `listMonth` (phones use the list). */
    public string $defaultView = 'dayGridMonth';

    /** Longest date range a calendar feed request may ask for, in days. */
    public int $maxRangeDays = 400;

    /**
     * Minutes before the start a user may ask to be reminded of an event.
     *
     * @var list<int>
     */
    public array $reminderOptions = [60, 15, 1440, 10080];

    /** Days ahead shown by the "Upcoming events" widget. */
    public int $upcomingDays = 30;

    /** Most occurrences a recurring event can produce (safety cap of the expansion). */
    public int $maxOccurrences = 1000;

    /** Seconds the results of calendar providers are cached per user and range (0: no cache). */
    public int $providerCacheSeconds = 300;

    /** Days before today covered by the iCal feeds. */
    public int $icalPastDays = 90;

    /** Days after today covered by the iCal feeds. */
    public int $icalFutureDays = 365;

    /** Days a deleted event stays in the iCal feeds as cancelled (so clients remove it too). */
    public int $icalGraceDays = 30;

    /** Refresh hint of the iCal feeds (`REFRESH-INTERVAL`, `X-PUBLISHED-TTL`; an ISO 8601 duration). */
    public string $icalRefresh = 'PT1H';

    /** Seconds an iCal feed is cached (0: no cache). Changes of events clear the cache. */
    public int $icalCacheSeconds = 300;

    /** Whether the public iCal feeds (`ical/public/…`) exist (they also need `$publicCalendar`). */
    public bool $publicFeeds = true;
}
