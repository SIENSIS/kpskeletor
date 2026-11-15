<?php

declare(strict_types=1);

namespace Modules\Core\Calendar;

use CodeIgniter\Shield\Entities\User;

/**
 * Per-event access for a calendar whose rule cannot be written as a visibility and an audience
 * (e.g. "only the users enrolled in the course"): declared as `'policy' => Class::class` in the
 * manifest's `$calendars`. It narrows what the event's visibility already allows (it never widens
 * it) in the calendar, the details, the widget, reminders and the iCal feeds.
 */
interface EventAccessPolicy
{
    /**
     * Whether the user may see the published event `$key` of this calendar.
     */
    public function canSee(User $user, string $key): bool;
}
