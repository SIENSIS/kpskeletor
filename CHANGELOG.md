# Changelog

All notable changes to kpskeletor are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The version of the Core is
`Modules\Core\Config\Version::CORE`; each module has its own version in its manifest.

## [Unreleased]

### Added

- **Pages built from sections** (session 45, D-45-01, `docs/pages.md`, `docs/sections.md`): a
  page's layout is `article` (its text) or `sections`, an ordered list of sections edited with
  forms (no shortcodes), shown over the whole width of the public layout. Section editor at
  `backend/pages/<id>/sections` (`pages.edit` and the page's record rules): gallery of types with
  thumbnails, forms generated from each type's schema with language buttons, drag and drop and
  keyboard reordering, duplicate, hide, delete, preview; every change is a page revision
  (restoring one brings its sections back). Core registry of **section types**: new manifest key
  `$sectionTypes` (`Modules\Core\Sections\SectionType`, keys `<module>.<name>`), field types
  validated on the server (text, textarea, rich text, image with alt text, link checked like menu
  items, icon, select, number, boolean, list), common options (palette background or image,
  spacing, width, anchor, hidden) and a theme view per type. Six types in Pages: hero banner,
  quick links (icons, tiles, band), cards, image and text, call to action, text. Tables
  `page_sections` and `page_section_translations`, column `pages.layout`. Demo page "Portada amb
  seccions" in the main demo menu.

- **Web Push notifications** (session 44, D-44-01, D-44-02, `docs/notifications.md`): a third
  notification channel, `push`, to the browsers and installed PWAs (phone or desktop) where each
  user turns it on in Profile › Notifications ("Activate notifications on this device", devices
  with "Send a test", "Rename" and "Remove", the logout question "keep or remove this device").
  Standard Web Push with VAPID, free and without provider accounts, implemented in the Core with
  OpenSSL only (RFC 8291 `aes128gcm` encryption and RFC 8292 ES256 tokens, checked against the
  RFC test vectors); tables `push_subscriptions` and `push_queue`; the scheduled task "Send the
  queued push notifications" sends through `OutboundClient` (gone devices removed, retries with
  backoff). Settings `Core.pushEnabled` (off) and `Core.pushContent` (`full` | `generic`) in
  Settings › Security › Notifications; personal routes under `backend/profile/push`; only
  endpoints of the known push services (`Config\Push::$serviceHosts`) are accepted. `.env` keys
  `push.vapidPublicKey`, `push.vapidPrivateKey`, `push.subject`, written by `app:install` and
  `php spark push:keys [--force]`; `security:check` warns when push is on without keys
  (`secrets.pushKeys`). Theme helper `push_logout_attributes()` for logout forms.

