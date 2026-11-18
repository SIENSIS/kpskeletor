# Session 36 — Final acceptance and release

Branch: `ccr-86ec5c0e-ha268p` · Pull request: see `docs/progress.md` · Date: 2026-11-18

## What was built

- **Acceptance criteria 1–13 checked** on fresh clones: SQLite (development, every module and
  demo data, Tasks enabled), MariaDB 10.11 (production) and a production-like site (that MariaDB
  install behind nginx + PHP-FPM with HTTPS). Every criterion passes; the table with the evidence
  is in [`docs/progress.md`](../../docs/progress.md), "Final acceptance (session 36)".
- **Fixes found by the acceptance run:**
  - `app:install` on a fresh clone with `--demo` in production crashed with "Unable to connect to
    the database" (it read `Packages.allowDemoInProduction` from the settings table before the
    database existed). It now reads the configuration (`packages.allowDemoInProduction` in `.env`)
    and refuses with the usual message (`Installer::demoAllowedInProduction()`).
  - The notifications bell failed WCAG 2.5.3 (axe `label-content-name-mismatch`) on every
    back-office page as soon as there was one unread notification: its name said "una per llegir"
    while the badge showed "1". The names are now "Notificacions per llegir: 1" /
    "Notificaciones por leer: 1" / "Unread notifications: 1" (axe only accepts the visible text
    as a whole word at the start or the end of the name; checked with axe on minimal markup).
  - `docs/web-server.md` never gave the PHP user write access to `public/uploads` (the public
    disk), so public uploads — and the probe's "uploads are not executed" check — failed on a site
    set up by the docs.
- **Version 1.0.0:** `Modules\Core\Config\Version::CORE = '1.0.0'`; the 14 bundled modules require
  `'Core' => '^1.0'` (`^0.1` would refuse 1.0.0); `CHANGELOG.md` section `[1.0.0] - 2026-11-18`
  and an empty `[Unreleased]`; `docs/commands.md`, `docs/creating-a-module.md` and tutorial 13
  updated. The Updates tests now derive their release numbers from `Version::CORE` (installed,
  next minor, the one after, an older one), so they follow every future release.
- **Release procedure** in [`docs/updates.md`](../../docs/updates.md) ("Release process",
  "Signing releases"): version and changelog, signed tag, `git archive` with one top-level folder,
  `sha256sum`, `minisign -S` with the owner's offline key, verification before uploading, the
  three release assets, where the public key lives (`Config\Updates::$publicKeys`, `.env` can
  replace it), planned key rotation through a release that trusts both keys, and what to do with a
  lost or leaked key.

## How it was built

- No new tables, routes, permissions, commands or settings.
- Changed code: `modules/Core/Install/Installer.php`, `modules/Core/Commands/AppInstall.php`,
  `modules/Core/Language/{ca,es,en}/Core.php` (bell labels), `modules/Core/Config/Version.php`,
  `modules/*/Config/Module.php` (`$requires`).
- Tests added or changed: `InstallerTest::testDemoInProductionIsDecidedByTheConfigurationNotTheMissingDatabase`,
  `NotificationScreensTest::testTheBellsAccessibleNameContainsTheVisibleCount`,
  `UpdatesTestCase` (versions relative to `Version::CORE`), Core fixtures requiring `Core ^1.0`.
- Production-like setup used for criterion 12 (in the container, not in the repository): a copy
  of the MariaDB install in `/var/www/kpskeletor`, owned as in `docs/web-server.md`; nginx with
  the documented server block on 443 (HTTP/2) plus a port-80 redirect; a certificate from a local
  test CA, trusted only by the probe (`php -d curl.cainfo=<ca.crt> spark security:check --probe=…`,
  run as `www-data`).

## Decisions and deviations

- No new decision entries. D-00-48 says the owner's public key ships in `Config\Updates`; it is
  still empty because the owner has not provided it yet (see "Known issues").
- Module versions stay as they are (0.x; Auth and Tasks 0.2.0): only the Core is 1.0.0, as
  `CHANGELOG.md` explains (each module has its own version, D-01-xx).

## How to try it

```bash
# SQLite, development, everything (one command)
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --url=http://localhost:8091/ \
    --db=sqlite --db-name=acc.db --email=admin@example.com --demo --2fa=none --no-interaction
php spark modules:enable Tasks && printf 'y\n' | php spark modules:demo Tasks
php spark serve --port 8091
E2E_BASE_URL=http://localhost:8091 E2E_USER=admin@example.com E2E_PASSWORD='…' npm run e2e

# MariaDB, production
INSTALL_DB_PASSWORD='…' INSTALL_ADMIN_PASSWORD='…' php spark app:install --url=https://<site>/ \
    --db=mysql --db-host=127.0.0.1 --db-name=kpacc --db-user=kpacc --email=admin@example.com --2fa=none --no-interaction
sudo -u www-data php spark security:check --probe=https://<site>/
```

## Tests and checks

- `vendor/bin/phpunit` and the end-to-end run: see "Final checks" at the end of this file.
- PHPStan level 6: `[OK] No errors`. php-cs-fixer: `Found 0 of 1001 files that can be fixed`.
  `php spark csp:check`: no inline scripts, handlers or styles. `composer validate --strict`:
  valid. `composer audit --locked`: no security vulnerability advisories found.
- **Criterion 1** — fresh installs: MariaDB production `app:install` 1.8 s, security check at the
  end "19 ok, 4 info, 7 warnings, 0 errors"; SQLite development with demo "Demo data loaded: Auth,
  Events, Files, Menus, News, Pages" (+ Tasks); `/` 200, `/news` 200, `/news/feed.xml` 200,
  `/login` 200, `/install` 404, `/backend` 302 → login.
