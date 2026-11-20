# Session 41 — Devtools module: development-only tools and their locks

Branch: `ccr-b5498d68-5s3wto` (assigned by the environment). Decisions: D-41-01, D-41-03 applied;
D-41-04, D-41-05 accepted (`docs/decisions.md`).

## What was built

- A new module **`Devtools`** (`modules/Devtools/`), only for development: the home of the CRUD
  recipes (this session), the code generator (session 42) and the package builder (session 43).
- **Six locks**, each with tests, that keep it out of production and off the network
  (`docs/devtools.md`, `docs/security.md` "Development-only modules"):
  1. routes only when `ENVIRONMENT === 'development'` (`Config/Routes.php`, a constant);
  2. a new manifest key **`$developmentOnly`** (Core): `modules:sync`, `modules:enable`,
     `modules:install` and the Modules screen refuse to install/enable such a module outside
     development, and a sync there disables it (security log `devonly_disabled`);
  3. `security:check` → `installation.devtools`: error outside development, info in development;
  4. loopback client + local `Host` only (`devtools` filter; routes not even registered for other
     web requests, D-41-05);
  5. resource `devtools` (`devtools.view`, `devtools.write`) granted to nobody (superadmins only);
     writes use `DevtoolsRoutes::WRITE` = `devtools`, `can:devtools.write`, `reauth`,
     `ratelimit:forms`;
  6. packaging: part of the repository and release archives, inert there (documented in
     `docs/security.md`, `docs/updates.md`).
- **CRUD recipes** (`backend/devtools/recipes`): 16 cards (plain CRUD, read-only, custom
  operation, re-authentication, record rules, own records, hidden fields, translated fields, rich
  text, uploads/file picker, export/import, trash, masked ids, activity log, API resource, menu and
  grants), each with what/when (ca/es/en), real code from the skeleton, the source file, a copy
  button and a link to its `docs/` section, shown by a read-only docs viewer
  (`backend/devtools/docs/<page>#<section>`).
- A banner on every Devtools screen: "Development tools: never enabled in production".

## How it was built

**Core (generic, no module hard-coded):**

| File | Change |
|---|---|
| `modules/Core/Config/ModuleManifest.php` | `public bool $developmentOnly = false;` |
| `modules/Core/Modules/DevelopmentOnly.php` (new) | The rule: `allowed()` (environment is `development`), `refuses($manifest)`; environment injectable for tests |
| `modules/Core/Modules/ManifestValidator.php` | `validateProperties()` (raw values); `$enabledByDefault`/`$developmentOnly` must be booleans; `$developmentOnly` requires `$enabledByDefault = false` |
| `modules/Core/Modules/ModuleToggler.php` | `enable()` refuses a development-only module (also as a dependency) outside development |
| `modules/Core/Modules/ModuleSynchronizer.php`, `SyncReport.php` | New modules installed disabled outside development; enabled ones disabled with `security_log('Core', 'devonly_disabled')`; `SyncReport::$devOnlyDisabled` + line |
| `modules/Core/Packages/PackageValidator.php` | A package whose manifest says `$developmentOnly = true` is refused outside development (and a non-boolean value always): covers `modules:install` and the Modules screen's upload |
| `modules/Core/Modules/ModuleOverview.php`, `Views/modules/index.php` | Rows `developmentOnly`, `blocked`; "Development only" badge, no Enable button when blocked |
| `modules/Core/Security/Check/Checks/InstallationCheck.php` | `installation.devtools` |
| Language `Core.php`, `SecurityCheck.php` (ca/es/en) | Messages above; `Core.activity.devonly_disabled` |

**Module `modules/Devtools/`:** `Config/Module.php` (single resource `tools`, prefix `devtools`,
extra `write`, no grants, menu: its own settings group `group.development` with the entry), `Config/Routes.php`,
`Config/Registrar.php` (alias `devtools`), `Filters/LocalOnly.php`, `Routing/DevtoolsRoutes.php`
(`VIEW`, `WRITE`, `define()`), `Controllers/{Home,Recipes,Docs}.php`, `Recipes/RecipeBook.php`,
`Recipes/DocPage.php`, `Views/{index,recipes,docs,_banner}.php`, `Assets/js/copy.js`, language
files, `CLAUDE.md`. No tables, settings or API.

**Routes** (development, local requests only): `GET backend/devtools` (`devtools`),
`GET backend/devtools/recipes` (`devtools-recipes`), `GET backend/devtools/docs/(:segment)`
(`devtools-docs`), all with `devtools` + `can:devtools.view`. No write route yet.

**Permissions:** `devtools.view`, `devtools.write` (catalogue: 171 permissions).
**Commands:** none new; `modules:enable`/`modules:sync`/`modules:install` behave as above
(`docs/commands.md`).

## Decisions and deviations

- D-41-04 (decided by the owner on the pull request): the entry is in its own settings group
  **Desenvolupament** (`group.development`, declared by the module, order 90). A group is shown
  only while one of its entries is visible, so admins and other users never see an empty heading,
  and nobody sees it outside development (no route). The Menus editor lists it like any declared
  group of an enabled module (Devtools is disabled outside development).
- D-41-05 (accepted): route filters run after the `backend` chain, so with the filter alone a
  signed-out request from the network got the login redirect (revealing the URLs). The routes are
  therefore also **not registered** for non-local web requests (console still sees them); the
  filter checks again. A plain-text docs viewer was added so cards can *link* their section.
