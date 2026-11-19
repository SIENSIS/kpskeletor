# Session 47 — Reusable blocks, theme zones, footer columns and home page models

> Run it by saying: **`executa deploy/47-reusable-blocks.md`** (or `run deploy/47-reusable-blocks.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (blocks shown on every page (e.g. the funding notice), a footer in columns and ready-made home pages; decision D-47-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 47
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
3. Check that session 46 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-47-reusable-blocks`. Record the branch name for session 47 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "47 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Modules" › "Pages", "Themes", "Menus".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

Sections defined **once** and shown in many places (the funding notice with its logos and legal
text, partner logos…), theme zones to place them on every page, a footer in columns built from the
footer menu, and two ready-made home page models so nobody starts from scratch.

**D-47-01 must be decided** (names, tables, the theme key for zones, URLs): if it is still Open,
stop and ask me to run `deploy/decide.md`.

**Visual references:** `docs/design/reference-home-sections.webp` (a modern home page: full-width
hero with an overlay, a cut-out image and a wavy bottom edge; a grid of icon links; cards; a dark
testimonials band; alternating image and text; a call-to-action band) and
`docs/design/reference-home-current-site.webp` (the school's current home: a photo banner /
carousel with a band of icon links; illustrated link tiles; a 4-column grid of news; an embedded
360° virtual tour; a strip of logos; a certification block; the funding notice with logos and legal
text; a footer with five columns of links). Every section type below exists to rebuild those pages
**without code**: the people who edit pages are not technical, so they never type markup or
shortcodes; they pick a section, fill in a form and move it.

## Scope

- **Reusable blocks** (Pages module): a block is a named list of sections (same editor, same
  section types, translations and revisions as page sections; D-47-01 proposes a table
  `page_blocks` and reusing the section tables with an owner type). Used in two ways:
  - inside a page, through a section type `pages.block` that shows a chosen block (changing the
    block changes every page that uses it);
  - in a **theme zone**: themes declare zones (proposal: `$zones` in `Theme.php`, e.g.
    `public.after_header`, `public.before_footer`); the default theme renders them; a block is
    assigned to a zone with a visibility rule (every page / the home page / selected pages /
    every page except selected ones) and an order.
  Screen `backend/pages/blocks` per D-47-01 (permissions of the Pages resource or its own per the
  decision). Blocks follow the visitor's language and the sections' own visibility.
- **Footer in columns:** the default theme renders the `footer` menu with each top-level item as a
  column heading and its children as the links (as in the school's current footer), with a theme
  option for the number of columns and an optional text column (address and contact, translated);
  a footer menu without children keeps today's look.
- **Home page models:** "New page from a model" creates a page in `sections` mode from a model
  shipped by the Pages module (definitions in the module, placeholder texts in ca/es/en and
  placeholder images made for the skeleton): **"Institut"** (banner, icon band, illustrated
  quick links, latest news, embed placeholder, logos, image and text) and **"Moderna"** (hero with
  wave, icon links, cards, testimonials, image and text, call to action). Themes and modules may
  add models (declared like section types).
- Demo data: the funding-notice block assigned to `public.before_footer`, a footer menu with
  columns.

## Tests

- Blocks: editor, revisions, a block section inside a page, zone assignment and each visibility
  rule, language fallback, deleting a block that is in use (refused or confirmed, per D-47-01).
- Footer columns from the menu, with and without children; theme option.
- Models create a page whose sections validate; placeholders are translated.
- e2e + axe on a page with zones and the footer at three widths, light and dark; screenshots of
  both models (desktop and phone).

## Docs

`docs/sections.md` (blocks, zones, models), `docs/themes.md` (`$zones`, footer columns),
`docs/pages.md`, `docs/menus.md` (footer columns), `docs/user-guide.md` (+ `ca`, `es`),
`CHANGELOG.md` `[Unreleased]`.

## Done when

- The two reference home pages can be rebuilt from the models and edited with forms; blocks appear
  where they are assigned; the footer shows columns; with tests.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-47-01`, `D-47-02`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/47-reusable-blocks/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 47 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/47-reusable-blocks/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 47: Reusable blocks, theme zones, footer columns and home page models" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
