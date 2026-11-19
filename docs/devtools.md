# Development tools (Devtools)

`modules/Devtools` is a module that exists **only for development** (D-41-01 … D-41-03): the
home of tools that help to build modules from the back office. Session 41 built the module, its
locks and the **CRUD recipes**; session 42 adds the code generator, session 43 the package
builder.

## Using it

```bash
# .env: CI_ENVIRONMENT = development (a development copy, never a server)
php spark modules:enable Devtools   # installed disabled ($enabledByDefault = false)
php spark serve                     # http://localhost:8080
```

Sign in as a **superadmin** and open *Configuració › Sistema › Eines de desenvolupament*
(`backend/devtools`, under the back-office prefix):

- **CRUD recipes** (`backend/devtools/recipes`): one card per variant of a back-office CRUD and
  its API — plain CRUD, read-only, custom operations, re-authentication, record rules, own
  records, fields hidden by permission, translated fields, rich text, uploads and the file
  picker, export/import, trash, masked ids, the activity log, the kpapy resource, the menu entry
  and default grants. Each card says what it is and when to use it, shows real code copied from
  the skeleton (with the file it comes from and a copy button) and links the section of `docs/`
  it comes from (`backend/devtools/docs/<page>`, opened at the section's anchor; a plain-text viewer of `docs/*.md`).
  The catalogue is `Modules\Devtools\Recipes\RecipeBook`; `RecipesTest` checks that every cited
  file and doc heading exists, so a renamed section breaks the test, not the page.

## The locks

Every lock has a test (`modules/Devtools/Tests/DevtoolsLocksTest.php`, `LocalOnlyTest.php`,
`modules/Core/Tests/DevelopmentOnlyTest.php`, `PackagesTest`).

| # | Lock | Where |
|---|---|---|
| 1 | Routes only when `ENVIRONMENT === 'development'` (a constant, no query): elsewhere every Devtools URL answers 404 | `modules/Devtools/Config/Routes.php` |
| 2 | Manifest `$developmentOnly = true`: `modules:sync`, `modules:enable`, `modules:install` and the Modules screen refuse to install or enable it outside development; a sync there disables it (security log `devonly_disabled`); `$enabledByDefault = false` | `ModuleManifest`, `ManifestValidator`, `DevelopmentOnly`, `ModuleToggler`, `ModuleSynchronizer`, `PackageValidator`, `ModuleOverview` |
| 3 | `security:check` → `installation.devtools`: **error** when a development-only module is enabled outside development, information in development | `InstallationCheck` |
| 4 | Local requests only: client `REMOTE_ADDR` in `127.0.0.0/8` or `::1` (proxy headers are never trusted) **and** `Host` `localhost` or a loopback address; otherwise 404. The routes are not registered for other web requests (so no login redirect reveals them), and the `devtools` route filter checks again | `Config/Routes.php`, `Filters/LocalOnly.php` |
| 5 | Resource `devtools` (single resource, prefix override): `devtools.view` (screens) and `devtools.write` (writes files or runs commands), granted to nobody — superadmins hold every permission. Write routes use `DevtoolsRoutes::WRITE`: `devtools`, `can:devtools.write`, `reauth`, `ratelimit:forms` | `Config/Module.php`, `Routing/DevtoolsRoutes.php` |
| 6 | Packaging: it is part of the repository and of release archives (`docs/updates.md`), made inert by 1–4 | `docs/security.md` |

Every Devtools screen shows the banner "Development tools: never enabled in production".

## Adding a tool (sessions 42–43)

- Add its routes in `DevtoolsRoutes::define()`: screens with `self::VIEW`, anything that writes
  files or runs commands with `self::WRITE` (`DevtoolsLocksTest::testEveryDevtoolsRouteHasItsLocks`
  checks every route under `backend/devtools`).
- Controllers extend `BackendController`; views include `Modules\Devtools\Views\_banner`.
- Log writes with `activity_log('Devtools', …)`; never weaken a lock to make a tool work.
