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
| `$pageTemplates` | Templates of content pages (Pages module): key → label key; inherited and extended by child themes (`theme()->pageTemplates()`). The view of a template is `Modules\Pages\Views\templates\<key>`; override or add one in `Views/overrides/Pages/templates/<key>.php` (D-20-01, `docs/pages.md`). The default theme offers `default`, `wide`, `sidebar` |
| `$styles`, `$scripts` | Assets loaded after the parent's: paths under `Assets/`, or `vendor:<path>` under `public/assets/vendor/` |
| `$options` | Options declared like settings (type, default, label, help, options) plus `css` for colour options |

The theme's PSR-4 namespace is registered when the theme is first loaded (no scan of `themes/`
per request). `modules:sync` validates every theme (`ThemeValidator`: version, layouts and their
files, regions, menu locations, asset paths, option types, CSS properties) and publishes the
themes' assets; any error stops the sync.

The two themes in the repository are `themes/default` (standalone: all five layouts, the
partials, `css/theme.css` and the options listed below) and `themes/tasks-demo` (a child of
`default`, the finished theme of the Tasks tutorial; see "Child themes").

## Layouts

Every theme chain provides the five layouts of `ThemeManifest::LAYOUTS`; a view extends one with
`$this->extend($layout)` (given by `BackendController::render()` / `FrontendController::render()`)
or `$this->extend(theme_layout('<name>'))`, and fills the section `content`.

| Layout | Used for (default theme) | Regions |
|---|---|---|
| `backend` | the back office: sidebar (generated menu), top bar with the notifications bell, theme switch and user menu, page header or breadcrumbs, session messages, footer; kpcrudy screens too | `sidebar`, `topbar`, `footer` |
| `public` | the public site: header with the logo and the `primary` menu location, optional sidebar column, footer with the `footer` menu location, SEO meta (`$meta`), cookie notice | `header`, `sidebar`, `footer`, `home` |
| `auth` | login, registration, 2FA, password reset: a centred card with the logo | `footer` |
| `blank` | the theme's styles and scripts around `content` only (error pages, embeds, pop-ups) | — |
| `print` | no navigation and no scripts, always light; site name and title on top, print date at the bottom | — |

The layouts only provide the section `content`: there is no `head` section. Page-specific
stylesheets are loaded by the page's component from a URL passed in its options (see the
tutorial in [`frontend.md`](frontend.md)); site-wide ones go in the theme's `$styles`.

## Regions and menu locations

**Regions** are named slots of a layout, declared per layout in `$regions` and printed with
`theme_region('<layout>', '<region>')`. Code fills them with already-escaped HTML
(`service('theme')->regions()->add('sidebar', $html, $order)`, or `FrontendController::region()`);
a region the active theme does not declare prints nothing (the public layout shows its sidebar
column only when `theme()->hasRegion('public', 'sidebar')` and something was added).

**Menu locations** are the public menus a theme prints, declared in `$menuLocations` (key →
label key; the default theme: `primary`, `footer`, `social`). The Menus module edits one menu per
location and the layout prints it with `menu('<location>')` through the partial
`partials/public_menu` (`primary` as the navbar with dropdowns, any other location as a list of
links). See [`menus.md`](menus.md).

## Which theme is active

`setting('Theme.backend')` and `setting('Theme.public')` (defaults in `Modules\Core\Config\Theme`,
overridable in `app/Config/Theme.php` or `.env`: `theme.backend = corporate`; or the theme tab of
Settings, `backend/settings/theme`). `BackendController` switches the request to the `backend` area,
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
| `errors/maintenance` | `message` (the administrator's plain text, escaped; empty = default text), `retry` (URL); the 503 page of maintenance mode in the `blank` layout. Rendered **without the database** (`MaintenancePage`): settings answer only the file configuration (`Config\Theme`'s public theme and default options), so the view must not read models or the session (D-28-07) |
| `partials/sidebar_menu`, `partials/sidebar_menu_items`, `partials/user_menu` | the generated back-office menu (`backend_menu($zone)`, list of `MenuEntry`; see `docs/menus.md`) |

