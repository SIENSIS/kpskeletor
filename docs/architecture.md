# Architecture

kpskeletor is a CodeIgniter 4 application in which everything is a **module**: the Core, the
authentication, the API, the back-office screens and the public site's content. This document is
the map; each section links to the detailed document.

## Folders

| Folder | Contents |
|---|---|
| `app/` | CodeIgniter's application folder: `Config/` (framework configuration, `Kpcrudy.php`, `Kpapy.php`, `Outbound.php`, `Storage.php`, `SecurityHeaders.php`, `ProductionCheck.php`…), the generated `Config/ModuleNamespaces.php`, the home controller and a few views. No features. |
| `modules/<Name>/` | One module: `Config/` (`Module.php` manifest, `Routes.php`, optional `Registrar.php`, `Events.php`, `Services.php`), `Controllers/`, `Database/Migrations` and `Seeds`, `Language/{ca,es,en}`, `Views/`, `Assets/` (JS, CSS, images), `Widgets/`, `Tasks/`, `Notifications/`, `Tests/` and `CLAUDE.md`. |
| `modules/Core/` | The kernel: module lifecycle (`Modules/`, `Registry/`), permissions (`Permissions/`), themes (`Theme/`), front end (`Frontend/`), filters, cache, settings, storage, scheduler, notifications, activity log, calendar contracts, packages, maintenance, security checks, commands and the standard views. |
| `themes/<slug>/` | Themes (`Theme.php` manifest, `Views/` with layouts, components and `overrides/<Module>/…`, `Assets/`); see [`themes.md`](themes.md). |
| `public/` | The document root ([`web-server.md`](web-server.md)): `index.php`, `assets/vendor/` (vendored libraries, committed), and the published, git-ignored copies `assets/core/`, `assets/modules/<name>/`, `themes/<slug>/` and `assets/manifest.json`; `uploads/` (the `public` storage disk). |
| `writable/` | Cache, logs, sessions, `storage/` (the `private` disk), `backups/`, `packages/` (staged packages), `security/` (results of `security:check` and `security:audit`), `api/openapi.json`, `install.lock`, `maintenance.json`. |
| `tests/` | Application-wide tests (`unit/`, `feature/`, `_support/`); each module keeps its own tests in `modules/<Name>/Tests/`. |
| `docs/` | This documentation; `deploy/` and `deploy-status/` hold the build sessions and their reports. |
| `.claude/commands/` | Claude Code commands `/new-module` and `/check-module` ([`creating-a-module.md`](creating-a-module.md)). |

## Modules

### The manifest

Every module has a manifest `modules/<Name>/Config/Module.php`, a class extending
`Modules\Core\Config\ModuleManifest`. Every key is typed and has a default; `ManifestValidator`
checks it on `modules:sync` and fails with a message naming the module and the key.

| Key | Contents |
|---|---|
| `$name`, `$version`, `$icon`, `$description`, `$author` | Identity (semantic version; description as a language key or text). |
| `$requires` | Dependencies: module name => version constraint (`^`, `~`…). |
| `$enabledByDefault` | `false` installs the module disabled (the `Tasks` tutorial module). |
| `$repository` | Where the Updates module looks for new releases of the module. |
| `$resources` | One per CRUD or screen: `label`, `type` (`crud`/`page`), `except`, `extra`, optional `name` (permission prefix override). Generates the permissions `<module>-<resource>.<operation>` ([`permissions.md`](permissions.md)). |
| `$defaultGrants` | Group => permissions, applied once on first install. |
| `$menu` | Back-office menu entries ([`menus.md`](menus.md)). |
| `$menuLinks` | Link types offered to the public menu editor. |
| `$settings` | Runtime settings with type, default, rules and label ([`settings.md`](settings.md)). |
| `$widgets` | Dashboard widgets ([`widgets.md`](widgets.md)). |
| `$tasks` | Scheduled tasks ([`scheduler.md`](scheduler.md)). |
| `$notifications` | Notification types ([`notifications.md`](notifications.md)). |
| `$backup` | Extra content for backups ([`backup-restore.md`](backup-restore.md)). |
| `$calendars`, `$calendarProviders` | Calendars the module publishes to and event providers ([`calendar.md`](calendar.md)). |
| `$api` | kpapy resources under `api/v1/`, checked against the module's routes ([`api.md`](api.md)). |
| `$csp` | Extra Content-Security-Policy sources ([`security.md`](security.md)). |
| `$outboundHosts` | Hosts the module may call through `OutboundClient` ([`outbound-requests.md`](outbound-requests.md)). |

