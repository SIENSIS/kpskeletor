# Events (calendar)

`modules/Events` is the calendar of the skeleton (specification "Modules" › "Events (calendar)").
Session 24 built the events, their audiences, the administration, the calendar views, reminders,
the *Upcoming events* widget and the "Calendar" menu link type; session 25 added iCal (exports,
subscribable feeds) and the calendar API for other modules (`docs/calendar-api.md`).

## What it does

- **Events** (`events`, texts in `event_translations`: title and rich-text description; Catalan
  required, Spanish and English optional): start and optional end (stored in UTC, edited and
  shown in the site's time zone), all day, location, organiser, category, colour, link, status
  (`confirmed`, `tentative`, `cancelled`), visibility, recurrence. Events published by
  other modules (`docs/calendar-api.md`) have `calendar`, `source_module`, `source_key`,
  `source_url` and are never editable here; `sequence` grows on every change (iCal);
  `allow_reminders` (session 25) says whether users may ask for a reminder.
- **Categories** (`event_categories` + `event_category_translations`): name, description, colour,
  slug (public calendar filter), order, active.
- **Visibility:** `public` (everyone, the public site), `private` (any signed-in user) or
  `restricted` (only its **audience**, `event_audience`: any combination of groups and users; each
  row targets a group **or** a user, D-24-03).
- **Recurrence** (expanded when a range is queried, never stored; D-24-09): daily, weekly (days of
  the week), monthly, yearly; every N periods; until a date or N times; skipped dates.
- **Reminders** (`event_reminders`, D-24-10): each user may ask for one in the event's details.

## Screens and URLs

| URL | Resource / permission | What |
|---|---|---|
| `backend/calendar` | `events-calendar.view` (every group) | Month, week, day and list views; category filter |
| `backend/calendar/feed?start=&end=[&category=]` | `events-calendar.view` | JSON of the calendar component (at most `maxRangeDays`) |
| `backend/calendar/event/{code}[?date=]` | `events-calendar.view` | An event (masked id; anything not visible is a 404) and the user's reminder |
| `POST backend/calendar/event/{code}/reminder` | `events-calendar.view` | `minutes`: one of `reminderOptions`, empty removes it |
| `backend/events` | `events-events.*` | Administration (kpcrudy): filters, CSV export/import, operation `duplicate` |
| `backend/events/categories` | `events-categories.*` | Categories (kpcrudy) |
| `calendar[?category=<slug>]`, `calendar/event/{code}` | public | Public events only |
| `calendar/feed?start=&end=[&category=]` | public, `ratelimit:feeds` | JSON of public events |
| `backend/calendar/event/{code}/export.ics`, `calendar/event/{code}/export.ics` | as the details | One-off iCal export (a static copy) |
| `POST backend/calendar/calendars` | `events-calendar.view` | `calendars[]`: the calendars shown in the calendar view (saved per user) |
| `backend/calendar/subscription` (+ `POST generate` with `reauth`, `revoke`, `filters`) | `events-calendar.view` (user menu) | "Calendar subscription": the personal iCal feed |
| `ical/user/<secret>.ics` | the secret; `ratelimit:feeds` per IP and per secret | Personal feed |
| `ical/public/all.ics`, `ical/public/<category slug>.ics` | public, `ratelimit:feeds` | Public events only |

Names and URLs: decision **D-24-01** (decided as built); the session 25 URLs: **D-25-01** (open, built so they can be renamed).

## Rules

- **Who sees what** (`Services/EventVisibility.php`, the only place the rule lives): guests and
  the public site see `public` events; signed-in users `public`, `private` and the `restricted`
  events whose audience lists one of their groups or themselves. **Administrators and
  superadmins follow the same rule** in the calendar, the details, the widget and reminders: an
  event "only for John" is in John's calendar only. Deleted events are never visible.
- **Administration list:** with `events-events.all`, every event, with a filter "visible to me /
  all"; without it, kpcrudy's `where(Closure)` limits every operation (list, details, edit,
  delete, duplicate, export, bulk actions) to the events the user sees or created (D-24-07).
- **Audience groups** (`Services/AssignableGroups.php`, D-24-02, decided as built): the groups field offers
  only the groups the user may target (superadmins: all; others: never `superadmin`, a group
  they belong to or whose every permission they hold). A forged group is refused with an error
  and a `security` entry `forbidden_audience`; groups someone else set and the user cannot
  assign are kept when they edit. A restricted event needs an audience; the audience of an event
  that is not restricted is deleted. The users field is a multiple select of active users
  (D-24-04; library request for AJAX).
