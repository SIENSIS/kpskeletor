# Dashboard widgets

How the back-office home works and how a module adds a widget (specification: "Dashboard
widgets", "Modules" › "Dashboard"; decisions D-16-01 … D-16-05). The step-by-step tutorial
"A new widget" (the `Tasks` module's *My pending tasks* and *Task activity chart*) is written in
sessions 32–34; this page is the reference.

## The pieces

| Piece | Where | What it does |
|---|---|---|
| `WidgetInterface` | `modules/Core/Widgets/` | The contract: `id()`, `title()`, `icon()`, `permission()`, `sizes()`, `settings()`, `render(array $options)`. |
| `AsyncWidgetInterface` | `modules/Core/Widgets/` | Adds `data()`, `renderData()`, `component()`, `cacheSeconds()`, `refreshSeconds()`. |
| `Widget`, `AsyncWidget` | `modules/Core/Widgets/` | Base classes with the defaults (extend these). |
| `WidgetRegistry` | Core | The widgets of the **enabled** modules (manifest `$widgets`, stored by `modules:sync`), by id. Broken declarations are skipped and logged. |
| `WidgetRenderer` | Core | Renders a card with failure isolation; caches async data per user. |
| `WidgetOptions` | Core | Defaults, stored values and validation of the per-group options. |
| `WidgetSizes` | Core | `small`, `medium`, `large`, `full` and their grid columns. |
| Dashboard module | `modules/Dashboard/` | The home `backend`, the group layouts and the user layouts (`DashboardLayout`). A core module: it cannot be disabled (D-16-05). |

Shipped widgets: *Scheduled tasks* (`core.scheduled-tasks`, `schedule.list`: failing tasks and
the next run), *Quick links* (`core.quick-links`, no permission of its own: an ordered list per
group of menu entries, which each user sees only when they can open them, and free links with an
`https://` address or an internal path, shown to every user of the group; see below) and *Users summary* (`users.summary`,
`users-users.list`, async: active accounts, users online, accounts without 2FA). Later modules
add theirs (News, Events, Updates, Backup, Security status).

## Who sees a widget

A widget is shown — and its async URL answers — only when **both** are true:

1. the user holds `permission()` (checked with `Authorization::for($user)`; null means the
   dashboard's own `dashboard.view` is enough, so such a widget must only show data every user
   may see or filter it per user, like *Quick links*);
2. the widget is **enabled for one of the user's groups** (Dashboard › Group layouts). A widget
   without a stored row is enabled with its defaults, so a newly installed module's widget
   appears for the groups that hold its permission (D-16-02).

When several of the user's groups enable a widget, its default position, size and options come
from the first of those groups in the groups' order (reserved groups first: superadmin, admin,
editor, user, then custom groups by creation).

Disabling a module hides its widgets at once (the registry only reads enabled modules).

## Writing a widget

```php
<?php

declare(strict_types=1);

namespace Modules\News\Widgets;

use Modules\Core\Widgets\Widget;
use Modules\Core\Widgets\WidgetSizes;

final class LatestNews extends Widget
{
    public function id(): string { return 'news.latest'; }            // "<module>.<name>", lower case
    public function title(): string { return lang('News.widget.latest'); }
    public function icon(): string { return 'bi bi-newspaper'; }
    public function permission(): string { return 'news-posts.list'; }

    public function sizes(): array                                       // first = default
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::SMALL, WidgetSizes::LARGE];
    }

    public function settings(): array
    {
        return ['limit' => ['type' => 'int', 'default' => 5, 'min' => 1, 'max' => 20, 'label' => 'News.widget.limit']];
    }

    public function render(array $options): string
    {
        $posts = model(PostModel::class)->latest((int) $options['limit']);

        return $posts === []
            ? $this->empty(lang('News.widget.none'))
            : $this->view('Modules\News\Views\widgets\latest', ['posts' => $posts]);
    }
}
```

Declare it in the manifest and run `php spark modules:sync`:

```php
public array $widgets = [\Modules\News\Widgets\LatestNews::class];
```

Rules:

- `render()` returns the card **body** (escaped HTML); the renderer prints the card, its header
  (icon and title) and the grid column. Use a view (`$this->view()`, overridable by themes in
  `themes/<slug>/Views/overrides/<Module>/…`) and the standard components.
- No inline scripts or styles (CSP): behaviour goes in an ES module (`data-component`).
- Titles and option labels come from the language files (ca, es, en).
- `modules:sync` validates the manifest: every `$widgets` class must exist and implement
  `WidgetInterface`. The registry also skips (and logs) a widget whose id is invalid or repeated.

### Sizes

| Size | Phones | Tablets (≥ 768 px) | Desktops (≥ 1200 px) |
|---|---|---|---|
| `small` | full width | half | a third |
| `medium` | full width | full width | half |
| `large` | full width | full width | two thirds |
| `full` | full width | full width | full width |

`sizes()` lists the sizes the widget allows (the first is the default). Group layouts and users
can only choose among them; anything else falls back to the default.

### Options

`settings()` declares the options an administrator sets **per group** (users do not change
options). Types: `int` (`min`, `max`), `bool`, `string` (one line, up to 255 characters), `select`
(`options`: value => language key), `menu` (back-office menu entry keys such as
`Users.users` or `settings`) and `links` (an ordered list of at most 20 items, each a menu entry
`{"menu": "settings"}` or a free link `{"label": "…", "url": "…"}`; addresses are `https://`
URLs without credentials or internal paths starting with one `/`, checked by
`WidgetOptions::linkUrl()`; `http:`, `javascript:`, `data:`, `mailto:` and `//host` are refused). `render()`/`data()` always receive every option, already
validated; invalid stored values fall back to the default.

## Async widgets

For slow queries or data drawn in the browser, extend `AsyncWidget`:

```php
final class UsersSummary extends AsyncWidget
{
    // id(), title(), permission(), settings() as above
    public function cacheSeconds(): int { return 120; }      // per user, language and options
    public function refreshSeconds(): int { return 120; }    // 0: never (minimum 15 s in the browser)

    public function data(array $options): array
    {
        return ['active' => …, 'online' => …];             // JSON-serialisable, for auth()->user()
    }

    public function renderData(array $data, array $options): string
    {
        return $this->view('Modules\Users\Views\widgets\summary', $data);
    }
}
```

- The dashboard prints the placeholder (`render()`, by default a spinner) inside
  `data-component="core/ui/widget"` with the URL
  `backend/dashboard/widgets/<id>/data`.
- That URL (`dashboard.view` + the `widgets` rate limit, `Core.rateLimitWidgets`) answers
  `{"widget": "<id>", "data": {…}, "html": "…"}` with `Cache-Control: no-store`, or **403** when
  the widget is unknown, not async, or not available to the user (permission or group layout);
  refusals are written to the activity log (`widget_forbidden`, security).
- The response is cached in the Core cache scope `widgets` (`CacheInvalidator` clears it) per
  user, language and options. Permissions and group layouts are checked on every request, before
  the cache is read, so a revoked permission takes effect at once.
- A widget that draws its data itself returns its own `component()` and reuses the loader:

  ```js
  // modules/Tasks/Assets/js/activity-chart.js  →  component "tasks/activity-chart"
  import { load } from 'core/ui/widget.js';

  export function mount(root) {
      load(root, (response, content) => {
          // response.data is data(); draw it into content with DOM methods (never innerHTML)
      });
  }
  ```

## Failures

A widget that throws in `title()`, `render()` or `data()` never breaks the dashboard: the error
(class, message, file and line) goes to the application log and the card shows "This widget
could not be shown" (`Core.widgets.failed`); the async URL answers 500 with the same generic
message. Nothing of the exception reaches the browser.

## Group layouts (administrators)

Settings › *Dashboard widgets* (`backend/dashboard/groups[/<group>]`, `dashboard.configure`,
granted to `admin`): one tab per group, one card per widget with *enabled*, *position*, *size*
and the widget's options. The screen warns when the group does not hold the widget's
permission. Saving is validated (sizes, positions 0–9999, options) and written to the activity
log (`group_layout_saved`, with the before/after summary).

A `links` option (the *Quick links*) is edited as one ordered list: each row is a screen of the
back-office menu or a "free link" (label + address). The order of the list is the order users
see; with JavaScript (`dashboard/links-editor`) rows are dragged (mouse, touch, arrow keys) or
moved with up/down buttons, removed and added; without it, a position field per row and three
empty rows do the same. External links open in a new tab (`rel="noopener noreferrer"`). Table: `dashboard_group_widgets`
(`group_name`, `widget`, `enabled`, `position`, `size`, `options` JSON).

## The user's layout

On the dashboard, **Customise** turns on the edit mode (component `dashboard/dashboard`):

- drag a card by its handle (pointer events: mouse, pen and touch) or focus the handle and use
  the arrow keys;
- change its size (only the widget's sizes) or hide it; hidden widgets are listed below with a
  *Show* button;
- every change is saved at once (`POST backend/dashboard/layout`, JSON); the server stores only
  widgets the user may see, with allowed sizes (`dashboard_user_layouts`, one JSON row per user).

Without JavaScript, *Customise* opens `backend/dashboard/customize` (position, size and
*visible* per widget). **Restore the group layout** (`POST backend/dashboard/layout/reset`)
deletes the user's row. Widgets the user gains later are added at the end of their layout;
widgets they lose disappear from it.

## Tests

`modules/Dashboard/Tests/` (`DashboardTest`, `GroupLayoutTest`, `DashboardModuleTest`) and
`modules/Core/Tests/WidgetsTest.php`. `DashboardTestCase::addTestWidgets()` declares a failing
widget and an async counting widget; copy its tests for a module's widget: hidden without the
permission, hidden when its group disables it, async URL 403, failure card.