- The test suite runs in `testing`, so the tests register the routes with
  `DevtoolsRoutes::define()` (`DevtoolsTestCase`) — the `Routes.php` condition itself is checked by
  "no Devtools route is registered" and by the HTTP checks below.

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --url=http://localhost:8080/ --db=sqlite --demo --2fa=none --no-interaction
php spark modules:enable Devtools
php spark serve
# sign in as the superadmin → Configuració › Desenvolupament › Eines de desenvolupament
# http://localhost:8080/backend/devtools/recipes
```

Production-like copy (same install, `.env` `CI_ENVIRONMENT = production`), console output:

```
$ php spark security:check
  [error]   Mòduls només per a desenvolupament: Hi ha mòduls només per a desenvolupament activats fora de desenvolupament: Devtools.
$ php spark modules:sync
S’ha desactivat Devtools: és un mòdul només per a desenvolupament i aquest entorn no és de desenvolupament.
Mòduls sincronitzats.
$ php spark security:check
  [ok]      Mòduls només per a desenvolupament: No hi ha cap mòdul només per a desenvolupament activat.
$ php spark modules:enable Devtools
Devtools és un mòdul només per a desenvolupament: només es pot activar quan CI_ENVIRONMENT és development.
activity_log: security | devonly_disabled | Devtools
```

HTTP against `php spark serve` (signed out): development + `localhost` → 302 (login);
development + `Host: dev.example.com` → **404**; production → **404**.

Development `security:check`: `[info] Mòduls només per a desenvolupament: Entorn de
desenvolupament … (ara activats: Devtools). No despleguis mai amb CI_ENVIRONMENT=development.`

## Screenshots

Playwright against `php spark serve`, demo data, superadmin, device scale factor 2
(`deploy-status/screenshot.mjs`; no CSP violation, no horizontal scroll):

| File | Caption |
|---|---|
| `screenshots/01-devtools-home.png` | Devtools home: banner, the CRUD recipes tool and the six locks; menu entry in its own group, Desenvolupament |
| `screenshots/02-recipes.png` | CRUD recipes, full page: contents and the 16 cards (what, when, code, source, doc link) |
| `screenshots/03-recipes-dark.png` | Recipes in dark mode |
| `screenshots/04-recipes-phone.png` | Recipes at phone width (390 px) |
| `screenshots/05-docs-section.png` | The docs viewer opened at a recipe's section (`docs/permissions.md#hidden-fields`) |
| `screenshots/06-modules-devonly-badge.png` | Modules screen: Devtools with the "Només desenvolupament" badge |

## Tests and checks

New tests: `modules/Devtools/Tests/DevtoolsLocksTest.php` (manifest; no route outside
development; loopback addresses/hosts accepted; network clients, `X-Forwarded-For` and foreign
hosts → 404; disabled module → 404; `devtools.view` needed, admins refused; every route has
`VIEW`/`WRITE`; a write route needs `devtools.write` and re-authentication and answers 404 from the
network; no inline code in the views), `LocalOnlyTest.php` (18 cases), `DevtoolsMenuTest.php` (own group shown to the superadmin where the routes exist, never empty for admins/editors/users, absent without the routes), `RecipesTest.php` (every
recipe's source file, doc heading and texts in ca/es/en exist; page renders for the superadmin,
403 for others; code escaped; docs viewer refuses unknown names), `modules/Core/Tests/DevelopmentOnlyTest.php`
(manifest key, enable refused also as a dependency, sync disables and logs, first sync, Modules
screen rows, `security:check` error/ok/info and from the registry), `PackagesTest` (+2: package
refused outside development, non-boolean refused).

Results (SQLite, PHP 8.3):

| Check | Result |
|---|---|
| `vendor/bin/phpunit` | **OK (1481 tests, 19522 assertions)**, 13 min 07 s, after the menu-group change (D-41-04). The first run (1478 tests) had one failure, `ReauthTest::testPostIsNotReplayedAndJsonGets401`, also red on `main` since commit `edac5e2` (hidden `index.php`; the test hard-coded `index.php/auth/reauth`), fixed in this session |
| `vendor/bin/phpstan analyse` | No errors (level 6) |
| `vendor/bin/php-cs-fixer fix --dry-run` | 0 of 1056 files to fix |
| `php spark modules:sync` | OK (Devtools installed disabled; enabled with `modules:enable` in development) |
| `php spark permissions:check` | Permisos correctes: 171 permisos, 349 rutes (also after the menu-group change) |
| `php spark csp:check` | No inline scripts, handlers or styles |
| `php spark assets:publish --check` | Up to date |
| `php spark security:check` (development) | `installation.devtools` info; the 6 errors are the development install's own (environment, debug, HTTPS ×3, demo data) |
| `php spark security:check` (production-like copy) | `installation.devtools` **error** while Devtools is enabled; after `modules:sync` it is disabled and the check is ok (output above) |

Fixes to earlier tests needed by the new module: `PermissionsCommandsTest` (169 → 171
permissions), `SecurityCheckTest` (passes the development-only list explicitly),
`tests/unit/Docs/DocumentationTest` (adds the Devtools routes, which exist only in development, to
the known routes); `DevtoolsTestCase` resets the shared route collection after each test so no
other test sees the Devtools menu entry.

## Known issues and notes for the next session

- `ReauthTest` line 159 was red on `main` after commit `edac5e2` (hidden `index.php`); fixed here.
- No Devtools write route exists yet; sessions 42–43 add them with `DevtoolsRoutes::WRITE`
  (the route test enforces it). Tests: `DevtoolsTestCase::withDevtoolsRoutes()` and
  `fromLocalhost()`; a password login counts as re-authentication (D-06-04), so move the clock
  16 minutes to test the `reauth` redirect.
- Cloud environment: Composer needed `--prefer-source` and `phpstan/phpstan` seeded into its
  cache from a git clone (as in sessions 39–40); Playwright was linked from the pre-installed
  copy (`node_modules/playwright` → `/opt/node-tools/...`, not committed).
