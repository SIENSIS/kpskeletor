# Commands

Every `php spark` command added by the skeleton: what it does, its arguments and options, what it
needs (environment, user, modules) and its exit codes (0 = success). Examples with their output
are in "Examples" at the end. CodeIgniter's own commands (`migrate`, `routes`, `serve`,
`key:generate`…) and the libraries' (`kpcrudy:*`, `kpapy:*`, `shield:*`) are documented by them
(`php spark help <command>`; the libraries' `docs/commands_EN.md` in `vendor/siensis/`).

The commands run from the project root as the deploy user unless the row says otherwise; none of
them checks a back-office permission (whoever has a shell on the server is trusted), so the
sensitive ones ask for confirmation and are written to the activity log. Their messages follow
the site's default language (`Core.defaultLocale`; the examples below are in English).

## Application

| Command | What it does | Needs, exit codes |
|---|---|---|
| `app:install [options]` | Installs a fresh clone (see "Installing" in `README.md`): refuses when `writable/install.lock` exists or the database has tables; generates `encryption.key` and `app.maskKey`; writes `.env` from the `env` template with `CI_ENVIRONMENT = production` unless `--env=development` (when the project root is not writable it prints the content to copy); runs `modules:sync`; creates the first superadmin and offers 2FA (`--2fa=none\|email\|totp`; Auth); loads demo data (`--demo` = every module, `--demo=News,Events` = those and the modules they require, in dependency order; development only unless `packages.allowDemoInProduction`); writes `writable/install.lock`, prints the cron line and ends with the summary of `security:check` (errors and warnings, for the installed environment; stored for the Security status page). Interactive by default; every value is also an option (`--url`, `--db=mysql\|sqlite`, `--db-host`, `--db-port`, `--db-name`, `--db-user`, `--db-prefix`, `--backend-path` (the back-office prefix, default `backend`; written to `.env` as `app.backendPath`, validated: 2–30 lowercase letters, digits and hyphens, not a reserved path, a language code or another module's route), `--admin`, `--email`) and `--no-interaction` never asks. Passwords are never options: they are asked without echo, or read from `INSTALL_DB_PASSWORD` and `INSTALL_ADMIN_PASSWORD`. With an existing `.env` (development) it asks to keep it (`--keep-env`): only missing keys are added. | A fresh clone: no `.env` (or `--keep-env` in development), no `writable/install.lock`, an empty database; run as the deploy user (writes `.env` when the project root is writable). Exit 1 when refused or failed. |
| `app:backend-path [<new>] [--yes]` | Without an argument prints the back-office prefix (`app.backendPath`). With one, changes it (D-40-01): validates it like the installer (and refuses the current one), asks for confirmation, turns maintenance mode on, rewrites `app.backendPath` in `.env`, rewrites the stored links that still carry the old prefix (the Core's notifications, then the modules through the event `backendPathChanged`: Dashboard quick links, Menus addresses; calendar entries are published with the canonical prefix and need nothing), clears the caches (menus included), writes a security log entry (`backend_path_changed`) and turns maintenance off. When `.env` is not writable it prints the line to copy and changes nothing. On failure maintenance stays on and it prints how to undo it. Never available from the back office. Bookmarks, API clients and web-server rules that name the old path are updated by hand. | The site installed; the deploy user (writes `.env`). Exit 1 when refused, cancelled or failed. |
| `about` | Versions (Core, CodeIgniter, PHP), environment, base URL, installation date, whether `.env`, `encryption.key` and `app.maskKey` are set (never their values), time zone, languages, cache handler, the database (driver, version, tables) and the modules (code and installed version, enabled). | Nothing (read-only; it reports a database it cannot reach instead of failing). |
| `app:maintenance on\|off\|status [--message="…"]` | Turns maintenance mode on by hand (an optional plain-text message on the 503 page; the login paths stay open and signed-in holders of `maintenance.manage` keep using the site, D-28-02), off (also after a failed update or restore), or shows its state. Logged as security entries. Settings › Actions does the same in the back office. | Writes `writable/maintenance.json`. Exit 1 for an unknown action. |
| `app:update [--dry-run] [--yes] [--password]` | Updates the application to the newest release of `updates.repository`: checks (newer version, no local changes, maintenance off, signed release, backup password), downloads and **verifies** the archive (SHA-256 + Ed25519 minisign signature) or the git tag (`git verify-tag`) before anything changes, maintenance mode, a full backup, the new code, `composer install --no-dev`, `modules:sync`, `app:cache-clear`, maintenance off. On failure maintenance mode stays on and the backup is named. `--dry-run` shows the plan. See `docs/updates.md`. | Updates module; the deploy user (writes the code); `updates.repository` and its keys, a backup password, `git`/`composer` and network access. Exit 1 when a check or a step fails. |
| `app:cache-clear` | Clears the Core's caches (every `CoreCache` scope, through `CacheInvalidator`, and the theme registry) and CodeIgniter's whole cache, then fires the `cache.cleared` event so modules can clear caches they keep elsewhere. CodeIgniter's own `cache:clear` cannot be replaced (D-00-43). Settings › Actions › "Clear caches" does the same. | Writes `writable/cache`. Exit 1 when a cache cannot be cleared. |

The web installer (`/install`) is the same service with a form; see `README.md`. Under PHP-FPM
(or LiteSpeed) it installs after answering and shows its progress (`progress/install/<job>`,
then `install/result/<job>`); otherwise it installs inside the request.

## Modules

| Command | What it does | Needs, exit codes |
|---|---|---|
| `make:module <Name> [--crud=<table>] [--api] [--standalone]` | Generates an empty module in `modules/<Name>` (manifest, routes, ca/es/en language files, controller, view, asset folders, an example migration, the initial-data seeder, a demo seeder, widget and scheduled-task stubs, a test, and the module's `CLAUDE.md`: purpose, resources and permissions, decisions, open tasks, how to test) and adds its namespace to `app/Config/ModuleNamespaces.php`. Refuses invalid, reserved (`Security`, `Update`) and existing names. With `--crud=<table>`: the migration creates that table, the manifest declares its `crud` resource (`<module>-<key>`, key = table without the `<module>_` prefix), the table is migrated, `php spark kpcrudy:make` writes the controller and route, and both are rewritten to `CrudFactory` (`docs/cruds.md`, D-09-04). With `--api` (needs `--crud`, D-26-06): `php spark kpapy:make` writes the API resource of the same table (`Controllers/Api/<Key>.php`, route `api/v1/<module>/<key>` with the `kpapy` filter, `setResource()` = the CRUD's resource), the manifest declares it in `$api` and `Tests/<Name>ApiTest.php` checks the token and its scopes (`docs/api.md`). With `--standalone` (a module kept in its own repository and developed inside a checkout of the skeleton): also a `composer.json`, a `README.md`, and a `CLAUDE.md` that summarises the skeleton's rules and how to test the module inside it (`docs/creating-a-module.md`). | Development: writes `modules/<Name>` and `app/Config/ModuleNamespaces.php` (and the database with `--crud`). Exit 1 for an invalid, reserved or existing name. |
| `modules:sync [--prune] [--demo[=<Module,Module>]]` | Discovers `modules/*/Config/Module.php`, validates every manifest, dependency, permission name (duplicates refused) and `$api` route (on any error: lists them and changes nothing), rewrites `app/Config/ModuleNamespaces.php`, runs the migrations of Shield, Settings and kpapy and then of each module in dependency order, runs `<Name>Seeder` on a module's first install, upserts the `modules` registry and the permission catalogue (`permissions`), applies each module's `defaultGrants` with their implied permissions once (`group_permissions`), writes Shield's `AuthGroups.permissions` and `AuthGroups.matrix` settings from them, stores the settings definitions, widgets, tasks, notification types, menu link types, calendars and API resources (`module_declarations`), validates every theme in `themes/` (errors stop the sync before anything is written) runs `assets:publish` (Core, modules and themes, asset manifest), invalidates the caches and writes the OpenAPI document of `api/v1` to `writable/api/openapi.json` when it changed (D-26-04). Idempotent: a second run prints "Nothing to do". Permissions no module generates any more are only reported; `--prune` lists them and removes them (and their grants) after a y/n confirmation. `--demo` loads the demo data of every enabled module that has a demo seeder (or of the listed ones) after a confirmation; refused in production unless `Packages.allowDemoInProduction`. | The deploy user (writes `app/Config/ModuleNamespaces.php` and `public/assets`), the database. Exit 1 on invalid manifests or a failed migration (nothing changed). |
| `modules:list` | Modules on disk and in the registry: code version, installed version, enabled, status (up to date, not installed, needs sync, folder missing, invalid manifest) and whether demo data is loaded. | The database (read-only). |
| `modules:enable <Name> [--with-dependencies]` | Enables a disabled module: runs its pending migrations (it stays disabled if they fail), publishes its assets and invalidates the caches. If dependencies are disabled, asks whether to enable them too (`--with-dependencies` does it without asking). A development-only module (`$developmentOnly`, e.g. `Devtools`) is refused unless `CI_ENVIRONMENT` is `development` (D-41-01). | The database; publishes assets. Exit 1 when it cannot be enabled. |
| `modules:disable <Name>` | Disables a module: its routes answer 404 and it disappears from the registries (widgets, tasks, notification types, menu link types, calendars, API) and from the enabled permissions; no data or grant is deleted. Refused for core modules (`Core`, `Auth`, `Api`, `Users`, `Settings`, `Dashboard`) and while enabled modules depend on it. | The database. Exit 1 when refused. |
| `modules:demo <Name> [--remove\|--reload]` | Loads (idempotent), removes or reloads the demo data of an enabled module (`Modules\<Name>\Database\Seeds\<Name>DemoSeeder`), always after a y/n confirmation. Removing deletes only the recorded rows and files; rows other data depends on are kept and listed. Refused when `CI_ENVIRONMENT = production` unless `Packages.allowDemoInProduction` (`.env`: `packages.allowDemoInProduction = true`). Demo users get random passwords, printed once after loading (also by `modules:sync --demo` and `app:install --demo`; the web installer shows them on its last page); they are never stored in clear or logged. | Development, or `packages.allowDemoInProduction`; the module enabled. Exit 1 when refused, or after `--remove` when rows were kept. |
| `modules:status` | Pending migrations per namespace (packages and modules). | The database (read-only). |
| `modules:install <file.zip\|url> [--enable] [--force] [--demo] [--yes] [--no-backup] [--password] [--signature=<file>]` | Installs or upgrades a module from a ZIP package (a file, or an https URL of an allowed host downloaded through `OutboundClient` with its `<url>.minisig`). Validates the package without running it (single folder, manifest, zip-slip, links, extensions, sizes, zip bombs, signature with `<file>.minisig` or `--signature`; required when `packages.requireSignature`), shows what it adds, checks dependencies, the Core version, the author and the version (a downgrade or the same version needs `--force`), asks for confirmation (`--yes`), then: backup (Backup module; password from `backup.password`/`BACKUP_PASSWORD` or `--password`; `--no-backup` goes on without one), maintenance mode, extraction to `writable/packages/` with `php -l` on every PHP file, atomic move into `modules/<Name>`, `modules:sync` in a new process, maintenance off; on failure the previous folder is put back. A new module stays disabled in production unless `--enable` (or `packages.autoEnable`; D-29-02). Run it as a user that may write `modules/`. See `docs/installing-modules.md`. | A user that may write `modules/`, `themes/`, `app/Config/` and `writable/packages`; Backup module for the backup. Exit 1 when refused or failed (with `--pending`: when any operation failed). |
| `modules:install --pending [--no-backup] [--password]` | Runs the operations approved in the Modules screen (staged installs and uninstall requests), in order of approval, stores each log and notifies the approver; expired entries are cleaned. Run it by hand or from its own cron line as a user that may write the code folders. | Like `modules:install`; run as the same user, by hand or from its own cron line. |
| `modules:uninstall <Name> [--keep-data] [--yes] [--no-backup] [--password]` | Backup, maintenance mode, disables the module, rolls back its migrations (unless `--keep-data`), lets other modules remove what they keep about it (calendars, menus, dashboard widgets), removes its permissions, grants (groups and users), settings, tasks, declarations and registry row, deletes its folder and runs `modules:sync` (namespace, published assets). Refused for core modules and modules other installed modules require. | Like `modules:install`. Exit 1 when refused or failed. |

## Security

| Command | What it does | Needs, exit codes |
|---|---|---|
| `security:check [--probe=<url>] [--json] [--only=<group>]` | The production checklist (`docs/security.md`, "Production checklist"): every check of the groups `configuration`, `secrets`, `installation`, `accounts`, `dependencies`, `outbound` (and those modules add from the `securityChecks` event) with its status (ok, info, warning, error), what it means and how to fix it. Exit code 1 when a check is in error, so it can run in a deployment pipeline. `--only` runs one group; `--json` prints the results as JSON; `--probe=<url>` also sends requests to the site itself (security headers, hidden paths, a PHP probe file in `uploads/`, external `return` URLs, login throttling, HTTP → HTTPS, `progress/*` answers) through a client limited to the base URL host and `app.allowedHostnames`. A full run stores its results for the Security status page (`backend/security`) and notifies the superadmins when a check turns red. | Nothing (read-only, apart from the stored results); run it as the web server's user or set `productioncheck.webUser`; `--probe` needs the site reachable from the server. Exit 1 when a check is in error, 7 for a wrong `--only`. |
| `security:audit [--from=<file>]` | Runs `composer audit --locked --format=json` and stores the result with its date (`writable/security/composer-audit.json`) for `security:check` ("last checked on …", stale after 7 days). `--from` stores the JSON output of a `composer audit --format=json` run elsewhere (CI) instead. Exit code 1 with advisories or when the audit fails. The daily task "Security checks and composer audit" runs it too. | `composer` (`productioncheck.composer`) and network access to Packagist, or a file with `--from`. Exit 1 with advisories or a failed audit, 7 when the `--from` file cannot be read. |

## Permissions

| Command | What it does | Needs, exit codes |
|---|---|---|
| `permissions:list [--module=<Name>] [--group=<group>]` | The permission catalogue: name, module, description, whether its module is enabled, and the groups that hold it (through an atomic grant or a wildcard; `superadmin` holds every one). `--module` and `--group` filter it. See `docs/permissions.md`. | The database (read-only). Exit 1 before `modules:sync` has created the tables. |
| `permissions:check` | Fails (exit code 1) when a manifest is invalid or generates a duplicate name, a generated permission is not stored (run `modules:sync`), Shield's `AuthGroups` permissions/matrix do not match `group_permissions`, a `backend/` route has no `can:` filter or names a permission outside the catalogue, or an `api/v1/` route lacks kpapy's filter (`kpapy:login` only on `auth/token*`, `kpapy:public` only for GET), or a manifest's `$api` entry has no matching route (`ApiRouteChecker`). Every kpcrudy CRUD must be routed with `CrudFactory::routes()`, built with `CrudFactory::make()`/`$this->crud()` for the route's resource, and ask only about operations its manifest declares (`CrudAuditor`); every kpapy resource route must be declared in a manifest's `$api`, render a `Kpapy\Resource` and ask only about declared operations (`ApiAuditor`; public resources ask none). Warns about stored permissions no manifest generates, grants naming unknown permissions and grants missing the permissions they imply. Run it in CI and before a deploy. | The database (read-only). Exit 1 on errors (warnings do not fail). |

## Scheduler

| Command | What it does | Needs, exit codes |
|---|---|---|
| `schedule:run [--task=<name>] [--force]` | The single cron entry (`* * * * * php /path/spark schedule:run`): runs the scheduled tasks that are due, records each run and exits with 1 if one failed. `--task` limits it to one task (class or short class name) if it is due; with `--force` it runs now, due or not (the `withoutOverlapping()` lock still applies). See `docs/scheduler.md`. | Cron, as the web server's user ([`scheduler.md`](scheduler.md)). Does nothing while maintenance mode is on. Exit 1 when a task failed or the tables are missing. |
| `schedule:list` | Every scheduled task of the enabled modules with its schedule, next run, last run and result, and notes (inactive, other environment). | The database (read-only). Exit 1 before `modules:sync`. |

## Calendar

| Command | What it does | Needs, exit codes |
|---|---|---|
| `calendar:resync <Module>` | Asks the module's calendar sources (`CalendarSource::all()`, declared as `'source'` in its `$calendars`) for every event again: publishes them (unchanged ones stay untouched) and unpublishes the published events of those calendars they no longer return. Prints the counts and writes `calendar_resync` to the activity log. See `docs/calendar.md`. | Events module enabled. Exit 1 for an unknown or disabled module. |

## Backups

| Command | What it does | Needs, exit codes |
|---|---|---|
| `backup:create [--only=db\|files] [--password]` | Creates an encrypted backup in `writable/backups` (database dump, Storage disks, `writable/uploads` when not empty, `.env`). `--password` asks for the password twice with a hidden prompt (it takes no value); without it the password is `backup.password` from `.env` or `BACKUP_PASSWORD`. At least 12 characters. See `docs/backup-restore.md`. | Backup module; a password of 12+ characters; writes `writable/backups`. Exit 1 on failure. |
| `backup:list` | The backups, newest first: date, file, type, origin, size, result (files copied into the folder by hand are listed as found). | Backup module (read-only). |
| `backup:verify <file> [--password]` | Decrypts and authenticates the backup and checks every checksum; restores nothing. | Backup module; the backup's password. Exit 1 when it does not open or a checksum differs. |
| `backup:restore <file> [--only=db\|files] [--with-env] [--password] [--yes]` | Restores a backup: checks, safety backup, maintenance mode, database and/or files, `modules:sync`. Asks to type the file name (or `--yes`). Keeps the current `.env` and merges the backup's secrets after a confirmation; `--with-env` replaces the whole `.env`. | Backup module; the password; the deploy user (it may write `.env` and run `modules:sync`). Exit 1 when refused or failed. |
| `backup:prune` | Deletes the backups the retention settings (`Backup.keepDaily`, `keepWeekly`, `keepMonthly`) do not keep. | Backup module; writes `writable/backups`. |

## Updates

| Command | What it does | Needs, exit codes |
|---|---|---|
| `updates:check` | Checks the application's repository (`updates.repository`) and the modules that declare `$repository` for new versions now (like the daily task and "Check now"), stores the result for the page and the widget and notifies new versions once. Exit code 1 when a repository cannot be read. See `docs/updates.md`. | Updates module; network access to the repositories in the allow-list. Exit 1 when a repository cannot be read. |

## Front end

| Command | What it does | Needs, exit codes |
|---|---|---|
| `assets:publish [--watch] [--check]` | Copies `modules/Core/Assets/` → `public/assets/core/`, `modules/<Name>/Assets/` → `public/assets/modules/<name>/` and `themes/<slug>/Assets/` → `public/themes/<slug>/` with CodeIgniter's Publisher (allowed extensions only: never PHP or HTML; symbolic links skipped; stale copies removed) and writes the asset manifest `public/assets/manifest.json` (URL and SHA-256 prefix of every file, used by `asset()` and the import map). Idempotent. `--watch` keeps running and republishes when a source changes (development). `--check` writes nothing and exits with an error when a copy or the manifest is out of date, or when a vendored library in `public/assets/vendor` does not match `vendor.json` (CI). Also run by `modules:sync`, `modules:enable` and `make:theme`; in development the pages republish stale assets by themselves (`assets.devRefresh`). See `docs/frontend.md`. | Writes `public/assets` and `public/themes` (the deploy user in production). Exit 1 with `--check` when something is out of date. |
| `csp:check` | Checks the views of `app/Views`, `modules/*/Views`, `themes/*/Views` and the generator templates for inline `<script>` code, `on*=""` handlers, `style=""` attributes, `javascript:` URLs and `<style>` elements (only the nonce'd theme-variables block of a theme's `partials/head.php` is allowed). Exits with an error and lists `file:line: problem` (CI). | Nothing (read-only). Exit 1 with violations. |

Options with a value accept both `--name=value` and `--name value`.

Run `php spark modules:sync` after `composer install`, after pulling new code and after copying a
module folder by hand (its namespace is only loadable once the sync has written it).

## Themes

| Command | What it does | Needs, exit codes |
|---|---|---|
| `make:theme <Name> [--parent=default\|<slug>\|none]` | Generates a theme in `themes/<slug>` (`Corporate` → `corporate`, `TasksDemo` → `tasks-demo`): manifest `Themes\<Studly>\Theme`, `Assets/css/theme.css`, empty `Views/overrides`, `Views/components`, `Views/partials`, ca/es/en language files. A child (default: of `default`) inherits every layout; `--parent=none` creates a standalone theme with a minimal file per layout. Validates the theme and publishes its assets. Refuses invalid and existing names and missing parents. See `docs/themes.md`. | Development: writes `themes/<slug>` and `public/themes`. Exit 1 for an invalid or existing name or a missing parent. |
| `themes:install <file.zip\|url> [--force] [--yes] [--no-backup] [--password] [--signature=<file>]` | Installs or upgrades a theme (`<slug>/Theme.php`) with the same validation, backup, maintenance and atomic move as `modules:install`; its parent must be installed. Choosing the active theme is a setting (Modules › Themes, Settings › Theme). | Like `modules:install` (writes `themes/`). |
| `themes:uninstall <slug> [--yes] [--no-backup] [--password]` | Deletes a theme, its published assets and its option values. Refused for the active theme of either area, the fallback theme and a parent of another theme. | Like `modules:uninstall`. Exit 1 when refused or failed. |

## Examples

Shortened outputs (`…`); paths, dates and counts are examples.

### Application

```console
$ INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --url=http://localhost:8080/ \
    --db=sqlite --email=root@example.org --2fa=none --demo=News --no-interaction
Installing: migrations, permissions, settings and assets…
.env written to /var/www/kpskeletor/.env (with new encryption and mask keys).
Demo data loaded: Auth, News.
First superadmin: admin <root@example.org>.
Add this line to the crontab of the web server user (scheduled tasks, queued emails):
* * * * * cd '/var/www/kpskeletor' && /usr/bin/php8.3 spark schedule:run >> /dev/null 2>&1
Security check (php spark security:check; errors and warnings, details on the Security status page):
…
Installation complete. Lock file: /var/www/kpskeletor/writable/install.lock.
```

```console
$ php spark about
Application
| Item               | Value                                |
| Name               | KP Skeletor                          |
| Core version       | 1.0.0                                |
| CodeIgniter        | 4.7.6                                |
| PHP                | 8.3.6 (cli)                          |
| Environment        | production                           |
| Base URL           | https://intranet.example.com/        |
| Installed on       | 2026-11-17 09:12:40                  |
| .env               | set                                  |
| encryption.key     | set                                  |
| app.maskKey        | set                                  |
| Time zone          | Europe/Madrid                        |
| Language           | ca (ca, es, en)                      |
| Cache handler      | file                                 |
| writable/ is writable | yes                               |
Database
| Driver | MySQLi … |
…
Modules
| Module   | Version | Installed | Enabled |
| Activity | 0.1.0   | 0.1.0     | yes     |
…
```

```console
$ php spark app:maintenance on --message="Back at 10:00"
Maintenance mode is on. Users with the maintenance.manage permission (and superadmins) can keep using the site.
$ php spark app:maintenance off
Maintenance mode is off.
```

### Modules

```console
$ php spark make:module Library --crud=library_books --api
Module Library created in modules/Library.
Creating the table library_books (php spark migrate -n Modules\Library)…
Writing the CRUD with php spark kpcrudy:make library_books…
CRUD library-books created: … (route backend/library/books).
Writing the API resource with php spark kpapy:make library_books…
API resource … created: … (route api/v1/library/books).
Next: php spark modules:sync, then adapt the CRUD in … (fields, relations, hideFields(), where()) and its tests.
```

```console
$ php spark modules:sync
Module namespaces file updated.
Library: 1 migration(s) run.
Library 0.1.0 installed.
12 permission(s) added to the catalogue.
Library: default grants applied (9 added).
Settings, widgets, tasks and other declarations updated.
Library: assets published (2 file(s) changed).
Modules synchronised.
$ php spark modules:sync
Nothing to do: every module is up to date.
```

```console
$ php spark modules:list
| Module  | Version | Installed | Enabled | Status       | Demo |
| Core    | 1.0.0   | 1.0.0     | yes     | up to date   | -    |
| News    | 0.1.0   | 0.1.0     | yes     | up to date   | yes  |
| Library | 0.2.0   | 0.1.0     | yes     | needs sync   | -    |
…
```

```console
$ php spark modules:demo News --remove
Remove the demo data of News? Real data is not touched. [y, n]: y
News: demo data removed (14 row(s), 3 file(s)).
```

### Security

```console
$ php spark security:check --only=configuration
Configuration
  [ok]      Environment: CI_ENVIRONMENT is production.
  [ok]      Debug toolbar and detailed errors: Off.
  [ok]      HTTPS for every request: forceGlobalSecureRequests is on: HTTP is redirected to HTTPS.
  …
  [warning] Strict-Transport-Security (HSTS): Not sent: browsers may still use plain HTTP.
            Fix: Remove securityheaders.hsts = false from .env once the site is served over HTTPS.

9 ok, 0 information, 1 warnings, 0 errors.
$ echo $?
0
```

The full list of checks and a real example are in [`security.md`](security.md), "Production
checklist"; `--json` prints `{"checkedAt": …, "counts": {"ok", "info", "warning", "error"}, "results":
[{"group", "id", "status", "title", "message", "fix"}…]}`.

```console
$ php spark security:audit
No known vulnerabilities (last checked on 2026-11-17 04:43:12).
```

### Permissions

```console
$ php spark permissions:list --module=News
| Permission            | Module | Description           | Enabled | Groups                     |
| news-posts.list       | News   | List                  | yes     | superadmin, admin, editor  |
| news-posts.delete     | News   | Delete                | yes     | superadmin, admin          |
| news-posts.publish    | News   | Publish and unpublish | yes     | superadmin, admin, editor  |
| news-read.view        | News   | View                  | yes     | superadmin, admin, editor, user |
…
```

```console
$ php spark permissions:check
Permissions OK: 214 permission(s) in the catalogue, 389 route(s) checked.
```

On a problem it prints `Error: …` / `Warning: …` lines, then
`Permission check failed: 1 error(s).` and exits with 1.

### Scheduler

```console
$ php spark schedule:run
Success  Send the queued notification emails (38 ms)
$ php spark schedule:run --task=PurgeActivityLog --force
Success  Delete the old activity log entries (112 ms)
```

With nothing due it prints `No scheduled task is due.`; while maintenance mode is on,
`Skipped: maintenance mode is on.`

### Backups

```console
$ php spark backup:create --password
…
Backup created: backup-20261117-021500-full-3f9a1c2e.kpbackup (…).
$ php spark backup:verify backup-20261117-021500-full-3f9a1c2e.kpbackup --password
The backup opens with this password and every checksum matches.
```

### Front end

```console
$ php spark assets:publish --check
The published assets are up to date.
$ php spark csp:check
No inline scripts, handlers or styles in the project views.
```

`csp:check` with a problem lists `file:line: problem` (e.g.
`modules/Library/Views/index.php:12: event handler attribute on*="" (use data attributes and a component)`)
and ends with `1 CSP violation(s) in the project views.` (exit code 1).
