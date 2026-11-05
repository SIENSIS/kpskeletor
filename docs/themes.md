# Themes and standard views

How the back office and the public site are rendered: themes, view resolution, the standard
views and components every module composes, theme options and assets. Specification: "Themes"
and "Common module base"; decisions D-03-01 … D-03-09 in `docs/decisions.md`.

## A theme

A theme is a folder `themes/<slug>/` (lower-case letters, digits and hyphens: `default`,
`tasks-demo`) with a manifest class `Themes\<Studly>\Theme` (`Themes\Default\Theme`,
`Themes\TasksDemo\Theme`) extending `Modules\Core\Theme\ThemeManifest`:

```
themes/corporate/
├── Theme.php                       manifest
├── Views/
│   ├── layouts/                    backend, public, auth, blank, print (a child inherits them)
│   ├── partials/                   head, scripts, brand, flash, user_menu, sidebar_menu, page_top…
│   ├── components/                 overrides of the Core components (badge, page_header…)
│   └── overrides/<Module>/…        overrides of module views
├── Assets/css, Assets/js, Assets/img   published to public/themes/<slug>/
└── Language/ca|es|en/Theme<Studly>.php
```

| Manifest property | Meaning |
|---|---|
| `$name`, `$version`, `$author` | Human-readable name, semantic version, author |
| `$parent` | Slug of the parent theme, or `null` for a standalone theme |
| `$layouts` | Layouts the theme provides itself (`backend`, `public`, `auth`, `blank`, `print`); the chain must provide all five |
| `$regions` | Regions per layout (`['public' => ['header', 'sidebar', 'footer', 'home']]`) |
| `$menuLocations` | Menu locations the layouts print (`primary`, `footer`, `social` → label key) |
| `$styles`, `$scripts` | Assets loaded after the parent's: paths under `Assets/`, or `vendor:<path>` under `public/assets/vendor/` |
| `$options` | Options declared like settings (type, default, label, help, options) plus `css` for colour options |

The theme's PSR-4 namespace is registered when the theme is first loaded (no scan of `themes/`
per request). `modules:sync` validates every theme (`ThemeValidator`: version, layouts and their
files, regions, menu locations, asset paths, option types, CSS properties) and publishes the
themes' assets; any error stops the sync.

## Which theme is active

`setting('Theme.backend')` and `setting('Theme.public')` (defaults in `Modules\Core\Config\Theme`,
overridable in `app/Config/Theme.php` or `.env`: `theme.backend = corporate`; the Settings
screen arrives in session 14). `BackendController` switches the request to the `backend` area,
everything else renders in the `public` area. A missing or broken theme falls back to
`Theme.fallback` (`default`) and is logged, so a bad setting never takes the site down.

## View resolution

Modules never hard-code a theme path: they render with `theme_view()` (or
`BackendController::render()` / `FrontendController::render()`) and extend `theme_layout()`.

