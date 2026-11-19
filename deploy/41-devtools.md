# Session 41 — Devtools module: development-only tools and their locks

> Run it by saying: **`executa deploy/41-devtools.md`** (or `run deploy/41-devtools.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (a development module for building modules from the back office; decisions D-41-01 … D-41-03).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 41
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
3. Check that session 40 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-41-devtools`. Record the branch name for session 41 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "41 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "The module contract" (manifest), "Security baseline (must be on by default)" (and its "Security status page and `security:check`"), "Menus", "CRUDs with kpcrudy".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

A new module `Devtools` that exists **only for development**: it is the home of the code
generator (session 42) and the package builder (session 43), and this session builds the module,
**every lock** that keeps it out of production, and a first useful screen: the CRUD recipes.

Read D-41-01 … D-41-03, D-42-01 and D-43-01 in `docs/decisions.md` first: they define what
this module may do and the locks it must keep.

## Scope

### The locks (all of them, each with a test)

1. **Development only, before the database:** `modules/Devtools/Config/Routes.php` registers its
   routes only when `ENVIRONMENT === 'development'` (a constant, no query). In any other
   environment every Devtools URL answers 404.
2. **A manifest key** (D-41-01): `public bool $developmentOnly = true;` (new key of
   `ModuleManifest`, validated by `ManifestValidator`, documented in `docs/creating-a-module.md`).
   `modules:sync`, `modules:enable`, `modules:install` and the Modules screen refuse to install or
   enable a `developmentOnly` module when `CI_ENVIRONMENT` is not `development`, and a sync in
   production disables one that is enabled (with a security log entry). `enabledByDefault = false`.
3. **`security:check`:** a new check (`installation.devtools`, through the `securityChecks` event
   from the module or the Core's module check) is an **error** when a `developmentOnly` module is
   enabled outside development, and info in development.
4. **Local requests only:** a filter (e.g. `devtools`, in the module) answers 404 unless the
   request comes from the loopback (`127.0.0.1`, `::1`) **and** the `Host` is `localhost` or a
   loopback address (no proxy headers trusted). `php spark serve` is the supported way.
5. **Permission and re-authentication:** single resource `devtools` with `devtools.view` (screens)
   and `devtools.write` (anything that writes files or runs commands), no default grants except
   the superadmin (who has everything); every write action needs `reauth` and `ratelimit:forms`.
6. **Packaging:** a `developmentOnly` module is never put in a release archive by mistake: the
   release procedure (`docs/updates.md`) and `git archive` keep it (it is part of the repository),
   but locks 1–4 make it inert; say so in `docs/security.md`.

### The module

- `modules/Devtools/` created with `make:module Devtools` and completed: manifest (resource,
  menu entry in the settings zone under the **Sistema** group of D-38-01 — or its own group
  "Desenvolupament" if you find it clearer; record it), language files ca/es/en, `CLAUDE.md`.
- A banner on every Devtools screen: "Development tools: never enabled in production".
- **CRUD recipes** (`backend/devtools/recipes`, `devtools.view`): one card per variant with what
  it is, when to use it and the code, taken from `docs/cruds.md`, kpcrudy's
  `vendor/siensis/kpcrudy/docs/howto_EN.md` and the existing modules (copy real snippets, cite the
  file): plain CRUD through `CrudFactory`, read-only (`unset*` + `except` in the manifest), custom
  operations (`addOperation()` declared in `extra`), operations behind re-authentication, record
  rules (`setPermission()`, own rows with `where()`), hidden/sensitive fields per permission,
  translated fields (`setTranslations()` with `attrs`), rich text (editor + `HtmlSanitizer`),
  uploads (`CrudUploadHandler`, file picker), export/import, trash, masked ids, the activity log
  (automatic through `AuditedCrud`), the API resource (kpapy, scopes), the menu entry and default
  grants. Each card links the doc section. This page is the "cheat sheet" the owner asked for.

## Tests

- Each lock: routes absent outside development (404); refusing to enable/install a
  `developmentOnly` module in production and disabling it on sync; `security:check` error;
  non-loopback request or foreign `Host` → 404; permission and `reauth` on write routes;
  `ManifestValidator` accepts the key and rejects a non-boolean.
- The recipes page renders for the superadmin, 403 for others; no inline code.

## Docs

`docs/devtools.md` (new: purpose, the locks, how to use it with `php spark serve`), `docs/security.md`,
`docs/creating-a-module.md` (`$developmentOnly`), `docs/commands.md` if commands change,
`modules/Devtools/CLAUDE.md`, `CHANGELOG.md` `[Unreleased]`.

## Done when

- `Devtools` exists, is impossible to use outside development or from another machine, and shows
  the CRUD recipes; every lock has a test.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-41-01`, `D-41-02`…, with where, problem, proposal and alternatives; format in
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
   `php spark permissions:check`, `php spark csp:check`, `php spark security:check` in development and in a production-like copy. Fix everything that fails.
2. **Status report** in `deploy-status/41-devtools/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 41 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/41-devtools/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 41: Devtools module: development-only tools and their locks" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
