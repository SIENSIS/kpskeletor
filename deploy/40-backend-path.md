# Session 40 — Configurable back-office path

> Run it by saying: **`executa deploy/40-backend-path.md`** (or `run deploy/40-backend-path.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's review of 2026-11-19 (point 6: choose `/admin`, `/private`, `/intranet`… instead of `/backend` at install time; decision D-40-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 40
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
3. Check that session 39 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-40-backend-path`. Record the branch name for session 40 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "40 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Security baseline (must be on by default)", "Installer and CLI", "Menus", "Pages", and every passage that names `backend/`.
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

The back-office path prefix, `backend` today, becomes an installation choice (D-40-01), so a site
can use `/admin`, `/private`, `/intranet`… without changing code.

**Requires D-40-01 decided.** If it is still Open, stop and ask me to run `deploy/decide.md`.
Build exactly what the decision says; the scope below is the proposal it was written from.

## Findings (counted on 2026-11-19)

`backend` appears about 380 times in code (15 route files, `site_url('backend…')` ≈ 240 times,
the `backend` filter chain and `backend/*` in `app/Config/Filters.php`, Shield's redirects in
`app/Config/Auth.php`, `RouteSecurityAuditor`, 4 JavaScript files), about 950 times in tests, and
in **stored data**: notification links (`NotificationType::url`, e.g. `backend/news/read/…`),
dashboard quick links, `backend_menu_links`, the activity log.

## Scope (proposal of D-40-01)

- **Configuration, not the database:** `.env` key `app.backendPath` (default `backend`), read
  by a config class at boot (`Routes.php` must never query the database). Helpers
  `backend_path()` and `backend_url(string $path = '')`; every route group, filter pattern,
  redirect, audit rule, JS URL (through `core/config.js`) and view uses them. `CrudFactory::routes()`
  receives paths relative to the prefix or builds them with the helper.
- **Stored links:** new data is stored relative to the back office (or with a marker), resolved
  when shown; old values starting with `backend/` keep working. Follow what D-40-01 decided.
- **Installer:** `app:install --backend-path=<slug>` and a field in the web installer (with
  session 39's progress page), validated: lowercase letters, digits and hyphens, 2–30 characters,
  one segment, not a reserved top-level path (`api`, `install`, `progress`, `login`, `logout`,
  `register`, `auth`, `uploads`, `assets`, `themes`, `ical`, any other top-level route of an
  installed module, `robots.txt`, `sitemap.xml`…). Pages refuses it as a top-level slug
  (`PageSlugs::reservedSlugs()`), and the installer refuses a path a demo page uses (`intranet`
  is a demo page today: solve it as the decision says).
- **After installation:** as decided (proposal: not changed from the UI; documented as a choice
  made at install time).
- `security:check` reports the path (info) and the probe uses it (`?return=` check on the back
  office); `CLAUDE.md` and the docs say "the back-office prefix" instead of `backend/`.
- The public sign-in link of session 38 keeps working (route names).

## Tests

- The suite keeps running with the default `backend`.
- A test case with `app.backendPath = 'admin'`: routes, the `backend` filter chain, `can:` audit
  (`permissions:check`), login redirect and forced reset, menus, CRUD URLs (kpcrudy), notification
  links, quick links and back-office menu links resolve under `/admin`; `/backend/...` answers 404.
- Installer validation (reserved paths, page slugs, format) for the CLI and the web form.
- e2e against a demo site installed with `--backend-path=admin` (the specs read the prefix).

## Docs

`docs/security.md`, `docs/web-server.md`, `docs/menus.md`, `docs/permissions.md`,
`docs/creating-a-module.md` (modules use `backend_path()`), `docs/commands.md`, `README.md`
(installing: choose the path once), the tutorials, the specification, `CLAUDE.md`,
`CHANGELOG.md` `[Unreleased]`; `make:module` templates use the helpers.

## Done when

- A site installed with another path works entirely under it, with tests, and the default install
  is unchanged.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-40-01`, `D-40-02`…, with where, problem, proposal and alternatives; format in
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
   `php spark permissions:check`, `php spark csp:check`, `php spark security:check`, `npm run e2e` against a demo site installed with a non-default path. Fix everything that fails.
2. **Status report** in `deploy-status/40-backend-path/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 40 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/40-backend-path/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 40: Configurable back-office path" with a summary, the checks' output and a link to the status report.
   No session follows this one: merge this branch into `main` yourself with the procedure of
   "Before starting" (checks, `docs/progress.md` → merged, push, never `--force`; if `main`
   cannot be pushed, stop and ask me to merge the pull request on GitHub).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "the planned sessions are complete".