### Discovery and namespaces

Each module has its own PSR-4 namespace (`Modules\News` → `modules/News`), listed in the
generated `app/Config/ModuleNamespaces.php` and merged by `app/Config/Autoload.php`. CodeIgniter's
discovery then finds each module's `Config/Routes.php`, `Config/Registrar.php`, `Config/Events.php`,
`Config/Services.php`, commands and language files. Nothing is scanned on regular requests:
`ModuleDiscovery` (folders with a `Config/Module.php`) only runs from the console and the Modules
screen. The namespaces file is written by `make:module`, `modules:sync` and `modules:install`; never
edit it by hand. A module copied by hand needs `php spark modules:sync`.

### Synchronisation and the registry

`php spark modules:sync` (`ModuleSynchronizer`) is idempotent and checks everything before writing
anything:

1. discover and validate the manifests, dependencies, duplicate permissions and `$api` routes (and
   the themes);
2. run migrations (third-party packages first, then modules in dependency order);
3. seed new modules and upsert the **registry** (`modules` table: name, version, enabled,
   `grants_applied`, dates);
4. write the permission catalogue (`permissions`; `--prune` removes stale ones after confirmation);
5. apply the default grants once per module (`group_permissions`, completed with implied
   permissions);
6. store the declarations (settings, widgets, tasks, notification types, menu link types,
   calendars, API, CSP sources, menu entries, outbound hosts) in `module_declarations`, so regular
   requests never load manifests;
7. publish the assets and write the asset manifest;
8. invalidate the derived caches;
9. compute Shield's groups, permissions and group matrix;
10. write the OpenAPI document (`writable/api/openapi.json`).

### Enabling, disabling and core modules

`ModuleToggler` (`modules:enable`, `modules:disable`, the Modules screen `backend/modules`) hides a
module at once without deleting data or grants: its routes answer 404 (the global `moduleenabled`
filter, by controller namespace), its permissions, menus, widgets and declarations disappear.
Enabling runs pending migrations and publishes its assets; enabling a module with disabled
dependencies needs them enabled too. The **core modules** `Core`, `Auth`, `Api`, `Users`,
`Settings` and `Dashboard` (`ModuleToggler::CORE_MODULES`) cannot be disabled or uninstalled.
Installing from ZIP packages and uninstalling: [`installing-modules.md`](installing-modules.md).

The modules shipped with the skeleton:

| Module | Purpose |
|---|---|
| `Core` | The kernel (above), the Modules, Themes, Scheduled tasks and Security status screens. |
| `Auth` | Login, 2FA, re-authentication, sessions, profile ([`auth.md`](auth.md)). |
| `Api` | The API core: kpapy configuration, app login, personal tokens, documentation page ([`api.md`](api.md)). |
| `Users` | Users, groups, the permission matrix ([`users.md`](users.md)). |
| `Settings` | Settings forms generated from the manifests, Settings › Security ([`settings.md`](settings.md)). |
| `Dashboard` | The back-office home with the widgets ([`widgets.md`](widgets.md)). |
| `Activity` | The activity and security log screens ([`activity.md`](activity.md)). |
| `News` | Articles and notices; the reference module ([`news.md`](news.md)). |
| `Pages` | Content pages of the public site ([`pages.md`](pages.md)). |
| `Menus` | Public menus and back-office menu customisation ([`menus.md`](menus.md)). |
| `Files` | File manager, folder permissions, file picker ([`files.md`](files.md)). |
| `Events` | Calendar, events, iCal feeds ([`events.md`](events.md), [`calendar.md`](calendar.md)). |
| `Backup` | Encrypted backups and restores ([`backup-restore.md`](backup-restore.md)). |
| `Updates` | Update checks and `app:update` ([`updates.md`](updates.md)). |
| `Tasks` | Tutorial module, disabled by default ([`tasks.md`](tasks.md)). |

