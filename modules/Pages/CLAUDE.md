# Pages module

**Purpose:** content pages of the public site: a tree of multilingual pages with slugs per
language, templates from the public theme, rich text (the Core's editor + HTMLPurifier),
statuses and scheduling, visibility (public / signed-in users / groups), SEO fields, revisions
with restore, preview and duplicate, the home page setting, the XML sitemap and the menu link type
"Page". Full description: `docs/pages.md`.

## Resources and permissions

- `pages` (crud − `import`, named `pages`; extra `publish`): `pages.{list,show,add,edit,delete,
  export,trash,restore,purge,publish}`. Operations: preview and revisions `show`, restore a
  revision `edit`, duplicate `add`, publish/unpublish `publish`.
- Grants: admin `pages.*`; editor `pages.{list,show,add,edit,publish}`.
- Setting `Pages.homePage` (path). Menu "Pages" (sidebar, order 25). Link type
  `Menu/PageLink.php`. Events `Config/Events.php` (`homePage`). No API, widgets, tasks or
  notifications yet.
- Tables: `pages`, `page_translations`, `page_groups`, `page_revisions` (one migration each).

## Main pieces

- `Services/PageVisibility.php` (who reads what; Core `PublishWorkflow` + `Audience`),
  `PageTree.php` (tree, paths, parent options), `PageResolver.php` (path → page),
  `PageSlugs.php` (fill, clash, reserved), `PageRepository.php`, `PageRenderer.php` (templates,
  meta), `Revisions.php`, `PageCopier.php`, `SitemapBuilder.php`, `HomePageResolver.php`.
- `Controllers/Pages.php` (kpcrudy), `PublicPages.php` (catch-all, lowest priority),
  `Sitemap.php`; views `templates/{default,wide,sidebar}.php`, `partials/article.php`,
  `revisions.php`; `Storage/FeaturedImages.php` (Core `CrudUploadHandler`).

## Decisions

D-20-01 (names, URLs, setting, theme `$pageTemplates`: **open**, decide), D-20-02 … D-20-07
accepted (`docs/decisions.md`).

## Open tasks

- D-20-01: apply the owner's decision.
- Session 21: the Menus module consumes `Menu/PageLink.php`.
- Session 23: featured image with the file picker; "Insert image" in the editor (D-17-08).
- Session 26: pages in the API (if wanted). Session 31: the path's language could select the
  content language (D-20-05).

## How to test

`vendor/bin/phpunit modules/Pages` (base `Tests/PagesTestCase.php`: Auth fixture, `page()`,
`form()`, `code()`, `text()`, `editor()`). By hand: `php spark modules:demo Pages`, then
`backend/pages`, `/qui-som`, `/intranet`, `/sitemap.xml`.
