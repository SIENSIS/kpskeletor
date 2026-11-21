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
- Setting `Pages.homePage` (page id, a `select` with `optionsProvider` `Settings/HomePageOptions.php`). Menu "Pages" (sidebar, order 25). Link type
  `Menu/PageLink.php`. Events `Config/Events.php` (`homePage`). No API, widgets, tasks or
  notifications yet.
- Tables: `pages` (+ `layout`, a later migration), `page_translations`, `page_groups`,
  `page_revisions`, `page_sections`, `page_section_translations` (one migration each).
- Section types (`$sectionTypes`, D-45-01): `Sections/` (`HeroSection`, `LinksSection`, `CardsSection`,
  `MediaTextSection`, `CtaSection`, `TextSection`, base `PagesSectionType`), views `Views/sections/`,
  thumbnails `Assets/img/sections/`.
- Session 46: `CarouselSection` (`pages/carousel`, no rotation by default, D-46-02),
  `TestimonialsSection`, `LogosSection`, `EmbedSection` (`pages/embed`, click to load; hosts in
  `Embeds/EmbedHosts.php`, setting `Pages.embedHosts` in Settings › Security, group
  `security.embeds`; CSP `frame-src` per page through `cspSources()`, D-46-01). Demo pages
  "Notícies", "Sortides", "Agenda", "Més seccions" (`PagesDemoSeeder::moduleSectionPages()`).
  Tests `Tests/ModuleSectionsTest.php` (also the News and Events section types).

- Session 47 (D-47-01, D-47-02): resource `blocks` (crud − show, export, import, trash, restore,
  purge) → `pages-blocks.{list,add,edit,delete}` (admin all; editor list, add, edit); menu
  "Reusable blocks" (sidebar, 26). Tables `page_blocks`, `page_block_zones`,
  `page_block_sections`, `page_block_section_translations`, `page_block_revisions`. Section type
  `BlockSection` (`pages.block`), page models `Models/SchoolModel.php` (`pages.school`) and
  `Models/CompanyModel.php` (`pages.company`), thumbnails `Assets/img/models/`. Event
  `themeZone` in `Config/Events.php`.

## Main pieces

- `Services/PageVisibility.php` (who reads what; Core `PublishWorkflow` + `Audience`),
  `PageTree.php` (tree, paths, parent options), `PageResolver.php` (path → page),
  `PageSlugs.php` (fill, clash, reserved), `PageRepository.php`, `PageRenderer.php` (templates,
  meta), `Revisions.php`, `PageCopier.php`, `SitemapBuilder.php`, `HomePageResolver.php`.
- `Controllers/Pages.php` (kpcrudy), `PublicPages.php` (catch-all, lowest priority),
  `Sitemap.php`; views `templates/{default,wide,sidebar}.php`, `partials/article.php`,
  `revisions.php`; `Storage/FeaturedImages.php` (the featured image's file picker, Core `CrudUploadHandler`,
  session 23); "Insert image" in the body (`RichTextEditor::images('pages')`).

- Pages built from sections (session 45): `Services/PageSections.php` (storage, order, snapshot /
  replace for revisions and duplicates), `Controllers/Sections.php` (the editor at
  `backend/pages/<id>/sections`, routes before the CRUD), views `Views/editor/{list,gallery,form}.php`,
  `Assets/js/section-list.js` (`pages/section-list`), template `templates/sections.php` (full width).
  The Core gives the registry, fields, forms and renderer (`modules/Core/Sections`, docs/sections.md).

- Reusable blocks (session 47): `Services/PageBlocks.php` (repository, uses, delete with uses),
  `BlockZones.php` (placements, rules `all|home|only|except`), `BlockRenderer.php` (expands
  `pages.block` sections, fills zones), `CurrentPage.php` (set by `PageRenderer`),
  `BlockRevisions.php`; controllers `Blocks.php` (kpcrudy, `backend/pages/blocks`),
  `BlockSections.php` and `Sections.php` (both extend `SectionEditor.php`), `BlockPlacements.php`
  (`backend/pages/blocks/<id>/zones`), `PageModels.php` (`backend/pages/models`) with
  `Services/PageModelBuilder.php`; views `Views/blocks/zones.php`, `Views/editor/models.php`.
  Demo: `PagesDemoSeeder::partnerLogos()` (block in `public.before_footer`).

## Decisions

D-20-01 decided by the owner (names and URLs as built; the home page chosen from a list of pages,
stored as the id), D-20-02 … D-20-07 accepted (`docs/decisions.md`).

Session 37 (D-37-01): translated texts use the site's languages and require its default language through `Modules\Core\Content\ContentTranslations` (`locales()`, `crudOptions()`, `defaultText()`…); never write a language code (`tests/unit/I18n/ContentLanguagesTest.php`). Tests `Tests/ContentLocalesTest.php` (`Modules\Core\Tests\ContentLocalesTrait`).

## Open tasks

- Session 21: the Menus module consumes `Menu/PageLink.php`.
- Session 26: pages in the API (if wanted).
- Done in session 37 (D-37-02): public URLs carry the language (`/es/quienes-somos`); `PublicPages`
  gives each language's path to `service('localeurls')->setAlternates()` and redirects another
  language's slug to the URL language's path; `PageTree::translatedPaths()`; the sitemap lists every
  translation; language codes are reserved slugs.

Session 47: D-47-01 decided (blocks, zones, `pages-blocks`, generic models), D-47-02 accepted
(block tables of their own, `pages` prefix beside `pages-blocks`, page models, zones, footer).

Session 45: D-45-01 decided (layout, tables, `$sectionTypes`, editor URL), D-45-02 accepted (how it was built).

## How to test

`vendor/bin/phpunit modules/Pages` (base `Tests/PagesTestCase.php`: Auth fixture, `page()`,
`form()`, `code()`, `text()`, `editor()`). By hand: `php spark modules:demo Pages`, then
`backend/pages`, `/qui-som`, `/intranet`, `/sitemap.xml`, `/portada-seccions` and its editor
(`backend/pages/<id>/sections`). Tests of sections: `Tests/PageSectionsTest.php` and
`modules/Core/Tests/SectionsTest.php`. Session 47: `Tests/BlocksTest.php`,
`Tests/PageModelsTest.php` (also the forbidden-words check), `modules/Menus/Tests/FooterColumnsTest.php`,
`modules/Core/Tests/ThemeZonesTest.php`; by hand `backend/pages/blocks`, `backend/pages/models`.
