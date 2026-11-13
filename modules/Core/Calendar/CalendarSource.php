<?php

declare(strict_types=1);

namespace Modules\Core\Calendar;

/**
 * Every event a module should have published, for `php spark calendar:resync <module>` (repairs
 * drift between the module's data and the calendar). Declared as `'source' => Class::class` in
 * the manifest's `$calendars`; events published under a key the source no longer returns are
 * unpublished.
 */
interface CalendarSource
{
    /**
     * Key => event, for every event of this calendar that should be published now.
     *
     * @return iterable<string, CalendarEvent>
     */
    public function all(): iterable;
}
