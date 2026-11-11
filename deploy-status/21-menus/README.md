# Session 21 — Menus module: public menus and back-office customisation

Branch: `claude/deploy-next-md-rjmbga` · Pull request: [SIENSIS/kpskeletor#29](https://github.com/SIENSIS/kpskeletor/pull/29) · Date: 2026-11-11

## What was built

- **Menus module** (`modules/Menus`) with two screens:
  - **Public menus** (`backend/menus/public`): menus placed in the theme's locations (`primary`,
    `footer`, `social`; one menu per location) and a **tree editor** of their items: drag and
    drop (mouse, pen, touch), the arrow keys on the handle, and up/down/outdent/indent buttons
    that also work without JavaScript. Item types: address (URL or site path), route, and the
    modules' link types (a page, a news category). Each item: label in ca/es/en, icon, window,
    CSS class, visibility (everyone, visitors without a session, signed-in users, groups).
  - **Back-office menu** (`backend/menus/backend`): the generated menu in the same tree editor,
    every entry marked with its module and permission, for **everyone or one group**: reorder,
    relocate (parent, zone), rename (ca/es/en), change the icon, hide; **custom groups**
    (headings) and **custom links**; reset per entry, per scope or everything; **orphaned**
    customisations listed and deletable.
- **Rendering:** `menu('primary')` + the theme partial `partials/public_menu` in the public
  layout's header (navbar with dropdowns) and footer; the back-office sidebar and user menu show
  custom links (new-tab links with `rel="noopener noreferrer"`) and renamed entries.
- **Rules from the specification:** permissions always win (a relocated or renamed entry keeps
  its route and permission and is still filtered), `javascript:`/`data:`/`//` refused, cycles
  impossible, links to internal routes require that route's `can:` permissions (no 403 links),
  new modules' entries in their default place, per-group customisations with "most specific
  wins", cache per group set invalidated on every change.

## How it was built

| Piece | Where |
|---|---|
| Manifest (resources `public`, `backend`; grants; menu; no settings/API) | `modules/Menus/Config/Module.php` |
| Routes (`backend/menus/public` CRUD, `backend/menus/items/<menu>/…`, `backend/menus/backend/…`) | `modules/Menus/Config/Routes.php` |
| Listeners: `backendMenu` (customisation layer), `publicMenu` (`menu()`) | `modules/Menus/Config/Events.php` |
| Menus CRUD (kpcrudy) and the items editor | `Controllers/PublicMenus.php`, `Controllers/MenuItems.php`, `Views/items*.php`, `Views/item_form.php` |
| Back-office editor, retouch form, custom groups and links | `Controllers/BackendMenu.php`, `Controllers/CustomLinks.php`, `Views/backend*.php`, `Views/entry_form.php`, `Views/custom_form.php` |
| Addresses, routes, link types, targets | `Services/UrlValidator.php`, `RouteAccess.php`, `LinkTypes.php`, `LinkTarget.php` |
| Tree moves and cycle checks | `Services/TreeMoves.php` |
| Public menus: storage and rendering | `Services/MenuRepository.php`, `Services/MenuRenderer.php` |
| Back-office layer: storage, applier, editor view | `Services/Customisations.php`, `CustomisationApplier.php`, `BackendLayout.php` |
| Tree editor and link form (ES modules) | `Assets/js/tree-editor.js`, `Assets/js/link-form.js`; styles `.menu-tree*` in `themes/default/Assets/css/theme.css` |
| Core: link type contract | `modules/Core/Menus/MenuLinkType.php` (checked by `ManifestValidator`; `PageLink` and `CategoryLink` implement it) |
| Core: back-office extension point | `modules/Core/Menus/BackendMenuLayer.php`, `MenuBuilder.php` (`definitions()`, event, kinds `group`/`link`, `hidden`), `MenuEntry.php` (`labels`, `href`, `target`, `newTab()`) |
| Core: public menus | `modules/Core/Frontend/PublicMenu.php`, `modules/Core/Menus/PublicMenuItem.php`, `menu()` in `modules/Core/Helpers/menu_helper.php`, `themes/default/Views/partials/public_menu.php`, `layouts/public.php` |
| Core: demo order | `modules/Core/Demo/DemoSeeder.php` (`AFTER`), `DemoDataManager::ordered()` |
| Demo data | `modules/Menus/Database/Seeds/MenusDemoSeeder.php` |

- **Tables** (one migration each, `down()` implemented): `menus`, `menu_items`,
  `menu_item_translations`, `menu_item_groups`, `backend_menu_customisations`,
  `backend_menu_links`.
- **Permissions:** `menus-public.{list,add,edit,delete}`, `menus-backend.{view,customise,links,reset}`;
  admin all, editor `menus-public.{list,edit}`.
- **Activity:** CRUD writes of menus through `AuditedCrud`; item, order, customisation, custom
  link, reset and orphan changes with `activity_log()` (labels `Menus.activity.*`); a refused
  custom group/link edit with `security_log()`.
- **Commands:** none new.

## Decisions and deviations

- **D-21-01 (decided by the owner, applied):** permission names, URLs and the Core contracts
  (`MenuLinkType`, events `backendMenu`/`publicMenu`, `menu()`, `DemoSeeder::AFTER`) as built.
- D-21-02 items in a tree editor and a form of the module (menus themselves are kpcrudy).
- D-21-03 customisations stored as differences, merged field by field (everyone, then groups;
  the group listed first in Users › Groups wins); hiding hides the subtree.
- D-21-04 addresses (`https://`, `http://`, `/path`, route names; no spaces, control
  characters or backslashes) and the 403 rule read from the route's `can:` filters when the menu
  is built.
- D-21-05 public menus: three levels, cached five minutes per menu/language/group set, active
  item by URL.