- **Description:** sanitised with HTMLPurifier (`Modules\Core\Security\HtmlSanitizer`) before
  kpcrudy reads the form and again when shown; edited with the Core's rich-text editor (kpcrudy
  1.3 `setTranslations(..., ['attrs' => …])`).
- **CSV:** export with one column per language (`exportTranslations()`); import reads the same
  columns (`title:ca`, …; D-24-06) **except the description** (kpcrudy stores imported
  translations unsanitised, so rich text is never imported), groups and users by id or name
  (only assignable groups).
- **Duplicate** copies the event, its texts (title "… (copy)") and its audience and opens the
  copy's form; it needs `events-events.duplicate` and `events-events.add`.
- **Dates:** an event without an end lasts one hour (or the whole day); all-day events cover
  whole local days. Recurrences keep the local time across daylight saving changes.

## Calendar view

`<div data-component="events/calendar" data-options='…'>` (`Assets/js/calendar.js`) loads the
vendored **FullCalendar 7.1.3** (MIT, `public/assets/vendor/fullcalendar/`, D-24-08) on demand:
its CSS files, the classic theme coloured with Bootstrap's variables
(`Assets/css/calendar.css`, light and dark), the `ca`/`es` locale. It needs no CSP exception
(no inline styles: version 7 only uses the CSSOM). Phones start with the list view. Events link
to their details page. The feed sends the site's wall-clock times and the calendar runs in UTC
mode, so every browser shows the site's times. Options come from
`Services/CalendarComponent::options()`.

## iCal (RFC 5545)

Two different features (D-25-01 … D-25-03):

- **One-off export** (`…/export.ics`, button "Add to my calendar" in the details): a static copy;
  later changes do not reach it.
- **Subscribable feeds** (read-only): clients poll the URL and show changes, new events and
  deletions on their next refresh. Two-way sync (CalDAV) is out of scope.
  - **Personal feed:** every event the user can see **now** (public, private, their audiences,
    other modules' calendars and providers), evaluated with the user's current permissions on
    every request (a user who loses access stops seeing the event; a banned or inactive user, a
    user without `events-calendar.view`, a revoked or regenerated secret: 404). Filters by
    calendars and categories. The user's reminders are included as `VALARM`.
  - **Public feeds:** public events of every category or one (`ical/public/<slug>.ics`), while
    `publicCalendar` and `publicFeeds` are on.
  - **The secret** (D-25-02): 32 random bytes in the URL, stored as SHA-256 in `ical_tokens`,
    shown **once** when created or regenerated (with "Copy" and a `webcal://` link); creating or
    regenerating needs re-authentication; revoking disables it. Last access (time and IP) shown
    on the screen; accesses logged once a day or on a new IP (`ical_accessed`).
- **Format** (`Ical/IcalWriter.php`, `Folding.php`, `VTimezone.php`): `UID` stable per event
  (`event-<code>@host`, `<module>-<key>@host`, `<calendar>-<key>@host` for provided events),
  `SEQUENCE`, `DTSTAMP`/`LAST-MODIFIED` from the last change, local times with `TZID` and the
  site's `VTIMEZONE` (built from PHP's time zone database), all-day events as dates, recurrences
  as `RRULE`/`EXDATE` (not expanded), `STATUS:CANCELLED` for cancelled events and for events
  deleted in the last `icalGraceDays`, plain-text descriptions, escaping and folding at 75
  octets, CRLF. Refresh hints `REFRESH-INTERVAL;VALUE=DURATION:PT1H` and `X-PUBLISHED-TTL:PT1H`
  (`icalRefresh`); the client decides the real delay.
- **Performance:** range `icalPastDays` (90) before and `icalFutureDays` (365) after today;
  documents cached `icalCacheSeconds` (300; any change clears the cache); `ETag` (hash of the
  document) and `Last-Modified` with `304 Not Modified`.
- **Manual check with a real client:** install with demo data on a host reachable from the client
  (or a tunnel), sign in as usuari@example.com, open the user menu › "Calendar subscription",
  "Create my feed address" (asks for the password again), then in Thunderbird: New calendar ›
  On the network › iCalendar (ICS) › paste the URL; or Google Calendar › Other calendars › From
  URL (Google needs a public HTTPS host). Check that the demo events appear in the site's times,
  that the weekly meeting repeats without the skipped date, then change an event's title and
  refresh the calendar in the client.

