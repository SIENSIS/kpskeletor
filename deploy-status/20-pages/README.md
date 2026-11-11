# Session 20 — Pages module

Branch: `claude/eager-brahmagupta-2odzxu` · Pull request: (pending) · Date: 2026-11-11

## What was built

- **Pages module** (`modules/Pages`): content pages of the public site in a tree, with title,
  slug, summary, rich text and SEO fields (meta title, description, canonical URL) in Catalan,
  Spanish and English, a template from the public theme, a featured image, statuses (draft,
  scheduled, published), visibility (public, signed-in users, selected groups), "hide from search
  engines", order and author.
- **Back office** `backend/pages`: the list as a tree with each page's address and a "Home"
  badge, filters by status and visibility, search; publish / unpublish (also in bulk), **preview**,
  **revisions** (who, when, what changed, before/after) with **restore**, and **duplicate**.
- **Public site**: pages at their nested slugs (`/qui-som/equip`), the theme's 404 for anything
  not published, private pages that send guests to the login and are a 404 for users without
  access, `noindex` for non-public pages, the **home page** chosen in Settings › Pages, and the
  **XML sitemap** `/sitemap.xml`.
- **Rich-text editor** for every module: the vendored Quill 2.0.3 (BSD-3-Clause) as the
  `core/forms/editor` component, working under the enforced CSP, following light/dark mode,
  translated toolbar; News' body now uses it. HTMLPurifier keeps alignment as classes and drops
  `style`.
- **Page templates** declared by themes (`$pageTemplates`); the default theme offers *default*,
  *wide* and *with subpages beside*.
- **Menu link type** "Page" for the Menus module (session 21), demo data, permissions and tests.

## How it was built

| Piece | Where |
|---|---|
| Manifest (resource `pages` named `pages`, extra `publish`, grants, menu, setting `Pages.homePage`, `$menuLinks`, `$backup`) | `modules/Pages/Config/Module.php` |
| Routes: CRUD, `sitemap.xml` (`ratelimit:feeds`), catch-all `(:pagepath)` with `priority => 1` | `modules/Pages/Config/Routes.php`, `app/Config/Routing.php` (`$prioritize = true`) |
| Home page hook | `app/Controllers/Home.php` triggers `homePage` with `Modules\Core\Frontend\HomePage`; `modules/Pages/Config/Events.php` → `Services/HomePageResolver.php` |
| Back office (kpcrudy, `tree()`, `setTranslations()`, `setRelationNN()`, operations) | `modules/Pages/Controllers/Pages.php` |
| Public pages, sitemap | `Controllers/PublicPages.php`, `Controllers/Sitemap.php`, `Services/SitemapBuilder.php` |
| Who reads what | `Services/PageVisibility.php` (Core `PublishWorkflow` + `Audience`) |
| Tree, paths, resolver, slugs | `Services/PageTree.php`, `PageResolver.php`, `PageSlugs.php` |
| Rendering and templates | `Services/PageRenderer.php`, `Views/templates/{default,wide,sidebar}.php`, `Views/partials/article.php` |
| Revisions, duplicate | `Services/Revisions.php`, `Views/revisions.php`, `Services/PageCopier.php` |
| Featured image | `Storage/FeaturedImages.php` (Core `CrudUploadHandler`, folder `modules/pages`) |
| Menu link type | `Menu/PageLink.php` (`pages.page`) |
| Editor | `public/assets/vendor/quill/` (+ `vendor.json`), `modules/Core/Assets/js/forms/editor.js`, `modules/Core/Assets/css/editor.css`, `modules/Core/Frontend/RichTextEditor.php`, `Core.js.editor.*` texts |
| Sanitiser | `modules/Core/Security/HtmlSanitizer.php` + `AlignmentToClass.php` |
| Theme templates | `modules/Core/Theme/{ThemeManifest,ThemeManager,ThemeValidator}.php` (`$pageTemplates`, `pageTemplates()`), `themes/default/Theme.php`, `theme.css` |
| Extracted from News (D-20-07) | `modules/Core/Content/{ContentTranslations,Audience,PublishWorkflow}.php`, `modules/Core/Storage/CrudUploadHandler.php` |

