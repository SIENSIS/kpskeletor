# Session 25 — Events module: iCal feeds and calendar API

Branch: `claude/awesome-feynman-p4kb32` · Pull request: [SIENSIS/kpskeletor#33](https://github.com/SIENSIS/kpskeletor/pull/33) · Date: 2026-11-15

## What was built

- **iCal (RFC 5545)**
  - **One-off export** of an event: "Add to my calendar (.ics)" on the back-office and public
    details pages (a static copy).
  - **Personal feed** per user (`ical/user/<secret>.ics`): every event the user can see *now*
    (public, private, their audiences, other modules' calendars and providers), with their
    reminders as `VALARM`, filterable by calendars and categories, in the user's own language.
    Screen **"Calendar subscription"** (user menu): create the address (shown **once**, with
    "Copy" and a `webcal://` link; re-authentication required), regenerate (the old URL stops
    working at once), revoke, last access, instructions for Synology, Google, Outlook, Apple and
    Thunderbird.
  - **Public feeds** (`ical/public/all.ics`, `ical/public/<category>.ics`): public events only.
  - Robust output: stable `UID`, `SEQUENCE`, `LAST-MODIFIED`, `VTIMEZONE`, `RRULE`/`EXDATE`
    (not expanded), all-day dates, `STATUS:CANCELLED` for cancelled events and for deleted ones
    during 30 days, escaping and folding, refresh hints, `ETag`/`Last-Modified` with `304`,
    limited range, short cache, rate limit per IP and per secret.
- **Calendar API for other modules** (`docs/calendar-api.md`): `service('calendar')->publish()` /
  `unpublish()` (push, idempotent, locked in the administration with "Managed by …" and "Edit
  in …"), `CalendarProvider`s (pull, cached), calendars declared in the manifest with an optional
  permission, `EventAccessPolicy` and `CalendarSource`, per-user calendar toggles in the calendar
  view, events of disabled modules hidden, `php spark calendar:resync <Module>`, CodeIgniter
  events `calendar.created|updated|deleted`.
- **Examples:** News **notices with an event date** go to the *Notices* calendar with the
  notice's audience (push); Users' **Birthdays** provider (pull, off by default, day and month
  only).

## How it was built

- **Core contracts** (`modules/Core/Calendar/`): `CalendarEvent` (validated value object),
  `CalendarPublisher` + `NullCalendar`, `CalendarProvider`, `EventAccessPolicy`,
  `CalendarSource`; `Config\Calendars::$publisher` and `Services::calendar()`; the manifest
  validator checks `$calendars` (`policy`/`source` implement their interfaces, unknown keys
  refused) and that `$calendarProviders` implement `CalendarProvider`. No module is hard-coded
  in the Core: Events plugs its service in through `modules/Events/Config/Registrar.php`.
- **Events services:** `CalendarService` (publish/unpublish/purgeModule/resync, events, cache
  invalidation), `Calendars` (declared calendars of enabled modules + the own `events`
  calendar, permissions, policies, toggles), `ProvidedEvents` (providers per user and range,
  cached, own calendars only, failures skipped), `IcalFeeds` (secrets, personal/public feeds,
  filters, cache), `IcalExport`; `EventVisibility` gained `calendars()` and `allowedByPolicy()`,
  `EventRepository` calendar/category filters and `deletedSince()`.
- **iCal writer:** `modules/Events/Ical/IcalWriter.php`, `Folding.php`, `VTimezone.php` (built from
  PHP's time zone database).
- **Controllers:** `IcalFeed` (feeds, 304), `Subscription` (screen), `CalendarView::export()` and
  `calendars()`, `PublicCalendar::export()`; the administration column "Source" shows the badge
  and link; CRUD writes fire the calendar events and bump `sequence` on delete/restore.
- **Tables:** `ical_tokens`, `event_calendar_preferences`, column `events.allow_reminders`;
  News `news.event_starts_at`, `event_ends_at`, `event_all_day` (one migration each, `down()`).
- **Routes:** `GET backend/calendar/event/{code}/export.ics`, `POST backend/calendar/calendars`,
  `backend/calendar/subscription` (+ `POST generate` with `reauth`, `revoke`, `filters`), all
  `can:events-calendar.view`; public `calendar/event/{code}/export.ics`, `ical/user/(:segment)`,
  `ical/public/(:segment)` (`ratelimit:feeds`). `permissions:check`: 127 permissions, 229 routes.
- **No new permissions.** New setting `Users.birthdaysCalendar`. New command `calendar:resync`.
  Configuration `Config\Calendar`: `icalPastDays`, `icalFutureDays`, `icalGraceDays`,
  `icalRefresh`, `icalCacheSeconds`, `providerCacheSeconds`, `publicFeeds`.
- **Front end:** `events/copy` (copy button), calendar toggles saved by `events/calendar` with
  `core/http.js` (a form with a submit button without JavaScript). No inline code.
- **Docs:** `docs/calendar-api.md` (new), `docs/events.md`, `docs/news.md`, `docs/users.md`,
  `docs/commands.md`, `docs/decisions.md`, `CLAUDE.md` (calendar convention), module `CLAUDE.md`s.

## Decisions and deviations

- **D-25-01 (decided as built by the owner, Decisions 25):** iCal URLs, API names, manifest keys and the subscription screen —
  built as proposed so they can still be renamed. The export URL deviates from the plan
  (`events/{code}/export.ics`) to sit next to the details pages of D-24-01.
- D-25-02 (secret shown once, hashed, re-authentication), D-25-03 (deterministic documents,
  ETag, cache), D-25-04 (disabled modules, calendar permissions, policies), D-25-05 (publish
  contract, purge on uninstall), D-25-06 (new columns), D-25-07 (birthdays off by default):
  accepted proposals.
- Calendar clients send no language: the personal feed uses the user's profile language
  (`RecipientLocale`), public feeds the site's default.

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --db=sqlite --demo --email=you@example.com
php spark serve
```

- As **usuari@example.com**: `backend/calendar` shows the toggles *Events / Notices / Birthdays*
  and the notice "Tall del servei dissabte" (published by News); its details show "Managed by
  News", "Edit in News" and the `.ics` export.
- User menu › **Calendar subscription** › "Create my feed address", then
  `curl -i <url>` and `curl -i -H 'If-None-Match: <etag>' <url>` (304).
- `curl http://localhost:8080/ical/public/all.ics` (public events only).
- As the superadmin: Settings › Users › "Show the birthdays calendar"; birthdays come from the
  profiles' date of birth. `backend/events` shows News' notices locked.
- `php spark calendar:resync News`.
- Manual check with a real client: `docs/events.md` › "iCal" (Thunderbird / Google Calendar).
  The sandbox of this session is not reachable from outside, so no real client was used; the
  feeds were validated with the tests' RFC 5545 checker (below).

Excerpt of a personal feed (demo data, site time zone UTC):

```
BEGIN:VEVENT
UID:event-GiisYgvNJl_YeQOAqg@localhost
DTSTAMP:20261113T110157Z
LAST-MODIFIED:20261113T110157Z
SEQUENCE:0
DTSTART;TZID=UTC:20261027T090000
DTEND;TZID=UTC:20261027T100000
RRULE:FREQ=WEEKLY;BYDAY=TU;COUNT=20
EXDATE;TZID=UTC:20261124T090000
SUMMARY:Reunió de coordinació
LOCATION:Sala de reunions
URL:http://localhost:8080/index.php/backend/calendar/event/GiisYgvNJl_YeQOA
 qg
STATUS:CONFIRMED
END:VEVENT
```

Against the demo site: `ical/public/all.ics` → 200 `text/calendar`, `ETag` + `Last-Modified`;
the same request with `If-None-Match` → **304**; an unknown secret → **404**; the public feed (3
events) and the personal feed (10 events) pass the RFC 5545 checker with **0 problems**.

## Tests and checks

New tests (34 + 1 updated):

- `modules/Events/Tests/IcalTest.php` (13): folding/escaping; a document with recurrences,
  exceptions, all-day events and a Europe/Madrid `VTIMEZONE`; the secret (shown once, hashed,
  regenerated → old URL 404, revoked, logged); re-authentication; permission changes reflected
  (group removed, user banned); unknown secrets; ETag/304; cancelled and deleted events (grace
  period); VALARM and filters; public feeds; one-off exports; the subscription page; the user's
  language. Every document goes through `Tests/Support/IcalValidator.php` (CRLF, 75-octet
  folding without splitting UTF-8, nesting, required properties, value formats, `TZID`
  references, unique UIDs).
- `modules/Events/Tests/CalendarApiTest.php` (13): the service; publish idempotency and
  `SEQUENCE`; audiences of groups and users; another module's calendar refused; locked events
  (forged edit/delete refused); calendar permissions and access policies; disabled module;
  providers per user and own calendars only, public site excluded; provider cache; calendar
  toggles; resync (also the command); purge and CodeIgniter events; invalid events.
- `modules/News/Tests/NoticeCalendarTest.php` (5), `modules/Users/Tests/BirthdaysProviderTest.php` (3).
- `EventsModuleTest` updated (9 migrations).

Results:

- `vendor/bin/phpunit`: **OK (1040 tests, 6112 assertions)** (no existing test needed changes except `EventsModuleTest`'s migration count).
- `vendor/bin/phpstan analyse`: **[OK] No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: **0 files** to fix.
- `php spark permissions:check`: **Permissions OK: 127 permission(s) in the catalogue, 229 route(s) checked.**
- `php spark csp:check`: **No inline scripts, handlers or styles in the project views.**
- `php spark modules:sync`: nothing to do; `php spark assets:publish --check`: up to date.
- `php spark security:check`: does not exist yet (session 30).
- Screenshot script: no CSP violation of the application, no page error, no horizontal scroll.

## Screenshots

Taken with `deploy-status/25-events-ical-api/screenshots.mjs` (Playwright, device scale factor 2)
against `php spark serve` after a fresh `app:install --demo` (two demo users given a date of
birth). The flame at the bottom right is CodeIgniter's development toolbar.

| File | Caption |
|---|---|
| `screenshots/01-settings-birthdays.png` | Settings › Users: the new "Show the birthdays calendar" setting (off by default) |
| `screenshots/02-calendar-calendars-toggles.png` | usuari@'s calendar: toggles Events / Notices / Birthdays, a News notice (red) and a birthday (pink) |
| `screenshots/03-published-event-details.png` | A notice published by News: "Managed by News", `.ics` export, "Edit in News" |
| `screenshots/04-subscription-empty.png` | Calendar subscription before creating the feed |
| `screenshots/05-admin-managed-by-news.png` | The administration: News' notices are locked (only "show"), with the badge and link |
| `screenshots/06-news-notice-event-date.png` | The News form: the notice's event date fields |
| `screenshots/07-subscription-new-address.png` | The new address, shown once, with Copy and `webcal://` (full page; the sticky save bar of the filters form overlaps its help text only in this full-page capture) |
| `screenshots/08-subscription-active.png` | The active feed afterwards: state, created, last access, regenerate/revoke (the address is no longer shown) |
| `screenshots/09-subscription-phone-dark.png` | Phone width, dark mode |

## Known issues

- The Birthdays calendar is offered in the toggles even while its setting is off (it is empty).
- A weekly event whose chosen days exclude the start's weekday: iCal counts `DTSTART` as an
  occurrence, the expansion does not (edge case).
- Permission changes reach a cached feed within `icalCacheSeconds` (5 minutes).
- Composer: known issue 04 still needs the workaround (`phpstan/phpstan`'s zip seeded into the
  cache, then `env -u COMPOSER_AUTH composer install --prefer-source`).

## What the next session must know

- Session 26 (`events` API): filter with `EventVisibility::apply()` (now includes calendar
  permissions and disabled modules) plus `EventVisibility::allowedByPolicy()` per row; reuse
  `EventRepository::occurrences()` and `ProvidedEvents`.
- Session 29: the module uninstaller must call `CalendarService::purgeModule(<Module>)`.
- D-25-01 was decided as built (Decisions 25): the URLs and API names are final.