| View name | Resolution order |
|---|---|
| Module view `Modules\News\Views\posts/index` | active theme `Views/overrides/News/posts/index.php` → each parent's override → the module's own view → the Core standard view `modules/Core/Views/posts/index.php` |
| Standard view `components/badge`, `layouts/backend`, `errors/404` | active theme `Views/<path>.php` → each parent → the Core standard view `modules/Core/Views/<path>.php` |
| Any other namespaced view (`App\…`, a library's) | unchanged |

```php
// A module controller
return $this->render('Modules\News\Views\posts/index', ['title' => lang('News.title'), 'posts' => $posts]);
```

```php
<?php // modules/News/Views/posts/index.php ?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $posts === [] ? theme_component('empty_state', ['title' => lang('News.empty')]) : '…' ?>
<?= $this->endSection() ?>
```

`BackendController::render()` adds `layout`, `breadcrumbs` (start with "Back office"; add more
with `$this->breadcrumb($label, $url)`) and, when the data has a `title`, the `pageHeader`
(title, `subtitle`, `icon`, breadcrumbs, `actions`) the layout prints. kpcrudy uses the same
layout with the section `content` (`setLayout(theme_layout('backend'), 'content')`, session 09).

## Helpers

| Helper | Returns |
|---|---|
| `theme()` | the `ThemeManager` service (`service('theme')`) |
| `theme_view($view, $data, $options)` | the rendered view, resolved through the theme |
| `theme_layout($name)` | the view name of a layout, for `$this->extend()` |
| `theme_resolve($view)` | the view name that renders a view, for `$this->include()` |
| `theme_component($name, $data)` / `theme_partial($name, $data)` | `components/<name>` / `partials/<name>` with their own data |
| `theme_region($layout, $region)` | the content of a region (empty when the theme does not declare it) |
| `theme_asset($path)` | URL of a theme asset (found along the chain) or `vendor:` file |
| `theme_option($key)` | value of an option of the active theme |

Regions are filled with already-escaped HTML: `service('theme')->regions()->add('sidebar', $html, $order)`
(`FrontendController::region()`).

## Standard views and components

Core standard views (`modules/Core/Views/`), every one overridable by a theme:

| View | Data |
|---|---|
| `components/page_header` | `title`, `subtitle`, `icon`, `breadcrumbs` (`[['label', 'url']]`), `actions` (`[['label', 'url', 'icon', 'variant']]`) |
| `components/breadcrumbs` | `items` (the last one is the current page; middle entries hidden on phones) |
| `components/crud_container` | `content` (kpcrudy's HTML), `title`, `resource` |
| `components/form_layout` | `action`, `method`, `fields` (HTML), `title`, `submitLabel`, `cancelUrl`, `hidden`, `multipart`; adds the CSRF field |
| `components/empty_state` | `icon`, `title`, `message`, `action` |
| `components/tabs` | `id`, `tabs` (`[['id', 'label', 'content', 'icon', 'active']]`) |
| `components/card` | `title`, `icon`, `body`, `footer`, `actions` |
| `errors/403`, `errors/404`, `errors/429`, `errors/500` | `message` (escaped), `homeUrl`; rendered in the `blank` layout |
| `partials/sidebar_menu`, `partials/sidebar_menu_items`, `partials/user_menu` | the generated back-office menu (`backend_menu($zone)`, list of `MenuEntry`; see `docs/menus.md`) |

Small components (`ui_helper`): `ui_badge($text, $variant, $icon)`, `ui_status($status, $label)`,
`ui_avatar($name, $imageUrl, $size)`, `ui_date($date, 'date'|'datetime'|'long'|'relative')`,
`ui_link($label, $url, $icon)`, `ui_file_link($name, $url, $bytes, $mime)`,
`ui_user_link($name, $url, $avatarUrl)`, `ui_pagination_info($page, $perPage, $total)`.
They escape their input, accept only Bootstrap colour names and Bootstrap Icons classes, and
turn `javascript:`/`data:`/protocol-relative URLs into `#` (`ui_safe_url()`).

The 404 page (`app/Views/errors/html/error_404.php`), the production 500 page
(`production.php`) and the 403 of the `can:` filter (JSON when the client asks for it) render the
theme's `errors/<code>` view, with a plain fallback if the theme itself fails.

## Options

Declared in the manifest like settings and inherited along the chain; a child redeclares only
what changes:

```php
public array $options = [
    'primaryColor' => ['default' => '#6f42c1'],   // parent: type color, css --bs-primary
];
```

Saved values are stored by codeigniter4/settings as `Theme.<option>` with the context
`theme:<slug>` (`service('settings')->set('Theme.primaryColor', '#198754', 'theme:default')`;
the Settings screen arrives in session 14). Colour options with a `css` key become CSS custom
properties (`--bs-primary` and `--bs-primary-rgb`) printed by the layouts in one `<style>` with
the CSP nonce; only validated hex colours reach it (D-00-25).

An option with `'mode' => 'dark'` sets its property only in dark mode (`[data-bs-theme=dark]`);
an empty value keeps the light one. For `--bs-primary` the layouts also print
`--kp-primary-light-rgb`, the lighter primary used for links and text on dark backgrounds.

Default theme options: `logo`, `logoDark` (logo for dark backgrounds), `primaryColor`,
`primaryColorDark`, `defaultMode` (auto, light, dark), `forceMode` (empty, light, dark: forces a
mode and hides the switch), `modeSwitch` (show the light/dark switch), `sidebarColor`, `font`
(system, serif, mono), `footerText`, `cookieNotice`.

## Light and dark mode

Three choices: light, dark and auto (the default, following `prefers-color-scheme`), with
Bootstrap 5.3 colour modes. `Modules\Core\Frontend\ThemeMode` picks the mode: `forceMode`, then
the user's stored preference (the profile, once the Users module stores it), then the `kp_theme`
cookie written by the switch, then `defaultMode`. Layouts print it on `<html>` with
`theme_mode()->attributes()` (`data-bs-theme` for an explicit mode, so there is no flash), and
`core::js/theme-mode.js` (in `<head>` with the nonce, before the stylesheets) resolves `auto`
before the first paint and follows system changes. The switch is `partials/theme_switch`
(back-office top bar and public header). Print layouts are always light. Dark-specific colours
live under `[data-bs-theme=dark]` in the theme CSS; child themes inherit both modes by overriding
variables only.

## Responsive layout

Reference widths: phone ≥ 360 px, tablet ≥ 768 px, desktop ≥ 1200 px; no horizontal page scroll
(the screenshot helper fails on overflow). The back-office sidebar is an offcanvas below 768 px,
an icon-only bar from 768 to 1199 px and a full sidebar from 1200 px; the top-bar button
(`[data-sidebar-toggle]`) switches between icon-only and full and the choice is remembered in the
`kp_sidebar` cookie (`theme_sidebar_class()` prints it on `<body>`). Menu links put their label in
`<span class="nav-text">` so the icon-only bar can hide it. On phones long breadcrumbs keep their
last two items, modals are full-screen and, on touch screens, targets are at least 44 × 44 px.

## Assets

Bootstrap 5.3 and Bootstrap Icons are vendored in `public/assets/vendor/` with
`vendor.json` (version, licence, source and SHA-256 per file; a test checks them). Theme assets are
copied from `themes/<slug>/Assets/` to `public/themes/<slug>/` by `assets:publish` (also run by
`modules:sync` and `make:theme`; never edit the copies) and versioned with the asset manifest's
hash; `asset('theme::css/x.css')` looks a file up along the chain and the import map exposes the
active theme's `js/` files as `theme/<path>` (see `docs/frontend.md`). A declared asset is looked
up from the theme that declares it, so a child's `css/theme.css` never hides its parent's.

A layout written from scratch keeps: `theme_mode()->attributes()` on `<html>`, the
`core::js/theme-mode.js` script in `<head>`, and before `</body>` the `kp-config` data block, the
nonce'd import map and `core::js/app.js` (copy `partials/head.php` and `partials/scripts.php`, or
the `make:theme --parent=none` layout template).

All colours are CSS custom properties: a child theme changes colours by overriding variables
(`:root { --kp-sidebar-bg: #312e81; }`) or option defaults, without copying the parent's CSS.

## Creating a child theme

```bash
php spark make:theme Corporate              # child of default (--parent=<slug>, or --parent=none)
```

Then override what you need, e.g. one colour in `Theme.php` (`'primaryColor' => ['default' => '#6f42c1']`)
and one view in `themes/corporate/Views/components/page_header.php`, run `php spark modules:sync`
and select it with `theme.backend = corporate` in `.env` (or the setting).

## Theme preview

`/theme-preview/components` (same conditions) is the JavaScript components demo: a
`data-component` with options and a JSON data block, toasts, confirmations, modals, JSON requests
with `core/http.js` and the form helpers, all under the enforced CSP.


In development, with `theme.preview = true` in `.env`, `/theme-preview/<layout>` shows every
layout (`backend`, `public`, `auth`, `blank`, `print`) and `errors-403|404|500` with sample
content; `?theme=<slug>` previews another theme. In any other environment, or with the option
off (the default), the pages answer 404 (D-03-05).
