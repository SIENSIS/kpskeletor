# Session 45 — Pages built from sections: registry, editor and the first section types

> Run it by saying: **`executa deploy/45-page-sections.md`** (or `run deploy/45-page-sections.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (banners and structured home pages without shortcodes; decision D-45-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 45
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
3. Check that session 44 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-45-page-sections`. Record the branch name for session 45 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "45 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Modules" › "Pages", "Themes", "Front-end assets and JavaScript", "Cross-module services (Core)".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

A page can be built from an ordered list of **sections** (hero banner, quick links, cards, image
and text, call to action, rich text) edited with forms, previewed and versioned like the rest of
the page, instead of a single rich-text body. The Core gets a **registry of section types** that
modules extend (sessions 46–48 add more), and the public layout gets full-width rendering.

**D-45-01 must be decided** (names, tables, manifest key, URLs): if it is still Open, stop and ask
me to run `deploy/decide.md`. Build what it says; the scope below is its proposal.

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

### Section types in the Core (D-45-01)

- `Modules\Core\Sections\SectionType` (abstract): key `<module>.<name>`, label, description, a
  thumbnail (an SVG schematic in the module's assets), the **field schema**, the styles it offers
  and `render(SectionData, RenderContext): string` through a theme view
  (`Views/sections/<name>.php`, overridable in `themes/<slug>/Views/overrides/<Module>/sections/`).
  Modules declare their types in the manifest (`$sectionTypes`, D-45-01); the registry reads them
  like widgets (`DeclarationRegistry`, disabled modules hidden).
- **Field types** (validated on the server against the schema; each with its form control):
  text (max length), textarea, rich text (the Core editor, `HtmlSanitizer` on save and on output),
  image (the file picker; only files the editor may use; alt text required unless marked
  decorative), link (internal page / module link types `MenuLinkType` / route / `https://` URL,
  validated like menu items; label), icon (from the vendored Bootstrap Icons, chosen in a searchable
  grid), select (from a fixed list), number (min/max), boolean, and **list** (repeated items with
  their own fields, min/max items, reorderable). Translated fields keep one value per available
  locale (`ContentTranslations`, the site's locales; required in the default one).
- **Common options of every section**, all from fixed lists (no CSS): background from the theme's
  palette (none, light, dark, primary, image), spacing (S/M/L), width (contained / full), anchor
  id (validated slug) and hidden.

### Pages in "sections" mode

- A page has a layout: `article` (today's body) or `sections` (D-45-01). Switching keeps the other
  content until saved. Tables per D-45-01 (proposal: `page_sections` with type, position, options
  and non-translated values as JSON; `page_section_translations` with one row per section and
  locale and the translated values as JSON). One migration per table; no released migration edited.
- **Section editor** (`backend/pages/<id>/sections` per D-45-01, `pages-pages.edit` and the page's
  own record rules): the list of sections with drag and drop and keyboard reordering (reuse the
  Menus tree editor's patterns), "Add section" opens a gallery of types with thumbnails and
  descriptions, each section opens its form (generated from the schema), duplicate / hide / delete,
  language tabs, unsaved-changes guard, "Preview" (the page's existing preview, draft content).
  Saving creates a **page revision** that includes the sections; restoring a revision restores them.
- **Rendering:** a page in `sections` mode renders its visible sections in order, each wrapped in
  `<section>` with its options as classes; the public layout gets a full-width mode (no outer
  `.container`; contained sections add their own) so banners reach the edges. The page's audience
  and publish rules are unchanged; alternate language URLs keep working (`LocaleUrls`).

### The first section types (module Pages)

1. **Hero banner** `pages.hero`: background image with an overlay colour from the palette, title,
   text, up to two buttons (link + style), optional side image (e.g. a cut-out photo), height
   (S/M/L/full), text alignment, bottom edge (straight or wave, an SVG in the theme's CSS).
2. **Quick links** `pages.links`: items with icon **or** image, label and link; styles *icons*
   (icon over the label, as in the first reference), *tiles* (illustration with a coloured label,
   as in "Avisos i anuncis") and *band* (a row of icons on a coloured band, as under the school's
   banner); 2–6 columns, responsive.
3. **Cards** `pages.cards`: heading, intro, cards (image, title, text, button), 2–4 columns.
4. **Image and text** `pages.media_text`: image left or right, title, rich text, button.
5. **Call to action** `pages.cta`: title, text, button, background.
6. **Text** `pages.text`: rich text (the Core editor).

All responsive (phone, tablet, desktop), light and dark mode, AA contrast on every background,
images with `loading="lazy"`, `width`/`height` and alt texts; no inline code (Bootstrap and ES
modules only); texts of the forms in ca/es/en.

### Demo data

`PagesDemoSeeder` adds a published page "Portada amb seccions" built with these six types
(placeholder images made for the demo, e.g. simple SVG illustrations: no third-party photos),
reachable from the demo menu.

## Tests

- Registry: types from enabled modules only; a module type overridden by a theme view.
- Validation (forged POSTs): unknown type or field, wrong types, too many list items, a
  `javascript:` or `http:` link, a file key the editor may not use, HTML in a text field (escaped),
  script in rich text (sanitised); another user's page per the record rules.
- Ordering, hide, duplicate, delete; translations with fallback; revisions restore sections;
  migrations up/down/up; switching layout keeps the other content.
- Rendering of each type (snapshot of the important markup), full-width layout, audience rules.
- e2e + axe: the demo page at phone, tablet and desktop, light and dark (no overflow, no CSP
  violation, no axe violation); the editor: add, reorder with the keyboard, save, preview.
- Screenshots: the demo page (desktop, phone, dark), the section gallery and a section form.

## Docs

`docs/pages.md` (sections: for authors and for developers), `docs/sections.md` (new: how a module
adds a section type, the field schema, theme overrides), `docs/themes.md` (section views and
palette), `docs/creating-a-module.md` (`$sectionTypes`), `docs/user-guide.md` (+ `ca`, `es`),
`CHANGELOG.md` `[Unreleased]`.

## Done when

- An editor can build a page like the references' top halves (hero, links, cards, image and text,
  call to action) with forms only, preview it, publish it and restore an earlier revision; modules
  can add section types; with tests.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-45-01`, `D-45-02`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/45-page-sections/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 45 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/45-page-sections/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 45: Pages built from sections: registry, editor and the first section types" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
