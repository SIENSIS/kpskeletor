# Calendar API for modules

Other modules (courses, bookings, tasks, HR…) put their dates in the calendar of the Events module
and get, for free, the same views, permissions, iCal feeds and reminders (specification "Events" ›
"Calendar API for other modules"; decisions D-25-01, D-25-04, D-25-05). Two ways:

| | **Publish (push)** | **Provider (pull)** |
|---|---|---|
| How | `service('calendar')->publish()` stores the event in `events` | A `CalendarProvider` returns events on the fly for a range |
| For | Events created at a moment: a course session, a booking | Dates already in the module's data: deadlines, holidays, birthdays |
| Example | News notices with an event date (`modules/News/Services/NoticeCalendar.php`) | Birthdays (`modules/Users/Calendar/BirthdaysProvider.php`) |

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
));

service('calendar')->unpublish('Courses', "session-{$id}");   // deleted or cancelled
```

- **Call it on every change** of the source record: it is idempotent per module + key. Equal data
  changes nothing; different data updates the event and bumps its iCal `SEQUENCE`; an unpublished
  event comes back. Both return whether the calendar changed.
- `unpublish()` soft-deletes: iCal clients receive the event as `STATUS:CANCELLED` for
  `Config\Calendar::$icalGraceDays` (30) days.
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
- **Uninstalling** a module must delete its events: `(new CalendarService())->purgeModule('Courses')`
  (called by the uninstaller of session 29).
- `php spark calendar:resync <Module>` asks the module's `CalendarSource`s (`all(): key => event`)
  for every event again, publishes them and unpublishes the published events of those calendars
  they no longer return (`docs/commands.md`).
- CodeIgniter events after every change: `calendar.created`, `calendar.updated`, `calendar.deleted`
  with `['id' => <events.id>, 'module' => …, 'key' => …]` (module and key are null for events
  created by hand).

## 6. Testing

Copy `modules/Events/Tests/CalendarApiTest.php` (a fake module declared in
`module_declarations`, a policy, a provider and a source in `modules/Events/Tests/Support/`) and
`modules/News/Tests/NoticeCalendarTest.php`. Check: publish/unpublish idempotency, the audience,
that the provider filters by user, and that the module's events disappear when it is disabled.
