# Tutorial 9 — A child theme

A theme changes how the site looks without touching a module. You will create **Tasks Demo**, a
child of the `default` theme that changes the primary colour and the logo, adds its own CSS, and
redraws the public page of the to-do tasks (tutorial 5) as coloured columns. Everything it does
not override comes from its parent.

The finished theme ships in the repository as tutorial material: `themes/tasks-demo/` (themes
live in `themes/`, never inside a module). Reference: [`themes.md`](../themes.md), sections
"View overrides" and "Child themes".

## 1. Generate it

```bash
php spark make:theme TasksDemo          # child of "default" (--parent=<slug>, --parent=none)
```

`TasksDemo` becomes the slug `tasks-demo`. `make:theme` writes `themes/tasks-demo/`: the manifest
`Theme.php` (namespace `Themes\TasksDemo`), `Assets/css/theme.css`, empty `Views/overrides`,
`Views/components` and `Views/partials`, and ca/es/en language files; it validates the theme and
publishes its assets. (The repository already has this theme: to follow along, generate another
name, such as `TasksDemo2`.)

## 2. Colours and logo through the parent's options

A child theme changes a colour by **redeclaring the default of a parent option**, not by copying
CSS. The parent turns `primaryColor` into the Bootstrap variables (`--bs-primary` and its
variants, light and dark mode):

```php
final class Theme extends ThemeManifest
{
    public string $name    = 'Tasks Demo';
    public string $version = '1.0.0';
    public string $author  = 'kpskeletor';
    public ?string $parent = 'default';

    /** Layouts this theme provides itself (the others come from the parent). */
    public array $layouts = [];

    /** Loaded after the parent's stylesheets. */
    public array $styles = ['css/theme.css'];

    public array $options = [
        'primaryColor' => ['default' => '#6f42c1'],
        'logo'         => ['default' => 'themes/tasks-demo/img/logo.svg'],
    ];
}
```

The logo is `Assets/img/logo.svg`, published to `public/themes/tasks-demo/img/logo.svg`.
An SVG of the theme itself is fine: it is code you ship, not a user upload (uploads never accept
SVG by default). Administrators can still change both values in Settings › Theme.

## 3. The theme's own CSS

`Assets/css/theme.css` is loaded after the parent's stylesheets (`$styles`). Use CSS custom
properties built on Bootstrap's, so light and dark mode both keep working:

```css
:root {
    --tasks-demo-accent: #6f42c1;
    --tasks-demo-column-todo: var(--bs-secondary-bg);
    --tasks-demo-column-doing: var(--bs-primary-bg-subtle);
    --tasks-demo-column-done: var(--bs-success-bg-subtle);
}

.tasks-demo-hero {
    border-left: 0.35rem solid var(--tasks-demo-accent);
    padding-left: 1rem;
}
```

Never `style=""` in a view: the Content-Security-Policy refuses inline styles.

## 4. Override a module view

A theme overrides a module's view by putting a file at the same path under
`Views/overrides/<Module>/`. The Tasks controller renders `Modules\Tasks\Views\public\index`, so
the override is:

```
themes/tasks-demo/Views/overrides/Tasks/public/index.php
```

It receives exactly the same variables as the module's view (copy its docblock) and extends the
same `$layout`:

```php
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<header class="tasks-demo-hero mb-4">
    <?php if ($breadcrumbs === []): ?><h1 class="h2 mb-1"><?= esc($title) ?></h1><?php endif ?>
    …
</header>
<div class="row g-3 tasks-demo-board">
    …
</div>
<?= $this->endSection() ?>
```

Rules for an override: escape everything, keep the data contract (an override must not query
the database or check permissions: the controller already did), and override as little as
possible: `tasks/request` keeps the module's own view because the theme does not override it.
The view resolution order is the active theme, then its parent chain, then the module
([`themes.md`, "View resolution"](../themes.md#view-resolution)).

## 5. Activate it

```bash
php spark assets:publish      # copies the theme's Assets/ to public/themes/tasks-demo/
```

Choose it in Settings › Theme for the public site (or set `theme.public = tasks-demo` in `.env`)
and open `/tasks`: purple buttons and links, the theme's logo, and the to-do tasks in coloured
columns. Switch the colour mode: the columns follow it. The back office keeps its own theme.

## Tests

`modules/Tasks/Tests/ChildThemeTest.php` (activates the theme with
`setting()->set('Theme.public', 'tasks-demo')` and resets the theme service):

- `testTheThemeIsAValidChildOfTheDefaultTheme`: the manifest, the override file, no layouts of its
  own, the chain `['tasks-demo', 'default']`;
- `testItOverridesTheTasksPublicPageAndItsColour`: `/tasks` uses the override inside the parent's
  public layout, `--bs-primary: #6f42c1;`, the logo, both stylesheets; `tasks/request` keeps the
  module's view; no inline script, handler or style.

`php spark csp:check` scans `themes/*/Views` too.

```bash
vendor/bin/phpunit modules/Tasks/Tests/ChildThemeTest.php
```

Next: [tutorial 10](10-file-uploads.md), file uploads.
