# Changelog of the Tasks module

The module's own versions (`$version` in `Config/Module.php`), newest first. Format:
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). How a version is released:
[`docs/tutorials/13-releasing-a-version.md`](../../docs/tutorials/13-releasing-a-version.md).

## 0.2.0

### Added

- `tasks.started_at`: when a to-do task left "To do" (set by the form, the board, the
  `complete` operation and the API; cleared when it goes back to "To do"). Shown in the details,
  the export and the API. Migration `AddStartedAtToTasks` (fills it in for to-do tasks already
  started).

## 0.1.0

### Added

- First version: projects, to-do tasks, the board, widgets, the overdue reminder, calendars,
  the public page and request form, attachments and the API (tutorials 1–12).