## Core services

Services are reached with `service('<name>')`.

From `modules/Core/Config/Services.php` (discovered through the Core's namespace):

| Service | Class | Purpose |
|---|---|---|
| `theme` | `Modules\Core\Theme\ThemeManager` | Active theme per area (back office, public), view resolution, options, regions, layouts. |
| `assets` | `Modules\Core\Frontend\AssetManifest` | Published asset URLs and hashes; republishes stale assets in development. |
| `notifications` | `Modules\Core\Notifications\Notifier` | Sends notifications (`notify()`, `notifyGroup()`, `notifyPermission()`). |
| `activity` | `Modules\Core\Activity\ActivityLogger` | The activity and security log (`activity_log()`, `security_log()`). |
| `storage` | `Modules\Core\Storage\Storage` | The `public` and `private` disks and the upload rules ([`storage.md`](storage.md)). |
| `installstate` | `Modules\Core\Install\InstallState` | Whether the application is configured (`.env` or `writable/install.lock`). |
| `calendar` | `Modules\Core\Calendar\CalendarPublisher` | `publish()` / `unpublish()` of module events: the Events module's publisher (`Config\Calendars::$publisher`) or a `NullCalendar`. |

From `app/Config/Services.php` (overrides of CodeIgniter's services):

| Service | Class | Purpose |
|---|---|---|
| `logger` | `Modules\Core\Log\SafeLogger` | File log that escapes line breaks and control characters in interpolated values. |
| `email` | `Modules\Core\Mail\GuardedEmail` | Mail with the SMTP host checked against the outbound allow-list. |
| `language` | `Modules\Core\I18n\Language` | Translations with the fallback chain requested → base locale → `es` → `en` ([`i18n.md`](i18n.md)). |

Modules can add their own (`modules/Updates/Config/Services.php`: `updatechecker`). Other Core
classes are used directly: `Authorization` and `GrantManager` (permissions), `MaintenanceMode`,
`OutboundClient`, `HtmlSanitizer`, `SafeZip`, `CoreCache`/`CacheInvalidator`, `ScheduledTasks`.
The helpers loaded for every request (`app/Config/Autoload.php`) are `auth`, `setting`, `theme`,
`ui`, `asset`, `can`, `menu`, `redirect`, `notification` and `activity`.

## Request lifecycle

The order comes from `app/Config/Filters.php` plus the aliases registered by
`modules/Core/Config/Registrar.php`, `modules/Auth/Config/Registrar.php`,
`modules/Api/Config/Registrar.php` and kpapy's registrar. `php spark routes` shows the filters of
each route.

**Before** the controller:

1. Required: `forcehttps` (HTTPS in production), `installguard` (before installation every page
   but the web installer redirects to `install`), `pagecache`.
2. Global: `maintenance` (503 while maintenance mode is on, first so nothing touches a database
   being restored), `moduleenabled` (404 for a disabled module's routes), `cspmodules` (adds the
   enabled modules' `$csp` sources and the origins of Settings › Security to the policy), `locale`
   (not on the API), `csrf` (not on the API), `authsession` (session hardening and idle lock; not
   on the API). `install` and `progress/*` skip the filters that need the database or the session.
3. By path: `backend` on `backend` and everything under it — the back-office chain: Shield's
   session login, forced password change (`ForcePasswordReset`), forced 2FA set-up
   (`ForceTwoFactor`), the per-user rate limit (`RateLimit`, limit `backend`) and, as an after
   filter, `PoorMansCron`. `apilocale` on the API.
4. By route: `can:<permission>[,…]` on every back-office route (`can:all,…` requires all; default
   deny, 403 logged as a security event), `reauth` after `can:` on sensitive routes,
   `ratelimit:<limit>` on forms and feeds; on the API, `kpapy` (bearer token, scopes, CORS),
   `kpapy:login` on the app login and `kpapy:public` on public endpoints.

**Controller:** back-office controllers extend `Modules\Core\Controllers\BackendController`
(`render()` sets the `backend` theme area, layout, breadcrumbs; `crud()` / `renderCrud()` for
kpcrudy screens), public ones `FrontendController`. Views go through the theme's
`ViewResolver` (active theme override → parent themes → module view → Core standard view).

**After** the controller: route filters (`can:`, `reauth`, `kpapy`), `PoorMansCron` (back office),
the global `csrftoken` (returns the regenerated CSRF token to scripts), then the required
`pagecache`, `performance`, `toolbar` and `securityheaders` (HSTS in production, Referrer-Policy,
`nosniff`, frame options…; also on 404s). The Content-Security-Policy header is built by
CodeIgniter with nonces (`Config\App::$CSPEnabled`, `autoNonce`): layouts print
`csp_script_nonce()` / `csp_style_nonce()` on the import map and their own tags, and no view
contains inline code (`php spark csp:check`). Details: [`security.md`](security.md),
[`permissions.md`](permissions.md), [`auth.md`](auth.md).

## Caching

`Modules\Core\Cache\CoreCache` groups the Core's caches in **scopes** on top of CodeIgniter's
cache. Every scope has a version number stored in the cache and part of every key; invalidating a
scope bumps the version (the old entries expire, TTL one day by default).

| Scope | What is cached | Invalidated by |
|---|---|---|
| `permissions` | The permissions of the enabled modules (`PermissionCatalogue`). | sync, enable/disable |
| `menus` | The back-office menu tree per user's set of groups and individual permissions (`MenuBuilder`); public menus per menu, user and locale (Menus module); `menu` declarations. | sync, enable/disable, grant changes (`GrantManager`), menu edits, theme and settings changes |
| `settings` | The `setting` declarations read by `SettingsRegistry` and the manifest-defaults settings handler. | sync |
| `widgets` | `widget` declarations; data of async widgets per widget, user, locale and options (`AsyncWidgetInterface::cacheSeconds()`). | sync, enable/disable, a module uninstall (Dashboard) |
| `assets` | Invalidated with theme and settings changes; the asset manifest itself is a file (`public/assets/manifest.json`) read once per request. | sync, theme change, Settings › Actions |
| `modules` | The disabled modules (`ModuleState`) and the other declarations. | sync, enable/disable |
| `calendar` | Events module: provided events and iCal feeds. | event changes |

`Modules\Core\Modules\CacheInvalidator` drops scopes (all of them by default) and fires the event
`modules.cacheInvalidate` with the scopes, so caches kept elsewhere can clear themselves.
`Modules\Core\Cache\CacheClearer` (`php spark app:cache-clear`, Settings › Actions › Clear caches)
invalidates every scope, resets the theme registry, clears CodeIgniter's whole cache and fires
`cache.cleared`. Settings values are read by codeigniter4/settings (handlers `database`, then
`manifest` defaults; `Config\Settings`). Commands: [`commands.md`](commands.md).

## Front end

Bootstrap 5.3, Bootstrap Icons, Quill and FullCalendar are vendored in `public/assets/vendor/`
([`dependencies.md`](dependencies.md)). Each module's and theme's `Assets/` are published by
`assets:publish` (also run by `modules:sync`) to `public/assets/core/`,
`public/assets/modules/<name>/` and `public/themes/<slug>/`, with SHA-256 hashes in
`public/assets/manifest.json`; `asset('<owner>::<path>')` returns versioned URLs. JavaScript is
native ES modules: layouts print an **import map** (`ImportMap`: `core/…`, `<module>/…`,
`theme/…`) and `core/app.js` mounts components found by `data-component`. No inline scripts,
handlers or styles. Details: [`frontend.md`](frontend.md), [`themes.md`](themes.md).

## JSON API

The `Api` module configures kpapy (`app/Config/Kpapy.php`) with the Core's shared permission
resolver (`CrudPermissions`) and `app.maskKey`, so a grant and a masked id mean the same in the
back office and the API. Every endpoint is under `api/v1/` (token only, no cookies or CSRF),
authenticated by personal tokens or the app login with 2FA, limited by scopes; modules declare
their resources in `$api` and route them with `Kpapy\Resource::routes()`. The documentation page
is `backend/api/docs`. Details: [`api.md`](api.md).
