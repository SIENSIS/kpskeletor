# Menus module

**Purpose:** the public menus (created by hand, placed in the theme's locations, edited in a tree
editor) and the customisation layer of the generated back-office menu (reorder, rename, change
icons, hide, relocate, custom groups and links, per group). Full description: `docs/menus.md`
("The Menus module").

## Resources and permissions

- `menus-public` (crud − show, export, import, trash, restore, purge): `list`, `add`, `edit`,
  `delete`. The items editor (`backend/menus/items/<menu>`) needs `edit`.
- `menus-backend` (page + extras): `view` (the editor), `customise` (order, place, retouch,
  custom groups), `links` (custom links), `reset` (reset, orphans).
- Grants: admin `menus-public.*`, `menus-backend.*`; editor `menus-public.{list,edit}`.
- Menu: "Menus" (sidebar, 28), "Back-office menu" (settings zone, 74). Events
  `Config/Events.php` (`backendMenu`, `publicMenu`). No settings, widgets, tasks, notifications
  or API.
- Tables: `menus`, `menu_items`, `menu_item_translations`, `menu_item_groups`,
  `backend_menu_customisations`, `backend_menu_links` (one migration each).

## Main pieces

- `Services/UrlValidator.php` (addresses), `RouteAccess.php` (route paths and their `can:`
  requirements), `LinkTypes.php` (the modules' `MenuLinkType`s), `LinkTarget.php` (validate and
  resolve a target), `TreeMoves.php` (moves, cycles), `MenuRepository.php` (menus and items),
  `MenuRenderer.php` (`menu()`), `Customisations.php` (stored layer), `CustomisationApplier.php`
  (layer on top of the generated menu), `BackendLayout.php` (the editor's view and writes).
- `Controllers/PublicMenus.php` (kpcrudy), `MenuItems.php`, `BackendMenu.php`, `CustomLinks.php`;
  views in `Views/`; `Assets/js/tree-editor.js`, `link-form.js`; styles in the default theme's
  `theme.css` (`.menu-tree*`).
- Core pieces it relies on: `Modules\Core\Menus\{MenuLinkType,BackendMenuLayer,PublicMenuItem}`,
  `Modules\Core\Frontend\PublicMenu`, the helper `menu()`, the partial `partials/public_menu`.

## Decisions

D-21-01 (names, URLs, Core contracts) open (decide); D-21-02 … D-21-06 accepted
(`docs/decisions.md`).

## Open tasks

- Session 26: menus in the API (if wanted). Session 31: language negotiation (guests see `en`).
- Themes other than the default need their own `partials/public_menu` if they want another look.

## How to test

`vendor/bin/phpunit modules/Menus` (base `Tests/MenusTestCase.php`: Auth fixture plus the
modules' declarations, `menu()`, `item()`, `customise()`, `customLink()`, `placeOf()`,
`entry()`). By hand: `php spark modules:demo Menus` (after Pages and News), then
`backend/menus/public`, `backend/menus/backend` and the public site's header and footer.
