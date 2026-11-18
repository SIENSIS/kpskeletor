# kpskeletor

kp Web Skeletor: a modular CodeIgniter 4 application skeleton (back office, public site, JSON API).
Everything is a module (`modules/<Name>/`) described by a typed manifest: permissions, menus,
settings, widgets, scheduled tasks and API resources are generated from it. Back-office CRUDs use
[`siensis/kpcrudy`](https://packagist.org/packages/siensis/kpcrudy) and the JSON API
[`siensis/kpapy`](https://packagist.org/packages/siensis/kpapy); authentication is CodeIgniter
Shield with two-factor authentication. See [`docs/architecture.md`](docs/architecture.md).

## Requirements

- PHP 8.2+ with `curl`, `fileinfo`, `gd`, `intl`, `json`, `mbstring`, `openssl`, `sodium` and
  `zip`, plus `mysqli` (MariaDB/MySQL) or `sqlite3` (SQLite).
- Composer 2.
- MariaDB 10.6+ / MySQL 8, or SQLite.
- A web server whose document root is `public/` ([`docs/web-server.md`](docs/web-server.md)) and
  HTTPS in production.
- Cron (or the fallback without cron, [`docs/scheduler.md`](docs/scheduler.md)).

## Quick start

```bash
git clone <repository> kpskeletor && cd kpskeletor
composer install
php spark app:install
```

`app:install` asks for the site URL, the database (an existing, empty one) and the first
superadmin, writes `.env` with new keys (`CI_ENVIRONMENT = production`), runs `modules:sync`,
offers two-factor authentication and writes `writable/install.lock`. Without questions:

```bash
INSTALL_DB_PASSWORD=… INSTALL_ADMIN_PASSWORD=… php spark app:install --no-interaction \
    --url=https://intranet.example.com/ --db=mysql --db-name=kpskeletor --db-user=kpskeletor \
    --email=admin@example.com --2fa=email
```

On your own computer: `php spark app:install --env=development --db=sqlite --demo`, then
`php spark serve` (http://localhost:8080). Alternatively, open the site in a browser before
installing: every page leads to the web installer `/install`, which disappears once installed.
With `php spark serve` and `http://localhost` it opens over HTTP without a certificate; on a real
web server it is redirected to HTTPS (`docs/security.md`, "Installer").

Before going live, run `php spark security:check` (the production checklist in
[`docs/security.md`](docs/security.md)). Development workflow, tests and CI:
[`docs/development.md`](docs/development.md).

### Cron

One line runs every scheduled task and the email queue (the installer prints it):

```
* * * * * cd /path/to/kpskeletor && php spark schedule:run >> /dev/null 2>&1
```

Shared hosting, the fallback without cron and troubleshooting: [`docs/scheduler.md`](docs/scheduler.md).

## Documentation

All documents are in English, except the user guide, which is also in Catalan and Spanish.

**Architecture and development**

- [`architecture.md`](docs/architecture.md): modules, Core services, request lifecycle, caching.
- [`creating-a-module.md`](docs/creating-a-module.md): a module step by step, the module checklist, Claude Code.
- [`tutorials/`](docs/tutorials/README.md): 14 step-by-step tutorials built on the `Tasks` module.
- [`installing-modules.md`](docs/installing-modules.md): installing, upgrading and uninstalling modules and themes.
- [`permissions.md`](docs/permissions.md): resources, permission names, wildcards, the matrix.
- [`themes.md`](docs/themes.md): themes, layouts, regions, options, view overrides, child themes.
- [`frontend.md`](docs/frontend.md): assets, import map, components, the JS API, CSP rules.
- [`scheduler.md`](docs/scheduler.md): the cron line and writing scheduled tasks.
- [`notifications.md`](docs/notifications.md): notification types, channels, preferences.
- [`widgets.md`](docs/widgets.md): dashboard widgets, with a tutorial.
- [`api.md`](docs/api.md): the JSON API for client developers and module authors.
- [`commands.md`](docs/commands.md): every `spark` command.
- [`calendar.md`](docs/calendar.md): publishing events from a module, providers, iCal feeds.
- [`files.md`](docs/files.md): disks, folder permissions, the file picker, quotas.
- [`backup-restore.md`](docs/backup-restore.md): backups, restores, disaster recovery.
- [`updates.md`](docs/updates.md): releases, signing, `app:update`, rolling back.
- [`security.md`](docs/security.md): security baseline, threat model, production checklist, incident response.
- [`outbound-requests.md`](docs/outbound-requests.md): the `OutboundClient` and the allow-list.
- [`dependencies.md`](docs/dependencies.md): Composer, audits, Dependabot, vendored front-end libraries.
- [`development.md`](docs/development.md): development environment, tests, CI.
- [`web-server.md`](docs/web-server.md): Apache and Nginx configuration, writable folders.
- [`library-requests.md`](docs/library-requests.md): features requested from kpcrudy and kpapy.

**Areas**

- [`activity.md`](docs/activity.md): the activity and security log.
- [`auth.md`](docs/auth.md): authentication, 2FA, re-authentication, sessions.
- [`cruds.md`](docs/cruds.md): CRUDs with kpcrudy.
- [`events.md`](docs/events.md): the Events module (calendar).
- [`i18n.md`](docs/i18n.md): languages and translations.
- [`menus.md`](docs/menus.md): back-office and public menus.
- [`news.md`](docs/news.md): the News reference module.
- [`pages.md`](docs/pages.md): the Pages module and shared content features.
- [`settings.md`](docs/settings.md): settings and Settings › Security.
- [`storage.md`](docs/storage.md): file storage and upload rules.
- [`tasks.md`](docs/tasks.md): the Tasks tutorial module.
- [`users.md`](docs/users.md): users, groups and the permission matrix.

**Users**

- [`user-guide.md`](docs/user-guide.md): back-office user manual
  ([català](docs/ca/user-guide.md), [español](docs/es/user-guide.md)).

Changes: [`CHANGELOG.md`](CHANGELOG.md).

## Licence

MIT: see [`LICENSE`](LICENSE).
