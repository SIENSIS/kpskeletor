# Session 32 — Tasks tutorial module and AI tooling

> Run it by saying: **`executa deploy/32-tasks-module.md`** (or `run deploy/32-tasks-module.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 32
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
3. Check that session 31 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-32-tasks-module`. Record the branch name for session 32 in `docs/progress.md`.
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md`, `docs/progress.md`, the previous
   `deploy-status/` report, and these sections of `docs/specification.md`: "Tutorials (`docs/tutorials/`)" (the module only), "Required libraries" (AI-assisted module development).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

The `Tasks` module containing the finished code of every tutorial, and the tooling for future modules.

## Scope

- `Tasks` module (disabled by default): projects, tasks, assignees, due dates, labels, the code
  for every tutorial (CRUD with relations, custom operation, translated field, multiselect,
  hideFields, afterWrite, bulk action, task board view with JS, two widgets, public page with menu
  link, notification, calendar push/pull, scheduled reminder, setting, a child theme
  `themes/tasks-demo/` (child of `default`) overriding a `Tasks` view,
  attachments with a FileAccessPolicy, API with kpapy, public form with captcha), demo seeder,
  tests.
- `make:module` writes `modules/<Name>/CLAUDE.md` from a template; `--standalone` option.
- `.claude/commands/new-module.md` and `.claude/commands/check-module.md`.
- Complete acceptance criterion 2 (session 19 checked it without these parts):
  `make:module Events2` writes `modules/Events2/CLAUDE.md` and `/check-module Events2` reports it
  complete once its tests are written.
- Texts say "scheduled task" and "to-do task" explicitly, never just "task".

## Out of scope

Tutorial texts (session 34).

## Done when

- `/check-module Tasks` reports the module complete and its tests pass in CI.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-32-01`, `D-32-02`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/32-tasks-module/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 32 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/32-tasks-module/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 32: Tasks tutorial module and AI tooling" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
