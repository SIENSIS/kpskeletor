# Users module

**Purpose:** user and group administration with the atomic permission matrix and
impersonation. Core module (cannot be disabled). Full description: `docs/users.md`.

## Resources and permissions

- `users-users` (crud, no delete/export/import/trash): `list`, `show`, `add`, `edit` and the row
  operations `ban`, `forcereset`, `reset2fa`, `logout`, `impersonate`, `access`.
- `users-groups` (crud over the Core `groups` table, no export/import/trash).
- `users-matrix` (page): `view`, `edit`.
- Default grants: `admin` everything except `users-users.impersonate`.
- Menu (`settings` zone): Users, Groups, Permissions.
- Setting `Users.impersonationMinutes` (30, 1–120, Settings › Security › Impersonation).
- No tables of its own (Shield's tables and the Core `groups` table), no demo data (the demo users
  are Auth's), no outbound hosts or CSP sources. Asset: `Assets/js/matrix.js` (`users/matrix`).

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

D-15-01 (names and URLs, decide), D-15-02 … D-15-05 (`docs/decisions.md`).

## Open tasks

- Search by email in the users list (the email lives in `auth_identities`; kpcrudy searches table
  columns only).
- Custom groups created after a module's first sync do not receive that module's later default
  grants for "every group" (only the grants shared by the reserved groups at creation time).
- Session 16: a users widget for the dashboard; session 23: avatars in the list.

## How to test

`vendor/bin/phpunit modules/Users` (base `Tests/UsersTestCase.php`: Auth fixture, settings
declarations, fixed clock, masked ids, `assertNotFound()`).