- **Tables** (one migration each, `down()` implemented): `pages`, `page_translations`,
  `page_groups`, `page_revisions`.
- **Permissions:** `pages.{list,show,add,edit,delete,export,trash,restore,purge,publish}`;
  admin `pages.*`, editor `pages.{list,show,add,edit,publish}`. Preview/revisions use `show`,
  restore `edit`, duplicate `add`.
- **Routes:** `backend/pages` (+ `preview/{id}`, `revisions/{id}`, `POST restorerevision/{id}`,
  `POST duplicate/{id}`, `POST publish|unpublish/{id}`, `POST bulk`), `/{slug}[/{slug}…]`,
  `sitemap.xml`, `/` through the `homePage` event. No new spark commands.
- **Setting:** `Pages.homePage` (path; Settings › Pages, group "Public site").
- **Activity:** CRUD writes and POST operations through `AuditedCrud` (labels
  `Pages.activity.{publish,unpublish,restorerevision,duplicate}`).

## Decisions and deviations

- **D-20-01 (decide, Open):** permission names (`pages.*`), URLs (pages at the site root,
  `sitemap.xml`), the setting `Pages.homePage` as a path, the theme manifest key
  `$pageTemplates`. Built so it can still change (names in the manifest; the setting is one
  string).
- D-20-02 private pages: login for guests, 404 for the wrong group, `noindex` + `no-store`.
- D-20-03 Quill 2 (BSD-3) as the editor; alignment as classes; `fieldType(…, 'translation', …)`.
- D-20-04 lowest-priority catch-all, reserved root slugs from the routes, the `homePage` event.
- D-20-05 slugs per language; any language's slug resolves; texts follow the visitor's language.
- D-20-06 revisions keep a snapshot; restore keeps parent, order, status and date.
- D-20-07 content helpers extracted from News into the Core.
- Deviations: Core changes outside the module (theme manifest key, `Home` controller hook,
  route priority, Core content helpers, editor) because the session's scope asks for them; the
  featured image is a plain upload until session 23's file picker; no Pages API (session 26).
- Library requests (`docs/library-requests.md`): attributes for translated kpcrudy fields; tree
  lists with translated labels (documentation).

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --db=sqlite --demo --email=you@example.com
php spark serve
```

- `http://localhost:8080/backend/pages` as the installer's admin or `editor@example.com`
  (password printed once by the installer): tree, edit "Qui som" (editor), Revisions, Preview of
  "Pàgina en preparació", Duplicate.
- Settings › Pages › Home page = `inici`, then `http://localhost:8080/`.
- `/qui-som` (template with subpages), `/qui-som/equip`, `/quienes-somos` (Spanish slug),
  `/avis-legal` (`noindex`), `/intranet` (asks for login), `/intranet/guia-estil` (group
  `editor` only), `/en-preparacio` and `/jornades` (404: draft, scheduled), `/sitemap.xml`.
- Screenshots: `KP_ADMIN_PASSWORD=… KP_EDITOR_PASSWORD=… node deploy-status/20-pages/screenshots.mjs`.

`/sitemap.xml` with the demo data and `Pages.homePage = inici` (first entries):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
 <url>
  <loc>http://localhost:8080/index.php/</loc>
  <lastmod>2026-11-11T12:52:32+00:00</lastmod>
 </url>
 <url>
  <loc>http://localhost:8080/index.php/qui-som</loc>
  …
