# Calendar

Other modules (courses, bookings, tasks, HR…) put their dates in the calendar of the Events module
and get, for free, the same views, permissions, iCal feeds and reminders (specification "Events" ›
"Calendar API for other modules"; decisions D-25-01, D-25-04, D-25-05). Two ways:

| | **Publish (push)** | **Provider (pull)** |
|---|---|---|
| How | `service('calendar')->publish()` stores the event in `events` | A `CalendarProvider` returns events on the fly for a range |
| For | Events created at a moment: a course session, a booking | Dates already in the module's data: deadlines, holidays, birthdays |
| Example | News notices with an event date (`modules/News/Services/NoticeCalendar.php`) | Birthdays (`modules/Users/Calendar/BirthdaysProvider.php`) |

The user-facing calendar (views, administration, reminders, iCal feeds) is the Events module:
[`events.md`](events.md). This page is for module developers.

The contracts live in the Core (`modules/Core/Calendar/`), so a module never depends on Events:
without it, `service('calendar')` is a `NullCalendar` and publishing does nothing.

## 1. Declare the calendars

```php
// modules/Courses/Config/Module.php
public array $calendars = [
    'sessions' => [
        'label'  => 'Courses.cal.sessions',         // language key
        'color'  => '#0d6efd',
        // optional:
        'permission' => 'courses-sessions.list',    // needed to see ANY event of it
        'policy'     => \Modules\Courses\Calendar\EnrolledOnly::class,   // EventAccessPolicy
        'source'     => \Modules\Courses\Calendar\SessionsSource::class, // CalendarSource (resync)
        'hidden'     => true,                           // hidden in the calendar view until shown
    ],
];
public array $calendarProviders = [\Modules\Courses\Calendar\DeadlinesProvider::class];
```

The calendar's id is `<module in lower case>.<key>` (`courses.sessions`). Without `permission`,
each event's visibility decides (D-00-41). Events created by hand in the calendar administration
form the built-in calendar `events`. `php spark modules:sync` stores the declarations.

## 2. Publish (push)

```php
use Modules\Core\Calendar\CalendarEvent;

service('calendar')->publish('Courses', "session-{$id}", new CalendarEvent(
    title: ['ca' => $titleCa, 'en' => $titleEn],   // or one string (Catalan)
    start: $start, end: $end,                        // any time zone; end optional
    allDay: false, location: $room,
    description: $html,                              // sanitised with HTMLPurifier
    calendar: 'courses.sessions',                    // only your own module's calendars
    visibility: 'restricted', groups: ['students'], users: [$teacherId],
    url: '/backend/courses/sessions/' . $code,       // "Edit in Courses" link
    reminders: true,                                 // users may ask for a reminder
    status: 'confirmed',                             // or 'tentative', 'cancelled'
    color: null,                                     // null: the calendar's colour
));

service('calendar')->unpublish('Courses', "session-{$id}");   // deleted or cancelled
```

- **Call it on every change** of the source record: it is idempotent per module + key. Equal data
  changes nothing; different data updates the event and bumps its iCal `SEQUENCE`; an unpublished
  event comes back. Both return whether the calendar changed.
- `end` is optional (one hour, or one day for `allDay`; all-day ends are inclusive); times are
  stored in UTC.
- `unpublish()` soft-deletes: iCal clients receive the event as `STATUS:CANCELLED` for
  `Modules\Events\Config\Calendar::$icalGraceDays` (30) days.
- `purgeModule(<Module>)` deletes every event of a module for good; only the Core's uninstaller
  calls it (`modules:uninstall` without `--keep-data`, [`installing-modules.md`](installing-modules.md)).
- Visibility `public`, `private` (signed-in users) or `restricted` (group **names** and user ids;
  unknown groups throw `InvalidArgumentException`, unknown users are dropped).
- Published events are **locked** in the calendar administration: badge "Managed by Courses" and
  an "Edit in Courses" link; edit, delete, trash and duplicate are refused.