## Calendars and other modules

The calendar view shows the calendars the user can see (the own `events` calendar and those
declared by enabled modules, e.g. *Notices* of News and *Birthdays* of Users) as checkboxes; each
user's choice is saved (`event_calendar_preferences`). Events published by another module show
"Managed by <module>" and "Edit in <module>" in the administration and the details. How modules
use it: `docs/calendar-api.md`.

## Configuration

`Modules\Events\Config\Calendar` (not `Config/Events.php`, D-00-04; no manifest settings,
D-24-05), overridable from `.env` (`calendar.maxRangeDays = 200`):

| Property | Default | |
|---|---|---|
| `publicCalendar` | `true` | The public calendar (`calendar…`); off: 404 and the menu link is hidden |
| `defaultView` | `dayGridMonth` | `dayGridMonth`, `timeGridWeek`, `timeGridDay` or `listMonth` |
| `maxRangeDays` | `400` | Longest feed range |
| `reminderOptions` | `[60, 15, 1440, 10080]` | Minutes before the start offered for reminders |
| `upcomingDays` | `30` | Days ahead of the widget |
| `maxOccurrences` | `1000` | Safety cap of the recurrence expansion |
| `providerCacheSeconds` | `300` | Cache of calendar providers per user and range (0: none) |
| `icalPastDays` / `icalFutureDays` | `90` / `365` | Range of the iCal feeds |
| `icalGraceDays` | `30` | Days a deleted event stays in feeds as cancelled |
| `icalRefresh` | `PT1H` | Refresh hint of the feeds |
| `icalCacheSeconds` | `300` | Cache of a feed (0: none) |
| `publicFeeds` | `true` | The public iCal feeds |

## Declarations

- **Widget** `events.upcoming` (*Upcoming events*, `events-calendar.view`, option `limit`).
- **Task** `Tasks/SendReminders.php` (every 5 minutes): one `EventReminder` notification per
  occurrence, only while the user can see the event, never for cancelled events.
- **Notification** `Notifications/EventReminder.php` (links to the event's details).
- **Menu link** `events.calendar` (`Menu/CalendarLink.php`): the public calendar, all categories or
  one (`calendar?category=<slug>`).
- **Activity log:** writes and `duplicate` through the Core (`AuditedCrud`); `forbidden_audience`
  (security) when a forbidden group is posted.
- **Command** `calendar:resync <Module>` (`Commands/CalendarResync.php`).
- **Calendar service** `service('calendar')` = `Services/CalendarService.php` (registrar
  `Config/Registrar.php`); menu entry "Calendar subscription" (user menu).
- **Activity log:** `ical_created`, `ical_regenerated`, `ical_revoked`, `ical_filters`,
  `ical_accessed`, `calendar_resync`.
- No backup files, CSP sources, outbound hosts or API resources (the `events` API is session 26).

## Demo data

`php spark modules:demo Events` (Catalan): three categories and eight events around today:
public ones (also on the public site), a weekly private meeting with a skipped date, a monthly
event, an all-day event, a cancelled one, one for the `editor` group and one for
usuari@example.com only (load Auth's demo users first).

## Tests

`vendor/bin/phpunit modules/Events` (base `Tests/EventsTestCase.php`: Auth fixture, declarations
with the Events widget, `event()` writes an event with its audience, `feedTitles()`):
`AudienceTest` (an event only for John, group + user audiences, admins and superadmins, public
site, range validation, the component), `EventsCrudTest` (assignable groups and forged groups,
kept groups, empty audience, list scope with and without `all`, forged posts, duplicate,
sanitised description, recurrence normalised, categories), `RecurrenceTest`,
`RemindersAndWidgetTest`, `EventsModuleTest` (manifest, grants, menu link, migrations down/up,
demo data, disabled module), `IcalTest` (RFC 5545 output checked by `Tests/Support/IcalValidator.php`,
the secret, regeneration and revocation, re-authentication, permission changes, ETag/304, public
feeds, exports, cancelled events, VALARM) and `CalendarApiTest` (publish/unpublish, audiences,
locked events, calendar permissions and policies, disabled modules, providers, toggles, resync,
purge, CodeIgniter events).
