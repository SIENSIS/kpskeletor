# Dashboard widgets

How the back-office home works and how a module adds a widget (specification: "Dashboard
widgets", "Modules" › "Dashboard"; decisions D-16-01 … D-16-05). This page is the
reference; the step-by-step tutorial ["A new widget"](#tutorial-a-new-widget) at the end builds
the two widgets of the `Tasks` tutorial module.

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
add theirs: News has *Notices* (`news.notices`) and *Latest news* (`news.latest`, `docs/news.md`); Events has *Upcoming events* (`events.upcoming`, `docs/events.md`); Updates, Backup and Security status follow.

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
    public function permission(): string { return 'news-read.view'; }

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
`Users.users` or `settings`) and `links` (an ordered list of at most `Core.widgetMaxLinks` items
— a setting in Settings › General › Dashboard, default 20, 1–100; a definition's `max` overrides it —, each a menu entry
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
- The default component `core/ui/widget` shows the `html` of the response (parsed in an inert
  document, never with `innerHTML`), a spinner while loading (`Core.widgets.loading`, the view
  `widgets/loading`), "The widget could not be loaded." (`Core.js.widget.error`) or the 403 text
  on errors, and reloads every `refreshSeconds()` while the page is visible.
- A widget that draws its data itself in the browser returns its own `component()` and reuses
  the loader (a pattern: no shipped widget does this yet; `<module>/<name>` is the component of
  `modules/<Name>/Assets/js/<name>.js`):

  ```js
  // modules/<Name>/Assets/js/activity-chart.js  →  component "<module>/activity-chart"
  import { load } from 'core/ui/widget.js';

  export function mount(root) {
      load(root, (response, content) => {
          // response.data is data(); draw it into content with DOM methods (never innerHTML)
      });
  }
  ```

## Failures

A widget that throws in `render()` or `data()` never breaks the dashboard: the error
(class, message, file and line) goes to the application log and the card shows "This widget
could not be shown" (`Core.widgets.failed`); the async URL answers 500 with the same generic
message (`{"error": …}`; the card then shows `Core.js.widget.error`). A `title()` or `icon()` that
throws falls back to the widget's id and the default icon. Nothing of the exception reaches the
browser.

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

## Tutorial: a new widget

This tutorial builds the two widgets of the `Tasks` tutorial module (`modules/Tasks/Widgets/`):
*My pending to-do tasks*, drawn on the server, and *To-do task activity*, loaded asynchronously.
The code is real and finished; the excerpts below are copied from it.

| Piece | Server-side widget | Asynchronous widget |
|---|---|---|
| Class | `Widgets/MyPendingTasks.php` (extends `Widget`) | `Widgets/TaskActivityChart.php` (extends `AsyncWidget`) |
| Id | `tasks.pending` | `tasks.activity` |
| Permission | `tasks-board.view` (every group) | `tasks-tasks.list` (admin, editor) |
| View | `Views/widgets/pending.php` | `Views/widgets/activity.php` (an SVG bar chart) |
| Option | `limit` (1–20, default 5) | `days` (7–60, default 14) |
| Texts | `Tasks.widgets.limit`, `Tasks.widgets.pending.*` | `Tasks.widgets.activity.*` |
| Test | `Tests/WidgetsAndReminderTest.php` | same file |

To see them: `php spark modules:enable Tasks`, `php spark modules:demo Auth`,
`php spark modules:demo Tasks`, then open the dashboard (`backend`).

### 1. A server-side widget: the class

A widget extends `Modules\Core\Widgets\Widget` (which implements `WidgetInterface` and gives
defaults for `icon()`, `permission()`, `sizes()` and `settings()`) and implements at least `id()`,
`title()` and `render()`:

```php
final class MyPendingTasks extends Widget
{
    public function id(): string
    {
        return 'tasks.pending';
    }

    public function title(): string
    {
        return lang('Tasks.widgets.pending.title');
    }

    public function icon(): string
    {
        return 'bi bi-check2-square';
    }

    public function permission(): string
    {
        return 'tasks-board.view';
    }

    public function settings(): array
    {
        return [
            'limit' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 20,
                'label'   => 'Tasks.widgets.limit',
            ],
        ];
    }
```

- `id()`: `<module>.<name>` in lower case, unique.
- `title()` and the option `label` come from the language files (`Tasks.widgets.pending.title`
  is "My pending to-do tasks" in English and "Les meves tasques pendents" in Catalan; keep ca, es
  and en in step).
- `permission()`: a permission of the module's manifest. The widget is shown only to users who
  hold it **and** whose group enables the widget.
- `sizes()`: not overridden here, so the widget offers the base class's
  `medium` (default), `small`, `large`, `full`; the asynchronous widget below narrows the list.
- `settings()`: the options an administrator sets per group ("Number of to-do tasks").

`render()` receives the validated options and returns the card **body**; the renderer adds the
card, its header (icon and title) and the grid column:

```php
public function render(array $options): string
{
    $user = auth()->user();

    if (! $user instanceof User) {
        return '';
    }

    $repository = new TaskRepository();
    $rows       = $repository->pendingFor((int) $user->id, (int) $options['limit']);

    if ($rows === []) {
        return $this->empty(lang('Tasks.widgets.pending.empty'), 'bi bi-check2-all');
    }
    // … $items built from $rows
    return $this->view('Modules\Tasks\Views\widgets\pending', [
        'items' => $items,
        'total' => $repository->pendingCount((int) $user->id),
        'url'   => backend_url('tasks'),
    ]);
}
```

`$this->empty()` renders the standard `empty_state` component; `$this->view()` renders through the
theme, so a theme can override the view in
`themes/<slug>/Views/overrides/Tasks/widgets/pending.php`. The view escapes everything and uses
the standard helpers (`ui_date()`, `ui_badge()`); no inline script or style. The widget only reads
the user's own to-do tasks, so it shows data the user may see.

### 2. Declaring it and configuring it per group

```php
/** Dashboard widgets (tutorial 4): one server-side, one asynchronous. */
public array $widgets = [
    \Modules\Tasks\Widgets\MyPendingTasks::class,
    \Modules\Tasks\Widgets\TaskActivityChart::class,
];
```

Run `php spark modules:sync`: it checks that every class exists and implements
`WidgetInterface` and stores the manifest; the registry then lists the widgets of **enabled**
modules. From then on:

- with no stored row, a widget is enabled for every group with its defaults, so *My pending to-do
  tasks* appears on the dashboard of every user (they all hold `tasks-board.view`) and the chart
  for admins and editors (`tasks-tasks.list`);
- Settings › *Dashboard widgets* (`backend/dashboard/groups`, `dashboard.configure`) shows one
  card per widget in each group's tab: *enabled*, *position*, *size* (only the widget's sizes)
  and its options (`limit`, `days`), and warns when the group lacks the widget's permission;
- each user can move, resize or hide the widgets the group gives them (**Customise**).

Disabling the module hides both widgets at once.

### 3. An asynchronous widget

For data that is slower to compute, extend `AsyncWidget`: the dashboard prints a placeholder at
once and the browser loads the data afterwards.

```php
final class TaskActivityChart extends AsyncWidget
{
    // id() 'tasks.activity', title(), icon() 'bi bi-bar-chart', permission() 'tasks-tasks.list'

    public function sizes(): array
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::LARGE, WidgetSizes::FULL];
    }

    public function cacheSeconds(): int
    {
        return 300;
    }

    public function data(array $options): array
    {
        $days = (new TaskRepository())->activity(max(7, min(60, (int) ($options['days'] ?? 14))));

        return [
            'days'      => $days,
            'created'   => array_sum(array_column($days, 'created')),
            'completed' => array_sum(array_column($days, 'completed')),
        ];
    }

    public function renderData(array $data, array $options): string
    {
        // … $days and $max from $data
        return $this->view('Modules\Tasks\Views\widgets\activity', [ /* days, max, created, completed */ ]);
    }
}
```

How it runs:

1. The dashboard renders `render()` — by default the Core's spinner view `widgets/loading`
   ("Loading…") — inside `<div data-component="core/ui/widget" data-url="…" data-refresh="0">`
   (`component()` and `refreshSeconds()` keep the base class's defaults here).
2. `core/ui/widget.js` requests `backend/dashboard/widgets/tasks.activity/data` with
   `core/http.js` (`aria-busy` while it loads).
3. The endpoint checks `dashboard.view` (route filter, plus the `widgets` rate limit), then the
   widget: it must be async, the user must hold `tasks-tasks.list` and one of their groups must
   enable it — otherwise **403** and a `widget_forbidden` security entry. These checks run on
   every request, before the cache.
4. It answers `{"widget": "tasks.activity", "data": {…}, "html": "<svg …>"}` with
   `Cache-Control: no-store, private`. `data` is `data()`; `html` is `renderData()`.
5. The component shows `html` (parsed in an inert document, without `innerHTML`); on an error it
   shows "The widget could not be loaded." or, on a 403, the forbidden text.

**Per-user cache.** `data()` and `renderData()` run at most once per `cacheSeconds()` (300 here)
for each user, language and set of options: the key is
`data.<id>.<user id>.<locale>.<hash of the options>` in the Core cache scope `widgets`
(`CacheInvalidator` clears the scope). Return `0` to disable the cache. Since the permission and
group checks run before the cache, a revoked permission takes effect on the next request.

**The chart.** This widget draws the chart **on the server**: `Views/widgets/activity.php` is an
`<svg role="img" aria-label="…">` with two `<rect>` per day whose sizes are SVG attributes and
whose colours are Bootstrap classes with `fill="currentColor"` (never `style=""`), plus a
`<title>` per day. It needs no JavaScript of its own: the Core's component shows the HTML. A widget
that must draw in the browser returns its own `component()` and reuses `load()` from
`core/ui/widget.js` (see "Async widgets" above); its ES module goes in
`modules/<Name>/Assets/js/` and is published like any other asset.

### 4. Translations

Titles, option labels, empty texts and the chart's legend and accessible summary are keys of
`modules/Tasks/Language/{ca,es,en}/Tasks.php`:

```php
'widgets' => [
    'limit'   => 'Number of to-do tasks',
    'pending' => [
        'title' => 'My pending to-do tasks',
        'empty' => 'Nothing pending. Well done!',
        'all'   => 'Open the board ({0} pending)',
    ],
    'activity' => [
        'title'     => 'To-do task activity',
        'days'      => 'Days shown',
        'created'   => '{0} created',
        'completed' => '{0} completed',
        'summary'   => 'Last {0} days: {1} to-do tasks created, {2} completed.',
    ],
],
```

The cached HTML is per language, so each user gets the chart in theirs.
`tests/unit/I18n/LanguageKeysTest.php` fails if a language lacks a key.

### 5. Failures are isolated

If `render()` throws, the dashboard still renders: the error (class, message, file, line) goes
to the application log and the card body says "This widget could not be shown. The error has been
logged." (`Core.widgets.failed`). A `title()` or `icon()` that throws falls back to the widget's id
and the default icon. If `data()` or `renderData()` throws, the data URL answers 500 with the same generic
message and the card shows "The widget could not be loaded.". Nothing of the exception reaches
the browser, and the other widgets are not affected.

### 6. Publishing assets

These two widgets have no assets of their own. A widget with its own ES module or CSS puts them in
`modules/<Name>/Assets/` and publishes them with `php spark assets:publish` (also run by
`php spark modules:sync`; `--watch` or `assets.devRefresh` while developing). See
[`frontend.md`](frontend.md) ("Tutorial: a new view with JavaScript", step 8).

### 7. The test

`modules/Tasks/Tests/WidgetsAndReminderTest.php` (base `TasksTestCase`, which extends the
Dashboard's `DashboardTestCase` and its helpers `cards()` and `setGroupWidget()`):

```php
public function testTheActivityChartIsAsynchronousAndNeedsItsPermission(): void
{
    $this->task('Creada avui');
    $this->task('Feta avui', ['status' => 'done', 'completed_at' => Time::now()->toDateTimeString()]);

    $this->createUser('pau@example.com', 'user');
    $this->login('pau@example.com');
    $this->send('GET', 'backend/dashboard/widgets/tasks.activity/data')->assertStatus(403);

    $this->send('POST', 'logout');
    $this->createUser('boss@example.com', 'admin');
    $this->login('boss@example.com');
    $this->assertContains('tasks.activity', $this->cards($this->send('GET', 'backend')));

    $json = json_decode((string) $this->send('GET', 'backend/dashboard/widgets/tasks.activity/data')->response()->getBody(), true);
    $this->assertSame(2, $json['data']['created']);
    $this->assertSame(1, $json['data']['completed']);
    $this->assertCount(14, $json['data']['days']);
    $this->assertStringContainsString('<svg', $json['html']);
    $this->assertStringNotContainsString('style=', $json['html']);

    // Hidden for the group: neither the card nor its data.
    $this->setGroupWidget('admin', 'tasks.activity', false);
    $this->assertNotContains('tasks.activity', $this->cards($this->send('GET', 'backend')));
    $this->send('GET', 'backend/dashboard/widgets/tasks.activity/data')->assertStatus(403);
}
```

`testMyPendingToDoTasksShowsOnlyTheUsersOpenToDoTasks` checks the server-side widget: a `user`
sees the `tasks.pending` card but not `tasks.activity` (no `tasks-tasks.list`), and the card lists
only their open to-do tasks. The failure card is tested once for every widget in
`modules/Dashboard/Tests/` (`DashboardTestCase::addTestWidgets()` declares a failing widget).
Run `vendor/bin/phpunit modules/Tasks/Tests/WidgetsAndReminderTest.php`.
