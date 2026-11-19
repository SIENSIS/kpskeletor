# Session 39 — Web installer progress

Branch: `claude/great-knuth-jz5xq8` · Pull request: [SIENSIS/kpskeletor#57](https://github.com/SIENSIS/kpskeletor/pull/57) · Date: 2026-11-19

## What was built

- While the web installer installs, the page shows a **progress bar** and a **log of its
  steps** (checks, keys, database, migrations "n of m" per namespace, modules, permissions,
  settings, assets, groups, API, superadmin, demo data per module, `.env`, lock file, security
  check), in the language of the page that started it.
- At the end it opens a **result page** that shows the one-time values (the `.env` content to
  copy when the root is not writable, the demo users' passwords) **once**; opened again, it says
  they were shown and deleted. On failure it shows the failing step, the reason and what to do.
- **Fallback:** without JavaScript, with `php spark serve` or mod_php, the installation runs
  inside the request as before and the same result page is shown.
- **One installation at a time** (a second POST answers 409); the progress and result pages
  answer only for an existing job, for an hour, also once installed; the progress JSON is rate
  limited and carries no secrets.
- The installer pages now follow the system's **dark mode** and get the **password show/hide
  button** (D-38-04), because they load the Core's scripts when published (the installer
  publishes the Core's assets itself when it can, D-39-01).

## How it was built

- **Steps from the service.** `Modules\Core\Install\InstallProgress` (interface:
  `step(code, done, total, params)`, `log(key, params)`, `failed(code, message)`).
  `Installer::install()` takes an optional listener (4th argument) and reports every step;
  on any exception it reports the current step as failed and rethrows. `ModuleSynchronizer::sync()`
  takes an optional `progress` closure (`migrations` per namespace, `modules` per module,
  `permissions`, `settings`, `assets`, `groups`, `api`). Parameters are counts and module /
  namespace names only; messages are language keys (`Install.step.*`, `Install.progress.*`).
- **Jobs.** `Modules\Core\Install\InstallJobs`: `writable/install-jobs/` (0700), `<id>.json`
  (0600, atomic writes: status, step, counts, percentage, log, locale, non-secret result),
  `<id>.secrets.json` (0600, deleted when first read), `running.lock` (`fopen('x')`, stale after
  the TTL). Id = 32 random hex characters; TTL one hour (`InstallJobs::TTL`); log capped at 100
  lines; expired files purged when a job is created.
- **Runner.** `Modules\Core\Install\InstallJob` implements the listener: each step moves the
  percentage inside its range (`InstallJob::RANGES`); after the installation it runs
  `security:check` (stored for the Security status page, no notifications), keeps the result and
  the one-time values and releases the lock. `canRunAfterResponse()` is true only for
  FastCGI/LiteSpeed outside tests; `afterResponse()` registers the job like the Backup module's
  `RestoreJob` (`session_write_close()`, `fastcgi_finish_request()`, `ignore_user_abort`).
- **Controller** `Modules\Core\Controllers\WebInstaller`:
  - `POST install`: validates as before (errors at once, no job). With `progress=1` (set by the
    page's script) and a server that can answer first → job + lock + progress page, installation
    after the response. Otherwise → lock, installation in the request, result page.
  - `GET progress/install/<job>` (`progress()`): JSON `status`, `step`, `label`, `percent`,
    `log`, `next`; 404 `{"status":"unknown"}` for an unknown or expired job; 429 past
    `PROGRESS_LIMIT` (120 per minute and IP, `RateLimiter::attemptCustom()` on the file cache).
  - `GET install/result/<job>` (`result()`): done (with the one-time values once), failed, or
    still running (the progress page again).
  - `scriptsPublished()`: publishes the Core's assets before installing when the manifest lacks
    them (D-39-01).
- **Routes and filters:** `modules/Core/Config/Routes.php`; `progress/*` already skips the
  database/session filters; `InstallGuard::isInstallerPath()` lets `progress/install/` through
  before installation.
- **Views:** `modules/Core/Views/install/progress.php` (Bootstrap `progress` with
  `role="progressbar"`, `aria-valuenow`, `aria-labelledby` the step; log in an `<ol>` with
  `aria-live="polite"`), `failed.php`, `_scripts.php` (page config, import map without theme,
  `core/app.js`), `_head.php` (`core/theme-mode.js`, optional meta refresh), `done.php` (note
  after the one-time values, security summary), `form.php` (hidden `progress` field).
- **Front end:** `modules/Core/Assets/js/install/progress.js`
  (`data-component="core/install/progress"`): on the form it sets `progress=1` and disables the
  button after submitting; on the progress page it polls every second (3 s after an error or a
  429), moves the bar, appends only the new log lines and opens the result page. No inline code
  (`csp:check` passes).
- **Texts:** `Install.step.*`, `Install.progress.*`, new `Install.web.*` and `Install.error.*`
  keys in ca, es and en.
- No tables, permissions, settings or commands added. `app:install` keeps its output.

## Decisions and deviations

- **D-39-01** (Accepted, proposal): the installer publishes the Core's assets before installing
  so its pages can load the Core's scripts (alternative B of D-38-04); without write access the
  pages work without JavaScript.
- **D-39-02** (Accepted, proposal): job storage, one-hour TTL, 120 requests per minute and IP,
  lock with 409, JSON keys; `security:check` runs after the lock file (as `app:install` does),
  so the step list ends `…, envFile, lock, securityCheck, done`. The superadmin step covers the
  2FA offer (no separate step on the web).
- The failure message is shown on the result page, not in the progress JSON.
- The result page of a running job is the live progress page too (with the scripts), so the
  "open the result page" link never shows a dead page.
- `app:install` does not print the steps (allowed by the session file: "may").

## How to try it

The after-response path needs PHP-FPM (or LiteSpeed). With `php spark serve` the installer uses
the fallback (installation inside the request, then the result page):

```bash
rm -f .env writable/install.lock writable/database.db    # an unconfigured checkout
php spark serve
# open http://localhost:8080/install, choose SQLite (database.db), development, demo data
```

Under PHP-FPM + nginx/Apache, the same form shows the progress page and then
`/install/result/<job>`. The JSON: `curl https://<site>/progress/install/<job>`.

To watch the progress page without PHP-FPM (how the screenshots were taken), run a job from the
console while the browser opens `/install/result/<job>`: a scratch script built the options,
called `InstallJobs::create()` and `lock()`, then `InstallJob::run()`, exactly what the web
server runs after the response.

## Tests and checks

New and changed tests:

- `modules/Core/Tests/InstallJobsTest.php` (8): files 0600 / folder 0700, secrets read once,
  expiry and purge, malformed ids, lock (holder only, stale lock), log cap, percentages.
- `modules/Core/Tests/InstallProgressTest.php` (4): steps in order to a fake listener
  (`RecordingInstallProgress`), the failure step (`database` with a non-empty database), a whole
  job (done, 100 %, lock released, `.env` only in the one-time file, read once), a failed job
  (step, message in the job's language, no one-time file).
- `modules/Core/Tests/WebInstallerTest.php` (+9): invalid form starts no job; in-request
  fallback installs and releases the lock; second POST refused (409, nothing installed);
  progress JSON shape, language, no secret-looking keys or values; 404 for unknown, malformed or
  expired jobs (also the result page); 429 when rate limited; result page shows the one-time
  values once, also once installed (form 404 meanwhile); failed and running result pages
  (`role="progressbar"`, `aria-valuenow`, `aria-live`); form carries `progress=0` and the
  component; `progress/install/` is an installer path.

Results (see the pull request for the output):

- `vendor/bin/phpunit`: **OK (1391 tests, 18418 assertions)**, 12 min 47 s (SQLite).
- `vendor/bin/phpstan analyse`: no errors. `vendor/bin/php-cs-fixer fix --dry-run`: 0 files.
- `php spark modules:sync`: nothing to do; `php spark permissions:check`: 169 permissions,
  346 routes; `php spark csp:check`: no inline code; `php spark assets:publish --check`: up to
  date.
- `npm run e2e` against a demo site installed **through the web form** (fallback path) plus
  Tasks: **188 passed, 10 skipped** (6.1 min).
- By hand (real browser, `php spark serve`): the form switches `progress` to 1; the POST
  installs in the request in about 5 s and shows the result page; no CSP violations;
  `progress/install/<unknown>` → 404 `{"status":"unknown"}`; a failed job's JSON keys are
  `status,step,label,percent,log,next`; `/install` → 404 once installed.

## Screenshots

Taken with Playwright (device scale factor 2, `ca-ES`) against `php spark serve`, no horizontal
scroll, no CSP violation.

| File | What |
|---|---|
| `screenshots/01-form.png` | The form, with the Core's scripts (show/hide buttons on the password fields). |
| `screenshots/02-form-phone-dark.png` | The form at phone width in dark mode. |
| `screenshots/03-progress-mid.png` | Progress page mid-installation: migrations "14 of 18", 40 %, the log. |
| `screenshots/04-progress-mid-phone-dark.png` | The same at phone width, dark mode. |
| `screenshots/05-result.png` | The result page opened a second time: the one-time values were already shown (note). |
| `screenshots/06-result-again-phone-dark.png` | The result page opened first (phone, dark): the demo passwords, shown once; the security summary. |
| `screenshots/07-failed.png` | A failed job (a second installation of an installed site): failing step, reason, what to do. |
| `screenshots/08-fallback-result.png` | The in-request fallback: the result page answered by the POST under `php spark serve`. |

(05 and 06 watched the same job in two windows: the phone window loaded the result first and got
the one-time values.)

## Known issues

- The after-response path (FastCGI) could not be run in this environment: no PHP-FPM or web
  server is installed and installing system packages was not allowed. It is covered by the same
  `InstallJob::run()` that the tests and the screenshots exercise; only `afterResponse()` (the
  same code as Backup's `RestoreJob`) is untested here. Worth a manual check on the first
  PHP-FPM deployment.
- In development the CodeIgniter debug toolbar icon appears on the installer's result pages
  (as on every page in development).
- A long demo installation makes the log list long (about 50 lines with every module's demo data); it is not scrollable on its
  own.

## Notes for the next session

- Session 40 adds the back-office path to the installer form: add the field in
  `modules/Core/Views/install/form.php`, read it in `WebInstaller::install()` (the `FIELDS`
  list) and it travels with `InstallOptions` into the job unchanged. The result page's login URL
  is computed before the job starts (`$page['loginUrl']`).
- `progress/install/<job>` and `install/result/<job>` must stay outside the back-office prefix.
- Composer in this cloud environment: `phpstan/phpstan` has no git source and its GitHub
  zipball is refused by the proxy (403), so `composer install` fails even with
  `--prefer-source`. Workaround used: clone `phpstan/phpstan` with git, `git archive` the locked
  commit to a zip and place it in Composer's file cache
  (`~/.cache/composer/files/phpstan/phpstan/<sha1 of the dist URL>.zip`); `composer.json` and
  the lock are unchanged.
