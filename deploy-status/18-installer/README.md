# Session 18 — Installer and CLI completion

Branch `session-18-installer`. A fresh clone now installs with one command (`php spark
app:install`), interactively or with options, on SQLite and MariaDB; an optional web installer
does the same from a browser before the first configuration. `about` and `app:cache-clear` complete
the application commands, and `docs/commands.md` lists every command.

## What was built

- **`php spark app:install`** (interactive by default, every value also an option,
  `--no-interaction` never asks): writes `.env` from the `env` template with
  `CI_ENVIRONMENT = production` (or `--env=development`), a new `encryption.key`
  (`hex2bin:` + 32 random bytes) and `app.maskKey` (64 hex), the base URL and the database; runs
  `modules:sync`; creates the first superadmin (Shield password rules) and offers 2FA
  (`--2fa=none|email|totp`; TOTP is confirmed with a code); loads demo data (`--demo`,
  `--demo=News` also loads Auth first); writes `writable/install.lock`; prints the cron line and a
  reminder to run `security:check` (session 30). Passwords never as options: hidden prompts or
  `INSTALL_DB_PASSWORD` / `INSTALL_ADMIN_PASSWORD`. With a developer's `.env`: `--keep-env` keeps
  it and only adds missing keys.
- **Web installer `/install`** (optional, same service): available only while there is no `.env`
  and no `writable/install.lock` (404 afterwards, and when the configured database has tables);
  every other page redirects to it meanwhile; warns when not served over HTTPS (production still
  forces HTTPS); when the project root is not writable by the web server it completes the
  installation and shows the `.env` content once to save by hand.
- **`php spark about`**: versions, environment, base URL, installation date, whether the keys are
  set (never their values), time zone, languages, cache, database and modules.
- **`php spark app:cache-clear`**: Core caches (every `CoreCache` scope + theme registry) and
  CodeIgniter's cache, then the `cache.cleared` event. Settings › Actions › "Clear caches" uses the
  same `CacheClearer`.
- `docs/commands.md` (new "Application" section; every skeleton command is listed), `README.md`
  quick start with the cron line, `docs/development.md`, `docs/security.md` ("Installer").
- CI: a fresh-install step on SQLite (development, demo data, `permissions:check`, `about`, HTTP
  smoke test: `/login` 200, `/install` 404) and on MariaDB (production into an empty database).

## How it was built

| Piece | Where |
|---|---|
| Installation service (steps, refusals, demo order, `install.superadmin` event) | `modules/Core/Install/Installer.php` |
| Options and validation (field => message) | `modules/Core/Install/InstallOptions.php` |
| `.env` rendering/writing (template lines replaced, single-quoted escaping, atomic, `0640`), `complete()` for `--keep-env` | `modules/Core/Install/EnvWriter.php` |
| Keys | `modules/Core/Install/KeyGenerator.php` |
| Configured state (`.env` / lock), lock file (JSON) | `modules/Core/Install/InstallState.php`, `service('installstate')` |
| New values applied to the running process (env vars, database, encryption, mask key) | `modules/Core/Install/RuntimeConfig.php` |
| Prompt abstraction for listeners; console implementation (hidden input) | `modules/Core/Install/{InstallPrompt,CliPrompt}.php` |
| Commands | `modules/Core/Commands/{AppInstall,About,AppCacheClear}.php` |
| Cache clearing | `modules/Core/Cache/CacheClearer.php` |
| Web installer (standalone views, no theme or settings) | `modules/Core/Controllers/WebInstaller.php`, `modules/Core/Views/install/` |
| Redirect before installation | `modules/Core/Filters/InstallGuard.php` (required filter `installguard`), `app/Config/Filters.php` |
| Base URL from the request before installation | `app/Config/App.php` (`requestBaseURL()`) |
| Mask-key boot check after installation only; toolbar without Shield's collector before | `modules/Core/Config/Events.php` |
| 2FA offer (Auth listens to `install.superadmin`) | `modules/Auth/Services/InstallTwoFactor.php`, `modules/Auth/Config/Events.php` |
| Texts (ca/es/en) | `modules/Core/Language/*/Install.php`, `Auth.install.*`, `Core.activity.installed|superadmin_created` |

