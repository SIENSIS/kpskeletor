# Back-office menus

The back-office menu is **generated** from the manifests of the enabled modules
(docs/specification.md, "Menus"); nobody writes it by hand. The Menus module adds a
customisation layer on top (reorder, rename, relocate, hide, custom groups and links) without
changing the manifests, and edits the **public menus** placed in the theme's locations (see
"The Menus module" below).

## Declaring entries (`$menu` in `Config/Module.php`)

```php
public array $menu = [
    // Linked to a resource: route named after the resource ("news-posts") and its permission
    // (".list" for crud resources, ".view" for pages).
    ['resource' => 'posts', 'label' => 'News.menu.news', 'icon' => 'bi bi-newspaper', 'order' => 20],
    // "parent" names a resource of the same module, or the key of any other entry.
    ['resource' => 'categories', 'label' => 'News.menu.categories', 'parent' => 'posts'],
    // Other entries give a route name and a permission.
    ['route' => 'news-archive', 'permission' => 'news-posts.list', 'label' => 'News.menu.archive', 'zone' => 'sidebar'],
];
```

| Key | Default | Meaning |
|---|---|---|
| `resource` | — | resource of the manifest (route + permission come from it) |
| `route`, `permission` | — | route **name** and permission when there is no `resource` |
| `label` | required | language key |
| `icon` | `bi bi-circle` | Bootstrap Icons classes |
| `order` | 100 | ascending; ties by key |
| `zone` | `sidebar` | `sidebar`, `settings` (sidebar, under a heading), `user` (the user menu)… |
| `parent` | none | nests the entry |

Every entry has a **stable key**: `<Module>.<resource>` (e.g. `News.posts`) or the route name.
The same resource in a second zone gets `<key>@<zone>`. Customisations refer to these keys.

`modules:sync` stores the normalised entries in `module_declarations` (kind `menu`); regular
requests never load the manifests.

## Rules

- An entry is **visible** when its module is enabled, its route exists (`route_to()`; routes
  with parameters cannot be menu entries) and the user holds its permission
  (`Authorization::for($user)`, superadmins included through the catalogue).
- A parent the user cannot open (or without a route) is kept as a **group** (no link) only while
  it has visible children; otherwise it is hidden.
- An entry whose parent does not exist (e.g. a disabled module) is shown at the root; entries in
  a parent cycle are dropped.
- The **active entry** is the one whose route name is the current route, else the one with the
  longest path that is the current path or one of its parents; its ancestors are opened. Parent
  paths count from two segments, so the home (`backend`) is active only on itself (D-16-04).
- **Breadcrumbs**: `BackendController` uses the trail to the active entry when the controller
  adds no breadcrumbs of its own (`breadcrumb()`).
- Guests get no menu. Permissions always win: the menu only hides what the routes' `can:` filters
  refuse anyway.

## Cache

The filtered tree is cached per **group set plus the user's individual permissions**
(`MenuBuilder::cacheKey()`) in the `menus` scope of `CoreCache`. `modules:sync`, enabling or
disabling a module and every group grant change (`GrantManager`) invalidate it through
`CacheInvalidator`. The active entry is computed per request.

## Code

- `Modules\Core\Menus\MenuDefinitions` (manifest → definitions), `MenuBuilder` (`tree()`,
  `zone()`, `trail()`), `MenuEntry` (`key`, `label`, `icon`, `path`, `url()`, `text()`,
  `children`, `active`, `open`).
- Helpers (`menu`): `backend_menu('sidebar')`, `backend_menu_trail()`.
- Theme partials: `partials/sidebar_menu` (zones `sidebar` and `settings`),
  `partials/sidebar_menu_items` (recursive, collapsible groups), `partials/user_menu` (zone
  `user` + logout). A theme overrides them like any partial (`docs/themes.md`).
- Extension point: `MenuBuilder` triggers `backendMenu` with a `BackendMenuLayer` (the Menus
  module's customisations) before filtering by permission; `MenuBuilder::definitions()` gives the
  generated entries.

## The Menus module

Two screens (`modules/Menus`, decisions D-21-01 … D-21-06).

### Public menus (`backend/menus/public`, `menus-public.*`)

- A kpcrudy CRUD of menus (name and theme location; one menu per location; deleting a menu
  deletes its items) and, per menu, the **tree editor** of its items
  (`backend/menus/items/<menu>`, `menus-public.edit`): drag the handle (mouse, pen, touch), use
  the arrow keys on it (up/down reorder, right nests under the previous item, left takes it out)
  or the buttons, then "Save the order". Without JavaScript the buttons apply one move each.
- Item: label per language (Catalan required), type and target — `url` (an `https://`/`http://`
  URL or a path of the site starting with `/`), `route` (a GET route name without parameters) or
  a module link type (`pages.page`, `news.category`…) — parent (three levels at most), icon,
  window (new tab adds `rel="noopener noreferrer"`), CSS class and visibility (everyone,
  visitors without a session, signed-in users, selected groups).
- Rendering: `menu('primary')` returns the items (`Modules\Core\Menus\PublicMenuItem`) for the
  visitor and language; the theme partial `partials/public_menu` shows `primary` as the navbar
  (dropdowns for children) and any other location as a list. Items whose target is gone (a deleted
  or unpublished page, a disabled module, an address no longer valid) are hidden; links to
  routes with a `can:` filter only show to users who pass it. Cached per menu, language and group
  set for five minutes; the active item is marked on each request.

### Back-office menu (`backend/menus/backend`, `menus-backend.*`)

- The editor shows every generated entry (with its module, permission, hidden/customised
  badges) and the custom ones, per zone, for **everyone** or for **one group**
  (`?group=<name>`). Moving and retouching need `customise`, custom links `links`, resets and
  orphans `reset`; `view` opens the screen.
- **Retouch:** label per language, icon, hidden (with its children), parent and zone. **Custom
  groups** (`customise`) are headings hidden while empty. **Custom links** (`links`): an address,
  route, public page or module link type, label, icon, window, place, and who sees it (a
  permission from the catalogue and/or groups). A link to a path or route of the site also
  requires the `can:` permissions of the route that answers it, read when the menu is built.
- Stored as differences per entry key and scope (`backend_menu_customisations`); scopes merge
  field by field (everyone, then the user's groups; the group listed first in Users › Groups
  wins). Route and permission never change, so a relocated or renamed entry is still filtered by
  its permission. Relocations under the entry's own descendants are refused (and ignored if
  stored). Customisations of a disabled or removed module are kept, inactive, and listed as
  **orphaned** with a delete option. **Reset** per entry, per scope or everything.
- Every change invalidates the `menus` cache and is written to the activity log.

### Link types for modules (`$menuLinks`)

A module offers link types by listing classes that implement `Modules\Core\Menus\MenuLinkType`:

```php
final class CategoryLink implements MenuLinkType
{
    public function key(): string { return 'news.category'; }            // stored in items
    public function label(): string { return lang('News.menuLink.category'); }
    public function options(): array { /* value => label */ }
    public function url(string $value): ?string { /* absolute URL, null when gone */ }
}
```

`modules:sync` refuses a class that does not implement the interface.

Types in the skeleton: `pages.page` (Pages), `news.category` (News) and `events.calendar`
(Events: the public calendar, all categories or one, hidden while the public calendar is off).

