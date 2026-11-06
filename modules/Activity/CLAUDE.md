# Activity module

**Purpose:** the read-only screens of the activity and security log: the whole log for
administrators and "My activity" for every user. The log itself (table `activity_log`,
`ActivityLogger`, `activity_log()`/`security_log()`, `AuditedCrud`, retention task) is the Core's
and keeps being written while this module is disabled. Full description: `docs/activity.md`.

## Resources and permissions

- `activity-log` (crud): `list`, `show`, `export`, `network` (IP addresses and user agents).
  Default grants: admin `activity-log.*`. URL `backend/activity`.
- `activity-mine` (crud): `list`, `show`, granted to every group (D-00-17). URL
  `backend/activity/mine` (registered before the full log's routes).
- No `add`, `edit`, `delete`: the log is append-only (`readOnly()`); only the Core's
  `PurgeActivityLog` deletes entries (`Core.activityRetentionDays`).
- Menu: "Activity log" in the `settings` zone, "My activity" in the `user` zone.
- No tables, settings, tasks, assets or outbound hosts of its own; no demo data (entries come
  from real actions).

## Main pieces

- `Controllers/Activity.php`: both CRUDs (`configure()`), action labels (`actionLabel()`:
  `<Module>.activity.<action>`, then `Activity.actions.<action>`), the before/after table
  (`changesTable()`), `hideFields()` without `activity-log.network`.

## Decisions

D-12-01 … D-12-05 in `docs/decisions.md` (D-12-04, the retention setting's name, is open).

## Open tasks

- Session 16: a "latest security events" widget could read `activity_log` (`type = security`).
- Session 26: an API resource for the log if needed (kpapy, same resource names).

## How to test

`vendor/bin/phpunit modules/Activity` (`ActivityScreensTest`: screens, permissions, append-only,
export; `ActivityEventsTest`: the events of sessions 05–11). The logger itself:
`modules/Core/Tests/ActivityLoggerTest.php`.
