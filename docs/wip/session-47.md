# Session 47 — work in progress (handoff)

The session stopped half way: the cloud container's shell stopped answering (every command,
even `echo`, exited 1 with no output), so the work so far was pushed through the GitHub
connector. Continue with `deploy/continue.md 47` in a new session on branch
`ccr-e8cb6c4c-zykzfx`, then delete this file.

## Done (pushed, not yet run through the full checks)

- `main`: session 46 marked **merged** in `docs/progress.md` (pushed to `main`). Add the row
  of session 47 (branch `ccr-e8cb6c4c-zykzfx`, in progress) to the status table.
- Core: `ThemeManifest::$zones` (`<layout>.<name>` => label key) and `$pageModels`, checked by
  `ThemeValidator::checkZones()`; `ThemeManager::zones()`, `zone()` (fires the Core event
  `themeZone` with a `ZoneContent`; failures are logged and leave the zone empty) and
  `pageModels()`; helper `theme_zone()`.
- Core: `Sections/PageModel`, `PageModelContext` (texts per site language, placeholder images),
  `PageModelRegistry` (modules + public theme chain), `PlaceholderImage` (GD: gradient,
  figure, logo). Manifest key `$pageModels` (`ModuleManifest`, `ManifestValidator`,
  declaration kind `pageModel` in `DeclarationRegistry`).
- Core: `ManifestValidator` lets a module with several resources keep one resource named after
  the module itself (Pages keeps `pages.*` beside the new `pages-blocks.*`; record as D-47-02).
  Still to do: update `Core.manifest.resourceName` in ca/es/en and the sentence in `CLAUDE.md`.
- Pages migrations: `page_blocks` (name, status), `page_block_zones` (block, zone, rule
  all/home/only/except, pages JSON, position), and the block owner of `page_sections` and
  `page_revisions` (`block_id` FK CASCADE, `page_id` nullable). `PagesModuleTest` migration
  up/down/up passed on SQLite (MariaDB not yet tried).

## Planned design (D-47-01 decided; write D-47-02 as Accepted (proposal))

1. `PageSections`: constructor `string $owner = 'page'` (`OWNERS = ['page' => 'page_id',
   'block' => 'block_id']`), `PageSections::forBlocks()`; replace `'page_id'` in every query by
   the owner column.
2. Services: `PageBlocks` (repository, uses = zone rows + `pages.block` sections, delete removes
   its uses and records revisions of the pages touched), `BlockZones` (assignments,
   `forZone()`), `CurrentPage` (set by `PageRenderer`: page id, home), `BlockRevisions`
   (snapshot `{block, sections}` in `page_revisions.block_id`), zone listener in
   `Pages/Config/Events.php` (`themeZone`).
3. Section type `pages.block` (select of blocks); `PageRenderer` expands it into the block's
   visible sections before `SectionRenderer` (no nesting: a block may not contain
   `pages.block`, refused on save and hidden in its gallery).
4. Controllers/routes (declared before the pages CRUD): kpcrudy CRUD `backend/pages/blocks`
   (resource `blocks` => `pages-blocks.{list,add,edit,delete}`, grants like `pages`), the block's
   section editor `backend/pages/blocks/<id>/sections` (share the page editor through a base
   class), placement `backend/pages/blocks/<id>/zones`, revisions; "New page from a model"
   (`backend/pages/models`, `pages.add`).
5. Models `pages.school` ("Centre educatiu") and `pages.company` ("Empresa"): neutral
   ca/es/en texts in `Pages.models.*`, images from `PlaceholderImage`; each section validated
   through `SectionForm::formValues()` + `validate()`. No funding or institution-specific words
   (test with a forbidden-word list).
6. Default theme: `$zones` `public.after_header` / `public.before_footer` printed inside
   `<main>` (landmarks); footer columns from the `footer` menu (top-level items with children =
   columns), options `footerColumns` and a translated `footerContact` text; no children = today's
   look.
7. Demo: neutral "partner logos" block in `public.before_footer` (all pages); footer menu with
   columns (Menus demo seeder).
8. Tests, docs (`sections.md`, `themes.md`, `pages.md`, `menus.md`, user guide ca/es/en),
   `CHANGELOG.md` [Unreleased], e2e + axe, screenshots, report, PR.

## Environment notes

Composer needed the known issue 04 workaround again: phpstan from a shallow clone of tag 2.3.5
through a scratch copy's lock, then `env -u COMPOSER_AUTH composer install --prefer-source
--no-scripts --no-autoloader` in the scratch copy, `vendor/` moved in, `composer dump-autoload`,
and `vendor/bin` links created by hand.
