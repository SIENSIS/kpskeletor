# Events module

**Purpose:** the calendar: events with translations, categories, visibility (public / private /
restricted to groups and users), basic recurrence, the administration with kpcrudy, the calendar
views (back office and public site) with the vendored FullCalendar 7, reminders, the *Upcoming
events* widget and the "Calendar" menu link type. Full description: `docs/events.md`.

## Resources and permissions

- `events-events` (crud with import; extras `all`, `duplicate`): the administration. Without
  `all`, the list and every operation are limited to the events the user sees or created.
- `events-categories` (crud − `import`, trash).
- `events-calendar` (page: `view`): calendar, feed, details, reminders — every group.
- Grants: admin all; editor events list/show/add/edit/delete/duplicate/export/import, categories
  list/show; every group `events-calendar.*`.
- Configuration `Config/Calendar.php` (never `Config/Events.php`; no manifest settings, D-24-05).
  Menu: "Calendar" with "Events" and "Categories". Widget `events.upcoming`. Task
  `Tasks/SendReminders.php`. Notification `Notifications/EventReminder.php`. Link type
  `Menu/CalendarLink.php`.
- Tables: `event_categories`, `event_category_translations`, `events`, `event_translations`,
  `event_audience` (`group_id` **or** `user_id`), `event_reminders` (one migration each).

## Main pieces

- `Services/EventVisibility.php` (who sees what; the only place the rule lives; admins follow it
  in the calendar), `AssignableGroups.php` (groups a user may target), `Recurrence.php`
  (expansion), `EventRepository.php` (occurrences in a range, details, categories, feed JSON),
  `EventDetails.php`, `CalendarComponent.php` (component options, feed range), `Reminders.php`.
- `Controllers/Events.php` (`events-events`), `Categories.php`, `CalendarView.php` (back office),
  `PublicCalendar.php` (public events only). `Assets/js/calendar.js` + `Assets/css/calendar.css`.

## Decisions

D-24-01 (names, URLs, configuration) and D-24-02 (assignable groups) open (decide); D-24-03 …
D-24-10 accepted (`docs/decisions.md`); D-00-04, D-00-40, D-00-41.

## Open tasks

- Session 25: iCal export and feeds, the calendar API (`$calendars`, `$calendarProviders`,
  `publish()`/`unpublish()`, locked published events with a "Managed by" badge — the columns and
  the edit lock already exist), calendars toggled per user, `calendar:resync`.
- Session 26: the `events` and `public/events` API endpoints (use `EventVisibility` with kpapy's
  `where(Closure)`).
- kpcrudy: `ajax` for `setRelationNN()` (users field, D-24-04).

## How to test

`vendor/bin/phpunit modules/Events` (base `Tests/EventsTestCase.php`). By hand:
`php spark modules:demo Auth`, `php spark modules:demo Events`, then `backend/calendar` as
usuari@example.com (sees "Entrevista personal") and as superadmin (does not), `backend/events`,
`calendar`.
