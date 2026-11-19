# Session 46 — Section types from modules: news, calendar, gallery, carousel, logos and embeds

> Run it by saying: **`executa deploy/46-module-sections.md`** (or `run deploy/46-module-sections.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (lists of news and events, galleries and embeds inside pages; decisions D-46-01 … D-46-03).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 46
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
3. Check that session 45 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-46-module-sections`. Record the branch name for session 46 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "46 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Modules" › "Pages", "News (reference module)", the Events and Files modules, "Security baseline (must be on by default)" (CSP).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

More section types, declared by the modules that own the data, so a page can show a banner and then
all the news of a category, a calendar, a gallery, testimonials, logos or an embedded virtual tour
— e.g. a page "Notícies" and another "Sortides", each with its own banner and its own category.

**D-46-01 … D-46-03 must be decided** (embeds, carousel, the main news list page): if one is still
Open, stop and ask me to run `deploy/decide.md`.

**Visual references:** `docs/design/reference-home-sections.webp` (a modern home page: full-width
hero with an overlay, a cut-out image and a wavy bottom edge; a grid of icon links; cards; a dark
testimonials band; alternating image and text; a call-to-action band) and
`docs/design/reference-home-current-site.webp` (the school's current home: a photo banner /
carousel with a band of icon links; illustrated link tiles; a 4-column grid of news; an embedded
360° virtual tour; a strip of logos; a certification block; a notice with logos and text; a footer with five columns of links). They
are **layout references only**: the skeleton stays generic (a website for a company and/or a public
school, D-47-01) and never ships their texts, images or institution-specific content. Every section
type below exists to rebuild pages like those **without code**: the people who edit pages are not technical, so they never type markup or
shortcodes; they pick a section, fill in a form and move it.

## Scope

- **News** `news.list` (in the News module): mode *latest* (N cards) or *full list* (pagination
  with a query parameter namespaced per section so two lists on one page do not clash, optional
  search box), categories (one or several), columns, show date / summary / image, "more" link.
  Shows exactly what the public news list shows to the same visitor (audience, publish dates,
  languages). Per D-46-03, a page can be marked as **the main news list**: `/news` then redirects
  to it and the sitemap lists only it.
- **News category banner:** categories get an optional banner image (a new migration adding the
  column; released migrations are not edited); the article page and the category page show it as
  a hero when present.
- **Events** `events.calendar` (the existing calendar component with categories and initial view)
  and `events.list` (the next N events as a list or cards), with the same visibility as the
  public calendar.
- **Files** `files.gallery`: the images of a folder the visitor may read (the folder ACL; public
  folders for guests), grid or masonry, a lightbox (ES module, keyboard, captions).
- **Pages** `pages.testimonials` (photo, quote, name, role; background), `pages.logos` (logos with
  alt text and optional link; grayscale option) and `pages.carousel` (slides like the hero; per
  D-46-02: no automatic rotation by default, arrows and dots, a pause button when rotating,
  `prefers-reduced-motion` respected, keyboard accessible).
- **Embeds** `pages.embed` (D-46-01): an `https://` URL whose host is in an allow-list kept in
  Settings › Security (proposal `Pages.embedHosts`, empty by default; e.g. the virtual tour's host,
  `www.youtube-nocookie.com`); the CSP gets `frame-src` for exactly those origins through the
  Core's `ContentSecurity`; the iframe has a required title, `loading="lazy"`, `sandbox` with the
  minimum permissions and `referrerpolicy`; optional **click to load** (no request to the third
  party until the visitor clicks; on by default). **Owner's addition (D-46-01):** a URL whose
  host is not allowed is refused with a message that names the host and says that an
  administrator must add it in Settings › Security (a direct link for holders of
  `settings.security`); a saved section whose host was later removed shows the same warning in
  the editor and the preview and renders nothing on the public page (no broken iframe).

## Tests

- Each type: validation, rendering, visibility (a news item for a group never appears to a guest;
  a private folder's images never appear), pagination of two lists on one page, category filters.
- Category banner migration up/down/up and display; `/news` redirect when a main list page is set
  (and not otherwise).
- Embeds: a host outside the list refused with the message that names the host and the setting
  (the link only for `settings.security` holders); a section whose host was removed later shows
  the warning and renders nothing; the CSP of the page contains only the listed origins;
  click-to-load makes no third-party request before the click (e2e).
- Carousel: no rotation by default; pause button; reduced motion; keyboard (e2e + axe).
- Demo: pages "Notícies" and "Sortides" (banner + news of one category each), "Agenda" (banner +
  calendar) in the demo data; screenshots of them (desktop, phone, dark).

## Docs

`docs/sections.md` (the new types and how modules declare theirs), `docs/news.md`, `docs/events.md`,
`docs/files.md`, `docs/security.md` (embed hosts and the CSP), `docs/user-guide.md` (+ `ca`, `es`),
`CHANGELOG.md` `[Unreleased]`.

## Done when

- Pages can include news lists, calendars, galleries, testimonials, logos, carousels and allowed
  embeds, each respecting visibility and the CSP, with tests.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-46-01`, `D-46-02`…, with where, problem, proposal and alternatives; format in
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
   `php spark permissions:check`, `php spark csp:check`, `php spark security:check`, `npm run e2e` against a demo site. Fix everything that fails.
2. **Status report** in `deploy-status/46-module-sections/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 46 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/46-module-sections/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 46: Section types from modules: news, calendar, gallery, carousel, logos and embeds" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