Small components (`ui_helper`): `ui_badge($text, $variant, $icon)`, `ui_status($status, $label)`,
`ui_avatar($name, $imageUrl, $size)`, `ui_date($date, 'date'|'datetime'|'long'|'relative')`,
`ui_link($label, $url, $icon)`, `ui_file_link($name, $url, $bytes, $mime)`,
`ui_user_link($name, $url, $avatarUrl)`, `ui_pagination_info($page, $perPage, $total)`.
They escape their input, accept only Bootstrap colour names and Bootstrap Icons classes, and
turn `javascript:`/`data:`/protocol-relative URLs into `#` (`ui_safe_url()`).

The 404 page (`app/Views/errors/html/error_404.php`), the production 500 page
(`production.php`) and the 403 of the `can:` filter (JSON when the client asks for it) render the
theme's `errors/<code>` view, with a plain fallback if the theme itself fails. The 503 page of maintenance mode is `errors/maintenance` (drawn without the database, D-28-07).

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
edited in the theme tab of Settings, `settings.core`). Colour options with a `css` key become CSS custom
properties (`--bs-primary` and `--bs-primary-rgb`) printed by the layouts in one `<style>` with
the CSP nonce; only validated hex colours reach it (D-00-25).

An option with `'mode' => 'dark'` sets its property only in dark mode (`[data-bs-theme=dark]`);
an empty value keeps the light one. For `--bs-primary` the layouts also print
`--kp-primary-light-rgb`, the lighter primary used for links and text on dark backgrounds.

Default theme options: `logo`, `logoDark` (logo for dark backgrounds), `primaryColor`,
`primaryColorDark`, `defaultMode` (auto, light, dark), `forceMode` (empty, light, dark: forces a
mode and hides the switch), `modeSwitch` (show the light/dark switch), `languageSwitch` (show the public language switch, `docs/i18n.md`), `sidebarColor`, `font`
(system, serif, mono), `footerText`, `cookieNotice`, `showAccountLink` (the account link of the
public navbar, below).

### Account link

The default theme's public navbar ends with an account link (partial `partials/account_link`,
option `showAccountLink`, on by default; at phone width it is inside the collapsed menu):

| Visitor | Link | Goes to |
|---|---|---|
| guest | **Entra** (Sign in) | the route `login` |
| signed in, can open the back office | **Administració** (Administration) | the first entry of their back-office menu (`sidebar`, then `settings`: the dashboard in the skeleton) |
| signed in, no back-office screen | **El meu compte** (My account) | the first entry of their `user` menu (the profile) |

`Modules\Core\Frontend\AccountLink::for($user)` decides it from route names and the generated
back-office menu (already filtered by permission), never from the `backend` string, so another
theme can reuse it: `(new AccountLink())->for(auth()->user())` returns `kind`, `label`, `url` and
`icon`, or null. Child themes (`tasks-demo`) inherit the partial and the option.

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

**Contrast (WCAG AA, 4.5:1), checked by axe in the end-to-end tests in both modes.** Bootstrap's
mid-tone literals fail on the back office's light grey page (`--bs-tertiary-bg`) and on the dark
body, so the default theme (`theme.css`):

- writes text in the primary colour (links, `.btn-outline-primary`) with
  `--kp-primary-text` = the primary colour mixed with 15 % black (Bootstrap's `#0d6efd` reaches
  only 4.26:1 on `#f8f9fa`); dark mode keeps the lightened `--kp-primary-light`. Plain links (no
  class) read `--bs-link-color` directly, because Bootstrap colours them through
  `--bs-link-color-rgb`, which cannot hold a mixed colour;
- uses the next shade of Bootstrap's palette for the text of outline buttons (secondary,
  success, danger, info, warning) in light mode, and the `*-text-emphasis` colours in dark mode
  (also `.text-danger`, `.text-success`, `.text-secondary`, `.link-secondary`);
- dims the labels of disabled checkboxes to 75 % instead of 50 % and darkens inline `code`.

