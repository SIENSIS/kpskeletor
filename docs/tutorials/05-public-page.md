# Tutorial 5 — A public page

A page of the public site that lists the **public** to-do tasks by status, one page per project,
with SEO metadata, and a **menu link type** so an editor can add it to a public menu.

Finished code:

| Piece | File |
|---|---|
| Routes | `modules/Tasks/Config/Routes.php` (`tasks`, `tasks/project/(:segment)`) |
| Controller | `modules/Tasks/Controllers/PublicTasks.php` (extends `FrontendController`) |
| Rule "what is public" | `modules/Tasks/Services/TaskRepository.php` (`publicTasks()`, `publicProjects()`, `activeProject()`) |
| View | `modules/Tasks/Views/public/index.php` |
| Menu link type | `modules/Tasks/Menu/ProjectLink.php`, declared in `$menuLinks` |
| Texts | `Tasks.public.*`, `Tasks.menuLink.*` |

## 1. The routes

Public routes need no `can:` filter: the page decides what a visitor may see. They are plain
GET routes pointing at a controller (a disabled module answers 404 through its controller
namespace):

```php
$routes->group('tasks', ['namespace' => 'Modules\Tasks\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PublicTasks::index', ['as' => 'tasks-public']);
    $routes->get('project/(:segment)', 'PublicTasks::project/$1');
    // … the request form of tutorial 12
});
```

## 2. One rule for "public"

What a visitor sees is decided once, in `TaskRepository`, and reused by the page, the menu link
type and the attachments (tutorial 10): a to-do task is public when `visibility = public`, it is
not in the trash and its project (if any) is active. Keep such rules in a service, not in the
controller or the view.

## 3. The controller: `FrontendController`

`FrontendController` switches the theme to the public area, so `render()` uses the theme's
`public` layout (header with the `primary` menu, footer, cookie notice). `meta()` sets the SEO
metadata the layout prints:

```php
final class PublicTasks extends FrontendController
{
    public function index(): string
    {
        $repository = new TaskRepository();

        $this->meta([
            'description' => lang('Tasks.public.description'),
            'canonical'   => site_url('tasks'),
            'og'          => true,
        ]);

        return $this->render('Modules\Tasks\Views\public\index', [
            'title'       => lang('Tasks.public.title'),
            'intro'       => lang('Tasks.public.description'),
            'columns'     => $this->columns($repository->publicTasks()),
            'projects'    => $repository->publicProjects(),
            'current'     => null,
            'requestUrl'  => (bool) setting('Tasks.publicRequests') ? site_url('tasks/request') : null,
            'breadcrumbs' => [],
        ]);
    }
```

`meta()` accepts `description`, `canonical`, `robots`, `og` (Open Graph tags from the title,
description and canonical), `image` and `type`. A project that does not exist or is inactive is a
404, like any page that does not exist:

```php
$project = $repository->activeProject($slug);

if ($project === null) {
    throw PageNotFoundException::forPageNotFound();
}
```

## 4. The view

`Views/public/index.php` extends the layout it receives and fills `content`; it escapes every
value (`esc()`), formats dates with `ui_date()`, uses the theme's components
(`theme_component('empty_state', …)`) and Bootstrap classes, with no inline style or script:

```php
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
…
<?php if (array_sum(array_map('count', $columns)) === 0): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-kanban', 'title' => lang('Tasks.public.empty'), 'message' => null, 'action' => null, 'compact' => false]) ?>
<?php else: ?>
    <div class="row g-4 tasks-public">
        …
    </div>
<?php endif ?>
<?= $this->endSection() ?>
```

Never hard-code a theme path: a theme can override this view in
`themes/<slug>/Views/overrides/Tasks/public/index.php` (tutorial 9 does).

## 5. A menu link type

Editors build public menus in Menus › Public menus. To let them pick "To-do tasks" (all, or one
project) instead of typing a URL, the module offers a link type: a class implementing
`Modules\Core\Menus\MenuLinkType`, listed in the manifest:

```php
/** Link type of the public menu editor (tutorial 5). */
public array $menuLinks = [\Modules\Tasks\Menu\ProjectLink::class];
```

```php
final class ProjectLink implements MenuLinkType
{
    public function key(): string
    {
        return 'tasks.project';
    }

    public function label(): string
    {
        return lang('Tasks.menuLink.label');
    }

    /** @return array<string, string> slug => project name ('*' = every public to-do task) */
    public function options(): array { /* '*' and the active projects */ }

    /** The public URL of a choice, or null when the project no longer exists (the item is hidden). */
    public function url(string $value): ?string
    {
        if ($value === '*') {
            return site_url('tasks');
        }

        return array_key_exists($value, $this->options()) ? site_url('tasks/project/' . rawurlencode($value)) : null;
    }
}
```

The menu stores only the key and the value; `url()` is asked again when the menu is built, so an
item pointing at a deleted or inactive project disappears by itself. See
[`menus.md`, "Link types for modules"](../menus.md#link-types-for-modules-menulinks).

Run `php spark modules:sync` after declaring it.

## Try it

With the demo data (`php spark modules:demo Tasks`), open `/tasks` as a guest: only the public
demo to-do tasks appear; `/tasks/project/<slug>` filters them. In Menus › Public menus, add an item
of type "To-do tasks" to the main menu and open the site.

## Tests

`modules/Tasks/Tests/PublicSiteTest.php`:

- `testThePublicPageShowsOnlyPublicToDoTasks` (private, trashed and inactive-project to-do tasks
  are hidden; the canonical link and description are printed; unknown or inactive projects are a
  404);
- `testTheMenuLinkTypeOffersTheActiveProjects`.

`modules/Tasks/Tests/ChildThemeTest.php` checks the same page through a theme override
(tutorial 9).

```bash
vendor/bin/phpunit modules/Tasks/Tests/PublicSiteTest.php --filter 'PublicPage|MenuLinkType'
```

Next: [tutorial 6](06-talking-to-other-modules.md), notifications, the calendar and events.
