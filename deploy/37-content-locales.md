# Session 37 — Content languages from the site's locales

> Run it by saying: **`executa deploy/37-content-locales.md`** (or `run deploy/37-content-locales.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's review of 2026-11-19 (point 1: content and menus only offer ca/es/en).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 37
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
3. Check that session 36 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-37-content-locales`. Record the branch name for session 37 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "37 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Languages" (in "Conventions"), "Configuration and settings" (locales), "Pages", "News (reference module)", "Menus", the Events module.
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

Every translated content form, reader and API takes its languages from the site's locales
(`Modules\Core\I18n\Locales`), so a language added by configuration (`docs/i18n.md`, "Adding a
language") reaches News, Pages, Events, Menus and Tasks without touching module code, and the
required language is the site's default language, not always Catalan.

## Findings (owner's review, checked in the code on 2026-11-19)

- `Modules\Core\Content\ContentTranslations::LOCALES = ['ca' => …, 'es' => …, 'en' => …]` and
  `FALLBACK_LOCALE = 'ca'` are constants; `News\Controllers\Posts::LOCALES` copies them.
- `'ca'` is written by hand as the required language, the fallback or the source of slugs and
  activity summaries in about 20 files (`grep -rn "'ca'\|LOCALES\|FALLBACK_LOCALE" modules
  --include=*.php`, outside `Language/`, `Tests/` and the demo seeders), e.g.:
  `Menus/Controllers/{MenuItems,CustomLinks,BackendMenu}.php`, `Menus/Views/{item_form,custom_form}.php`
  (the `*` marker and `required`), `News/Controllers/{Posts,Categories,Api/Posts,Api/PublicNews}.php`,
  `News/Services/{NewsRepository,Publisher}.php`, `Pages/Controllers/Pages.php`,
  `Pages/Services/{PageResolver,PageSlugs,Revisions}.php`,
  `Events/Controllers/{Events,Categories}.php`, `Events/Services/{CalendarComponent,EventsApi}.php`,
  `Tasks/Controllers/{Projects,Api/Projects}.php`, `Tasks/Calendar/DueDates.php`,
  `Users/Calendar/Birthdays.php`, `Core/Calendar/CalendarEvent.php`, `Core/Settings/TranslatedText.php`.
  Review each hit: some are legitimate (a language file name, a migration of released data).
- This breaks the `CLAUDE.md` rule "the site's locales only through `Modules\Core\I18n\Locales`".
  The database is already fine (one translation row per locale): **no migration is needed**.

## Scope

- One source for content languages in the Core: `ContentTranslations` gets methods that read
  `Locales::available()` (languages offered, in order, with `Locales::name()`) and
  `Locales::default()` (required language and first fallback). Replace every use of the constants
  and every hand-written `'ca'` content language with them. Remove the constants (record what you
  did with code outside the skeleton in mind: modules built from the tutorials use them).
- kpcrudy `setTranslations()` (read `vendor/siensis/kpcrudy/docs/functions_EN.md`): the locale
  list and `required` / `fallbacks` options come from those methods; the same for kpapy resources
  that expose translations.
- Menus: one label per available language, the default language required (validation, the `*`
  marker, activity summaries, the tree and parent list).
- Slugs, summaries and titles that used "the Catalan text" use the default language's text (with
  the usual fallback when it is empty).
- Behaviour to define and document (`docs/i18n.md`): a language added later shows empty tabs and
  readers fall back (existing chain); a language removed from `Core.locales` keeps its rows but
  they are not shown or edited; when the default language changes, existing items stay readable
  and the new default becomes required on their next edit.
- Demo seeders may keep literal `ca`/`es`/`en` texts (demo data).

## Tests

- A test case (Core support class reusable by modules) that configures a fourth language (e.g.
  `fr` in `Config\App::$supportedLocales` and `Core.locales`) and `es` as the default: the News,
  Pages, Events (event and category), Tasks (project) and Menus (item, custom link) forms show four
  tabs with `es` required; saving without `es` is refused and without `ca` is accepted; readers and
  the API answer in `fr` with fallback; slugs come from the `es` title.
- A static test (like `ActivityLabelsTest`) that fails when a module controller, service, view or
  calendar class names a content language literally (allow-list: language files, demo seeders,
  migrations already released, `Locales` itself).
- Existing tests keep passing with the default configuration (`ca`, `es`, `en`; default `ca`).

## Docs

`docs/i18n.md` ("Adding a language" now covers content), `docs/news.md`, `docs/pages.md`,
`docs/menus.md`, `docs/events.md`, `docs/tasks.md` and the tutorials that say "Catalan required";
the specification passages that fix ca/es/en for content; `CHANGELOG.md` `[Unreleased]` › Fixed.

## Done when

- Adding a language by configuration gives it to every content form, reader and API, with tests;
  no content language is hard-coded (static test).
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-37-01`, `D-37-02`…, with where, problem, proposal and alternatives; format in
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
   `php spark permissions:check`, `php spark csp:check`. Fix everything that fails.
2. **Status report** in `deploy-status/37-content-locales/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 37 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/37-content-locales/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 37: Content languages from the site's locales" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
