# Session 34 — Tutorials

Branch: `claude/fervent-gauss-9ng4h3` · Pull request: (see `docs/progress.md`) · Date: 2026-11-17

## What was built

- **The 14 tutorials** of the specification in `docs/tutorials/`, each built on the `Tasks`
  tutorial module and each ending with a **Tests** section naming the test methods that cover it,
  plus the index `docs/tutorials/README.md` (what each one teaches, prerequisites, recommended
  order, how to follow them in a scratch module `Tasks2`):

  | # | File | Topic |
  |---|---|---|
  | 1 | `01-module-from-scratch.md` | `make:module`, one migration per table, manifest, CRUD with 1-N and N-N relations, filters, `complete` operation, `modules:sync` |
  | 2 | `02-extending-a-crud.md` | translated field, `multiselect`, `hideFields()` by permission, `afterWrite()`, bulk action |
  | 3 | `03-view-with-javascript.md` | the board (summary of and links to `frontend.md`) |
  | 4 | `04-widget.md` | the two widgets (summary of and links to `widgets.md`) |
  | 5 | `05-public-page.md` | public route, `FrontendController`, SEO `meta()`, menu link type |
  | 6 | `06-talking-to-other-modules.md` | notification type, calendar push and pull, `privilegesChanged` listener |
  | 7 | `07-scheduled-task.md` | `OverdueReminder`: schedule, `withoutOverlapping()`, result, idempotency |
  | 8 | `08-setting.md` | `$settings`, rules, `setting()`, the generated Settings tab |
  | 9 | `09-child-theme.md` | `make:theme`, option defaults, theme CSS, a module view override |
  | 10 | `10-file-uploads.md` | the file picker, private disk, `FileAccessPolicy` |
  | 11 | `11-json-api.md` | kpapy resource, `$api`, filters/includes, custom operation, tokens and scopes, docs page |
  | 12 | `12-public-form.md` | captcha, rate limit, validation, notification, email in the visitor's language |
  | 13 | `13-releasing-a-version.md` | schema-change migration, version, changelog, ZIP, signing, `modules:install`, update notice |
  | 14 | `14-writing-tests.md` | forged POSTs, feature tests, migrations up/down/up, a JS component's endpoints, API scopes |

- **Tutorial 13's code: Tasks 0.2.0.** A new migration `AddStartedAtToTasks` adds
  `tasks.started_at` (when a to-do task left "To do") and fills it in for to-do tasks already
  started; the rule `TaskRepository::statusDates()` (both dates) is shared by the CRUD form, the
  board / `complete` / bulk action (`setStatus()`) and the API; the date is shown in the details,
  the export and the API; demo data has it; `modules/Tasks/CHANGELOG.md`; `$version = '0.2.0'`.
- **`tests/unit/Docs/TutorialsTest.php`** (5 tests): the index lists the 14 tutorials in order;
  each ends with "Tests"; every `test…` method a tutorial names exists; every repository file a
  tutorial (or the index) names exists; `modules/Tasks/CLAUDE.md` maps tutorial 13.
  `DocumentationTest` already checks links, anchors, `php spark` commands and URLs of
  `docs/tutorials/*.md`.
- Small fixes found while writing: the Tasks settings groups had no translated headings
  (`Tasks.settingGroups.*`, ca/es/en); the Tasks settings test now posts an invalid value (422,
  nothing stored) and a valid one; the API test checks `started_at`/`completed_at` and that a
  field outside `writeFields()` is ignored.
- Links to the tutorials from `README.md`, `docs/creating-a-module.md` and `docs/tasks.md`;
  root `CHANGELOG.md` (Unreleased › Tutorials).

## How it was built

- Every excerpt was copied from the code of `modules/Tasks` (or from the scratch module
  `Tasks2` for tutorial 1), and every command was run.
- **Tutorial 1 followed on a fresh install** (a git worktree of the branch, its own vendor
  autoloader, `php spark app:install --env=development --db=sqlite --demo --no-interaction` with
  `INSTALL_ADMIN_PASSWORD`): `make:module Tasks2 --crud=tasks2_tasks` → `migrate:rollback -f`
  (the example table) → three migrations, manifest (`complete` extra, projects resource, grants,
  menu), routes, two CRUDs, texts → `modules:sync` ("3 migrations", "18 permissions",
  "Installed Tasks2 0.1.0") → `permissions:check` OK → added two tests (forged `complete`,
  `complete` with permission) → `phpunit modules/Tasks2`: OK (7 tests, 22 assertions) → in the
  browser: a project, a to-do task with two assignees, the list with project, assignees and the
  `complete` button (screenshots 01–02).
- **Tutorial 3 followed on the same install:** `modules:enable Tasks`, `modules:demo Tasks`, the
  board as `admin`: a card dragged from "To do" to "In progress" (`POST …/move` 200, the card in
  the new column, no console or CSP error, no horizontal scroll at 1280 px and 390 px dark)
  (screenshots 03–05); the moved to-do task's details show "Started on" (screenshot 06).
