# Session 35 — Quality: end-to-end, accessibility and CI

> Run it by saying: **`executa deploy/35-quality.md`** (or `run deploy/35-quality.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session branch into `main`**, in order: for each branch `session-NN-*`
   (NN < 35) not yet contained in `main` (`git branch -r --no-merged origin/main`; a session may
   have used another branch name: then it is the head branch of the pull request "Session NN: …"):
   - check it is finished: its `docs/progress.md` marks the session "done" and its
     `deploy-status/NN-*/README.md` exists; if not, stop and tell me to run
     `deploy/continue.md NN` first;
   - `git merge --no-ff origin/session-NN-<slug> -m "Merge session NN: <title>"`; resolve trivial
     conflicts (e.g. `docs/progress.md`); if a conflict touches code, stop and tell me;
   - run the checks (`composer install`, `vendor/bin/phpunit`, PHPStan, php-cs-fixer); if they fail,
     fix them on `main` in a separate commit or stop and tell me;
   - in `docs/progress.md` change that session's status to **merged**;
   - `git push origin main` (if pushing to `main` is refused, stop and tell me to merge the pull
     request on GitHub, then run this file again).
3. Check that session 34 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Create the branch `session-35-quality` from the updated `main` (if this environment already
   assigned you a working branch, use that one and say so in the pull request).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md`, `docs/progress.md`, the previous
   `deploy-status/` report, and these sections of `docs/specification.md`: "Quality, tests and delivery", "Themes" (responsive and accessibility tests).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

The full quality bar of the specification.

## Scope

- Playwright: screenshots of the main screens at phone, tablet and desktop widths in light and
  dark mode, failing on horizontal overflow; axe accessibility checks (WCAG AA).
- GitHub Actions complete: PHP 8.2/8.3/8.4, composer validate + audit, cs-fixer, PHPStan level 6,
  PHPUnit on SQLite and MariaDB, assets in sync, CSP check, `vendor.json` checksums, secret
  scanning, Dependabot.
- Raise coverage where the specification lists tests that are missing.

## Done when

- CI green on every job.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-35-01`, `D-35-02`…, with where, problem, proposal and alternatives; format in
  `deploy/decide.md`):
  - if it changes a public convention (permission names, manifest keys, URLs, settings names) or
    security, mark it **(decide)**, leave it **Open** and, if possible, build this session so the
    decision can still be changed (or stop if you cannot continue without it);
  - otherwise apply the most secure and simplest option consistent with the specification and
    record it as **Accepted (proposal)**.
- Texts in `ca`, `es` and `en` from the start; no inline JavaScript; tests for every rule you add.

## Finish

1. Run the checks and show the results: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`,
   `vendor/bin/php-cs-fixer fix --dry-run --diff` (and `php spark modules:sync` once it exists).
   Fix everything that fails.
2. **Status report** in `deploy-status/35-quality/` (see `deploy-status/README.md` for the format):
   - `README.md`: what was built, **how** (design, main classes and files, tables, routes,
     permissions, commands), decisions and deviations, how to try it (commands and URLs with the
     demo data), test results (counts and the checks' output summary), known issues and what the
     next session must know;
   - `screenshots/`: screenshots of what this session added that has a screen (back office,
     public site, phone width and dark mode when relevant), taken with Playwright against
     `php spark serve` with demo data, at device scale factor 2, PNG, named
     `01-<what>.png`, `02-…`, each one listed in the README with a caption. If the session has no
     screen (e.g. a console command), include the console output in the README instead; if
     screenshots are impossible in this environment, say why in the README.
3. Update `docs/progress.md`: status of session 35 = **done**, decisions, deviations, known
   issues, notes for the next session, and the link to `deploy-status/35-quality/`.
4. Commit in small commits on the session branch, push it and open a pull request titled
   "Session 35: Quality: end-to-end, accessibility and CI" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
