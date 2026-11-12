# Session 24 — Events module: calendar, administration and audiences

Branch: `claude/gallant-curie-rmfn7k` · Pull request: [SIENSIS/kpskeletor#32](https://github.com/SIENSIS/kpskeletor/pull/32) · Date: 2026-11-12

## What was built

- **Events module** (`modules/Events`): events with Catalan/Spanish/English title and rich-text
  description, start/end (or all day), location, organiser, category, colour, link, status
  (confirmed, tentative, cancelled) and **visibility**: `public` (also the public site),
  `private` (any signed-in user) or `restricted` (an **audience** of groups and/or users).
- **Restricted audiences that bind administrators too:** an event "only for John" appears only in
  John's calendar, details, widget and reminders — not in every admin's or superadmin's agenda.
  In the administration, `events-events.all` sees every event (filter "visible to me / all");
  without it, every operation is limited to the user's audience or the events they created.
- **Assignable groups:** the groups field offers only the groups the user may target (an editor
  cannot create an event only for superadmins or admins); a forged group is refused with an error
  and a `security` log entry.
- **Recurrence** (expanded on query, never stored): daily, weekly on chosen days, monthly,
  yearly; every N periods; until a date or N times; skipped dates; local time kept across
  daylight saving.
- **Administration** with kpcrudy: list with filters (category, start date, visibility, status,
  groups), search, CSV export and import (one column per language), trash, operation
  **Duplicate**, categories with colour and slug.
- **Calendar view** in the back office (`backend/calendar`) and on the public site (`calendar`,
  public events only): month, week, day and list views (list on phones), category filter, light
  and dark mode, with the vendored **FullCalendar 7.1.3** (MIT) under the strict CSP; JSON feeds
  by date range; click an event to open its details.
- **Reminders:** a user picks "remind me" (1 h, 15 min, 1 day, 1 week) in the event details; the
  task *Send event reminders* (every 5 minutes) notifies them once per occurrence while they can
  still see the event.
- **Widget** *Upcoming events*, **menu link type** "Calendar" (all categories or one), Catalan
  **demo data**.
- kpcrudy upgraded to **1.3.0** (`where(Closure)` for the audience scope, `attrs` of translated
  fields for the description editor), as `docs/library-requests.md` asked before this session.

## How it was built

- **Tables** (one migration each, `modules/Events/Database/Migrations/`): `event_categories`,
  `event_category_translations`, `events` (incl. `recur_*`, `sequence`, `created_by`, and the
  source columns for session 25), `event_translations`, `event_audience` (`group_id` **or**
  `user_id`, D-24-03), `event_reminders` (`last_sent_for`).
- **Services** (`modules/Events/Services/`): `EventVisibility` (the only place the visibility
  rule lives; also kpcrudy's `where(Closure)` scope), `AssignableGroups` (D-24-02),
  `Recurrence`, `EventRepository` (occurrences in a range with texts and colours, details,
  categories, feed JSON), `EventDetails`, `CalendarComponent` (component options, feed range),
  `Reminders`.
- **Controllers:** `Events` (`events-events`: translations with the Core's rich-text editor,
  `setRelationNN()` for groups and users on `event_audience`, hooks that refuse forbidden groups
  and empty audiences, normalise recurrence, bump `sequence`; `duplicate` operation; published
  events of other modules locked), `Categories`, `CalendarView` (back office: page, feed,
  details, reminder), `PublicCalendar` (public events only).
- **Front end:** `Assets/js/calendar.js` (`events/calendar` component: loads the library's CSS and
  scripts on demand, feed via `core/http.js`, category filter), `Assets/css/calendar.css` (the
  classic theme's variables mapped to Bootstrap's). Library in
  `public/assets/vendor/fullcalendar/` with checksums in `vendor.json`.
- **Routes:** `backend/events` (+ `duplicate/{code}`), `backend/events/categories`,
  `backend/calendar`, `backend/calendar/feed`, `backend/calendar/event/{code}`,
  `POST backend/calendar/event/{code}/reminder` (all `can:`); public `calendar`, `calendar/feed`
  (`ratelimit:feeds`), `calendar/event/{code}`.
- **Permissions:** `events-events.{list,show,add,edit,delete,export,import,trash,restore,purge,all,duplicate}`,
  `events-categories.{list,show,add,edit,delete,export}`, `events-calendar.view`.
- **Declarations:** widget `events.upcoming`, task `SendReminders`, notification
  `EventReminder`, menu link `events.calendar`; configuration class `Config/Calendar.php`.
- **Docs:** `docs/events.md` (new), `modules/Events/CLAUDE.md`, `docs/frontend.md`,
  `docs/menus.md`, `docs/widgets.md`, `docs/library-requests.md`, `docs/decisions.md`, `CLAUDE.md`
  (kpcrudy `^1.3`).

## Decisions and deviations

- **D-24-01 (decided as built, Decisions 24):** permission names, URLs and the configuration class, built as
  proposed so they can still be renamed.
- **D-24-02 (decided as built, Decisions 24):** assignable groups = superadmins all; others never `superadmin`,
  their own groups or groups whose every permission they hold (the Core's escalation rule).
- **D-24-03:** `event_audience` has `group_id` and `user_id` instead of `type` + `id` (kpcrudy's
  N-N rewrite would otherwise delete user rows; foreign keys).
- **D-24-04:** the audience's users field is a multiple select (kpcrudy has `ajax` only for 1-N
  relations); library request recorded.
- **D-24-05:** no manifest settings (`setting('Events.*')` would autoload CodeIgniter's
  `app/Config/Events.php`); configuration in `Config\Calendar`.
- **D-24-06:** CSV import of translations with kpcrudy's own columns (`title:ca`), not a
  `callbackImport()` (D-00-40 predates kpcrudy 1.1).
- **D-24-07:** administration scope without `all` = audience **or** created by the user.
- **D-24-08:** FullCalendar 7.1.3 (CSS files, CSSOM only; v6 injected `<style>`); the feed sends the
  site's wall-clock times and the calendar runs in UTC mode.
- **D-24-09:** recurrence columns and text exceptions; counts include skipped dates (iCal).
- **D-24-10:** reminders per user and event, per occurrence, access checked when sending.
- `organiser` is a free text (name), not a user (the specification lists "organiser" without a
  type); `created_by` records the creator.
- Out of scope, left for session 25: iCal, the calendar API, per-user calendar toggles, the
  "Managed by" badge (the edit lock for `source_module` events already exists).

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --db=sqlite --demo --email=you@example.com
php spark serve
```

(Demo users' passwords are printed once by the installer.)

- `http://localhost:8080/backend/calendar` as **usuari@example.com**: "Entrevista personal"
  (tomorrow 11:00) is there; open it and set a reminder.
- The same page as **superadmin@example.com** (or the installer's superadmin): the event is not
  there; "Gestiona els esdeveniments" → `backend/events` lists it (`events-events.all`).
- As **editor@example.com**: "Revisió de continguts del web" (editors only) appears;
  `backend/events/add` offers only the groups Editor and User.
- `http://localhost:8080/calendar` signed out: public events only.
- `php spark schedule:run` sends due reminders.

## Tests and checks

New tests (38, in `modules/Events/Tests/`):

- `AudienceTest` (7): an event only for John (John yes; another user, an admin and a superadmin
  no — feed and details 404); group + user audience; the rule itself (guests, public site,
  superadmins, empty audience, deleted); the public calendar shows public events only even when
  signed in; permission and range validation of the feeds; the component without inline code;
  category filter, colour and cancelled class.
- `EventsCrudTest` (10): an editor cannot target `superadmin` or `admin` (offered options, forged
  posts, mixed lists, non-numeric values; security entry); allowed groups and users saved, user
  rows kept when groups change, `sequence` bumped, audience cleared when not restricted; groups
  set by an admin kept when an editor edits; an edit leaving the event outside the editor's scope
  is rolled back; restricted needs an audience; list scope without `all` (and every operation
  outside it refused); `all` with "visible to me"; forged posts of a read-only user and of a user
  without any permission; duplicate; sanitised description and normalised recurrence;
  categories.
- `RecurrenceTest` (12), `RemindersAndWidgetTest` (3), `EventsModuleTest` (6: manifest, grants,
  menu link, migrations down/up, demo data load/remove, disabled module 404).

Results:

- `vendor/bin/phpunit modules/Events`: **OK (38 tests, 341 assertions)**.
- `vendor/bin/phpunit` (whole suite): **OK (1005 tests, 5801 assertions)** (five cross-module counting tests updated for the new module: catalogue size, CRUD count, Shield matrix, widget/task registry, menu link types).
- `vendor/bin/phpstan analyse`: **[OK] No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: **0 files** to fix.
- `php spark permissions:check`: **Permissions OK: 127 permission(s) in the catalogue, 220 route(s) checked.**
- `php spark csp:check`: **No inline scripts, handlers or styles in the project views.**
- `php spark modules:sync`: nothing to do; `php spark assets:publish --check`: up to date.
- `php spark security:check`: does not exist yet (session 30).
- Screenshot script: no CSP violation of the application, no page error, no horizontal scroll.

## Screenshots

Taken with `deploy-status/24-events-core/screenshots.mjs` (Playwright, device scale factor 2)
against `php spark serve` after a fresh `app:install --demo`. The flame at the bottom right is
CodeIgniter's development toolbar (never in production); its own inline styles are reported by
the browser and ignored by the script.

| File | Caption |
|---|---|
| `screenshots/01-calendar-month-user.png` | usuari@: month view with "Entrevista personal" (an event only for them) on the 13th |
| `screenshots/02-calendar-week-superadmin.png` | The superadmin's week view: the event only for usuari@ is **not** there |
| `screenshots/03-calendar-phone-dark.png` | Phone width, dark mode: the calendar starts in the list view |
| `screenshots/04-event-details-reminder.png` | Details of the restricted event and the reminder saved ("1 hora abans") |
| `screenshots/05-admin-list.png` | Administration list (superadmin, `events-events.all`): every event, badges, actions incl. Duplicate |
| `screenshots/06-editor-form-assignable-groups.png` | editor@'s form, visibility "Restringit": only the groups Editor and User are offered |
| `screenshots/07-public-calendar.png` | Public calendar (signed out): public events only |
| `screenshots/08-dashboard-upcoming-widget.png` | The *Upcoming events* widget on usuari@'s dashboard |
| `screenshots/09-calendar-dark-editor.png` | Dark mode, desktop: editor@ sees the editors-only "Revisió de continguts del web" |

## Known issues

- The audience's users field is a plain multiple select (no AJAX search) until kpcrudy supports
  `ajax` in `setRelationNN()` (D-24-04).
- Events settings cannot be edited in Settings (no manifest settings, D-24-05); `.env` or
  `setting('Calendar.*')`.
- Long event titles are cut in the narrow list view on phones (the library's layout).
- Composer in this environment: `COMPOSER_AUTH` is rejected; installing needed
  `env -u COMPOSER_AUTH composer install --prefer-source` and `phpstan/phpstan`'s zip seeded into
  Composer's cache (cache key = SHA-1 of the dist URL) — known issue 04.

## Notes for the next session

- Session 25 (iCal + calendar API): `events.sequence` grows on every change; `source_module`,
  `source_key`, `source_url` and `calendar` exist; edit/delete/duplicate are already refused for
  rows with `source_module` (add the "Managed by" badge and the "Edit in" link); write `RRULE`
  from `recur_freq`/`recur_interval`/`recur_days`/`recur_until`/`recur_count` and `EXDATE` from
  `recur_exceptions` (count semantics are iCal's, D-24-09); always filter with
  `EventVisibility::apply()`; reminders can become `VALARM` (`event_reminders`).
- Session 26 (`events` API): use `EventVisibility::audience()` with kpapy's `where(Closure)` and
  `EventRepository::occurrences()` for ranges.
- D-24-01 and D-24-02 were decided by the owner as built (Decisions 24).
