<?php

declare(strict_types=1);

namespace Modules\Events\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Events module: the calendar (docs/events.md; specification "Modules" › "Events").
 *
 * Resources (permission names `events-<resource>.<operation>`, D-24-01):
 * - `events` (kpcrudy): the administration of the events; extras `all` (see and manage every
 *   event, not only those of the user's audience or created by them) and `duplicate`;
 * - `categories` (kpcrudy): the categories;
 * - `calendar` (page): the calendar view, the event details and the user's reminders (`view`),
 *   granted to every group.
 *
 * Configuration: `Config/Calendar.php` (D-00-04, D-24-05). The calendar API (`$calendars`,
 * `$calendarProviders`) and iCal arrive in session 25.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Events';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-calendar3';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2', 'Dashboard' => '^0.1'];

    public array $resources = [
        'events' => [
            'label' => 'Events.res.events',
            'type'  => 'crud',
            'extra' => [
                'all'       => 'Events.perm.all',
                'duplicate' => 'Events.perm.duplicate',
            ],
        ],
        'categories' => [
            'label'  => 'Events.res.categories',
            'type'   => 'crud',
            'except' => ['import', 'trash', 'restore', 'purge'],
        ],
        'calendar' => [
            'label' => 'Events.res.calendar',
            'type'  => 'page',
        ],
    ];

    public array $defaultGrants = [
        'superadmin' => ['events-calendar.*'],
        'admin'      => ['events-events.*', 'events-categories.*', 'events-calendar.*'],
        'editor'     => [
            'events-events.list', 'events-events.show', 'events-events.add', 'events-events.edit',
            'events-events.delete', 'events-events.duplicate', 'events-events.export', 'events-events.import',
            'events-categories.list', 'events-categories.show',
            'events-calendar.*',
        ],
        'user' => ['events-calendar.*'],
    ];

    public array $menu = [
        ['resource' => 'calendar', 'label' => 'Events.menu.calendar', 'icon' => 'bi bi-calendar3', 'order' => 25],
        ['resource' => 'events', 'label' => 'Events.menu.events', 'icon' => 'bi bi-calendar-event', 'order' => 1, 'parent' => 'calendar'],
        ['resource' => 'categories', 'label' => 'Events.menu.categories', 'icon' => 'bi bi-tags', 'order' => 2, 'parent' => 'calendar'],
    ];

    /** Link type "Calendar" of the public menu editor. */
    public array $menuLinks = [\Modules\Events\Menu\CalendarLink::class];

    /** No manifest settings: `setting('Events.*')` would autoload `app/Config/Events.php` (D-24-05). */
    public array $settings = [];

    public array $widgets = [\Modules\Events\Widgets\UpcomingEvents::class];

    public array $tasks = [\Modules\Events\Tasks\SendReminders::class];

    public array $notifications = [\Modules\Events\Notifications\EventReminder::class];

    /** No files: events have no uploads. */
    public array $backup = [];

    /** No extra Content-Security-Policy sources: the calendar library is vendored and needs none. */
    public array $csp = [];

    /** No outbound requests. */
    public array $outboundHosts = [];

    /** Calendars of other modules and providers arrive in session 25 (calendar API). */
    public array $calendars = [];

    /** No API resource yet: `events` is a Core endpoint of session 26. */
    public array $api = [];
}
