# Back-office menus

The back-office menu is **generated** from the manifests of the enabled modules
(docs/specification.md, "Menus"); nobody writes it by hand. The Menus module (session 21) will add
a customisation layer on top (reorder, rename, relocate, custom links) without changing the
manifests.

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
The same resource in a second zone gets `<key>@<zone>`. Customisations (session 21) refer to
these keys.

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
