# Session 49 — Icon picker for icon fields and the rich-text editor

> Run it by saying: **`executa deploy/49-icon-picker.md`** (or `run deploy/49-icon-picker.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's request of 2026-11-19 (typing `bi bi-egg` by hand is hard: a button to choose Bootstrap Icons in the icon fields and in the rich-text editor; decisions D-49-01 and D-49-02).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 49
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
3. Check that session 48 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-49-icon-picker`. Record the branch name for session 49 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "49 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Front-end assets and JavaScript", "Menus", "Modules › Menus", the rich-text editor and "Images inside rich text" (file picker), "Security baseline (must be on by default)" (HTMLPurifier).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

Nobody types `bi bi-…` by hand any more: every icon field of the back office gets a **"Choose an
icon"** button that opens a searchable grid of the vendored Bootstrap Icons, and the rich-text
editor gets an **"Insert an icon"** button. The text field stays (it works without JavaScript)
and the server validates icons as it does today.

**D-49-01 and D-49-02 must be decided** (the markup convention and the sanitiser change): if one
is still Open, stop and ask me to run `deploy/decide.md`.

## Findings (2026-11-19)

- Icon fields today are plain text inputs with a placeholder `bi bi-star`: Menus' public items
  (`modules/Menus/Views/item_form.php`), back-office entry retouches (`entry_form.php`) and
  custom groups/links (`custom_form.php`), validated with `BackendLayout::ICON`
  (`/^bi bi-[a-z0-9-]{1,50}$/`).
- Session 45 plans an **icon field type for page sections "chosen in a searchable grid"**: by
  the time this session runs, a picker may already exist (in Pages or in the Core). **Reuse it**:
  move it to the Core if it lives in a module, keep one implementation.
- Bootstrap Icons are vendored in `public/assets/vendor/bootstrap-icons/` (about 2,000 glyphs in
  `bootstrap-icons.min.css`; `vendor.json` holds the checksums). The editor is the vendored
  Quill 2 (`core/forms/editor.js`), already extended with "Insert image" (file picker).
- `Modules\Core\Security\HtmlSanitizer` (HTMLPurifier) allows `<i>` but only the alignment
  classes (`Attr.AllowedClasses`), so an icon `<i class="bi bi-egg">` loses its classes today.

## Scope

### Icon picker (Core component, D-49-02)

- One ES module in the Core (e.g. `core/forms/icon-picker.js`) enhances every
  `input[data-icon-picker]` (or the convention D-49-02 decides) on the page and fields added
  later, like `core/forms/password-reveal.js` (session 38): a button "Tria una icona" next to the
  field (`input-group`), a preview of the current icon, and a Bootstrap modal with a search box
  (by name and by the words of the icon's name: `egg-fried` matches "egg" and "fried"), a
  keyboard-accessible grid (arrow keys, Enter, Escape; each option has its name as accessible
  name), an option to clear, and a live region with the number of results.
- **The list of icons comes from the vendored font**, not from the network: `assets:publish`
  (or a small generator run at publish time) writes a JSON list (names, sorted) next to the
  vendored files or in the Core's published assets, checked by `assets:publish --check`; no CDN,
  no outbound request, nothing inline (CSP).
- The field keeps working as a text input; the server validation does not change (the Menus
  pattern; the section icon field of session 45). Add the attribute to the three Menus forms and
  to every icon field that exists when this session runs (section editors of sessions 45–47,
  Dashboard quick links if they take icons); a kpcrudy field gets it through its `attributes`
  option, documented for modules.
- Texts in ca/es/en through `core/config.js` (`Core.js.iconPicker.*`).

### "Insert an icon" in the rich-text editor (D-49-01)

- A toolbar button in `core/forms/editor.js` opens the same picker and inserts the icon at the
  cursor as an inline embed (a Quill blot) that renders `<i class="bi bi-<name>" aria-hidden="true"></i>`;
  it can be deleted like a character and survives reloading the content.
- `HtmlSanitizer` keeps on `<i>` only the classes `bi` and `bi-<name>` where `<name>` is in the
  icon list (a custom attribute definition, not a regular expression that lets anything through),
  plus `aria-hidden="true"`; everything else on `<i>` is still removed. No `style`, no other
  element gains classes. Bump `HTML.DefinitionRev`.
- Accessibility: inserted icons are decorative (`aria-hidden`); the editor's help text says to
  write the meaning in words next to an icon that carries one.
- Public pages already load Bootstrap Icons; check the readers of News and Pages, the API output
  of rich text (kept as sanitised HTML) and plain-text summaries (icons dropped).

## Tests

- Picker (e2e): a Menus item form and a kpcrudy form with the attribute — open, search ("egg"),
  choose with the keyboard, the field gets `bi bi-egg`, preview updated, clear; works at phone
  width; axe in light and dark mode; no CSP violation; without the attribute nothing changes.
- The icon list: generated from the vendored CSS, sorted, complete (a known icon present), and
  `assets:publish --check` fails when it is stale.
- Sanitiser (unit): `<i class="bi bi-egg" aria-hidden="true">` kept; an unknown icon name, extra
  classes, `style`, `onclick` and classes on other elements removed; the existing alignment
  classes still work; a test for each rule (security rule changed).
- Editor (e2e): insert an icon in a News post, save, reopen, the icon is there; the public page
  shows it with `aria-hidden`.
- Screenshots: the picker (desktop, phone, dark), a menu item with its icon chosen, the editor
  with an inserted icon and the public page.

## Docs

`docs/frontend.md` (a section "Icon fields": the attribute, the picker, the list), `docs/menus.md`,
`docs/pages.md` (icons in rich text), `docs/security.md` (what the sanitiser allows),
`docs/creating-a-module.md`, `docs/user-guide.md` (+ `docs/ca/`, `docs/es/`), `CLAUDE.md` (icon
fields convention), `CHANGELOG.md` `[Unreleased]`.

## Done when

- Every icon field has the picker, the editor inserts icons that the sanitiser keeps and nothing
  else gets through, with tests and docs.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-49-03`, `D-49-04`…, with where, problem, proposal and alternatives; format in
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
   `php spark security:check`, `npm run e2e` against a demo site. Fix everything that fails.
2. **Status report** in `deploy-status/49-icon-picker/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 49 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/49-icon-picker/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 49: Icon picker for icon fields and the rich-text editor" with a summary, the checks' output and a link to the status report.
   No session follows this one: merge this branch into `main` yourself with the procedure of
   "Before starting" (checks, `docs/progress.md` → merged, push, never `--force`; if `main`
   cannot be pushed, stop and ask me to merge the pull request on GitHub).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "the planned sessions are complete".