- **Module packages and signing** (session 43, D-43-01, D-43-02, `docs/devtools.md`):
  `php spark modules:package <Name> [--theme] [--out=<dir>] [--sign=<secret key file>]` and
  Devtools › Packages (`backend/devtools/packages`, development only) build `<Name>-<version>.zip`
  (one top folder; dotfiles, `node_modules`, `writable`, editor files and refused extensions left
  out; symbolic links refused), validate it with the installer's `PackageValidator`, write
  `<zip>.sha256` and sign it minisign-compatibly with libsodium (encrypted or unencrypted keys,
  verified with the Core's `Minisign`; the public key is shown for `packages.publicKeys`). The
  screen can raise the version (manifest and `CHANGELOG.md`), serves the files from
  `writable/devtools/packages/` and signs from a local form whose key and password are never
  stored (request emptied before the debug toolbar collects it, no session or logs, generic
  errors, `no-store`), followed by a cleanup (debug history, logs, caches) that ends the session.
  Activity entries `packaged`, `version_raised`, `signed`, `cleanup`.
- **Code generator** (session 42, D-41-02, D-42-01 … D-42-03, `docs/devtools.md`): Devtools ›
  Code generator (`backend/devtools/generator`, development only) — a wizard (module, tables and
  columns with portable types and ca/es/en labels, demo data with Faker, CRUD options, API, menu
  and default grants) that previews every file (new files and diffs of changed ones), downloads
  and loads the definition as JSON, and writes it after re-authentication (refused when a file
  changed after the preview; then `modules:sync` and optionally `modules:demo`; activity
  `generated`). The engine is in the Core (`Modules\Core\Generator`): migrations, demo seeder,
  CRUD and API controllers, file-picker handler, manifest, routes, texts and tests that pass;
  existing modules are changed with `nikic/php-parser` (format-preserving), migrations only added.
- `php spark make:module --definition=<file.json> [--dry-run]`: the same engine from the console.
- `nikic/php-parser` `^5` as a development dependency (D-42-01).
- Tutorial 15, "Generating a module with the code generator" (`docs/tutorials/`).
- `php spark groups:export [<file>] [--grants]` and `groups:import <file> [--grants] [--yes]`: copy
  the groups (titles, descriptions, 2FA policy, optionally grants; never users) to another site
  (D-42-04). The generator's grants matrix shows each group's title and internal name.

- `Devtools` module, only for development (D-41-01 … D-41-03, `docs/devtools.md`): installed
  disabled; superadmins only (`devtools.view`, `devtools.write`), from the loopback with a local
  `Host` (`php spark serve`); **CRUD recipes** (`backend/devtools/recipes`): every CRUD/API variant
  with real code, its source file and a link to its `docs/` section (plain-text docs viewer).
- Manifest key `$developmentOnly` (D-41-01): `modules:sync`, `modules:enable`, `modules:install`
  and the Modules screen ("Development only" badge) refuse to install or enable such a module
  outside `development`; a sync there disables it (security log `devonly_disabled`).
- `security:check`: `installation.devtools` (error when a development-only module is enabled
  outside development, info in development).
- Configurable back-office path (D-40-01): `app.backendPath` in `.env` (default `backend`),
  chosen with `app:install --backend-path=<slug>` or the web installer's "Back-office address"
  (validated: format, reserved paths, language codes, module routes, top-level page slugs); helpers
  `backend_path()`, `backend_url()`, `backend_link()` and `backendUrl()` (`core/config.js`);
  `Modules\Core\Routing\BackendPath`; the Core event `backendPathCheck` (Pages refuses a top-level
  page slug).
- `php spark app:backend-path [<new>] [--yes]`: changes the prefix later (maintenance mode, `.env`,
  stored links through the Core event `backendPathChanged`, caches, security log
  `backend_path_changed`).
- `security:check` reports the back-office path (`configuration.backendPath`, info).
- Settings menu groups (D-38-01): the `settings` zone of the back-office sidebar shows five Core
  groups — Usuaris i accés, Seguretat, API, Personalització, Sistema — each visible only while
  one of its entries is; menu group entries in manifests (`'group' => 'group.<name>'`,
  `MenuDefinitions::GROUP_*`) and a `parent` pointing to them (`docs/menus.md`, "Groups of the
  settings zone").
- Public account link (theme option `showAccountLink`, on by default): "Entra" for visitors,
  "Administració" for users who can open the back office, "El meu compte" for the rest
  (`Modules\Core\Frontend\AccountLink`, partial `account_link`; `docs/themes.md`).
- Show/hide button on every password field (`core/forms/password-reveal.js`): automatic for module
  views and kpcrudy forms, opt-out with `data-no-reveal`; the field is hidden again on submit and
  on `pagehide` (`docs/frontend.md`, "Password fields").

- The language in public URLs (D-37-02): `/qui-som` in the site's default language,
  `/es/quienes-somos` in Spanish; internal links keep the prefix (the application's `site_url()`),
  a language switch next to the light/dark switch links to the same page in every language (theme
  option `languageSwitch`), `hreflang` alternates and a sitemap with every translation;
  `Config\LocaleUrls` (`$enabled`, `$excluded`). Back office, API and sign-in pages are unchanged
  (`docs/i18n.md`, "Language in the URL").
- `ContentTranslations::locales()`, `codes()`, `defaultLocale()`, `defaultName()`, `crudOptions()`, `apiOptions()`,
  `defaultText()` and `keepUnavailable()`: the content languages of a module come from the site's
  locales (`docs/i18n.md`, "Content languages"); `CalendarEvent::translated()` for per-language
  calendar titles; `Modules\Core\Tests\ContentLocalesTrait` for module tests with another
  language set.

### Changed

- The default theme's public layout has a full-width mode (`$fullWidth`, used by pages built from
  sections); a theme with its own public layout should honour it (`docs/themes.md`, "Section views
  and palette"). Page duplicates copy the layout and sections; revision diffs show line breaks.
- The service worker (`/sw.js`) shows push notifications and is registered while Web Push is on
  even with the PWA off; `/sw.js` and the PWA icons are served in that case too.
- `NotificationType::CHANNELS` includes `push` and the default `defaultChannels()` returns it:
  types that must never reach a lock screen should return only `database`/`email`. Stored
  preferences with at least one channel get `push` (migration `AddPushToNotificationPreferences`).
- Existing installations: run `php spark push:keys` (or add the `push.*` keys) before turning push
  on.
- `ModuleGenerator::files()` returns `make:module`'s files without writing them (the generator's
  base); `generate()` writes them, with the same output as before.
- Devtools: `DevtoolsRoutes::DRAFT` for the generator's draft-only POSTs (D-42-02); the lock test
  also checks the views in subfolders.

- `ManifestValidator` checks that `$enabledByDefault` and `$developmentOnly` are booleans
  (`validateProperties()` validates raw manifest values).
- Every back-office route, filter pattern, redirect, link and the `make:module` templates use the
  back-office prefix instead of a written `backend/`; the `backend` filter chain, Shield's
  redirects, `RouteSecurityAuditor`, the error pages, the language-prefix exclusions and the probe
  follow it.
- Stored back-office links (notification and calendar links, dashboard quick links, Menus
  addresses) are saved with the canonical prefix `backend/…` and resolved when shown (D-40-02).
  Modules written for 1.0.0 keep working with the default prefix; with another prefix their
  `'backend/…'` literals must become `backend_path()` / `backend_url()` / `backend_link()`
  (`docs/creating-a-module.md`).
- The demo page "Intranet" uses the slug `area-interna` when the back office is at `/intranet`.
- The web installer shows its progress (session 39): a progress bar and a log of the steps
  (checks, keys, database, migrations "n of m", modules, permissions, settings, assets, groups,
  API, superadmin, demo data, `.env`, lock, security check), then a result page that shows the
  one-time values (`.env` to copy, demo passwords) once. The installation runs after the response
  under PHP-FPM / LiteSpeed (`Modules\Core\Install\InstallJob`, `InstallJobs`, routes
  `progress/install/<job>` and `install/result/<job>`, ES module `core/install/progress.js`) and
  inside the request otherwise (no JavaScript, `php spark serve`, mod_php). One installation at a
  time; a second POST answers 409. `Installer::install()` takes an `InstallProgress` listener and
  `ModuleSynchronizer::sync()` a `progress` closure (both optional). The installer pages follow
  the system's dark mode and get the password show/hide button when the Core's scripts are
  published (the installer publishes them when it can).

- The settings entries of Users, Settings, Core, Activity, Api, Menus, Dashboard, Backup and
  Updates now have a `parent` (a Core group); their keys did not change, so saved back-office
  menu customisations still apply. The Activity log's `order` is 85 (was 84). A module's own
  `settings` entries without `parent` still show at the root of the zone: add
  `'parent' => 'group.system'` (or the group that fits).

- **Removed** the constants `ContentTranslations::LOCALES`, `ContentTranslations::FALLBACK_LOCALE`,
  `NewsRepository::FALLBACK_LOCALE`, `News\Controllers\Posts::LOCALES` and
  `TranslatedText::FALLBACK_LOCALE` (D-37-01). Modules that used them: replace them as described in
  `docs/i18n.md` ("Upgrading a module written for 1.0.0").
- The settings `Core.defaultLocale` and `Core.locales` offer every supported locale
  (`Config\App::$supportedLocales`) instead of a fixed ca/es/en list.
- The required content language is the site's default language (`Core.defaultLocale`), not always
  Catalan; a language no longer offered keeps its texts but they are neither shown nor edited.

### Fixed

- The public language switch's button names its visible language code (axe `label-content-name-mismatch`).
- Content forms, readers and APIs (News, Pages, Events, Menus, Tasks) only offered Catalan,
  Spanish and English and always required Catalan: a language added by configuration now reaches
  every translated form, reader and API, slugs and activity summaries use the default language's
  text, and kpapy's translated fields fall back to the site's default language.
- The calendar titles of birthdays and to-do task due dates are written in every site language;
  FullCalendar loads its locale file for any vendored language.
- Help texts and messages that named Catalan (slug help of News posts, News categories and Tasks
  projects; the required label of Menus) name the site's default language.

## [1.0.0] - 2026-11-18

First release. The Core reports version `1.0.0` (`Modules\Core\Config\Version::CORE`) and the
bundled modules require `Core ^1.0`; the modules keep their own versions (0.x, `Auth` and `Tasks`
0.2.0). Everything the skeleton contains, grouped by area. Details: [`docs/`](docs/) (start with
[`docs/architecture.md`](docs/architecture.md)); acceptance criteria 1–13 were checked on fresh
SQLite and MariaDB installations ([`docs/progress.md`](docs/progress.md), "Final acceptance").

### Added

#### Foundation

- CodeIgniter 4.7 application with Shield, Settings and Translations, `siensis/kpcrudy` and
  `siensis/kpapy` from Packagist; PHPUnit (SQLite and MariaDB), PHPStan level 6, php-cs-fixer,
  GitHub Actions CI on PHP 8.2–8.4 and Dependabot.
- Installer: `php spark app:install` (interactive or `--no-interaction`, production by default,
  new keys, first superadmin with optional 2FA, demo data) and the web installer `/install`, which
  disappears once installed.

#### Modules

- Modules in `modules/<Name>/` with a typed manifest (`ModuleManifest`): resources, grants,
  menus, settings, widgets, tasks, notifications, backup, calendars, API, CSP sources and outbound
  hosts, validated by `modules:sync`.
- Loading through CodeIgniter discovery with the generated `app/Config/ModuleNamespaces.php`;
  `modules:sync` (migrations, seeds, registry, permission catalogue, default grants,
  declarations, assets, caches, Shield matrix, OpenAPI document), idempotent.
- Enable and disable modules (a disabled module answers 404), dependencies with version
  constraints, core modules that cannot be disabled, demo data per module (`modules:demo`).
- Modules screen: enable/disable, demo data, sync, uninstall requests; module and theme ZIP
  packages (validation, optional signatures, staged installs applied from the console with
  `modules:install`, uninstall with backups).
- `make:module` (with `--crud` and `--api`), `/new-module` and `/check-module` for Claude Code,
  and the module's `CLAUDE.md`.

#### Themes and front end

- Themes with manifest, layouts, regions, menu locations, options, child themes and view
  overrides; separate back-office and public themes; `make:theme`; default theme on Bootstrap 5.3
  with light/dark mode, responsive layout and a minimal PWA.
- Asset pipeline (`assets:publish`, asset manifest with hashes, `asset()`), import map, native ES
  modules mounted by `data-component`, the Core's JS API (`http`, `confirm`, `toast`, file
  picker); vendored Bootstrap, Bootstrap Icons, Quill and FullCalendar with checksums.
- Strict Content-Security-Policy with nonces, no inline code (`csp:check`).

#### Authentication and security

- Login, remember-me, magic links, password reset, generic messages and rate limits; hardened
  sessions (absolute and idle lifetimes, remote logout, new-device email).
- Two-factor authentication by email or authenticator app, recovery codes, per-group 2FA policy;
  re-authentication for sensitive actions.
- Central RBAC: generated permissions `<module>-<resource>.<operation>`, `can:` on every
  back-office route, implied permissions, escalation rules, Shield's matrix generated;
  `permissions:check`.
- Security headers, rate limits, outbound requests only through `OutboundClient` with an
  allow-list, safe redirects, `SafeZip`, maintenance mode.
- `security:check`, `security:audit` and the Security status page (production checklist,
  `composer audit`, vendored file checksums, external probe).

#### Back office

- Every CRUD through kpcrudy inside the theme (`CrudFactory`), with the shared permission
  resolver and masked ids.
- Generated back-office menu filtered by permission; Menus module for public menus and per-group
  customisation of the back-office menu.
- Settings module with forms generated from the manifests and Settings › Security.
- Users, groups (with 2FA policy), user permissions and the permission matrix; impersonation.
- Dashboard with widgets (server-side and asynchronous), per-user layout and per-group
  configuration.
- Append-only activity and security log, written by the Core for every CRUD write, operation and
  export, with the Activity module's screens.
- Notifications (in-app bell and queued email, user preferences) and the scheduler (one cron line,
  `schedule:run`, scheduled tasks screen, fallback without cron).

#### Content and files

- News (reference module): articles and notices, audiences, publish workflow, RSS, widgets, API.
- Pages: page tree, SEO, revisions, preview, home page, sitemap.
- Storage service (public and private disks, upload rules, re-encoded images) and the Files
  module: file manager, folder permissions, quotas, file picker with signed upload tokens, avatars.
- Events: calendar, audiences, recurrence, reminders, the calendar API for modules, iCal feeds.
- Rich-text editor and HTML sanitising shared by modules.

#### API

- JSON API under `api/v1/` with kpapy: app login with a second factor, personal tokens with
  scopes, the core endpoints (`me`, notifications, news, events), OpenAPI documentation page.

#### Operations

- Encrypted backups and restores (console and back office, retention, safety backup).
- Update checks, notifications and the signed `app:update` from the console.
- `app:cache-clear`, `app:maintenance` and the other commands in
  [`docs/commands.md`](docs/commands.md).

#### Languages

- Catalan by default, Spanish fallback, English; per-user language, the site's available
  languages, translated settings; every text in `ca`, `es` and `en`.

#### Tutorials

- The `Tasks` tutorial module (disabled by default) with the finished code of the tutorials;
  version 0.2.0 adds `tasks.started_at` as the worked example of releasing a module version
  (its own `modules/Tasks/CHANGELOG.md`).
- 14 step-by-step tutorials in [`docs/tutorials/`](docs/tutorials/README.md), each ending with
  the tests that cover it; `tests/unit/Docs/TutorialsTest.php` keeps them in step with the code.

#### Quality

- End-to-end and accessibility tests (Playwright + axe-core, `npm run e2e`): the public site and
  the main back-office screens at phone, tablet and desktop widths in light and dark mode, failing
  on horizontal overflow, CSP violations and WCAG 2.1 AA violations; keyboard skip links.
- CI jobs for the end-to-end tests and secret scanning (gitleaks); `assets:publish --check` also
  checks the `vendor.json` checksums; Dependabot for npm.
- WCAG AA contrast of the default theme in both modes (links, outline buttons, coloured text,
  calendar), keyboard access to scrolled tables, ARIA names in the rich-text editor.
- One minisign verifier for releases and packages (`Modules\Core\Security\Minisign`).

### Fixed

- The Events administration list failed when an event was managed by the Tasks module.
- `app:install` on a fresh clone (no database yet) with `--demo` in production crashed reading the
  settings table instead of refusing with a message.
- The notifications bell's accessible name now contains the unread count shown in its badge
  (WCAG 2.5.3).
- `docs/web-server.md`: the PHP user may write `public/uploads` (the public disk).

[Unreleased]: https://github.com/SIENSIS/kpskeletor/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/SIENSIS/kpskeletor/releases/tag/v1.0.0