- Invalid input (empty title, end before start, unknown visibility, a non-http URL, another
  module's calendar, a key outside `[A-Za-z0-9._:-]{1,150}`) throws `InvalidArgumentException`.

## 3. Provide (pull)

```php
final class DeadlinesProvider implements \Modules\Core\Calendar\CalendarProvider
{
    public function calendar(): string { return 'courses.deadlines'; }

    public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable
    {
        foreach ($this->deadlinesOf($user, $from, $to) as $task) {      // ONLY what $user may see
            yield new CalendarEvent(title: $task['title'], start: $task['due'], key: 'task-' . $task['id'], url: …);
        }
    }
}
```

- Every event needs a stable `key` (its iCal `UID` is `<calendar>-<key>@<host>`).
- Providers only run for signed-in users (back-office calendar and personal feeds; never the public
  site or public feeds), only fill their own module's calendars, and a failing provider is logged and
  skipped. Results are cached per user and range for `providerCacheSeconds` (300).
- Provided events have no details page: their `url` links back to the module.

## 4. Access policies

When "who sees it" cannot be expressed with a visibility and an audience (e.g. only the students
enrolled in a course), declare an `EventAccessPolicy` for the calendar:

```php
final class EnrolledOnly implements \Modules\Core\Calendar\EventAccessPolicy
{
    public function canSee(User $user, string $key): bool { /* the published key */ }
}
```

It **narrows** what the visibility allows (never widens it) in the calendar, details, widget,
reminders and feeds; guests never pass it.

## 5. Consistency

- Events and providers of a **disabled** module are hidden (they come back when it is enabled).
- **Uninstalling** a module deletes its events: the Core's `Uninstaller` calls
  `service('calendar')->purgeModule('<Module>')` unless `--keep-data` is given
  ([`installing-modules.md`](installing-modules.md)); a module never calls it itself.
- `php spark calendar:resync <Module>` asks the module's `CalendarSource`s (`all(): key => event`)
  for every event again (section 6; [`commands.md`](commands.md)).
- CodeIgniter events after every change: `calendar.created`, `calendar.updated`, `calendar.deleted`
  with `['id' => <events.id>, 'module' => …, 'key' => …]` (module and key are null for events
  created by hand).

## 6. Resync

A module that publishes should declare a `source` (`Modules\Core\Calendar\CalendarSource`) for
each published calendar: `all()` returns every event the calendar should hold, as
`key => CalendarEvent`. `php spark calendar:resync <Module>` (or `CalendarService::resync()`)
publishes each one again (unchanged events change nothing), unpublishes the published events of
those calendars it no longer returns, prints the counts and writes `calendar_resync` to the
activity log. Use it after restoring a backup, after a failed publish or when the module's data
was changed outside its code. Calendars without a source (providers) need no resync. Examples:
`modules/News/Services/NoticeCalendar.php`, `modules/Tasks/Calendar/DueDates.php`.

## 7. iCal feeds

Published and provided events reach iCal clients without extra code; the details are in
[`events.md`](events.md) (section "iCal (RFC 5545)"):

- **One-off export** (`…/export.ics`): a static copy of one event.
- **Personal feed** (`ical/user/<secret>.ics`, user menu › Calendar subscription): every event
  the user may see **now**, other modules' calendars and providers included, evaluated with
  their current permissions and the calendars' policies on every request; filters by calendars
  and categories; the user's reminders as `VALARM`. The secret is shown once, created with
  re-authentication and can be revoked.
- **Public feeds** (`ical/public/all.ics`, `ical/public/<category>.ics`): public events only,
  never providers, while `publicCalendar` and `publicFeeds` are on.
- **What a module controls:** the `UID` (`<module>-<key>@host` for published events,
  `<calendar>-<key>@host` for provided ones, so keys must be stable), `SEQUENCE` (grows on each
  real change), `STATUS:CANCELLED` (`status: 'cancelled'` or `unpublish()`, kept for
  `icalGraceDays`), and the plain-text description.

## 8. Testing

Copy `modules/Events/Tests/CalendarApiTest.php` (a fake module declared in
`module_declarations`, a policy, a provider and a source in `modules/Events/Tests/Support/`) and
`modules/News/Tests/NoticeCalendarTest.php`. Check: publish/unpublish idempotency, the audience,
that the provider filters by user, and that the module's events disappear when it is disabled.
