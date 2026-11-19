# Session 50 — Site search: a Core search index fed by the modules

> Run it by saying: **`executa deploy/50-site-search.md`** (or `run deploy/50-site-search.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's request of 2026-11-19 (a chat assistant without external services, built on the keywords and metadata of pages and news: first the search it needs; decision D-50-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 50
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
3. Check that session 49 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-50-site-search`. Record the branch name for session 50 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "50 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Modules" › "Pages", "News (reference module)", "Cross-module services (Core)" (calendar, as the model of a contract), "Security baseline (must be on by default)", the i18n sections (language in public URLs).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

A **site search**: one box in the public header finds pages, news and, later, any module's
content in the visitor's language, ranked by where the words appear, and never shows anything the
visitor may not see. It is built on a Core **search index** fed by modules through a contract,
like the calendar; session 51's chat assistant uses the same index.

**D-50-01 must be decided** (names, URLs, the contract, tables, settings): if it is still Open,
stop and ask me to run `deploy/decide.md`.

## Findings (2026-11-19)

- Pages have per language `title`, `summary`, `body`, `meta_title`, `meta_description`
  (`page_translations`); News have `title`, `summary`, `body` (`news_translations`) and categories.
  There is no keywords field and no public search: only kpcrudy/kpapy `searchFields()` in lists.
- Visibility is already centralised per module (`NewsVisibility`, the Pages audience from
  `Modules\Core\Content`: public / private / groups, publish workflow, dates) and the calendar
  shows how a module pushes its items to a Core service (`service('calendar')->publish()` /
  `unpublish()`, providers, resync; `docs/calendar.md`).
- MariaDB and SQLite full-text search differ; the project tests on both.

## Scope

### Core search index (D-50-01)

- A contract in `modules/Core/Search/` (names as decided): a module declares `$searchSources`
  (`<module>.<key>`) in its manifest and keeps the index current with
  `service('search')->index(SearchDocument)` / `remove()` on every change, plus a
  `reindex` source method used by `php spark search:reindex [<Module>]`. A `SearchDocument` has:
  source and id, locale, title, summary, body text (HTML stripped), extra keywords, URL (built
  with `site_url()`, per language), date, and its **audience** (public, signed-in, groups) and
  publish window.
- **An index of our own** that works the same on MariaDB and SQLite: tables for documents and
  terms (one migration each), terms normalised per language (lower case, accents folded, the
  language's stop words removed; a light suffix rule per language is optional), weights: title
  (×5) > meta title / keywords (×3) > summary / meta description (×2) > body (×1); recency as a
  tiebreak. Queries are bound parameters only; limits on query length and number of terms.
- **Visibility is enforced in the query**: guests only get public documents inside their publish
  window; signed-in users also documents for signed-in users and their groups; drafts, private
  items of other groups and disabled modules never appear (a disabled module's documents are
  skipped; `moduleUninstalling` deletes them). Tested with forged requests.
- Pages and News are the first sources (index on save, publish, unpublish, delete, translation
  change, audience change); `modules:sync` and `search:reindex` rebuild them.

### Public search

- A theme option `showSearch` (default on) puts a search box in the public navbar (inside the
  collapsed menu on phones); results page with the language prefix (D-37-02), title, summary,
  the matching words highlighted (escaped), source label and date; pagination; "no results" with
  a link to the contact page when the Contact module is enabled.
- Rate limit per IP (`ratelimit:<limit>`), no outbound request, results not cached across users.
- A kpapy endpoint `api/v1/public/search` (public, same rules) only if D-50-01 says so.

## Tests

- Index: normalisation per language (accents, stop words), weights and order, a document per
  locale, updates and removals on every change, `search:reindex`, the same results on SQLite and
  MariaDB.
- Visibility: a guest never finds drafts, scheduled, private or group content; a group member
  finds their group's page; a disabled module's documents disappear; forged queries (very long,
  many terms, SQL-looking input) are harmless.
- Public: the box and results page per language, highlighting escaped, phone width, dark mode,
  axe, `csp:check`; the rate limit answers 429.
- Screenshots: the search box, results (desktop, phone, dark), no results.

## Docs

`docs/search.md` (new: the contract for modules, the index, visibility, `search:reindex`),
`docs/creating-a-module.md`, `docs/themes.md` (the option), `docs/commands.md`,
`docs/user-guide.md` (+ `docs/ca/`, `docs/es/`), `CLAUDE.md` (search convention),
`CHANGELOG.md` `[Unreleased]`.

## Done when

- Visitors can search pages and news in their language and only find what they may see; modules
  have a documented contract to add their content; with tests.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-50-02`, `D-50-03`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/50-site-search/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 50 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/50-site-search/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 50: Site search: a Core search index fed by the modules" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
