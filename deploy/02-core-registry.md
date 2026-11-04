# Session 02 — Core kernel: catalogue, settings registry, enable/disable and demo data

> Run it by saying: **`executa deploy/02-core-registry.md`** (or `run deploy/02-core-registry.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session branch into `main`**, in order: for each branch `session-NN-*`
   (NN < 02) not yet contained in `main` (`git branch -r --no-merged origin/main`; a session may
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
3. Check that session 01 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Create the branch `session-02-core-registry` from the updated `main` (if this environment already
   assigned you a working branch, use that one and say so in the pull request).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/plan.md`, `docs/progress.md`, the previous `deploy-status/` report, and
   these sections of `docs/specification.md`: "Module lifecycle", "Modules screen (back office)" (only the enable/disable rules), "Demo data", "Configuration and settings", "RBAC" (only "The catalogue is generated").

## Goal

Everything modules:sync must register from the manifests, module enable/disable, and the demo-data infrastructure.

## Scope

- Permission catalogue generation from `resources` (`permissions` table; `--prune` with confirmation),
  `defaultGrants` on first install (storage only; enforcement comes in session 07).
- Settings definitions registry (`codeigniter4/settings`, namespaced per module, typed casting).
- Registries for widgets, tasks, notification types, menu link types, calendars, API resources
  (store the declarations; the services come later).
- `modules:enable|disable` with dependency rules; disabled modules: routes 404 (filter), hidden from
  registries; core modules cannot be disabled.
- Demo data: `DemoData` helper, `demo_records` table, `<Name>DemoSeeder` convention,
  `modules:demo <Name> [--remove|--reload]`, `modules:sync --demo`, refusal in production unless the
  setting allows it.
- Caches (permissions, menus, settings, widgets, assets) with one invalidation service.
- Tests for each item.

## Out of scope

Modules screen UI (session 29), ZIP installs (session 29).

## Done when

- Disabling a test module makes its routes answer 404; enabling restores them.
- Loading and removing demo data leaves real rows untouched.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous, take the most secure and simplest option consistent with
  it and record the decision in `docs/progress.md` ("Decisions"). Stop and ask only if the
  decision changes a public convention (permission names, manifest keys, URLs) or weakens security.
- Texts in `ca`, `es` and `en` from the start; no inline JavaScript; tests for every rule you add.

## Finish

1. Run the checks and show the results: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`,
   `vendor/bin/php-cs-fixer fix --dry-run --diff` (and `php spark modules:sync` once it exists).
   Fix everything that fails.
2. **Status report** in `deploy-status/02-core-registry/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 02 = **done**, decisions, deviations, known
   issues, notes for the next session, and the link to `deploy-status/02-core-registry/`.
4. Commit in small commits on the session branch, push it and open a pull request titled
   "Session 02: Core kernel: catalogue, settings registry, enable/disable and demo data" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, anything I must decide, and the
   next step: "run `deploy/next.md`".
