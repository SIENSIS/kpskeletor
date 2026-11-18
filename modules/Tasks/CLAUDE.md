# Tasks module (tutorial module)

> Read this file first when you resume work on this module, and keep it current. Project rules:
> root `CLAUDE.md`; steps: `docs/creating-a-module.md`; run `/check-module Tasks` before calling
> it done.

**Purpose:** a small manager of to-do tasks (projects, to-do tasks, assignees, due dates, labels)
that holds **the finished code of every tutorial** in `docs/tutorials/` (index: `docs/tutorials/README.md`).
It is installed **disabled** (`$enabledByDefault = false`, D-32-01): enable it in Modules (or
`php spark modules:enable Tasks`) and load `php spark modules:demo Tasks`. Full description:
`docs/tasks.md`.

**Wording (D-00-56):** "to-do task" for this module's records, "scheduled task" for the
scheduler's jobs. Never just "task" in texts or docs. The folder `Tasks/` holds the module's
**scheduled tasks** (`Modules\Tasks\Tasks\…`).

## Resources and permissions

- `tasks-tasks` (crud − `import`; extras `complete` (operation and bulk action), `estimates`
  (see/write `estimate_hours`)): every to-do task, `backend/tasks/tasks`.
- `tasks-projects` (crud − `import`, trash): projects (translated name/description), `backend/tasks/projects`.
- `tasks-board` (page: `view`): the board `backend/tasks` (+ `board/data`, `board/{code}/move`).
- `tasks-requests` (crud − `add`, `edit`, `import`, trash; extras `accept`, `reject`): requests
  from the public form, `backend/tasks/requests`.
- API (same resources, same permissions): `api/v1/tasks/tasks` (`tasks-tasks`, operation
  `complete`), `api/v1/tasks/projects` (`tasks-projects`).
- Grants: admin everything; editor tasks list/show/add/edit/complete, projects list/show, board;
  user and superadmin board.
- Settings `Tasks.reminderGraceDays` (0–30), `Tasks.publicRequests` (bool). Menu "To-do tasks"
  (board) with "All to-do tasks", "Projects", "Requests". Widgets `tasks.pending`,
  `tasks.activity`. Scheduled task `Tasks/OverdueReminder.php`. Notifications `TaskAssigned`,
  `TaskOverdue`, `TaskRequested`. Link type `tasks.project`. Calendars `tasks.due` (push) and
  `tasks.projects` (pull, `tasks-projects.list`, hidden by default).
- Version 0.2.0 (`CHANGELOG.md`). Tables (one migration each): `task_projects`, `task_project_translations`, `tasks`,
  `task_assignees`, `task_attachments`, `task_requests`; 0.2.0 adds `tasks.started_at`. Files: `modules/tasks` (private disk).

## Where each tutorial lives

| Tutorial | Files |
|---|---|
| 1 Module + CRUD with relations, custom operation | `Config/Module.php`, `Database/Migrations/*`, `Controllers/Tasks.php` (`setRelationNN` assignees, project select, filters, `addOperation('complete')`), `Language/*` |
| 2 Translated field, multiselect, hideFields, afterWrite, bulk action | `Controllers/Projects.php` (`setTranslations`), `Controllers/Tasks.php` (`labels` multiselect, `hideFields(['estimate_hours'])`, `afterWrite`, `addBulkAction('complete')`) |
| 3 View with JavaScript | `Controllers/Board.php`, `Views/board/*`, `Assets/js/board.js`, `Assets/css/tasks.css` |
| 4 Widgets | `Widgets/MyPendingTasks.php` (server), `Widgets/TaskActivityChart.php` (async), `Views/widgets/*` |
| 5 Public page + menu link | `Controllers/PublicTasks.php`, `Views/public/index.php`, `Menu/ProjectLink.php` |
| 6 Talking to other modules | `Notifications/TaskAssigned.php` + `Services/Assignees.php`, `Calendar/DueDates.php` (push), `Calendar/ProjectDeadlines.php` (pull), `Config/Events.php` (`privilegesChanged`) |
| 7 Scheduled task | `Tasks/OverdueReminder.php`, `Notifications/TaskOverdue.php` |
| 8 Setting | `$settings` in the manifest; read in `OverdueReminder`, `PublicTasks`, `RequestForm` |
| 9 Child theme | `themes/tasks-demo/` (child of `default`, overrides `Tasks/public/index.php`) |
| 10 Uploads | `Storage/AttachmentUploads.php` (picker, private disk), `Storage/AttachmentPolicy.php` (`FileAccessPolicy`, registered in `Config/Events.php`) |
| 11 JSON API | `Controllers/Api/Tasks.php`, `Controllers/Api/Projects.php`, `$api`, `Config/Routes.php` |
| 12 Public form with email | `Controllers/RequestForm.php`, `Views/public/request.php`, `Services/TaskRequests.php`, `Views/emails/request_received.php`, `Controllers/Requests.php` |
| 13 Releasing a version | `Database/Migrations/2026-11-18-100001_AddStartedAtToTasks.php` (0.2.0: `tasks.started_at`, filled in for started to-do tasks), `TaskRepository::statusDates()`, `$version = '0.2.0'`, `CHANGELOG.md` |
| 14 Tests | `Tests/*` |

## Main pieces

- `Services/TaskRepository.php`: every read and the shared rules (who sees a to-do task on the
  board: `tasks-tasks.list` or assignee; who moves it: `tasks-tasks.edit` or assignee; what is
  public), `setStatus()` and `statusDates()` (keep `started_at`/`completed_at` and the calendar in step).
- `Services/Assignees.php` (notify new assignees, drop users without board access),
  `Services/TaskRequests.php` (public form: store, notify, confirmation email through the Core's
  `MailQueue` in the visitor's language; accept/reject).
- The public form always asks for Auth's captcha (its image at `tasks/request/captcha`, so it
  works while `Auth.captcha` is off) and has `ratelimit:forms`.

## Decisions

D-32-01 (`$enabledByDefault` manifest key, decided by the owner as built), D-32-02 … D-32-05 (accepted;
`docs/decisions.md`); D-00-56 (naming), D-00-32 (`themes/tasks-demo`).

## Open tasks

- A change to a tutorial's code must update its text in `docs/tutorials/` (excerpts are copied
  from the code; `tests/unit/Docs/TutorialsTest.php` checks the files and test names they cite).
- Demo data removal leaves the published calendar events of the demo to-do tasks until
  `php spark calendar:resync Tasks`.

## How to test

```bash
vendor/bin/phpunit modules/Tasks      # base Tests/TasksTestCase.php (+ TasksFixtures)
php spark permissions:check
```

By hand: `php spark modules:enable Tasks`, `php spark modules:demo Auth`, `php spark modules:demo
Tasks`, then `backend/tasks` (board), `backend/tasks/tasks`, `tasks` (public page),
`tasks/request` (form), `backend/api/docs` (API documentation page).