A child theme that changes `primaryColor` should keep `--kp-primary-text` at 4.5:1 on
`#f8f9fa` (run `npm run e2e`).

## Responsive layout

Reference widths: phone ≥ 360 px, tablet ≥ 768 px, desktop ≥ 1200 px; no horizontal page scroll
(the screenshot helper fails on overflow). The back-office sidebar is an offcanvas below 768 px,
an icon-only bar from 768 to 1199 px and a full sidebar from 1200 px; the top-bar button
(`[data-sidebar-toggle]`) switches between icon-only and full and the choice is remembered in the
`kp_sidebar` cookie (`theme_sidebar_class()` prints it on `<body>`). Menu links put their label in
`<span class="nav-text">` so the icon-only bar can hide it. On phones long breadcrumbs keep their
last two items, modals are full-screen and, on touch screens, targets are at least 44 × 44 px.

## PWA manifest (minimal PWA)

Off by default; enable the setting `Core.pwa` (Settings › General, group "Web app", or
`service('settings')->set('Core.pwa', true)`). Then (`Modules\Core\Controllers\Pwa`,
`Modules\Core\Frontend\Pwa`):

- `/manifest.webmanifest` (`application/manifest+json`): `name` and `short_name` (the site name,
  12 characters), `lang`, `start_url` (setting `Core.pwaStart`: `public` home or `backend`),
  `scope` (the site root), `display: standalone`, `background_color` `#ffffff`, `theme_color`
  (the public theme's `primaryColor`) and the two icons;
- `/pwa/icon-192.png`, `/pwa/icon-512.png`: generated with GD from the public theme's `logo`
  (a local PNG/JPEG/GIF/WebP under `public/`; SVG or remote logos are never read, the default
  icon is drawn instead) into `writable/pwa/`, and regenerated whenever the logo or the primary
  colour changes (the file name and the `?v=` of the manifest carry a hash);
- `/sw.js` (`modules/Core/Assets/pwa/sw.js`, header `Service-Worker-Allowed: /`): served from the
  root so it controls the whole site; it **caches nothing** (no Cache API, no `respondWith()`), so
  authenticated pages and data always come from the network;
- the theme's `partials/head.php` adds, when `pwa_enabled()`, `<link rel="manifest">`, the Apple
  touch icon (`pwa_icon_url(192)`) and a `theme-color` per colour scheme (`pwa_theme_colors()`:
  the primary colour in light mode, `#212529` in dark mode); `core/pwa.js` registers the worker,
  and unregisters it when the PWA is switched off.

All three routes answer 404 while `Core.pwa` is off (`modules/Core/Tests/PwaTest.php`). A layout
written from scratch keeps these links by including `partials/head`.

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

## View overrides

A theme replaces a view without touching the module or the Core:

| To replace | Put the file in |
|---|---|
| a module view `Modules\<Module>\Views\<path>` | `themes/<slug>/Views/overrides/<Module>/<path>.php` |
| a standard view or component (`components/page_header`, `errors/404`, `widgets/…`) | `themes/<slug>/Views/<path>.php` |
| a partial of the parent (`partials/brand`, `partials/head`…) | `themes/<slug>/Views/partials/<name>.php` |
| a layout | `themes/<slug>/Views/layouts/<name>.php` (and list it in `$layouts`) |

The override receives the same data as the original view (copy its `@var` docblock), extends
`$layout` like it and must follow the same rules (escaping, no inline code: `php spark csp:check`
also reads `themes/*/Views`). Overrides are looked up along the chain, so a child's override wins
over its parent's.

## Section views and palette

Pages built from sections (D-45-01, [`sections.md`](sections.md)) render each section type
through a module view (`Modules\Pages\Views\sections\hero` …), overridable like any module view
(`themes/<slug>/Views/overrides/Pages/sections/hero.php`); the section forms' controls are
`Modules\Core\Views\sections\fields\<type>` (`overrides/Core/sections/fields/<type>.php`).

The page template `sections` asks the public layout for its **full-width mode** (`$fullWidth`:
no outer `.container`, no sidebar, no vertical padding; notices stay in a container), so
backgrounds reach the edges; each section brings its own `.container` (or `.container-fluid`
when its width is `full`). A theme's own public layout must honour `$fullWidth` the same way.

The default theme styles sections in `Assets/css/theme.css` from variables only:

| Class | Meaning |
|---|---|
| `kp-bg-none`, `kp-bg-light`, `kp-bg-dark`, `kp-bg-primary`, `kp-bg-image` | background from the palette: body, `--bs-tertiary-bg`, `--kp-section-dark-bg`, `--kp-section-primary-bg` (the primary colour darkened for AA contrast with white text), an image under `.kp-section-overlay` |
| `kp-space-s|m|l` | vertical padding |
| `kp-width-contained|full` | content in `.container` or edge to edge |
| `kp-style-<style>` | the type's style (`icons`, `tiles`, `band` of quick links) |
| `kp-hero-*`, `kp-overlay-*`, `kp-edge-wave` | hero heights, overlay colours and the wave bottom edge (an SVG mask painted in `--bs-body-bg`) |

Dark, primary and image backgrounds carry `data-bs-theme="dark"`, so Bootstrap's text and
component colours turn light inside them, in light and dark mode alike. A child theme changes
`--kp-section-dark-bg` / `--kp-section-primary-bg` or the primary colour option, never the views.

## Child themes

A child theme (`$parent = '<slug>'`) inherits the parent's layouts, partials, components,
options, page templates and assets; it declares only what changes. Its `$styles` and `$scripts`
load after the parent's, a redeclared option changes only that option's default, and its views
win over the parent's. A parent cannot be uninstalled while a child uses it.

**The real example, `themes/tasks-demo`** (child of `default`, tutorial 9 of the Tasks module):

```
themes/tasks-demo/
├── Theme.php                                   Themes\TasksDemo\Theme, $parent = 'default', $layouts = []
├── Assets/css/theme.css                        its own touches, through CSS custom properties
├── Assets/img/logo.svg
├── Views/overrides/Tasks/public/index.php      the Tasks public page as coloured columns
└── Language/ca|es|en/ThemeTasksDemo.php
```

```php
public ?string $parent = 'default';
public array $layouts  = [];                    // every layout comes from the parent
public array $styles   = ['css/theme.css'];     // loaded after the parent's stylesheets
public array $options  = [
    'primaryColor' => ['default' => '#6f42c1'],
    'logo'         => ['default' => 'themes/tasks-demo/img/logo.svg'],
];
```

`modules/Tasks/Tests/ChildThemeTest.php` checks it: the chain is `['tasks-demo', 'default']`,
there is no `Views/layouts/public.php` of its own, `GET tasks` renders the theme's override with
`--bs-primary: #6f42c1;`, the logo and both stylesheets (`themes/default/css/theme.css` then
`themes/tasks-demo/css/theme.css`), `tasks/request` (not overridden) keeps the module's view, and
the page has no inline script, handler or `style` attribute.

### Creating a child theme

```bash
php spark make:theme Corporate              # child of default (--parent=<slug>, or --parent=none)
```

`make:theme` writes `themes/<slug>/` (`Corporate` → `corporate`, `TasksDemo` → `tasks-demo`):
the manifest, `Assets/css/theme.css`, empty `Views/overrides`, `Views/components` and
`Views/partials`, and the ca/es/en language files; with `--parent=none`, a standalone theme with a
minimal file per layout. It validates the theme and publishes its assets (see
[`commands.md`](commands.md)).

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

## Installing, switching and removing themes

A theme packaged as a ZIP (`<slug>/Theme.php` …) is installed with `php spark themes:install
<file.zip|url>` (same validation, backup and maintenance as module packages) or uploaded in
**Modules › Packages** and installed from the console. **Modules › Themes** lists the installed
themes with their screenshot (`Assets/img/screenshot.png`, optional) and colours and switches the
theme of the back office or of the public site with one click (`modules.toggle`); the options stay
in Settings › Theme. `php spark themes:uninstall <slug>` removes a theme that is not active, not
the fallback and not a parent. See `docs/installing-modules.md`.
