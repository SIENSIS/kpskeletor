# Users module

**Purpose:** user and group administration with the atomic permission matrix and
impersonation. Core module (cannot be disabled). Full description: `docs/users.md`.

## Resources and permissions

- `users-users` (crud, no delete/export/import/trash): `list`, `show`, `add`, `edit` and the row
  operations `ban`, `forcereset`, `reset2fa`, `logout`, `impersonate`, `access`, and the extra
  `birthdate` (dates of birth in the record and the administrative birthdays calendar).
- `users-birthdays` (page): `view` — may see the shared "Birthdays" calendar (every reserved group).
- `users-groups` (crud over the Core `groups` table, no export/import/trash).
- `users-matrix` (page): `view`, `edit`.
- Default grants: `admin` everything except `users-users.impersonate`.
- Menu (`settings` zone): Users, Groups, Permissions.
- Setting `Users.impersonationMinutes` (30, 1–120, Settings › Security › Impersonation).
- Tables of its own: `group_birthday_audiences` (D-25-08; created with defaults),
  `group_birthday_greetings`, `birthday_greetings_sent` (D-25-09); no demo data (the demo users
  are Auth's), no outbound hosts or CSP sources. Asset: `Assets/js/matrix.js` (`users/matrix`).
- Widget `users.summary` (`Widgets/UsersSummary.php`, async, `users-users.list`): active accounts,
  users online (option `onlineMinutes`), active accounts without 2FA.

## Main pieces

- `Controllers/Users.php` (kpcrudy, Shield hooks, row operations), `Controllers/Groups.php`
  (kpcrudy + `GroupRegistry` hooks, re-authentication for changes), `Controllers/Access.php`,
  `Controllers/Matrix.php`.
- `Services/UserAccounts.php` (Shield identities, bans, forced resets, 2FA reset, logout
  everywhere), `Services/UserAccess.php` (groups through Auth's `GroupRules`, permissions through
  `GrantManager`, one transaction), `Services/PermissionMatrix.php` (blocks, atomic save).
- Uses: Core `GroupRegistry`, `EscalationGuard::canManageUser()`, `GrantManager`; Auth
  `Impersonation`, `TwoFactor`, `SessionTracker`, `GroupRules`.

## Decisions

D-15-01 (names and URLs) decided by the owner as proposed; D-15-02 … D-15-05 accepted (`docs/decisions.md`).

## Calendar (session 25)

Two pull calendars (D-25-07, D-25-08; `docs/users.md`, "Birthdays calendar"): `users.birthdays`
(`Calendar/BirthdaysProvider.php`: setting `Users.birthdaysCalendar`, off by default + the owner's
consent `user_profiles.share_birthday` + `users-birthdays.view` + table `group_birthday_audiences`
edited in Groups "Sees the birthdays of") and `users.birthdaysadmin`
(`Calendar/AdminBirthdaysProvider.php`, every date of birth, `users-users.birthdate`, hidden by
default). Shared code `Calendar/Birthdays.php`. The record shows the date of birth with
`users-users.birthdate` (with the age as a tooltip). Tests `Tests/BirthdaysProviderTest.php`.

Birthday greetings (D-25-09): task `Tasks/SendBirthdayGreetings.php` (daily at
`Users.birthdayGreetingTime`), notification `Notifications/BirthdayGreeting.php`, service
`Services/BirthdayGreetings.php`; groups chosen with the switch of the Groups form
(`group_birthday_greetings`), once a year (`birthday_greetings_sent`); settings
`birthdayGreetingSharedOnly` (on), `birthdayGreetingCa|Es|En`. Tests `Tests/BirthdayGreetingsTest.php`.

## Open tasks

- Search by email and filters by group or 2FA in the users list: they live in other tables and
  kpcrudy only searches/filters the CRUD table's columns (request in `docs/library-requests.md`;
  today: search by user name, filters active, banned and last activity).
- Custom groups created after a module's first sync do not receive that module's later default
  grants for "every group" (only the grants shared by the reserved groups at creation time).
- Session 23: avatars in the list.

## How to test

`vendor/bin/phpunit modules/Users` (base `Tests/UsersTestCase.php`: Auth fixture, settings
declarations, fixed clock, masked ids, `assertNotFound()`).
