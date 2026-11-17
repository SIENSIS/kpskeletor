# Session 30 — security:check and the Security status page

Branch: `ccr-f19bfaf6-ek95xk` · Pull request: [SIENSIS/kpskeletor#38](https://github.com/SIENSIS/kpskeletor/pull/38) · Date: 2026-11-17

## What was built

- **`php spark security:check [--probe=<url>] [--json] [--only=<group>]`**: the production
  checklist. Every check prints its status (ok, info, warning, error), what it means and how to
  fix it; exit code 1 when a check is in error (deployment pipelines). Groups: `configuration`,
  `secrets`, `installation`, `accounts`, `dependencies`, `outbound`, and `probe` with `--probe`.
- **`php spark security:audit [--from=<file>]`**: runs `composer audit` (or imports CI's JSON
  output) and stores the result with its date; `security:check` reports "last checked on …" and
  warns after 7 days.
- **Security status page** (`backend/security`, permission `security.check`, superadmin and
  admin): the same results grouped, "Check again", the last external probe, and the **effective**
  Content-Security-Policy and security headers (configuration + modules' `$csp` + origins of
  Settings › Security). "Run external probe" is a deliberate action behind re-authentication.
- **Dashboard widget "Security status"** (`core.security-status`): the summary of the last run and
  the checks in error (it never runs the checks itself).
- **Daily task** "Security checks and composer audit" (04:43): `composer audit`, every check, the
  stored results, and a notification to the superadmins when a check turns red (once).
- **`app:install`** ends with the summary of `security:check` (errors and warnings) for the
  environment it installed.
- Module checks through the new Core event `securityChecks`: Auth (superadmins without 2FA,
  optional-2FA groups, password length), Api (CORS never `*`, personal tokens without expiry),
  Backup (`backup.password` when scheduled backups are on, backup files not readable by others
  and never inside `public/`), Updates (a release repository without signing keys). The Core also
  warns about unsigned module packages, `packages.autoEnable` and a missing
  `public/uploads/.htaccess` (notes of sessions 13, 28 and 29).

## How it was built

- `modules/Core/Security/Check/`:
  - `CheckResult` (group, stable id `<group>.<what>`, status, title, message, fix),
    `SecurityReport` (results, counts, worst status, error ids, JSON round trip),
    `SecurityCheck` (interface: `groups()`, `run(SecurityReport)`), `SecurityChecks` (the
    collector passed to the `securityChecks` event), `SecurityChecker` (Core checks + event,
    `--only`, a throwing check becomes an error).
  - `Checks/ConfigurationCheck`, `SecretsCheck`, `InstallationCheck`, `DependenciesCheck`,
    `OutboundCheck` (everything injectable, so tests build a development and a hardened
    configuration without touching the real one).
  - `EffectivePolicy` (the CSP exactly as the `cspmodules` filter builds it, nonces shown as
    `'nonce-…'`, unsafe sources; the headers of `securityheaders`), `FileAccess` (modes; "writable
    by the web user" from `productioncheck.webUser` with POSIX, or `is_writable()` on a web
    request, D-30-04), `SecretScanner` (`git ls-files`; private keys, GitHub/GitLab/AWS/Slack/
    Stripe tokens, `.env` secrets with a value; placeholders and the `security-check: ignore`
    marker skipped), `ComposerAudit` (`proc_open` with an argument list, stored in
    `writable/security/composer-audit.json`), `SecurityStatus` (`status.json`, `probe.json`; the
    "turned red" notification).
  - `Probe/ProbeClient` (curl, no proxy, no redirects, only the base URL host and
    `allowedHostnames`, HTTPS except the explicit HTTP redirect check, 1 MB, timeout) and
    `Probe/SiteProbe` (headers, hidden paths, PHP in `uploads/`, open redirect, login throttling,
    HTTP → HTTPS, `progress/*` answers; D-30-03).
- `modules/Core/Config/ProductionCheck.php` + `app/Config/ProductionCheck.php` (`.env`
  `productioncheck.*`).
- Commands `modules/Core/Commands/SecurityCheck.php`, `SecurityAudit.php`; `AppInstall` prints
  the summary.
- Page: `modules/Core/Controllers/SecurityStatus.php`, views `modules/Core/Views/security/`
  (`index`, `_results`, `probe`); widget `Widgets/SecurityStatus.php` + view; task
  `Tasks/SecurityCheckTask.php`; notification `Notifications/Types/SecurityCheckFailed.php`.
- Manifest (Core): resource `security` (name `security`, extra `check`) → permission
  `security.check` (144 permissions now), admin grant, menu "Security status" (settings zone,
  order 84), task, widget, notification.
- Routes: `GET backend/security` (`can:security.check`), `POST backend/security/refresh`
  (`+ ratelimit:forms`), `GET|POST backend/security/probe` (`+ reauth`, POST also
  `ratelimit:forms`); the probe always targets the configured base URL.
- Activity: `security_check` (activity) and `security_probe` (security entry).
- Module checks: `modules/Auth/Services/AccountSecurityCheck.php`,
  `modules/Api/Services/ApiSecurityCheck.php`, `modules/Backup/Services/BackupSecurityCheck.php`, `modules/Updates/Services/UpdatesSecurityCheck.php`,
  registered in each module's `Config/Events.php`.
- Texts: `modules/Core/Language/{ca,es,en}/SecurityCheck.php` (new file), `securityCheck` keys in
  the Auth, Api and Backup language files, `Install.cli.securityCheck*`, `Core.activity.*`.
- No tables, no migrations, no new settings, no assets (no JavaScript needed).

## Decisions and deviations

- **D-30-01 (decide, Open):** URLs, the `securityChecks` event, `Config\ProductionCheck` keys,
  `security:audit`, admin grant. Built so it can still change.
- D-30-02 (levels), D-30-03 (what the probe sends), D-30-04 (web-writable from the console and the
  page), D-30-05 (stored results, task, notification): accepted.
- PHP's development server on this computer (D-19-05) does not force HTTPS nor `Secure` cookies:
  the page shows those two as information there instead of errors.
- Not added: a gitleaks step in CI (the specification lists secret scanning in CI; the server-side
  scanner covers tracked files).
- Environment: `composer install` could not download `phpstan/phpstan` 2.3.5 (its only download
  is a zip from api.github.com, refused with 403 by this environment's proxy); with the owner's
  approval it was installed from its git tag (the same commit as `composer.lock`) through a
  temporary, untracked copy of the lock file. `composer.json`/`composer.lock` are unchanged.

## How to try it

```bash
php spark security:check                     # everything; exit code 1 on errors
php spark security:check --only=secrets      # one group
php spark security:check --json
php spark security:audit                     # composer audit, stored with its date
php spark security:check --probe=https://intranet.example.org/   # on the real server
```

Back office: Settings › **Security status** (`/backend/security`) as a superadmin or admin;
"Check again"; "Run external probe" (asks for the password when the last re-authentication is
older than 15 minutes). Dashboard: the "Security status" widget.

### Fresh production install (acceptance criterion 12, by hand)

`INSTALL_ADMIN_PASSWORD=… php spark app:install --env=production --url=https://intranet.example.org/ --db=sqlite --email=root@example.org --2fa=none --no-interaction`
on a clean copy ends with:

```
Security check (php spark security:check; errors and warnings, details on the Security status page):
  [warning] Permissions of writable/: 20 entries are more open than 0750 (folders) / 0640 (files): …
  [warning] What the web server may write: Unknown from the console: set productioncheck.webUser …
  [warning] Module and theme packages: Unsigned packages are accepted (packages.requireSignature is off or packages.publicKeys is empty).
  [warning] Superadmins with two-factor authentication: 1 superadmins without 2FA: admin.
  [warning] Minimum password length: 8 characters (recommended: at least 12).
  [warning] composer audit: Never checked on this server.

19 ok, 5 information, 6 warnings, 0 errors.
```

After the checklist steps (`chmod` of `writable/`, `productioncheck.webUser = www-data`,
`php spark security:audit`): `22 ok, 5 information, 3 warnings, 0 errors`, exit code 0 (the
remaining warnings are unsigned packages, the superadmin's 2FA and the password length, which the
owner decides).

The same installation with `CI_ENVIRONMENT = development`: exit code 1,

```
  [error]   Environment: CI_ENVIRONMENT is "development": debug features may be on.
  [error]   Debug toolbar and detailed errors: CI_DEBUG is on: …
  [error]   HTTPS for every request: forceGlobalSecureRequests is off.
  [error]   Cookies: Missing: Secure.
  [warning] Strict-Transport-Security (HSTS): Not sent: browsers may still use plain HTTP.
17 ok, 5 information, 4 warnings, 4 errors.
```

`chmod 777 modules` → `[error] What the web server may write: The web server can write: modules.`
(exit code 1).

### The probe

There is no web server with HTTPS in this environment (no nginx/Apache, and installing one was
not allowed), so `--probe` was run for real only against `php spark serve`, where it refuses the
plain-HTTP URL as expected:

```
  [error]   Probed site: http://localhost:8080/ is not this site (localhost).
            Fix: Probe the base URL or one of app.allowedHostnames, over https.
```

Every probe step is covered by `SiteProbeTest` against a hardened and a misconfigured fake site
(the PHP file in `uploads/` is really written and removed). Run it on the real server before going
live.

## Tests and checks

- New tests: `modules/Core/Tests/SecurityCheckTest.php` (fails on a development configuration,
  passes on a hardened one; `--only`; a throwing check; report round trip; unsafe and report-only
  CSP; the effective CSP with module and Settings origins; file modes; the secret scanner; the
  stored `composer audit` and its age; vendored checksums), `SiteProbeTest.php` (the client never
  leaves the site; each probe passes and fails; another site is refused),
  `SecurityStatusScreenTest.php` (permissions and forged POSTs; the page shows the same results as
  the stored run; `security:check --only`; an origin added in Settings › Security appears in the
  effective CSP; "Check again" logged; the probe needs re-authentication; the widget; one
  notification to superadmins when a check turns red; the daily task), and
  `AccountSecurityCheckTest`, `ApiSecurityCheckTest`, `BackupSecurityCheckTest`, `UpdatesSecurityCheckTest` in their modules.
  `PermissionsCommandsTest`: 144 permissions.
- `vendor/bin/phpstan analyse`: no errors. `vendor/bin/php-cs-fixer fix --dry-run`: clean.
- `php spark csp:check`: no inline scripts, handlers or styles.
- `php spark modules:sync` and `php spark permissions:check` (on the temporary installation):
  `Permissions OK: 144 permission(s) in the catalogue, 300 route(s) checked.`

### Results

- `vendor/bin/phpunit`: **OK (1213 tests, 7387 assertions)**, 9 min 56 s (1188 tests before
  this session).
- `vendor/bin/phpstan analyse` (level 6): `[OK] No errors`.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: 0 of 929 files to fix.
- `php spark csp:check`: no inline scripts, handlers or styles.
- `php spark permissions:check` (temporary install): 144 permissions, 300 routes checked.
- Fixed during the session: tests that list the Core's permissions, tasks and widgets exactly
  (`CatalogueBuilderTest`, `RegistrySyncTest`, `ShieldMatrixTest`, `PermissionsCommandsTest`);
  the Dashboard tests leave the new Core widget out through `$hiddenWidgets`.

## Screenshots

Taken with `deploy-status/screenshot.mjs` (`screenshots.json`) against `php spark serve` on a
production install with the Auth and News demo data, signed in as the superadmin, device scale
factor 2; no CSP violation, no horizontal scroll.

| File | What |
|---|---|
| `screenshots/01-security-status.png` | The page (full height): summary, every group, no probe yet, effective CSP and headers. Errors are real for this demo server: `http://localhost` base URL, demo data, and the dev server runs as root (so it can write the code) |
| `screenshots/02-security-status-phone.png` | Phone width (390 px) |
| `screenshots/03-security-status-dark.png` | Dark mode; HTTPS on the local development server shown as information (D-19-05) |
| `screenshots/04-probe-confirmation.png` | "Run external probe" confirmation (after re-authentication) |
| `screenshots/05-dashboard-widget.png` | Dashboard with the "Security status" widget listing the checks in error |

## Known issues

- `--probe` has not run against a real HTTPS site here (see above).
- `app:install … --demo` given right before `--no-interaction` is parsed as interactive and fails
  with a MySQL connection error (pre-existing; `--demo` last, or `modules:demo`, works). Not
  changed in this session.
- No gitleaks step in CI yet (secret scanning of the history).

## Notes for the next session

- D-30-01 is **Open (decide)**: run `deploy/decide.md` before session 31.
- Modules add checks from `securityChecks` (`docs/security.md`, "Production checklist"); texts in
  their own language files (session 31, i18n, must keep `SecurityCheck.php` in ca/es/en).
- `writable/security/` is a new folder (only `index.html` is tracked).