- D-21-06 `DemoSeeder::AFTER` so the Menus demo loads after Pages and News.
- Deviations: Core changes outside the module (extension points, `menu()`, the public layout's
  menu locations, demo order) because the session's scope asks for them.

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --db=sqlite --demo --email=you@example.com
php spark serve
```

- `http://localhost:8080/` — header menu (Inici, Qui som ▾, Notícies ▾, Contacte; "Intranet" only
  when signed in) and footer menu (legal notice, contact, external link in a new tab).
- `http://localhost:8080/backend/menus/public` as the admin → "Items" of "Menú principal":
  drag, keyboard, buttons, add/edit an item (try `javascript:alert(1)`: refused).
- `http://localhost:8080/backend/menus/backend`: the demo groups News, Pages and Menus under the
  custom group "Contingut", adds "Web pública"; the `editor` scope moves "Contingut" first. Sign in
  as `editor@example.com` to see it.
- Screenshots: `KP_ADMIN=… KP_ADMIN_PASSWORD=… KP_EDITOR_PASSWORD=… node deploy-status/21-menus/screenshots.mjs`
  (Playwright must be resolvable from the repository, e.g. a temporary `node_modules` link).

## Tests and checks

- `vendor/bin/phpunit`: **OK (908 tests, 4977 assertions)** (867 before the session).
- `vendor/bin/phpstan analyse`: **[OK] No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: **Found 0 of 641 files that can be fixed**.
- `php spark csp:check`: *No inline scripts, handlers or styles in the project views.*
- `php spark assets:publish --check`: *The published assets are up to date.*
- `php spark modules:sync`: *Nothing to do: every module is up to date.*
- `php spark permissions:check` (development install with demo data): *Permissions OK: 99 permission(s) in the catalogue, 183 route(s) checked.*

New tests (`modules/Menus/Tests/`): `UrlValidatorTest` (accepted and refused addresses,
including `javascript:` variants, `data:`, `//`, credentials, control characters),
`TreeMovesTest` (moves, zones, cycles), `BackendMenuTest` (a relocated entry still filtered by
its permission, hiding never grants, rename/icon/order, custom groups hidden when empty, custom
links follow the route's permission, the chosen permission and groups, invalid stored links never
shown, most specific scope wins, cycles refused and ignored, only differences stored and the
cache invalidated, moves without JavaScript, per-group retouch, link validation, orphans,
resets, every route's permission and forged POSTs, a disabled module's layer inactive),
`PublicMenusTest` (visibility per visitor, labels per language, route permissions, the theme's
primary and footer menus, gone targets and the active item, the Pages link type, cache
invalidation, address validation, cycles and depth, moves and subtree delete, the menus CRUD,
forged POSTs, items of another menu, a disabled module), `MenusModuleTest` (manifest,
permission names and grants, link types and their interface, demo data order and removal,
migrations down/up). Counting tests updated (`PermissionsCommandsTest`, `CrudAuditorTest`,
`ShieldMatrixTest`).

## Screenshots

Playwright (`deploy-status/21-menus/screenshots.mjs`) against `php spark serve` after
`app:install --env=development --db=sqlite --demo` and `modules:demo Menus`, device scale 2;
the script fails on a CSP violation, a page error or a horizontal scroll (none). The admin's
interface is in English, the editor's in Catalan; guests see `en` until session 31.

| File | What |
|---|---|
| `screenshots/01-public-menus.png` | The public menus (kpcrudy) with their location and item count |
| `screenshots/02-items-tree-editor.png` | The items' tree editor after a keyboard move ("Save the order" shown) |
| `screenshots/03-item-form.png` | An item's form: labels, type and target, place, visibility |
| `screenshots/04-backend-editor.png` | The back-office editor for everyone: modules, permissions, the custom group "Content" and link, badges |
| `screenshots/05-retouch-entry-for-a-group.png` | Retouching "Pages" for the `editor` group |
| `screenshots/06-custom-link-refused.png` | A `javascript:` custom link refused |
| `screenshots/07-admin-sidebar.png` | The admin's sidebar with "Content" (News, Pages, Menus) and "Public website" |
| `screenshots/08-editor-sidebar.png` | The editor's sidebar: "Contingut" first (group scope), only what they may open |
| `screenshots/09-public-menu.png` | The public header menu with a dropdown, and the footer menu |
| `screenshots/10-public-menu-phone.png` | The public menu at phone width |
| `screenshots/11-backend-editor-dark.png` | The back-office editor in dark mode |
| `screenshots/12-items-editor-phone.png` | The items editor at phone width |

## Known issues

- Public menus are cached five minutes: a page published or unpublished shows in or leaves the
  menu within that time (D-21-05).
- A public path answered by the Pages catch-all is accepted even if no page has that slug (the
  item then leads to a 404).
- Touch drag and drop relies on pointer events and was not tested on a real device.
- Composer: `env -u COMPOSER_AUTH composer install --prefer-source`, and `phpstan/phpstan`'s zip
  had to be seeded under Composer's cache key `sha1(<dist URL>).zip` (the `<reference>.zip` name
  used before was not picked up) — known issue 04.

## What the next session must know

- D-21-01 decided as built: the names, URLs and contracts are stable.
- Modules offer menu link types by implementing `Modules\Core\Menus\MenuLinkType` in a
  `$menuLinks` class (Events: the calendar link type).
- Demo seeders that use another module's demo data declare `public const AFTER = ['<Module>']`.
- Tests needing the modules' menu declarations store them like `MenusTestCase::storeDeclarations()`.
- Feature tests and `php spark serve` share `writable/cache`: clear it (`php spark cache:clear`)
  before taking screenshots after running the suite.
