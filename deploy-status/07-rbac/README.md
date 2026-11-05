# Session 07 — Central RBAC

Branch: `claude/tender-wright-gmq4ck` (assigned by the environment) · Pull request: [SIENSIS/kpskeletor#11](https://github.com/SIENSIS/kpskeletor/pull/11) "Session 07: Central RBAC" · Date: 2026-11-05

## What was built

- **One decision point for permissions** (`Authorization`): for the logged-in user or any user
  passed explicitly (feeds, tasks, CLI). Guests hold nothing, superadmins hold everything in the
  catalogue, and a permission outside the catalogue of the enabled modules is refused to everybody.
- **Shield's permissions and group matrix are generated**, never written by hand: computed from
  the permission catalogue and `group_permissions` and stored with `setting('AuthGroups.…')` after
  every `modules:sync`, module enable/disable and grant change. The interim matrix of
  `Config\AuthGroups` is gone (default deny).
- **Grant changes** (`GrantManager`): atomic group saves, implied permissions added
  (`edit` → `list`, `show`…), only catalogue names and matching wildcards stored, individual
  user permissions, sessions of the affected users regenerated.
- **Escalation rules** (`EscalationGuard`, used by Auth's `GroupRules`): nobody edits their own
  groups or permissions, grant only what you hold, reserved groups, last superadmin, plus the gaps
  around wildcards and group membership (D-07-04).
- **`CrudPermissions`**: the single resolver for every kpcrudy CRUD and kpapy resource
  (`search`/`filters`/`bulk` → `list`), failing loudly outside production when a CRUD asks
  about an operation the manifest does not declare.
- **`can:` filter with any/all** (`can:a,b` / `can:all,a,b`), the **`backend` filter chain**
  covering all of `backend/*`, the `can()` / `can_all()` view helpers.
- **Default deny on routes:** `RoutesSecurityTest` fails the suite if a `backend/` route has no
  `can:` (or names an unknown permission) or an `api/v1/` route lacks kpapy's filter.
- Commands **`permissions:list`** and **`permissions:check`**.

## How it was built

### Design

- `PermissionCatalogue` (session 02) stores the catalogue and grants; it gained `allGrants()` and
  `replaceGrants()`. `enabledNames()` (cached, joined with `modules.enabled`) is what
  `Authorization` and `ShieldMatrix` treat as "existing".
- `ShieldMatrix::compute()` → `permissions` = enabled catalogue (name → description key),
  `matrix` = per group the stored grants that give some enabled permission (atomic or Shield
  wildcard, matched with Shield's `PermissionMatcher`). `write()` stores both; `inSync()` is what
  tests and `permissions:check` compare. Grants of a disabled module stay in the table and return.
- `Authorization::for(?User)` → `can(...)` (any), `canAll(...)`; `Authorization::current()` for the
  session; `isSuperadmin()`. The `Can` filter, `BackendController::can()`, the helpers,
  `EscalationGuard` and `CrudPermissions` all go through it.
- `CrudPermissions` (final, no-argument constructor because kpcrudy/kpapy do `new $class()`)
  implements both libraries' `PermissionResolver` with `allows(string, string, ?array, object): bool`.
  User: `forUser($user)` if given, else kpapy `Context::user()` for `Kpapy\Resource`, else the session.
  Outside production it compares `permissionCatalogue()` with the stored catalogue once per
  resource (`UndeclaredPermissionException`); `undeclared()` is public for session 09.
- `GrantManager` validates, completes (`ImpliedPermissions`, also used by `DefaultGrants`),
  checks `EscalationGuard`, saves in a transaction, rewrites the matrix and fires
  `privilegesChanged` (user id) per affected user; Auth listens and calls
  `SessionTracker::privilegesChanged()` (no Auth class named in the Core).
- `RouteSecurityAuditor` walks every (verb, route) of a `RouteCollection` and reports breaches;
  used by `RoutesSecurityTest` and `PermissionAudit` (`permissions:check`).
- The `backend` alias (`app/Config/Filters.php`) = Shield `SessionAuth` → `ForcePasswordReset` →
  `ForceTwoFactor`, applied by pattern to `backend` and `backend/*` (D-07-01).

### Main files

- `modules/Core/Permissions/`: `Authorization.php`, `ShieldMatrix.php`, `GrantManager.php`,
  `EscalationGuard.php`, `EscalationException.php`, `ImpliedPermissions.php`,
  `CrudPermissions.php`, `UndeclaredPermissionException.php`, `RouteSecurityAuditor.php`,
  `PermissionAudit.php`; changed `PermissionCatalogue.php`, `DefaultGrants.php`
- `modules/Core/Filters/Can.php`, `modules/Core/Helpers/can_helper.php`,
  `modules/Core/Controllers/BackendController.php`
- `modules/Core/Commands/PermissionsList.php`, `PermissionsCheck.php`
- `modules/Core/Modules/ModuleSynchronizer.php` (step 9: matrix), `ModuleToggler.php`
- `modules/Auth/Services/GroupRules.php` (delegates), `modules/Auth/Config/Events.php`
- `app/Config/AuthGroups.php` (empty permissions/matrix), `app/Config/Filters.php` (`backend`),
  `app/Config/Autoload.php` (helper `can`)
- Tests: `modules/Core/Tests/{RbacTestCase,RbacFixtureTrait,ShieldMatrixTest,AuthorizationTest,EscalationTest,CrudPermissionsTest,PermissionsCommandsTest}.php`,
  `tests/feature/Security/RoutesSecurityTest.php`
- Docs: `docs/rbac.md` (new), `docs/commands.md`, `docs/auth.md`, `docs/specification.md`, `CLAUDE.md`

### Tables, routes, permissions, settings, events

- No new table: `group_permissions` and `permissions` (session 02), Shield's
  `auth_permissions_users` for individual permissions.
- No new route or permission. New filter alias `backend` (chain); `can:` gains `all`.
- Settings written: `AuthGroups.permissions`, `AuthGroups.matrix` (computed; never edit them).
- Event `privilegesChanged` (int user id).

## Decisions and deviations

- **D-07-01** (accepted): `backend` is an alias of classes applied by pattern to `backend/*`;
  global filters keep the disabled-module check and the idle lock; throttling (08), activity (12)
  and maintenance (28) join later.
- **D-07-02** (accepted): `can:a,b` = any, `can:all,a,b` = all.
- **D-07-03** (accepted): names outside the enabled catalogue are refused to everybody, superadmins
  included; `can:` naming such a permission fails `RoutesSecurityTest`.
- **D-07-04** (accepted): `GrantManager` + extra escalation rules (wildcards, group membership,
  superadmin targets, own group's grants); `privilegesChanged` event.
- **D-07-05** (accepted): `CrudPermissions` final with `forUser()`; the kpcrudy/kpapy config files
  that select it (with `denyByDefault`) are created by sessions 09/10; the development check also
  runs in `testing`; `permissions:check` diagnostics in English.
- **Deviation:** grant and permission changes go to the application log (activity log: session 12).
- **Deviation:** `permissions:check` does not yet instantiate CRUD controllers (none exist);
  session 09 adds that through `CrudPermissions::undeclared()`.

## How to try it

```bash
cp env .env    # CI_ENVIRONMENT = development, database.default.DBDriver = SQLite3, database = database.db
php spark key:generate
php spark modules:sync
php spark modules:demo Auth        # answer y
php spark permissions:list
php spark permissions:list --group=admin
php spark permissions:check
php spark serve                    # http://localhost:8080/login, usuari@example.com / Demo-kpskeletor-2026
```

Try a breach: add `$routes->get('backend/reports', '…');` to any module's `Config/Routes.php`;
`permissions:check` exits with 1 and `vendor/bin/phpunit tests/feature/Security` fails.

## Tests and checks

```text
$ vendor/bin/phpunit
OK (335 tests, 1648 assertions)          # 292 before this session

$ vendor/bin/phpstan analyse
[OK] No errors

$ vendor/bin/php-cs-fixer fix --dry-run --diff
Found 0 of 279 files that can be fixed

$ php spark csp:check
No inline scripts, handlers or styles in the project views.

$ php spark permissions:check
Permissions OK: 12 permission(s) in the catalogue, 43 route(s) checked.
```

New tests (43):

- `ShieldMatrixTest` (6): config holds no permissions; **settings agree with `group_permissions`**;
  a grant change rewrites the matrix; a stale matrix is detected; **a disabled module leaves the
  matrix and comes back**; **`news-posts.*` does not match `news-postsx.edit`**.
- `AuthorizationTest` (8): guests; default deny; **superadmin bypass, but not for unknown or
  disabled permissions**; any/all; individual user permissions (implied added, revoke); **`can`
  filter any/all**; 403 on a real back-office route; guests redirected to the login.
- `EscalationTest` (13): **implied permissions on save** (also default grants); only catalogue
  names and matching wildcards (nothing saved on error); unknown group; **grant only what you hold**
  (atomic and wildcard); **nobody changes their own group's grants / own permissions**; user
  permissions follow the same rules; no wildcards for users; group membership rules; **reserved
  groups and the last superadmin**; `privilegesChanged` for members.
- `CrudPermissionsTest` (8): both interfaces with an `object` parameter; **search/filters/bulk →
  list**; a real kpcrudy `Crud` with the session user; record rules still restrict; superadmin and
  guests; `forUser()` never reads the session; **kpapy `Resource` uses `Context::user()`**;
  **undeclared operations throw outside production**.
- `PermissionsCommandsTest` (4): check passes; stale matrix, unknown grants, incomplete sets;
  missing stored permissions; `permissions:list` with `--module`/`--group`.
- `RoutesSecurityTest` (4): **every application route follows the rules**; the `backend` chain
  covers `backend/*`; **a back-office route without `can:` fails**; API rules (`kpapy`,
  `:optional`, `:hmac`, `:login` only on `auth/token*`, `:public` only GET).
- Updated: `GroupRulesTest` (superadmin bypass uses a catalogue permission; an unknown one is
  refused), `AuthConfigTest` (`backend` chain); `AuthTestCase` installs the catalogue and matrix.

## Screenshots

This session adds no screen (the permission matrix screen is session 15). Console output instead:

```text
$ php spark modules:sync          # fresh SQLite database

CodeIgniter\Shield: 1 migration(s) run.
CodeIgniter\Settings: 3 migration(s) run.
Kpapy: 1 migration(s) run.
Modules\Core: 6 migration(s) run.
Modules\Auth: 5 migration(s) run.
Auth: initial data loaded.
Core 0.1.0 installed.
Auth 0.2.0 installed.
12 permission(s) added to the catalogue.
Auth: default grants applied (8 added).
Core: default grants applied (3 added).
Settings, widgets, tasks and other declarations updated.
Modules synchronised.

$ php spark permissions:list

+----------------------+--------+------------------------------------+---------+---------------------------------+
| Permission           | Module | Description                        | Enabled | Groups                          |
+----------------------+--------+------------------------------------+---------+---------------------------------+
| auth-profile.edit    | Auth   | Edit                               | yes     | superadmin, admin, editor, user |
| auth-profile.view    | Auth   | View                               | yes     | superadmin, admin, editor, user |
| auth-sessions.revoke | Auth   | Log out sessions and revoke tokens | yes     | superadmin, admin, editor, user |
| auth-sessions.view   | Auth   | View                               | yes     | superadmin, admin, editor, user |
| modules.demo         | Core   | Load and remove demo data          | yes     | superadmin                      |
| modules.list         | Core   | List                               | yes     | superadmin, admin               |
| modules.sync         | Core   | Synchronise modules                | yes     | superadmin, admin               |
| modules.toggle       | Core   | Enable and disable modules         | yes     | superadmin, admin               |
| modules.uninstall    | Core   | Uninstall modules                  | yes     | superadmin                      |
| modules.upload       | Core   | Upload module packages             | yes     | superadmin                      |
| settings.auth        | Auth   | Change the settings of the module  | yes     | superadmin                      |
| settings.core        | Core   | Change the settings of the module  | yes     | superadmin                      |
+----------------------+--------+------------------------------------+---------+---------------------------------+

$ php spark permissions:check     # with a back-office route without can: added on purpose
Error: GET backend/reports: no can: filter with a permission
Permission check failed: 1 error(s).          (exit code 1)
```

A live run against `php spark serve` (Playwright): `usuari@example.com` and `admin@example.com`
log in and open `backend/profile` (200) through the computed matrix; a guest gets 302 to the login.

## Known issues

- `CrudPermissions` is not selected by any configuration yet: sessions 09 (`app/Config/Kpcrudy.php`)
  and 10 (`app/Config/Kpapy.php`) set it with `$denyByDefault = true`.
- Grant changes are logged with `log_message()` until the activity log (session 12).
- Earlier Core tests leave migrations of temporary modules behind; `RbacTestCase` starts from a
  clean database (`CleanDatabaseTrait`) to stay order-independent.
- `composer install` here still needs the phpstan cache workaround of session 04.

## Notes for the next session

- Session 08: add the throttling filter class to the `backend` alias in `app/Config/Filters.php`;
  menus filter entries with `Authorization::for($user)->can()` / `can()`.
- Session 09: `Config\Kpcrudy::$permissionResolver = CrudPermissions::class`, `$denyByDefault = true`;
  `CrudFactory::routes()` adds `can:<resource>.list`; extend `PermissionAudit` with
  `CrudPermissions::undeclared()` for every CRUD route.
- Session 10: same resolver in `Config\Kpapy`; `RoutesSecurityTest` already checks `api/v1/`.
- Session 11/feeds/tasks: `Authorization::for($user)` or `CrudPermissions::forUser($user)`, never the session.
- Session 12: replace the `log_message()` of `GrantManager` with activity entries.
- Session 15: the matrix screen saves with `GrantManager::setGroupGrants($actor, …)` (catch
  `EscalationException`), users' groups with `GroupRules`, individual permissions with
  `grantToUser()`/`revokeFromUser()`; protect them with `reauth`. When groups become data,
  `ShieldMatrix` reads the groups from the table (it uses `setting('AuthGroups.groups')` now).
