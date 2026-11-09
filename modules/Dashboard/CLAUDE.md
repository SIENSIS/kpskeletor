# Dashboard module

**Purpose:** the back-office home (`backend`) with the widgets of the enabled modules, configured
per group and arranged per user. Core module (cannot be disabled, D-16-05): `backend`, where a login
lands, always exists; a user who wants an empty home hides their widgets. Full description: `docs/widgets.md`. The widget framework itself is the Core's
(`modules/Core/Widgets/`).

## Resources and permissions

- `dashboard` (page, `'name' => 'dashboard'`): `dashboard.view` (home, own layout, async data;
  granted to every group) and `dashboard.configure` (group layouts; `admin` gets `dashboard.*`).
- Menu: "Dashboard" (sidebar, order 1, route `dashboard`), "Dashboard widgets" (settings zone,
  route `dashboard-groups`).
- Tables: `dashboard_group_widgets` (group layouts), `dashboard_user_layouts` (one JSON row per
  user). No settings, demo data, outbound hosts or CSP sources. Asset: `Assets/js/dashboard.js`
  (`dashboard/dashboard`).

## Main pieces

- `Services/DashboardLayout.php`: group layouts (defaults when no row), available widgets of a
  user (permission **and** enabled for one of their groups), the user's layout and its reset.
- `Controllers/Dashboard.php` (home, no-JS customise form, save JSON/form, reset),
  `Controllers/WidgetData.php` (async data: 403 unless available, cached per user, 500 on
  failure), `Controllers/GroupLayout.php` (per-group configuration, activity log).
- Views: `dashboard.php`, `customize.php`, `groups.php`.

## Decisions

D-16-01 (names, URLs, login landing) decided by the owner as proposed; D-16-02 … D-16-04 accepted; D-16-05
(core module) and D-16-06 (quick links: free links, order set by the administrator; editor
`Views/links_editor.php` + `Assets/js/links-editor.js`) decided by the owner
(`docs/decisions.md`).

## Open tasks

- Widgets of later modules (News, Events, Updates, Backup, Security status) are added by their
  sessions; the tutorial widgets by sessions 32–34.
- Rate limit of the async URL is the Core's `widgets` limit; no per-widget limit.

## How to test

`vendor/bin/phpunit modules/Dashboard` (base `Tests/DashboardTestCase.php`: Auth fixture,
declarations, `addTestWidgets()` with a failing and an async counting widget, `cards()`,
`postJson()`; only the widgets of `$widgetModules` (Core, Dashboard, Users) are declared, so a new
module's widgets do not change the counts: its own tests extend the list, e.g. News); `modules/Core/Tests/WidgetsTest.php` for the framework.