- **Criterion 2** — `make:module Events2` + `modules:sync`: "Modules\Events2: 1 migration run …
  Events2 0.1.0 installed … default grants applied (1 added) … events2: assets published";
  `git status`: only `modules/Events2/` and `app/Config/ModuleNamespaces.php`;
  `permissions:check`: "171 permissions in the catalogue, 345 routes checked"; its tests
  `OK (5 tests, 12 assertions)`.
- **Criterion 3** — `modules:disable Events2`: `/backend/events2` 404, no menu entry or widget,
  task "Inactive"; `modules:uninstall Events2 --yes` then `modules:install Events2.zip --enable`:
  installed and working; hostile packages (exit 1 each):
  `The archive contains a forbidden path: Evil/../../escape.php`,
  `The archive contains a symbolic link (Evil/link)`, `The manifest declares no class`.
- **Criterion 8** — `backup:create` (SQLite demo site) → upload and restore in the back office of
  a clean install: "The backup was restored. Backup of the previous site: …-safety-….kpbackup.
  The backup's secrets differ from this server's .env: encryption.key, app.maskKey"; 9 news and the
  demo users present afterwards; maintenance mode off; a byte-flipped copy: `backup:verify` →
  "Wrong password, or the file is not this backup".
- **Criterion 12** — production-like site, as `www-data`:
  `security:check --probe=https://localhost/` → exit 0, "26 ok, 5 info, 6 warnings, 0 errors"
  (warnings: base URL is local, web user unknown from the console, unsigned packages accepted,
  superadmin without 2FA, minimum password length 8, `composer audit` never run on this server);
  probe group 7/7 ok. Development install: exit 1, "6 errors" (environment, debug, HTTPS, cookie
  `Secure`, HTTP base URL, demo data).
- **End-to-end** (`npm run e2e` against the SQLite demo site, phone/tablet/desktop × light/dark):
  see "Final checks".

## Screenshots

Taken with Playwright at device scale factor 2 (`deploy-status/screenshot.mjs` for the
development sites; a small script with `ignoreHTTPSErrors` for the HTTPS site).

| File | What |
|---|---|
| `screenshots/01-make-module-events2-screen.png` | Criterion 2: the screen generated by `make:module Events2`, with its sidebar entry |
| `screenshots/02-make-module-events2-widget.png` | Criterion 2: the dashboard with the generated Events2 widget |
| `screenshots/03-backup-restore-form.png` | Criterion 8: restoring the console backup in the back office (password, parts, typed confirmation) |
| `screenshots/04-backup-restore-done.png` | Criterion 8: the finished restore (safety backup, changed secrets reported) |
| `screenshots/05-production-security-status.png` | Criterion 12: Security status on the production-like HTTPS site — 0 errors, probe 7/7, effective CSP and headers |
| `screenshots/06-production-dashboard-phone-dark.png` | The production site's dashboard at phone width in dark mode |
| `screenshots/07-bell-label-unread.png` | The bell with one unread notification (accessible name "Notificacions per llegir: 1") |

## Known issues

- **The owner's minisign public key is not in `Config\Updates::$publicKeys` yet** (D-00-48): until
  it is, every installation refuses archive updates unless `updates.publicKeys` is set in `.env`.
  The owner sends the `RW…` line of `minisign.pub` and it is added before tagging `v1.0.0`.
- Criterion 9 was checked through the tests' fake GitHub (real `OutboundClient`, fake transport):
  the repository has no release yet. The first real check is `v1.0.0` → the next release.
- A disabled module's ES modules stay listed in the import map (static files; nothing loads them
  without the module's views).
- `app:install` logs "Table … doesn't exist" errors in `writable/logs` while it reads settings
  before the migrations have run (caught; the install succeeds). Cosmetic, but alarming in a fresh
  production log.
- The probe's wrong-password attempts lock logins from the server's own IP for a few minutes
  (documented in `docs/security.md`); run it before logging in, or set
  `productioncheck.probeLoginPath = ""`.
- `HEAD /login` answers 404 (CodeIgniter routes `GET` only); uptime monitors should use `GET`.
- Composer needed the known issue 04 workaround again: `phpstan/phpstan` from a shallow clone of
  tag 2.3.5 copied into a `--prefer-source` install made in a scratch copy (project files
  unchanged). The `.git` folders that `--prefer-source` leaves inside `vendor/` (≈ 6 GB) filled
  the session's disk once and were removed.

## What the next session must know

No session follows. The owner adds the public key to `Config\Updates`, then creates and pushes
the signed tag `v1.0.0` and publishes the release with its archive, checksum and signature
(`docs/updates.md`).

## Final checks

- `vendor/bin/phpunit` (full suite, SQLite, 10 m 33 s): `Tests: 1329, Assertions: 17764,
  Failures: 1` — the failure was `DocumentationTest::testRelativeLinksResolve` on the link to this
  report, written while the suite was running; `vendor/bin/phpunit tests/unit/Docs` afterwards:
  `OK (13 tests, 320 assertions)`. The later PHPStan-only refactor of `UpdatesTestCase`:
  `vendor/bin/phpunit modules/Updates`: `OK (35 tests, 223 assertions)`. (Session 35: 1327 tests.)
- `vendor/bin/phpstan analyse`: `[OK] No errors`.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: `Found 0 of 1001 files that can be fixed`.
- `php spark csp:check`: no inline scripts, handlers or styles; `php spark assets:publish --check`:
  published assets up to date (vendor checksums included); `php spark permissions:check`: OK.
- `npm run e2e` against the SQLite demo site (with an unread notification, which exposed the bell
  issue): **`175 passed, 5 skipped (5.6m)`**, no axe, overflow or CSP failure (before the fix:
  95 passed, 80 failed on `label-content-name-mismatch`).
