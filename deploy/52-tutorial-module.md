# Session 52 — Rename the Tasks tutorial module to Tutorial

> Run it by saying: **`executa deploy/52-tutorial-module.md`** (or `run deploy/52-tutorial-module.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's request of 2026-11-19: the tutorial module built
> in session 32 is called `Tasks`, which can be confused with the Core's scheduled tasks and would
> take the name of a real tasks module the owner may need later (decision D-52-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 52
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
3. Check that session 51 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first. (If sessions were added after 51 and
   before this one, the previous session is the one with the highest number below 52.)
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-52-tutorial-module`. Record the branch name for session 52 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md` (D-52-01, and D-00-31 / the session 32 entries about the
   tutorial module), `docs/plan.md` (section "52 — …"), `docs/progress.md`, the previous
   `deploy-status/` report, `deploy-status/32-tasks-module/README.md`, `modules/Tasks/CLAUDE.md`,
   `docs/tasks.md`, `docs/tutorials/` and these sections of `docs/specification.md`: the tutorial
   module ("Tasks"), "Modules" (manifest), "Updates", "Installing and uninstalling modules".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

The tutorial module keeps exactly what it shows (a to-do list with projects, a board, public
requests, a calendar, widgets, notifications, a scheduled task, an API), but its **module name
becomes `Tutorial`** everywhere, so the name `Tasks` stays free and nobody mistakes it for the
Core's scheduled tasks. Every tutorial keeps working step by step with the new names.

## Scope (D-52-01)

- **Module:** `modules/Tasks` → `modules/Tutorial`, namespace `Modules\Tutorial`, manifest
  `$name = 'Tutorial'`, still disabled by default, version restarted at `1.0.0` (a new module).
  Visible name: "Mòdul tutorial" (ca), "Módulo tutorial" (es), "Tutorial module" (en).
- **Inside names keep the to-do example:** resources `tasks`, `projects`, `board`, `requests`;
  permissions `tutorial-tasks.*`, `tutorial-projects.*`, `tutorial-board.*`,
  `tutorial-requests.*` (from the manifest, never by hand); default grants unchanged otherwise.
- **Everything that carries the module name changes with it:** routes
  `backend/tutorial/…` (board at `backend/tutorial`, CRUDs `backend/tutorial/tasks`, `…/projects`,
  `…/requests`), the public page and request form `tutorial` and `tutorial/request` (with the
  language prefix as any public URL), route names `tutorial-*`, API `api/v1/tutorial/tasks` and
  `api/v1/tutorial/projects`, settings `Tutorial.*`, language files `Language/*/Tutorial.php`
  (keys `Tutorial.*`), assets owner and import specifiers `tutorial/…`, calendars
  `tutorial.due` / `tutorial.projects`, widget ids `tutorial.*`, notification types and scheduled
  task ids `tutorial.*`, demo seeder `TutorialDemoSeeder`, seeder `TutorialSeeder`, the module's
  `CLAUDE.md`, `CHANGELOG.md` and tests.
- **Tables** get the module's prefix: `tutorial_projects`, `tutorial_project_translations`,
  `tutorial_tasks`, `tutorial_task_assignees`, `tutorial_task_attachments`,
  `tutorial_task_requests` (one migration per table, `down()` implemented; the new module's
  migrations are new files under `Modules\Tutorial`, written as the current final schema, so
  `AddStartedAtToTasks` is folded into the tasks table migration; the old module's released
  migrations are not edited, they leave with the old module). Upload folders and storage paths
  of the module follow the new name.
- **Existing installations** (only those that enabled the tutorial module): the release notes
  (`CHANGELOG.md`, Changed, with a "before updating" step) and `docs/updates.md` say to run
  `php spark modules:uninstall Tasks` **before** updating, then `php spark modules:enable
  Tutorial` and `php spark modules:demo Tutorial` if wanted. Check what `app:update` /
  `modules:sync` do when an installation still has `Tasks` in the registry and its folder is gone
  (it is reported as missing): make the report name the step to follow (a hint for a missing
  module that ships with a later release under another name is enough, through a small map in the
  Core of renamed bundled modules, e.g. `Tasks` → `Tutorial`), and add a test. Do not migrate the
  old data automatically (it is demo/tutorial data).
- **Docs and tooling:** `docs/tasks.md` → `docs/tutorial-module.md` (and every link to it),
  `docs/tutorials/*.md` (paths, names, commands, URLs, permission names, screenshots' captions),
  `CLAUDE.md` ("copy patterns from `modules/News` (reference module) and `modules/Tutorial`
  (tutorial module)"), `.claude/commands/new-module.md`, `docs/creating-a-module.md`,
  `docs/development.md` (e2e setup: `modules:enable Tutorial && modules:demo Tutorial`), the CI
  workflow, `tests/e2e/*.spec.mjs`, `tests/unit/Docs/TutorialsTest.php`, the fixtures under
  `tests/` that name the module, the specification (the tutorial module section and every
  mention), `README.md`, and any reference added by sessions 40–51 (Devtools generator and
  packages, Contact's mention of the request form's captcha, …). Old status reports in
  `deploy-status/` and old entries in `docs/decisions.md` / `docs/progress.md` are history: do
  not rewrite them; add a line where useful ("renamed to Tutorial in session 52").
- **Search the whole repository** for `Tasks` / `tasks-` / `tasks.` / `modules/Tasks` /
  `Modules\Tasks` and decide each hit: the Core's scheduled tasks (`Tasks/` folders,
  `TaskInterface`, `$tasks` manifest key, `schedule:run`, Settings › Scheduled tasks) are a
  different thing and **do not change**.

## Tests

- The whole suite passes with the new names (the module's own tests moved and renamed:
  permissions including forged POSTs, migrations up/down/up, API scopes, the module's rules).
- `permissions:check` lists `tutorial-*` permissions and no `tasks-*` one; `modules:sync` on a
  fresh install creates the `tutorial_*` tables and no `task*` table.
- A test for the renamed-module hint of `modules:sync` / `app:update` (registry has `Tasks`, the
  folder is gone).
- `tests/unit/Docs/TutorialsTest.php` checks the tutorials against the new paths.
- `rg -n "modules/Tasks|Modules\\\\Tasks|'tasks-|tasks-board|api/v1/tasks" --glob '!deploy-status/**' --glob '!docs/decisions.md' --glob '!docs/progress.md' --glob '!vendor/**'` finds nothing.
- e2e with the tutorial module enabled (its screens at the new URLs).
- Screenshots of the module's board and one CRUD at the new URLs, and of the tutorial module in
  Modules (desktop; one at phone width in dark mode).

## Docs

`docs/tutorial-module.md`, `docs/tutorials/`, `CLAUDE.md`, `docs/creating-a-module.md`,
`docs/development.md`, `docs/updates.md` (the step before updating), `docs/commands.md` if an
example names the module, the specification, `CHANGELOG.md` `[Unreleased]` (Changed, with the
upgrade step).

## Done when

- No module, permission, URL, setting, table, asset or doc of the tutorial module is called
  `Tasks` any more (history excepted), the tutorials work step by step with `Tutorial`, existing
  installations have a documented and reported upgrade step.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- Move files with `git mv` so their history follows them.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-52-02`, `D-52-03`…, with where, problem, proposal and alternatives; format in
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
   `php spark permissions:check`, `php spark csp:check`, `php spark assets:publish --check`,
   `npm run e2e` against a demo site with the tutorial module enabled. Fix everything that fails.
2. **Status report** in `deploy-status/52-tutorial-module/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 52 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/52-tutorial-module/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 52: Rename the Tasks tutorial module to Tutorial" with a summary, the checks' output and a link to the status report.
   No session follows this one: merge this branch into `main` yourself with the procedure of
   "Before starting" (checks, `docs/progress.md` → merged, push, never `--force`; if `main`
   cannot be pushed, stop and ask me to merge the pull request on GitHub).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "the planned sessions are complete".
