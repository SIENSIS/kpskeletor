# Session 42 — Devtools: visual code generator with preview

> Run it by saying: **`executa deploy/42-devtools-generator.md`** (or `run deploy/42-devtools-generator.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (the code generator of the Devtools module; decisions D-41-02 and D-42-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 42
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
3. Check that session 41 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-42-devtools-generator`. Record the branch name for session 42 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "42 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "The module contract" (manifest, `make:module`), "CRUDs with kpcrudy", "JSON API (kpapy)", "Menus", "RBAC (centralised, automatic, atomic)", and the database rules of `CLAUDE.md`.
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

From the Devtools screens, with visual help and a **preview of every file before it is written**,
generate as much of a module as possible: a new module or a new table in an existing one, its
migration and demo seeder, its CRUD with the chosen options, API, menu, permissions, texts and
tests — always following the project's conventions, with **one generator engine shared with
`make:module`**.

Read D-41-01 … D-41-03, D-42-01 and D-43-01 in `docs/decisions.md` first: they define what
this module may do and the locks it must keep.

## Scope

### One engine, two front ends

- A generator service in the Core or in Devtools (record where; `make:module` in the Core must keep
  working without Devtools) that takes a **definition** (a PHP value object, serialisable as JSON)
  and returns a **plan**: the list of files to create or change with their full new content.
  `make:module` is refactored onto it (same output as today for the same options: keep its tests
  green), and gains `--definition=<file.json>`.
- Templates live in one place and follow `CLAUDE.md`: everything inside `modules/<Name>/`, one
  migration per table with `down()`, Forge/Query Builder only and portable to **MariaDB and
  SQLite**, demo data only in `<Name>DemoSeeder` through `DemoData`, CRUD through
  `CrudFactory::routes()` + `$this->crud()` + `$this->renderCrud()`, API through kpapy with
  `setResource()`, every operation declared in the manifest, `can:` on every route, ca/es/en texts,
  no inline code, `declare(strict_types=1)`, PSR-12 (run php-cs-fixer on the result).

### The wizard (`backend/devtools/generator`, `devtools.view`; writing needs `devtools.write` + `reauth`)

1. **Module:** a new one (name, description, author, dependencies) or an existing one.
2. **Table(s):** name (validated, prefixed by the module's convention), columns with portable
   types only (string with length, text, integer, big integer, decimal(p,s), boolean, date,
   datetime, JSON; "enum" becomes a string with an `in_list` rule), nullable, default, unique,
   index, foreign key (to a table of this or another installed module, ON DELETE), timestamps,
   soft delete (trash); translated fields (they go to the `<table>_translations` table of the
   Content convention). Live validation (e.g. SET NULL on a NOT NULL column).
3. **Demo data:** how many rows and, per column, a Faker generator chosen from a list (with a
   sensible default per name/type); written with `DemoData` so `modules:demo --remove` works.
4. **CRUD:** columns in the list, fields in forms, required fields and rules, search and filters,
   operations (list, show, add, edit, delete, trash, export, import) or **read only**, custom
   operations (`addOperation()` with label, icon, confirmation and **re-authentication** option),
   record rules (everyone / own rows / a column), fields hidden without a permission, uploads,
   rich text, masked ids — every option explained with a link to its CRUD recipe (session 41).
5. **API (optional):** kpapy resource with the same name, scopes, public or not.
6. **Menu and permissions:** zone, group (D-38-01), parent, icon, order; default grants per group
   (from `GroupRegistry`), shown as the permission names that will be generated.
7. **Preview:** a tree of the files with a badge *new* / *changed*, and the content (new) or a
   diff (changed), syntax-highlighted without inline code (a vendored, licensed highlighter or plain
   `<pre>`); the definition can be downloaded as JSON and loaded again later.
8. **Write:** only after the preview; refused if any file changed on disk since the preview
   (content hashes); writes atomically, runs `composer dump-autoload` only if needed,
   `php spark modules:sync` and optionally `modules:demo <Name>`, then shows the log, the next
   steps and the commands to run the generated tests. Logged with `activity_log()`.
- **Existing modules:** new files are simply added; changes to the existing manifest,
  `Routes.php` and language files are applied with `nikic/php-parser`'s format-preserving
  printer (D-42-01, decided: `composer require --dev nikic/php-parser:^5` from Packagist at the
  start of this session; stop and report if Composer fails; add it to `docs/dependencies.md`), and
  each change is shown as a diff in the preview. Never edit a migration that already ran: a change to an existing table
  is a new "alter" migration (add/drop column, add/drop index) — include it if time allows,
  otherwise record it as the next step.

## Tests

- Engine: for a set of definitions (new module + CRUD; read-only CRUD; CRUD with translated
  fields, custom operation with reauth, own-rows rule, API; table added to an existing module) the
  plan matches fixtures; the generated module passes `permissions:check`, `csp:check`, PHPStan,
  php-cs-fixer and **its own generated tests** on SQLite (generate into a temporary checkout or a
  test module folder and run them); migration up/down/up.
- `make:module` produces the same output as before the refactor.
- Wizard: validation errors, preview, write refused when a file changed after the preview, write
  needs `devtools.write` + `reauth`, all Devtools locks still apply.
- e2e: the wizard at desktop and phone width, light and dark, axe clean; screenshots of each step.

## Docs

`docs/devtools.md` (the generator, the definition JSON format, what it can and cannot generate),
`docs/creating-a-module.md` (generate first, then refine), `docs/commands.md`
(`make:module --definition`), a tutorial or a section in `docs/tutorials/` generating the
tutorial's `Tasks2`-like module, `CHANGELOG.md` `[Unreleased]`.

## Done when

- A new module with a working CRUD (and API) can be generated from the UI and from the console with
  the same engine, previewed and written, and its generated tests pass.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-42-01`, `D-42-02`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/42-devtools-generator/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 42 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/42-devtools-generator/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 42: Devtools: visual code generator with preview" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