```

(`qui-som`, `quienes-somos`, `contacte`, `qui-som/equip`, `qui-som/historia`; no private, group,
draft, scheduled or `noindex` page.)

## Tests and checks

- `vendor/bin/phpunit`: **OK (864 tests, 4638 assertions)** (833 before the session).
- `vendor/bin/phpstan analyse`: **[OK] No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: **Found 0 of 601 files that can be fixed**.
- `php spark csp:check`: *No inline scripts, handlers or styles in the project views.*
- `php spark assets:publish --check`: *The published assets are up to date.*
- `php spark modules:sync`: *Nothing to do: every module is up to date.*
- `php spark permissions:check` (development install with demo data): *Permissions OK: 91 permission(s) in the catalogue, 162 route(s) checked.*

New tests: `modules/Pages/Tests/PagesCrudTest.php` (permission, forged POSTs on every write
route with a snapshot, slug generation and sanitised body, slugs per parent and language and
reserved slugs, cycles, publish/schedule/unpublish, revisions and restore, purge, duplicate,
preview, editor fields), `PublicPagesTest.php` (nested slugs, statuses and scheduling, private
pages and groups, noindex, home page, templates, route priority, sitemap), `PagesModuleTest.php`
(manifest, permission names and grants, tree and resolver, menu link type, revisions, the home
hook of a disabled module, migrations down/up), `modules/Core/Tests/HtmlSanitizerTest.php`,
`ThemeManagerTest::testPageTemplatesAreInheritedAndExtendedByChildThemes`, `ThemeValidatorTest`
(`$pageTemplates`), `PostsCrudTest::testTheBodyUsesTheRichTextEditorAndKeepsAlignmentAsClasses`.
Counting tests updated for the new module (`PermissionsCommandsTest`, `CrudAuditorTest`,
`ShieldMatrixTest`).

## Screenshots

Playwright (`deploy-status/20-pages/screenshots.mjs`) against `php spark serve` after
`app:install --env=development --db=sqlite --demo`, device scale 2; the script fails on a CSP
violation or a horizontal scroll (none). The guest pages are in English texts where the theme
has them (guests see `en` until session 31).

| File | What |
|---|---|
| `screenshots/01-pages-list.png` | The tree of pages with addresses, statuses, visibility and the Home badge |
| `screenshots/02-page-form-editor.png` | Editing "Qui som": language tabs, the rich-text editor, SEO fields, template, visibility |
| `screenshots/03-revisions.png` | Revisions of "Qui som": history, changes before/after, Restore |
| `screenshots/04-settings-home-page.png` | Settings › Pages: the home page path |
| `screenshots/05-preview-draft.png` | Preview of a draft (notice, `noindex`) |
| `screenshots/06-news-editor.png` | News' post form with the same editor |
| `screenshots/07-page-form-dark.png` | The editor in dark mode |
| `screenshots/08-pages-list-phone.png` | The list at phone width (row actions in a menu) |
| `screenshots/09-public-home.png` | `/` showing the page "Inici" (English text, centred alignment kept as a class) |
| `screenshots/10-public-sidebar-template.png` | `/qui-som` with the template "with subpages beside" |
| `screenshots/11-private-page-asks-login.png` | A guest opening `/intranet` is sent to the login |
| `screenshots/12-public-phone.png` | `/qui-som/equip` at phone width with breadcrumbs |
| `screenshots/13-public-dark.png` | `/qui-som` in dark mode |
| `screenshots/14-group-page-editor.png` | `/intranet/guia-estil`, readable by the `editor` group |

## Known issues

- The content language follows the visitor's locale, not the language of the slug in the URL
  (D-20-05; locales are session 31's).
- The featured image is a plain upload until the file picker (session 23); no "Insert image" in
  the editor yet (D-17-08, session 23).
- kpcrudy's internal field type `translation` gives the editor attributes to translated fields
  (library request).
- SQLite does not enforce the foreign keys here: purging a page removes its revisions and
  re-roots its children in an `afterDelete` hook.
- A scheduled page keeps the stored status `scheduled` after its date (readers and the list
  treat it as published; no task needed).
- Composer: `env -u COMPOSER_AUTH composer install --prefer-source` and `phpstan/phpstan`'s zip
  seeded again (known issue 04).

## What the next session must know

- Run `deploy/decide.md` for **D-20-01** before session 21 (names, URLs, setting, theme key).
- Menus (21): consume `Modules\Pages\Menu\PageLink` (`pages.page`) and News' `CategoryLink`;
  public routes need no priority (the page catch-all has priority 1, never `backend/`/`api/`).
- Rich text anywhere: `RichTextEditor::attrs()` + `HtmlSanitizer`; shared content helpers in
  `Modules\Core\Content`; page tests extend `PagesTestCase`.
