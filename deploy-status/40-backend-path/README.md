# Session 40 — Configurable back-office path

Branch: `claude/busy-faraday-4rveyp` · Pull request: [SIENSIS/kpskeletor#58](https://github.com/SIENSIS/kpskeletor/pull/58) · Date: 2026-11-19

## What was built

- The back office no longer has to live under `/backend`: the prefix is `app.backendPath` in
  `.env` (default `backend`), chosen when installing — `php spark app:install --backend-path=admin`
  or the new "Back-office address" field of the web installer (D-40-01, alternative C).
- `php spark app:backend-path [<new>] [--yes]` changes it later from the console: validation,
  maintenance mode, `.env`, stored links, caches, security log, maintenance off. Never from the
  back office.
- Stored back-office links (notification and calendar links, dashboard quick links, Menus
  addresses) keep working across prefixes: they are stored as `backend/…` and resolved when
  shown (D-40-02).
- `security:check` reports the prefix (info) and its probe checks `?return=` on it.
- The default install is unchanged: every existing test still runs with `backend`.

## How it was built

- **Configuration:** `Config\App::$backendPath` (`app.backendPath`, commented in `env`).
  `Modules\Core\Routing\BackendPath` reads it (an invalid value falls back to `backend`) and
  offers `path()`, `url()`, `link()` (stored form), `contains()`, `resolve()` (stored → current),
  `store()` (current/old → stored), `error()` (validation) and `routedSegments()`.
- **Helpers** (`modules/Core/Helpers/backend_helper.php`, autoloaded): `backend_path()`,
  `backend_url()`, `backend_link()`; JavaScript: `backendUrl()` in `core/config.js` (the prefix is in
  the page's `kp-config` JSON as `backendPath`).
- **Everything that wrote `backend`:** the 15 route files (`backend_path()` in groups,
  `CrudFactory::routes()` and single routes), ≈ 260 `site_url('backend…')` → `backend_url()`,
  redirects, kpcrudy actions, views, `make:module` templates (`Routes.php.tpl`, `Test.php.tpl`,
  `CLAUDE.md.tpl`). The `backend` filter chain patterns are set in `Config\Filters::__construct()`,
  Shield's `login`/`force_reset` redirects in `Config\Auth::__construct()`. `RouteSecurityAuditor`
  and `ErrorPage` use `BackendPath::contains()`; `ForcePasswordReset`/`ForceTwoFactor` compare the
  stored form; `LocaleUrls::isPublic()` excludes the prefix; the Pages catch-all placeholder
  excludes it and `PageSlugs::reservedSlugs()` adds it. Names that are not paths (the theme area
  `backend`, the filter alias `backend`, the rate limit `backend`, `theme_layout('backend')`)
  are unchanged.
- **Validation** (installer, web form and `app:backend-path`): `/^[a-z][a-z0-9-]{0,28}[a-z0-9]$/`,
  not in `BackendPath::RESERVED` (`api`, `install`, `progress`, `login`, `logout`, `register`,
  `auth`, `uploads`, `assets`, `themes`, `ical`, `files`…), `Config\LocaleUrls::$excluded`, the
  supported language codes, the first segment of any route of another module, nor what a module
  refuses through the Core event `backendPathCheck` (`BackendPathCheck`; Pages refuses a top-level
  page slug). `backend` can always be chosen again.
- **Installer:** `InstallOptions::$backendPath` (validated), written to `.env` by `Installer::envValues()`
  (and with `--keep-env` through `EnvWriter::withValue()`), applied to the running process by
  `RuntimeConfig`. The demo page "Intranet" takes the slug `area-interna` when the back office is
  `/intranet` (`PagesDemoSeeder::intranetSlug()`, same in `MenusDemoSeeder`).
- **Change command:** `Modules\Core\Commands\AppBackendPath` → `Modules\Core\Routing\BackendPathChanger`
  (`MaintenanceMode` on, `EnvWriter::withValue()` + atomic write, `notifications.data`, the event
  `backendPathChanged` with `BackendPathChanged::rewrite()/rewriteJson()/rewriteColumn()`,
  `cache()->clean()`, `security_log('Core', 'backend_path_changed')`, maintenance off). A failure
  throws `BackendPathChangeFailed` (step `env`/`links`/`cache`) and leaves maintenance on; the
  command prints the `.env` line that undoes it. When `.env` is not writable it prints the line to
  copy and changes nothing. Listeners: Dashboard (`dashboard_group_widgets.options`,
  `dashboard_user_layouts.layout`), Menus (`menu_items.value`, `backend_menu_links.value`, type
  `url` only).
- **Stored links:** `NotificationMessage::path()`, `QuickLinks`, `LinkTarget::resolve()/error()`,
  the Events calendar views, `ProvidedEvents` and `EventsApi` resolve; `WidgetOptions` and
  `LinkTarget::storedValue()` store. Notification types and calendar publishers use
  `backend_link()`.
- **e2e:** `tests/e2e/support.mjs` exports `backend(path)`; the global set-up finds the prefix
  where the login lands (`E2E_BACKEND_PATH` overrides it).
- No tables, migrations, permissions or settings were added. Texts in ca/es/en
  (`Core.backendPath.*`, `Install.form.backendPath*`, `Install.cli.askBackendPath`,
  `Core.activity.backend_path_changed`, `SecurityCheck.backendPath.*`).

## Decisions and deviations

- D-40-01 (decided) applied as written.
- **D-40-02 (Accepted, proposal):** the stored form of a back-office link is the path with the
  canonical prefix `backend/…`, resolved when shown; `backend` stays a reserved top-level path on
  every site. No migration was needed (old rows are already in that form).
- The Events module cannot ship `Config/Events.php` (D-00-04), so it has no `backendPathChanged`
  listener: calendar entries are published with `backend_link()` and need none.
- The activity log is append-only: its old paths are not rewritten.

## How to try it

```bash
php spark app:install --env=development --url=http://localhost:8080/ --db=sqlite --demo --2fa=none --backend-path=admin
php spark serve
# http://localhost:8080/admin (login lands there); /backend answers 404
php spark app:backend-path            # shows the prefix
php spark app:backend-path intranet   # refused while the demo page /intranet exists
php spark app:backend-path gestio --yes
```

Console output on the demo site (installed with `--backend-path=admin`):

```
$ php spark app:backend-path
El back office és a /admin (app.backendPath).
$ php spark app:backend-path api
«api» ja la fa servir el lloc (adreça reservada, codi d'idioma o adreça d'un altre mòdul).
$ php spark app:backend-path intranet
«intranet» és l'adreça d'una pàgina de primer nivell: canvieu primer l'adreça de la pàgina.
$ php spark app:backend-path gestio --yes
El back office passarà de /admin a /gestio. El mode de manteniment estarà activat mentre es reescriuen els enllaços desats.
  notifications.data: 0
  menu_items.value: 0
  backend_menu_links.value: 0
  dashboard_group_widgets.options: 0
  dashboard_user_layouts.layout: 0
Fet: el back office ara és a /gestio.
Actualitzeu a mà les adreces d'interès, els clients de l'API i les regles del servidor web (…) que esmentin l'adreça antiga.
$ curl …/gestio → 302 (login) ; …/admin → 404
```

## Screenshots

| File | What |
|---|---|
| `screenshots/01-installer-backend-path.png` | Web installer: the new "Back-office address" field with its help |
| `screenshots/02-installer-backend-path-reserved.png` | The server refusing a reserved path (`api`) |
| `screenshots/03-dashboard-under-admin.png` | The dashboard of a site installed with `--backend-path=admin` |
| `screenshots/04-dashboard-under-admin-phone-dark.png` | The same at phone width, dark mode |
| `screenshots/05-security-status-backend-path.png` | Security status: the `configuration.backendPath` info line |
| `screenshots/06-old-path-404.png` | `/backend` answers 404 on that site |

(Config: `screenshots.json`; the installer shots were taken with a small Playwright script on a
fresh, unconfigured copy.)

## Tests and checks

New tests: `modules/Core/Tests/BackendPathTest.php` (helpers, resolve/store, notification links,
validation, rewrite, `.env` line), `modules/Dashboard/Tests/BackendPathSiteTest.php` (a site with
`admin`: routes, 404 under `/backend`, login landing, forced password change, the `can:` audit and
the filter patterns, CRUD URLs, the menu, quick links), `modules/Core/Tests/BackendPathChangerTest.php`
(invalid/reserved/current refused, `.env`, stored links rewritten and others left alone, menu cache,
security log, the site under the new prefix and 404 under the old one, a failure leaves maintenance
on), `InstallerTest` (the `.env` line, 17 invalid paths, valid ones), `WebInstallerTest` (the field,
refusals, the chosen path in `.env`).

- `vendor/bin/phpunit`: **OK (1432 tests, 18663 assertions)**, 13 min 01 s (SQLite).
- `vendor/bin/phpstan analyse`: **No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run`: **0 files**.
- On the demo site installed with `--backend-path=admin`: `php spark modules:sync` (nothing to do),
  `php spark permissions:check` (**169 permissions, 346 routes, OK**), `php spark csp:check` (OK),
  `php spark security:check` (the new info line; the errors are those of a development demo site:
  environment, debug, HTTPS, cookies, base URL, demo data).
- `npm run e2e` against that site: **187 passed, 10 skipped, 1 failed** in the first run (the menu
  walk filtered out `/index.php/admin/…` links); after the fix the test passes (re-run alone).

## Known issues and notes for the next session

- Third-party modules written for 1.0.0 work with the default prefix; with another prefix their
  `'backend/…'` literals must become `backend_path()`/`backend_url()`/`backend_link()`
  (`CHANGELOG.md`, `docs/creating-a-module.md`).
- Bookmarks, API clients and web-server rules that name the prefix are changed by hand after
  `app:backend-path` (`docs/web-server.md`).
- Composer: GitHub dist downloads were refused again ("Could not authenticate against
  github.com"); `composer install --prefer-source` worked, and `phpstan/phpstan` (dist only) was
  cloned with git and put in Composer's cache.
