# Changelog

All notable changes to kpskeletor are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The version of the Core is
`Modules\Core\Config\Version::CORE`; each module has its own version in its manifest.

## [Unreleased]

The skeleton has not been released yet: the Core reports version `0.1.0` while it is being built.
Everything below is what the skeleton contains so far, grouped by area. Details: [`docs/`](docs/)
(start with [`docs/architecture.md`](docs/architecture.md)).

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

[Unreleased]: https://github.com/SIENSIS/kpskeletor/commits/main