Order of the steps (D-18-08): validate → keys and `.env` content → apply to the process → database
reachable and empty → `modules:sync` → superadmin → demo data (a failure is reported, not fatal)
→ activity log → `install.superadmin` (2FA) → **`.env` and lock last**, so a failed installation
leaves nothing that blocks a new attempt. Routes: `GET|POST install`. No new tables, permissions or
settings. Activity log actions: `Core/installed`, `Core/superadmin_created` (security).

## Decisions and deviations

D-18-01 … D-18-08 (all accepted proposals, no open decisions): production keeps forcing HTTPS on
the web installer (warning outside production); redirect + request base URL before installation;
no passwords as options; demo data follows the installed environment and pulls required modules
first; 2FA through an event so the Core does not depend on Auth; `--keep-env`; `security:check`
summary replaced by a reminder until session 30; `.env`/lock written last.

Fixed in earlier work because it blocked this session: `AuthDemoSeeder` skipped existing users only
by email; a superadmin called `admin` with another email made the Auth demo data fail (unique
username). It now also skips an existing username.

## How to try it

```bash
composer install
php spark app:install                      # interactive
php spark app:install --env=development --db=sqlite --demo --email=you@example.com
php spark about
php spark app:cache-clear
```

Web installer: with no `.env` and no `writable/install.lock`, run
`CI_ENVIRONMENT=development php spark serve` and open http://localhost:8080 (it redirects to
`/install`). To start again: delete `.env`, `writable/install.lock` and the database file/tables.

Console output of an interactive installation (SQLite, development, demo News, TOTP; secrets and
recovery codes replaced by X):

```
Installing the application. Press Enter to accept the value in brackets.
Environment [production, development]: development
Site base URL (e.g. https://intranet.example.com/) : 
Database (mysql = MariaDB/MySQL, sqlite) [mysql, sqlite]: sqlite
SQLite file name (in writable/)  [database.db]: inter.db
Table prefix (optional) : 
Username of the first superadmin  [admin]: 
Email of the first superadmin : owner@example.com
Password of the first superadmin: 

Repeat the password: 

Load demo data? [n, y]: y
Modules (comma separated, or "all"): Auth, News  [all]: News
Installing: migrations, permissions, settings and assets…
  CodeIgniter\Shield: 1 migration(s) run.
  CodeIgniter\Settings: 3 migration(s) run.
  Kpapy: 2 migration(s) run.
  Modules\Core: 14 migration(s) run.
  Modules\Auth: 5 migration(s) run.
  Modules\Api: 1 migration(s) run.
  Modules\Dashboard: 2 migration(s) run.
  Modules\News: 6 migration(s) run.
  Auth: initial data loaded.
  News: initial data loaded.
  Core 0.1.0 installed.
  Auth 0.2.0 installed.
  Activity 0.1.0 installed.
  Api 0.1.0 installed.
  Dashboard 0.1.0 installed.
  News 0.1.0 installed.
  Settings 0.1.0 installed.
  Users 0.1.0 installed.
  80 permission(s) added to the catalogue.
  … (default grants per module)
  Settings, widgets, tasks and other declarations updated.
Set up two-factor authentication for this superadmin? (none, totp = authenticato 
r app, email = codes by email) [none, totp, email]: totp
Add this account to your authenticator app (open the URI or type the secret):
  otpauth://totp/kpskeletor%3Aowner%40example.com?issuer=kpskeletor&secret=XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
Secret: XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
Code shown by the app : 123456
Recovery codes (shown only once; keep them in a safe place):
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
  XXXXX-XXXXX
Two-factor authentication with the authenticator app is on.
.env written to /home/user/kpskeletor/.env (with new encryption and mask keys).
Demo data loaded: Auth, News.
First superadmin: admin <owner@example.com>.

Add this line to the crontab of the web server user (scheduled tasks, queued emails):
  * * * * * cd '/home/user/kpskeletor' && /usr/bin/php8.3 spark schedule:run >> /dev/null 2>&1

Before going live, run php spark security:check (available from session 30) and read docs/security.md.
Installation complete. Lock file: /home/user/kpskeletor/writable/install.lock.
```

