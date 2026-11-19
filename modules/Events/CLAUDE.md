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
- `events-api` (`list`, `show`, session 26): the `events` API resource — every group.
- Grants: admin all; editor events list/show/add/edit/delete/duplicate/export/import, categories
  list/show; every group `events-calendar.*`, `events-api.*`.
- Configuration `Config/Calendar.php` (never `Config/Events.php`; no manifest settings, D-24-05).
  Menu: "Calendar" with "Events" and "Categories". Widget `events.upcoming`. Task
  `Tasks/SendReminders.php`. Notification `Notifications/EventReminder.php`. Link type
  `Menu/CalendarLink.php`.
- Tables: `event_categories`, `event_category_translations`, `events` (+ `allow_reminders`,
  session 25), `event_translations`, `event_audience` (`group_id` **or** `user_id`),
  `event_reminders`, `ical_tokens` (personal feeds, hashed secret), `event_calendar_preferences`
  (calendars hidden per user).
- Session 25: `service('calendar')` (registrar `Config/Registrar.php` → `Services/CalendarService.php`),
  menu "Calendar subscription" (user zone, route `events-subscription`), command
  `calendar:resync`, iCal routes `ical/user/<secret>.ics`, `ical/public/<all|slug>.ics`,
  `…/event/{code}/export.ics`.

## Main pieces

- `Services/EventVisibility.php` (who sees what; the only place the rule lives; admins follow it
  in the calendar), `AssignableGroups.php` (groups a user may target), `Recurrence.php`
  (expansion), `EventRepository.php` (occurrences in a range, details, categories, feed JSON),
  `EventDetails.php`, `CalendarComponent.php` (component options, feed range), `Reminders.php`.
- `Controllers/Events.php` (`events-events`), `Categories.php`, `CalendarView.php` (back office),
  `PublicCalendar.php` (public events only). `Assets/js/calendar.js` + `Assets/css/calendar.css`.
- Calendar API (`docs/calendar.md`): `Services/CalendarService.php` (publish/unpublish,
  purge, resync, CodeIgniter events), `Calendars.php` (declared calendars, permissions, policies,
  per-user toggles), `ProvidedEvents.php` (providers, cached). Visibility of calendars and policies
  is in `EventVisibility` (`calendars()`, `allowedByPolicy()`).
- iCal: `Ical/IcalWriter.php`, `Folding.php`, `VTimezone.php`; `Services/IcalFeeds.php` (secrets,
  feeds, cache), `IcalExport.php`; `Controllers/IcalFeed.php` (feeds, ETag/304),
  `Subscription.php` (screen). `Assets/js/copy.js` (`events/copy`).

## Decisions

D-24-01 (names, URLs, configuration) and D-24-02 (assignable groups) decided by the owner as built; D-24-03 …
D-24-10 accepted (`docs/decisions.md`); D-00-04, D-00-40, D-00-41. Session 25: D-25-01 (iCal URLs and
API names, decided as built by the owner), D-25-02 … D-25-07 accepted.

Session 37 (D-37-01): translated texts use the site's languages and require its default language through `Modules\Core\Content\ContentTranslations` (`locales()`, `crudOptions()`, `defaultText()`…); never write a language code (`tests/unit/I18n/ContentLanguagesTest.php`). Tests `Tests/ContentLocalesTest.php`, `Tests/ContentLocalesApiTest.php` (`Modules\Core\Tests\ContentLocalesTrait`).

## Open tasks

- Done in session 29: `CalendarService::purgeModule()` is now part of the Core's `CalendarPublisher` contract: the Core's Uninstaller calls it for the uninstalled module (unless `--keep-data`); no `Config/Events.php` (D-00-04). (`docs/installing-modules.md`)
- Session 29: the module uninstaller must call `CalendarService::purgeModule(<Module>)`.
- Weekly events whose `recur_days` exclude the start's weekday: iCal counts DTSTART as an
  occurrence, the expansion does not (edge case; the form could normalise it).
- Done in session 26: `api/v1/events` (+ `occurrences`, `calendars`; resource `events-api`, every
  group) and `api/v1/public/events` (`Services/EventsApi.php`, `Controllers/Api/`; D-26-01 decided as built,
  D-26-05). Existing sites: grant `events-api.*` in the matrix after updating.
- kpcrudy: `ajax` for `setRelationNN()` (users field, D-24-04).
- Done in session 35: `CalendarView::moduleLabel()` reads `<Module>.module.name` (the
  administration list answered 500 for events of the Tasks module); feed items carry
  `contrastColor` (black or white, `EventRepository::textColor()`) for WCAG AA on any colour; the
  selected view tab and other-month days use AA colours (`calendar.css`).

## How to test

`vendor/bin/phpunit modules/Events` (base `Tests/EventsTestCase.php`). By hand:
`php spark modules:demo Auth`, `php spark modules:demo Events`, then `backend/calendar` as
usuari@example.com (sees "Entrevista personal") and as superadmin (does not), `backend/events`,
`calendar`. iCal: user menu › "Calendar subscription" › create the address, then
`curl -i <url>` (and again with `-H 'If-None-Match: <etag>'` → 304); `ical/public/all.ics`.
