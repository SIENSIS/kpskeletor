# Session 38 — Settings menu groups, the public sign-in link and password reveal

> Run it by saying: **`executa deploy/38-settings-menu.md`** (or `run deploy/38-settings-menu.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's review of 2026-11-19 (points 5 and 6: the settings menu is chaotic; the public site has no link to the back office; and, added the same day, a show/hide button on password fields).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 38
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
3. Check that session 37 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-38-settings-menu`. Record the branch name for session 38 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "38 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Menus" (back-office menu), "Themes", "Configuration and settings".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

The settings zone of the back-office sidebar shows a few groups (D-38-01, decided by the owner)
instead of fifteen loose entries, the public site has a link to sign in or to go to the back
office, and every password field has the usual "eye" button to show or hide what was typed.

## Scope

### Settings groups (D-38-01)

- The Core declares five group entries in the `settings` zone (no route, no permission; shown only
  while one of their children is visible, as `docs/menus.md` already does for groups), in this
  order and with these children (adjust only if an entry does not exist; record it):
  1. **Usuaris i accés** — Users, Groups, Permissions (matrix).
  2. **Seguretat** — Security settings (`settings-security`), Security status, Activity log.
  3. **API** — API tokens, API documentation.
  4. **Personalització** — Modules, Back-office menu, Dashboard widgets (group layouts).
  5. **Sistema** — Settings (general), Backups, Updates, Scheduled tasks.
- Each module entry gets `'parent' => '<group key>'` in its manifest. **Entry keys do not change**,
  so the customisations saved with the Menus module (`backend_menu_customisations`) and the demo
  seeder's still apply; check them (renamed, moved, hidden entries) and the back-office menu
  editor, which must show the groups.
- Group keys become a public convention for third-party modules: document them in
  `docs/menus.md` and `docs/creating-a-module.md` (which group fits what), and make sure
  `ManifestValidator` accepts a `parent` that is a Core group key and that an unknown parent still
  falls back to the root (existing rule).
- Icons and labels in ca/es/en; order inside groups by the entries' `order`.

### Public sign-in link

- The default theme gets an option (e.g. `showAccountLink`, default on, in Settings › Theme): in the
  public navbar (end) a link **"Entra"** for guests (login) and **"Administració"** for signed-in
  users who can open the back office (their profile otherwise). Build the URLs from route names,
  not from the `backend` string (session 40 makes the prefix configurable). Accessible name,
  phone width (inside the collapsed menu), dark mode; the `tasks-demo` child theme inherits it.

### Show/hide button on password fields

- A **global Core enhancement**, not a change per view: an ES module (e.g.
  `core/forms/password-reveal.js`, loaded by the Core's behaviours like the other global ones) adds
  a toggle button to **every** `input[type="password"]` on the page, including the fields kpcrudy
  renders (e.g. Users › new user) and those of future modules, with no change to their views.
  Opt-out with `data-no-reveal` on the input. Today's fields: Auth login, re-authentication, lock,
  profile (password change, email change), reset form, password page; the web installer; Backup
  create, verify and restore.
- Markup: wrap the input in a Bootstrap `input-group` (or attach to an existing one) without
  breaking `is-invalid` feedback, help texts, floating labels, `required`, autofocus or the
  `dirtyGuard`; the button is `type="button"` (never submits), has `aria-controls`, `aria-pressed`
  and an accessible name that changes ("Mostra la contrasenya" / "Amaga la contrasenya", ca/es/en
  through `core/config.js`), and a Bootstrap icon (`bi-eye` / `bi-eye-slash`, `aria-hidden`).
- Behaviour: hidden by default; the field goes back to `password` before the form is submitted and
  on `pagehide` (so it is never saved or restored as plain text); focus stays in the field; works
  with the keyboard. Without JavaScript nothing changes. Server-side nothing changes.
- Pages that do not load the Core's JavaScript (if any: check the auth layout and the installer)
  get it the same way as the rest of the Core components.
- Document it in `docs/frontend.md` (a section "Password fields": automatic, opt-out with
  `data-no-reveal`, what it does on submit) and mention it in `docs/creating-a-module.md`.

## Tests

- Menu: for an administrator the settings zone is exactly the five groups with the listed
  children; a user holding only `users-users.list` sees only "Usuaris i accés" with Users; a
  customisation saved before (moved/renamed entry) is still applied; `permissions:check` passes.
- Theme: guest sees "Entra" → login; a back-office user sees "Administració"; a user without
  back-office access does not; the option off hides it; no inline code (`csp:check`).
- Password reveal: an e2e test on the login page and on a kpcrudy form (new user): the button
  shows and hides the value, has the right accessible name and `aria-pressed`, the value is sent
  normally, the field is `password` again after submitting, `data-no-reveal` skips a field; axe
  passes in light and dark mode; `csp:check` passes.
- e2e: the menu crawl and the screens still pass (update `tests/e2e/` if they rely on the flat
  list); screenshots of the grouped sidebar (desktop, phone, dark) and the public navbar.

## Docs

`docs/menus.md`, `docs/creating-a-module.md`, `docs/themes.md` (the option), `docs/frontend.md`
(password fields), `docs/user-guide.md`
(+ `docs/ca/`, `docs/es/`), `CHANGELOG.md` `[Unreleased]`.

## Done when

- The settings zone shows the five groups, customisations still apply, the public link works and
  every password field has the show/hide button, with tests and docs.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-38-01`, `D-38-02`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/38-settings-menu/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 38 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/38-settings-menu/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 38: Settings menu groups, the public sign-in link and password reveal" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