## Tests and checks

- New tests: `EnvWriterTest` (7: DotEnv round trip of awkward passwords, template replacement,
  refusals, `0640`, `complete()`, keys), `InstallerTest` (12: install from scratch on SQLite with
  production `.env`, keys, sync, superadmin, lock, cron, activity; root not writable; refusals with
  `.env`, lock or tables; per-field validation; Shield password rules; demo in production refused;
  `--demo=News` loads Auth first; demo users never replace the superadmin; a failed demo does not
  undo the install; 2FA by email with recovery codes), `WebInstallerTest` (10: form and HTTPS
  warning, 404 with lock / `.env` / tables, invalid values without echoing passwords, full install
  showing `.env` when the root is not writable, `.env` written otherwise, redirect of other pages,
  guard idle once configured), `AppCommandsTest` (5: non-interactive install, missing password,
  existing `.env`, `about` without secrets, `app:cache-clear` + events). `AuthConfigTest` updated
  (`authsession` also skips `install`).
- `vendor/bin/phpunit`: **OK (809 tests, 4174 assertions)** (775 before the session).
- `vendor/bin/phpstan analyse`: **No errors**. `vendor/bin/php-cs-fixer fix --dry-run`: **0 files**.
  `php spark csp:check`: no inline scripts, handlers or styles.
- By hand: interactive console install (TOTP confirmed with a real code), non-interactive SQLite
  install, **production install on MariaDB 10.11** with a database password containing quotes
  (read back correctly; `permissions:check` OK: 80 permissions, 156 routes), refusal of a second
  run and of a non-empty MariaDB database, the whole web installation with Playwright followed by
  the first login (no CSP violations, no horizontal scroll at 390 px).

## Screenshots

Playwright against `CI_ENVIRONMENT=development php spark serve` (unconfigured), device scale 2.

1. `screenshots/01-web-installer.png` — `/backend` before installation redirects to the web
   installer; HTTP warning (development).
2. `screenshots/02-web-installer-phone.png` — the form at phone width (390 px).
3. `screenshots/03-web-installer-errors.png` — invalid values: per-field messages, passwords not
   sent back.
4. `screenshots/04-web-installer-done.png` — installation complete (SQLite, development, demo
   data), cron line and next steps.
5. `screenshots/05-first-login.png` — first login of the new superadmin, dashboard with the demo
   News.

Not captured: the "copy this `.env`" page (this environment runs as root, so the project root is
always writable; it is covered by `WebInstallerTest::testInstallsAndShowsTheEnvWhenTheRootIsNotWritable`)
and dark mode (the installer is a standalone page without the theme's colour modes).

## Known issues

- The Auth demo users still share a fixed password (`Demo-kpskeletor-2026`); the specification
  asks for random passwords shown once. The installer only loads demo data in development (or with
  `packages.allowDemoInProduction`). For session 19's review.
- The web installer's page is in the default locale and light mode only (no theme before
  installation).
- `app.allowedHostnames` is not written by the installer (an array; documented in
  `docs/security.md`).
- Composer in this environment: `COMPOSER_AUTH` is rejected ("Could not authenticate against
  github.com") and `api.github.com`/`codeload.github.com` return 403 through the proxy; installing
  needed `env -u COMPOSER_AUTH composer install --prefer-source` plus the `phpstan/phpstan` zip
  seeded into Composer's cache (known issue 04 still open).

## What the next session must know

- Session 19 (MVP review): acceptance criterion 1 is covered by the CI fresh-install steps
  (SQLite and MariaDB); review the demo users' fixed password.
- Session 30: replace the `Install.cli.securityCheck` reminder in `AppInstall` (and the web
  installer's last page) with a call to `security:check` and its summary.
- Tests that need an unconfigured installation inject `service('installstate')` on temporary
  folders (`Modules\Core\Tests\InstallFixturesTrait`); the testing environment counts as
  configured.
- Modules can clear their own caches by listening to `cache.cleared` (`CacheClearer::EVENT`).
