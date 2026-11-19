# Session 39 — Web installer progress

> Run it by saying: **`executa deploy/39-installer-progress.md`** (or `run deploy/39-installer-progress.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's review of 2026-11-19 (point 7: the web installer gives no feedback while it works; option B chosen).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 39
   whose branch is not yet contained in `main` (`git branch -r --no-merged origin/main`):
   - check it is finished: its `docs/progress.md` marks the session "done" and its
     `deploy-status/NN-*/README.md` exists; if not, stop and tell me to run
     `deploy/continue.md NN` first;
   - `git merge --no-ff origin/<its branch> -m "Merge session NN: <title>"`; resolve trivial
     conflicts (e.g. `docs/progress.md`); if a conflict touches code, stop and tell me;
   - run the checks (`composer install`, `vendor/bin/phpunit`, PHPStan, php-cs-fixer); if they fail,
     fix them on `main` in a separate commit or stop and tell me;
   - in `docs/progress.md` change that session's status to **merged**;
   - `git push origin main`, never with `--force`; if pushing to `main` is refused, stop and tell
     me to merge the pull request "Session NN: …" on GitHub, then run this file again.
3. Check that session 38 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-39-installer-progress`. Record the branch name for session 39 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "39 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Installer and CLI", "Security baseline (must be on by default)" (`progress/*` paths).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

While the web installer installs, the page shows a progress bar and a short log of what it is
doing (option B of the owner's review), reusing the long-job pattern the Backup module already
uses for restores.

## Scope

- **Steps from the installer service.** `Modules\Core\Install\Installer` reports its steps
  through a small listener interface (e.g. `InstallProgress`: `step(code, done, total)`,
  `log(message)`): checks, `.env` written or prepared to copy, database connection, migrations
  (one step per namespace, "n of m"), first-install seeds, superadmin and 2FA, demo data per
  module, assets, `security:check`, lock file. Messages are language keys (ca/es/en), short, and
  **never contain secrets** (passwords, keys, `.env` values, demo passwords). `app:install` may
  print the same steps (keep its current output otherwise).
- **Web flow.** The POST validates the form as today (errors come back at once, no job). When
  valid it creates a job (random 32-hex id, files `0600` under `writable/`, like Backup's
  `JobStore`), answers the progress page and runs the installation **after the response**
  (`fastcgi_finish_request()`, as `PoorMansCron` and `RestoreJob` do). The page polls
  `progress/install/<job>` (JSON: step, percentage, status `running|done|failed`, the log lines,
  the URL of the result page) and, when done, goes to the result page, which shows the one-time
  secrets (demo passwords, the `.env` to copy when the root is not writable) **once** and deletes
  them. On failure it shows the failing step and what to do (as the installer does today).
- **Fallbacks.** Without JavaScript, or where the response cannot be finished first
  (`php spark serve`, mod_php), the installation runs inside the request as today and the result
  page is shown; nothing depends on the progress page.
- **Security.** `progress/install/<job>` exists only for a job that exists and only for a short
  time (TTL), answers 404 otherwise, is rate limited, skips the database/session filters like the
  other `progress/*` paths and returns no secret-looking keys (`security:check --probe` checks
  `progress/*`). A second POST while a job runs is refused (lock). The usual rule stays: the
  installer answers 404 once installed, except the running job's progress and result pages.
- **Front end.** An ES module (`core/install/progress.js`, `data-component`), a Bootstrap progress
  bar with `role="progressbar"` and `aria-valuenow`, the log in a list with `aria-live="polite"`;
  phone width and dark mode; no inline code.

## Tests

- The installer reports its steps in order to a fake listener, including the failure step.
- Job files are `0600`, the secrets are readable once and then gone, expired jobs are purged.
- `progress/install/<job>`: JSON shape, no secret keys, 404 for an unknown or expired job, refused
  when rate limited; a second POST while running is refused; an invalid form starts no job.
- The in-request fallback still installs and shows the result page.
- Screenshots of the progress page (desktop, phone, dark) mid-installation and at the end.

## Docs

The web installer section of `README.md` and `docs/commands.md`, `docs/security.md` (`progress/*`
paths), the specification "Installer and CLI", `CHANGELOG.md` `[Unreleased]`.

## Done when

- The web installer shows real progress and the result page, with the fallbacks and tests above.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-39-01`, `D-39-02`…, with where, problem, proposal and alternatives; format in
  `deploy/decide.md`):
  - if it changes a public convention (permission names, manifest keys, URLs, settings names) or
    security, mark it **(decide)**, leave it **Open** and, if possible, build this session so the
    decision can still be changed (or stop if you cannot continue without it);
  - otherwise apply the most secure and simplest option consistent with the specification and
    record it as **Accepted (proposal)**.
- Texts in `ca`, `es` and `en` from the start; no inline JavaScript; tests for every rule you add.
- The skeleton is released (1.0.0): note every change under `[Unreleased]` in `CHANGELOG.md`
  (Added, Changed, Fixed); do not raise `Version::CORE` (the owner does it when releasing,
  `docs/updates.md`).

## Finish

1. Run the checks and show the results: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`,
   `vendor/bin/php-cs-fixer fix --dry-run --diff`, `php spark modules:sync`,
   `php spark permissions:check`, `php spark csp:check`, `npm run e2e` against a demo site. Fix everything that fails.
2. **Status report** in `deploy-status/39-installer-progress/` (see `deploy-status/README.md` for the format):
   - `README.md`: what was built, **how** (design, main classes and files, tables, routes,
     permissions, commands), decisions and deviations, how to try it (commands and URLs with the
     demo data), test results (counts and the checks' output summary), known issues and what the
     next session must know;
   - `screenshots/`: screenshots of what this session added that has a screen (back office,
     public site, phone width and dark mode when relevant), taken with Playwright against
     `php spark serve` with demo data, at device scale factor 2, PNG, named
     `01-<what>.png`, `02-…`, each one listed in the README with a caption. If the session has no
     screen, include the console output in the README instead; if screenshots are impossible in
     this environment, say why in the README.
3. Update `docs/progress.md`: status of session 39 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/39-installer-progress/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 39: Web installer progress" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