- **Tutorial 11 followed on `Tasks2`:** `kpapy:make tasks2_tasks …` with the generator's options
  (route appended to the module's `Routes.php`), the controller made `final`/strict with
  relations, filters, `writeFields()` and a `complete` operation, `$api` entry, `modules:sync`,
  `permissions:check` OK; tokens from `kpapy:token`: list with `include` and `filter[status]`
  (200), `POST` with a `tasks2-tasks.list` token (403), `complete` (200), create (201), no token
  (401). Also `make:module Tasks3 --crud=tasks3_tasks --api` checked for new modules.
- **Tutorial 13 followed:** a second worktree at `main` (Tasks 0.1.0) installed, Tasks enabled
  with Auth and Tasks demo data; `zip -r Tasks-0.2.0.zip Tasks -x '*/.*'` from the branch;
  `php spark modules:install Tasks-0.2.0.zip --yes --no-backup` → "1 migrations run",
  "Updated Tasks from 0.1.0 to 0.2.0", "Tasks 0.2.0 installed"; the demo rows "doing"/"done" got
  `started_at`, the "to do" ones did not:

  ```
  Definir el mapa del web | done | 2026-11-05 20:03:23 | 2026-11-17 20:03:23
  Maquetar la pàgina d'inici | doing | 2026-11-06 20:03:23 | -
  Migrar les notícies antigues | todo | - | -
  ```

  Signing was not run: `minisign` is not installable here (as in sessions 28–29); the steps are
  the ones of `docs/creating-a-module.md` §9.

## Decisions and deviations

- **D-34-01** (accepted): tutorial 13's release is Tasks 0.2.0 with `tasks.started_at`; ZIP built
  by hand (no packager command, D-32-05); the module declares no `$repository`, so the update
  notice is described, not triggered.
- **D-34-02** (accepted): tutorials 3 and 4 summarise and link the walk-throughs of
  `frontend.md` and `widgets.md` instead of copying them; the board was not extended (D-33-02
  allowed it; nothing needed it).
- **D-34-03** (accepted): tutorials 1 and 11 are followed in a scratch module `Tasks2`
  (`migrate:rollback -f` of the generator's example table; `kpapy:make` for an existing module);
  the finished module differs where later tutorials change it (translated project select).

No (decide) entries.

## How to try it

```bash
php spark modules:enable Tasks
php spark modules:demo Auth
php spark modules:demo Tasks
php spark serve
```

Read `docs/tutorials/README.md` and follow any tutorial; `backend/tasks` (board),
`backend/tasks/tasks` (details show "Started on"), `tasks`, `tasks/request`, `backend/api/docs`.

```bash
vendor/bin/phpunit modules/Tasks tests/unit/Docs
```

## Tests and checks

Full suite (`vendor/bin/phpunit`): `OK (1305 tests, 17612 assertions)` in 9:31 (session 33: 1288
tests; new: `TutorialsTest` 5, `TasksModuleTest` 2; the Tasks settings and API tests extended).

```
vendor/bin/phpunit modules/Tasks       OK (51 tests) before the extensions; all pass in the suite
vendor/bin/phpunit tests/unit/Docs     OK (13 tests, 320 assertions)
scratch Tasks2: phpunit modules/Tasks2 OK (7 tests, 22 assertions)
```

PHPStan (level 6): `[OK] No errors`. php-cs-fixer: `Found 0 of 996 files that can be fixed`.
`php spark csp:check`: no inline scripts, handlers or styles. `php spark permissions:check`
(scratch install with Tasks2 and Tasks3): `Permissions OK: 198 permissions, 382 routes checked`.

## Screenshots

| File | Caption |
|---|---|
| `screenshots/01-tutorial1-task-form.png` | Tutorial 1, scratch module `Tasks2`: the to-do task form with the project select (1-N) and assignees (N-N) |
| `screenshots/02-tutorial1-task-list.png` | Tutorial 1: the list with project, status, due date, assignees and the `complete` row button |
| `screenshots/03-tutorial3-board.png` | Tutorial 3 on a fresh install: the board with the Tasks demo data |
| `screenshots/04-tutorial3-board-after-move.png` | Tutorial 3: a card dragged from "To do" to "In progress" |
| `screenshots/05-tutorial3-board-phone-dark.png` | Tutorial 3: the board at phone width in dark mode (no horizontal scroll) |
| `screenshots/06-tutorial13-started-on.png` | Tutorial 13: the details of the moved to-do task with the new "Started on" date |

## Known issues

- `minisign` could not be installed in this environment: the signing step of tutorial 13 is
  documented, not run (signature checks are covered by `PackagesTest`).
- `make:module --api` works only for a new module with `--crud`; for an existing module the
  tutorial uses `kpapy:make` directly (D-34-03).
- `make:module --crud` migrates the example table immediately; changing it before the first
  release needs `migrate:rollback -f` (tutorial 1 says so).
- A git worktree with `vendor/` symlinked to another checkout loads that checkout's
  `modules/` (Composer's `Modules\` PSR-4 path is resolved from the real vendor path): give a
  worktree its own `vendor/composer/` (noted for anyone repeating these checks).
- Still open from session 33 (unchanged): `CoreCache` invalidation wording, the ca/es forms of
  address, no automatic Files trash purge, `defaultGrants` not applied to permissions an upgrade
  adds (tutorials 2 and 13 say so).
- Composer needed the known issue 04 workaround again (`env -u COMPOSER_AUTH composer install
  --prefer-source`, `phpstan/phpstan`'s zip seeded into the cache from a shallow clone of tag
  2.3.5; `composer.json`/`composer.lock` unchanged).

## Notes for the next session

- Session 35 (quality): `tests/unit/Docs/TutorialsTest.php` and `DocumentationTest` keep the
  tutorials in step; a change to the Tasks code must update the tutorial that quotes it
  (`modules/Tasks/CLAUDE.md`, "Open tasks"). The Playwright walk-throughs of this session
  (`scratchpad` scripts, not committed) can become e2e specs: board drag and drop, the request
  form, the Tasks2 CRUD.
- Tasks is now 0.2.0; its migration count in `TasksModuleTest` is 7.
