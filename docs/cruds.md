# CRUDs with kpcrudy

Every back-office list/form screen over database tables is a [kpcrudy](../vendor/siensis/kpcrudy/README_EN.md)
`Crud` (specification "CRUDs with kpcrudy"). Read the library's own docs first:
`vendor/siensis/kpcrudy/docs/functions_EN.md` (every method) and `docs/howto_EN.md` (recipes).
This page is what the Core adds.

## Quick start

```bash
php spark make:module Library --crud=library_books   # migration, resource, controller, route
php spark modules:sync                               # permissions, grants, menu
php spark permissions:check                          # the CRUD matches its manifest
```

`make:module --crud` creates the table's migration and migrates it, declares the `crud` resource
`books` (`library-books.*`, `except` `import`) with its admin grant, menu entry and language
keys, runs `php spark kpcrudy:make` and rewrites its output to the Core's conventions (below).
The generated controller is a starting point: choose the columns, fields, relations and rules.

## A CRUD in a module

```php
// modules/News/Config/Routes.php
use Modules\Core\Crud\CrudFactory;

CrudFactory::routes($routes, 'news-posts', 'backend/news/posts', '\Modules\News\Controllers\Posts::index');
```

```php
// modules/News/Controllers/Posts.php
final class Posts extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('news-posts')
            ->setTable('news_posts')
            ->setSubject('Post', 'Posts')
            ->columns(['title', 'author', 'status'])
            ->addOperation('publish', fn (string $id, array $row, Crud $crud) => …, ['icon' => 'bi bi-send']);

        if (! $this->can('news-posts.author')) {
            $crud->hideFields(['author_id']);   // list, details, forms, search, export, import
        }

        return $this->renderCrud($crud);
    }
}
```

- **`CrudFactory::routes($routes, $resource, $path, $handler, $options = [])`**: kpcrudy's two
  routes (`path` and `path/(:any)`, GET and POST) with the filters `backend` (login, forced
  password change, forced 2FA, rate limit) and `can:<resource>.list`, extra filters appended
  (`['filter' => 'reauth']`), and the route name `<resource>` (menus, `url_to('news-posts')`).
- **`$this->crud($resource)`** (`CrudFactory::make()`): a `Crud` with `setResource($resource)`
  and the active theme's back-office layout. Never `new Crud()` in a module: `permissions:check`
  reports it.
- **`$this->renderCrud($crud)`**: renders inside the layout with the page header (the CRUD's
  title and the breadcrumbs of the menu). It returns a response for redirects and downloads:
  return it as is.

## Permissions

`Config\Kpcrudy` selects the one resolver of the Core (`CrudPermissions`) with
`denyByDefault = true`: every operation is the permission `<resource>.<operation>` of the
catalogue (`search`, `filters` and `bulk` are part of `list`); superadmins pass, guests never.

- Every operation the CRUD can ask about must be declared in the manifest resource: kpcrudy's
  operations minus `except`, plus `extra` for custom operations (`addOperation()`), action and
  bulk-action `permission` options and permissions checked by your code (`news-posts.author`).
  Outside production, the first question about an undeclared operation throws
  `UndeclaredPermissionException`; `php spark permissions:check` runs every CRUD action and
  reports the same (D-09-03).
- Disable what the resource does not declare: `unsetAdd()`, `unsetDelete()`, `unsetExport()`…
  (a table with `deleted_at` has a trash: `trash`, `restore`, `purge`).
- Record rules still restrict what the resolver allows: `setPermission('edit', fn (?array $row) => …)`.
- **Own records only:** `where('owner_id', auth()->id())` limits list, details, edit, delete,
  bulk actions and export; a forged id is "not found". Personal screens ("my notes") are their
  own resource granted to every group (D-00-17).
- **Sensitive fields:** `hideFields()` after checking a permission.
- Refused operations redirect to the list with an error toast; the UI hides what the server
  refuses. Masked ids are not access control.

## Configuration (`app/Config/Kpcrudy.php`)

| Option | Value | Why |
|---|---|---|
| `theme` | `bootstrap5` | The themes are Bootstrap 5.3 |
| `layout`, `layoutSection` | the theme's `backend` layout, `content` | Section mode |
| `showTitle` | `false` | The layout prints the title and breadcrumbs |
| `flashKeys` | success→`success`, danger→`error`, warning→`warning`, info→`info` | Messages become toasts |
| `permissionResolver`, `denyByDefault` | `CrudPermissions`, `true` | Central RBAC, default deny |
| `maskIds`, `maskKey` | `true`, `app.maskKey` | Masked ids; the same key as kpapy |
| `uploadPath` | `writable/kpcrudy-unused-uploads` | kpcrudy upload fields are never used |
| `bootstrapCss`, `bootstrapJs`, `iconsCss` | `public/assets/vendor/…` | Local copies (only the library's own layout uses them) |

**`app.maskKey`** (`.env`, 64 random hex characters:
`php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`) masks ids and signs URLs in kpcrudy and
kpapy. Production refuses web requests without it, and no environment accepts a key shorter than
32 characters (D-09-01). Changing it breaks every masked or signed link.

## Files and uploads

Never use kpcrudy's `image`/`upload` field types: they write files outside the Storage service.
File fields use the Core's file picker (`callbackField()`, session 23). `make:module --crud`
replaces the upload fields kpcrudy guesses with a comment (`docs/library-requests.md`).

## Look and behaviour

- Page header: `partials/page_top` builds it from the CRUD title and the breadcrumbs.
- Messages: `partials/flash` → the `core/ui/flash` component shows them as toasts (alerts
  without JavaScript).
- Confirmations (delete, bulk actions, operations with `confirm`): kpcrudy's `kpcrudy:confirm`
  event opens the theme's modal (`core/ui/confirm.js`).
- kpcrudy prints its own nonce'd `<style>`/`<script>` block: the accepted CSP exception (D-00-24).

## Tests

`tests/feature/Crud/SampleCrudTest.php` is the reference (a test-only "Sample" module in
`tests/_support/Crud/`): a read-only user sees no write buttons and every write URL, custom
operation and forged POST is refused; `hideFields()` hides a field from list, details, form and
export; `where()` limits "my notes" even with a forged id; ids are masked. Copy its patterns for
a module's CRUD tests.
