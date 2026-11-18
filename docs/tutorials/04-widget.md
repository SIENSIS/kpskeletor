# Tutorial 4 — A new widget

Two dashboard widgets of the `Tasks` module: **My pending to-do tasks** (`tasks.pending`, drawn on
the server with the dashboard) and **To-do task activity** (`tasks.activity`, an asynchronous
widget whose bar chart is loaded after the page). Together they show the widget contract, sizes,
options per group, permissions and isolated failures.

The full walk-through, with every excerpt copied from the code, is in
[`widgets.md`, "Tutorial: a new widget"](../widgets.md#tutorial-a-new-widget). This page is the
summary, how to see it on a fresh install, and its tests.

## Files

| Piece | Server-side widget | Asynchronous widget |
|---|---|---|
| Class | `modules/Tasks/Widgets/MyPendingTasks.php` (extends `Widget`) | `modules/Tasks/Widgets/TaskActivityChart.php` (extends `AsyncWidget`) |
| View | `modules/Tasks/Views/widgets/pending.php` | `modules/Tasks/Views/widgets/activity.php` (SVG, no inline style) |
| Permission | `tasks-board.view` (every group) | `tasks-tasks.list` (admin, editor) |
| Option | `limit` (1–20, default 5) | `days` (7–60, default 14) |
| Declared in | `$widgets` of `modules/Tasks/Config/Module.php` | same |

## The steps

1. **The class** extends `Modules\Core\Widgets\Widget`: `id()` (`<module>.<name>`), `title()`,
   `icon()`, `permission()`, `sizes()`, `settings()` (options with their rules) and `render()`
   ([step 1](../widgets.md#1-a-server-side-widget-the-class)). `render()` reads only what the
   current user may see (`TaskRepository`), never trusts the browser.
2. **Declare it** in the manifest and run `php spark modules:sync`; administrators then enable it
   per group and set its options in Dashboard › Group layouts; users rearrange their own dashboard
   ([step 2](../widgets.md#2-declaring-it-and-configuring-it-per-group)).
3. **An asynchronous widget** extends `AsyncWidget`: the dashboard prints a placeholder and the
   Core's `core/ui/widget` component fetches
   `backend/dashboard/widgets/tasks.activity/data` (HTML and data as JSON). That URL checks the
   same rule as the card: the permission **and** the widget enabled for one of the user's groups
   ([step 3](../widgets.md#3-an-asynchronous-widget)).
4. **Texts** in `Language/{ca,es,en}/Tasks.php` (`widgets.*`) ([step 4](../widgets.md#4-translations)).
5. **Failures are isolated**: an exception in `render()` shows a "could not load" card and is
   logged; the rest of the dashboard works ([step 5](../widgets.md#5-failures-are-isolated)).

## See it on a fresh install

```bash
php spark modules:enable Tasks
php spark modules:demo Tasks
php spark serve
```

Open the dashboard (`backend`) as `admin`: both cards are there (enable them in Dashboard › Group
layouts if your group layout hides new widgets). As a demo `user`, only *My pending to-do tasks*
appears, and `GET backend/dashboard/widgets/tasks.activity/data` answers 403.

## Tests

`modules/Tasks/Tests/WidgetsAndReminderTest.php` (base `TasksTestCase`, which extends the
Dashboard's `DashboardTestCase` with its helpers `cards()` and `setGroupWidget()`):

- `testMyPendingToDoTasksShowsOnlyTheUsersOpenToDoTasks`
- `testTheActivityChartIsAsynchronousAndNeedsItsPermission` (403 without the permission, the JSON
  data and SVG with it, no inline style, and 403 again once the widget is hidden for the group)

A test case that shows widgets lists their modules in `$widgetModules`
(`protected array $widgetModules = ['Core', 'Dashboard', 'Users', 'Tasks'];` in
`modules/Tasks/Tests/TasksTestCase.php`).

```bash
vendor/bin/phpunit modules/Tasks/Tests/WidgetsAndReminderTest.php
```

Next: [tutorial 5](05-public-page.md), a page of the public site.
