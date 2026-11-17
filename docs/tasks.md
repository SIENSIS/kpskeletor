# Tasks module (tutorial module)

The `Tasks` module is a small manager of **to-do tasks**: projects, to-do tasks, assignees, due
dates and labels. It holds the finished code of every [tutorial](tutorials/README.md), so each
feature of the skeleton has a small, working example. Its own notes, with a
table "tutorial → files", are in `modules/Tasks/CLAUDE.md`.

> Wording (D-00-56): "to-do task" is a record of this module; "scheduled task" is a job of the
> scheduler. The module's `Tasks/` folder holds its **scheduled tasks**, as in every module.

## Trying it

The module is installed **disabled** (`$enabledByDefault = false`, D-32-01):

```bash
php spark modules:enable Tasks        # or Modules › Enable
php spark modules:demo Auth           # demo users (if not loaded yet)
php spark modules:demo Tasks          # two projects, eight to-do tasks, a request
```

Then: `backend/tasks` (board), `backend/tasks/tasks`, `backend/tasks/projects`,
`backend/tasks/requests`, the dashboard widgets, the calendar (`backend/calendar`, "To-do task due
dates" and "Project deadlines"), the public page `tasks`, the form `tasks/request`, and the API
(`backend/api/docs`).

## What it contains

| Piece | Where |
|---|---|
| Version | 0.2.0 ([`CHANGELOG.md`](../modules/Tasks/CHANGELOG.md); tutorial 13 added `tasks.started_at`) |
| Tables | `task_projects`, `task_project_translations`, `tasks`, `task_assignees`, `task_attachments`, `task_requests` (one migration each; `tasks.started_at` added by a 0.2.0 migration) |
| CRUDs (kpcrudy) | `tasks-tasks` (`backend/tasks/tasks`), `tasks-projects` (`backend/tasks/projects`), `tasks-requests` (`backend/tasks/requests`) |
| Board | `tasks-board.view`: `backend/tasks`, `GET backend/tasks/board/data`, `POST backend/tasks/board/{code}/move`; `Assets/js/board.js` |
| Widgets | `tasks.pending` (server, `tasks-board.view`), `tasks.activity` (asynchronous SVG chart, `tasks-tasks.list`) |
| Scheduled task | `OverdueReminder`: daily at 07:30, `withoutOverlapping()`, once a day per to-do task |
| Notifications | `TaskAssigned` (new assignees), `TaskOverdue` (reminder), `TaskRequested` (reviewers) |
| Calendars | `tasks.due` (push: due dates of open to-do tasks, assignees and creator), `tasks.projects` (pull provider: project deadlines, `tasks-projects.list`, hidden by default) |
| Public site | `tasks`, `tasks/project/{slug}` (public to-do tasks only), menu link type `tasks.project`, form `tasks/request` |
| Files | attachments on the private disk, `modules/tasks`, `AttachmentPolicy` (`FileAccessPolicy`) |
| API (kpapy) | `api/v1/tasks/tasks` (`tasks-tasks`, operation `complete`), `api/v1/tasks/projects` (`tasks-projects`) |
| Settings | `Tasks.reminderGraceDays` (0–30), `Tasks.publicRequests` (on) |
| Child theme | `themes/tasks-demo` (child of `default`): colour, logo, CSS and an override of the public page |

## Permissions

`tasks-<resource>.<operation>`, generated from the manifest:

- `tasks-tasks.*`: `list`, `show`, `add`, `edit`, `delete`, `export`, `trash`, `restore`,
  `purge`, `complete` (operation and bulk action), `estimates` (see and write the estimate);
- `tasks-projects.*`: `list`, `show`, `add`, `edit`, `delete`, `export`;
- `tasks-board.view`;
- `tasks-requests.*`: `list`, `show`, `delete`, `export`, `accept`, `reject`.

Default grants: `admin` everything; `editor` to-do tasks list/show/add/edit/complete, projects
list/show and the board; `user` and `superadmin` the board.

## Rules

- **Who sees a to-do task on the board and in the widget:** with `tasks-tasks.list`, every one;
  otherwise those assigned to the user. **Who moves it:** `tasks-tasks.edit` or an assignee; a
  to-do task the user cannot see answers 404, one they see but may not move 403 (logged as
  `move_refused`).
- **Public:** only `visibility = public`, not in the trash, in an active project or none.
- **Attachments:** files of a public to-do task are public; of a private one, for
  `tasks-tasks.show`, its creator and its assignees; a file not attached yet only for its
  uploader. Refused is a 404.
- **Assignees:** new assignees are notified (never the user who made the change); a user who loses
  the board (`privilegesChanged`) is removed from the open to-do tasks.
- **Request form:** always a captcha (Auth's local captcha, image at `tasks/request/captcha`),
  `ratelimit:forms`, validation, a `TaskRequested` notification to `tasks-requests.list`, a
  confirmation email in the visitor's language (Core `MailQueue`), and 404 while
  `Tasks.publicRequests` is off. Accepting a request creates a private to-do task.

## Tests

`vendor/bin/phpunit modules/Tasks`: `TasksModuleTest` (manifest, CRUD permissions with forged
POSTs, `complete`, hidden estimate, `afterWrite`, translated projects, migrations up/down/up),
`BoardTest`, `WidgetsAndReminderTest`, `PublicSiteTest`, `AttachmentsTest`,
`CalendarAndEventsTest`, `TasksApiTest`, `ChildThemeTest`.
