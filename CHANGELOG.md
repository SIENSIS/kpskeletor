# Changelog

All notable changes to kpskeletor are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The version of the Core is
`Modules\Core\Config\Version::CORE`; each module has its own version in its manifest.

## [Unreleased]

### Added

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

- **Removed** the constants `ContentTranslations::LOCALES`, `ContentTranslations::FALLBACK_LOCALE`,
  `NewsRepository::FALLBACK_LOCALE`, `News\Controllers\Posts::LOCALES` and
  `TranslatedText::FALLBACK_LOCALE` (D-37-01). Modules that used them: replace them as described in
  `docs/i18n.md` ("Upgrading a module written for 1.0.0").
- The settings `Core.defaultLocale` and `Core.locales` offer every supported locale
  (`Config\App::$supportedLocales`) instead of a fixed ca/es/en list.
- The required content language is the site's default language (`Core.defaultLocale`), not always
  Catalan; a language no longer offered keeps its texts but they are neither shown nor edited.

### Fixed

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
