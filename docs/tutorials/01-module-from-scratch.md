# Tutorial 1 — A complete module from scratch with kpcrudy

You will build the core of the `Tasks` module: projects, to-do tasks with a project (a 1-N
relation) and assignees (an N-N relation), filters, and a custom operation `complete` with its
own permission. The finished code is in `modules/Tasks`; because that module already exists, you
build it here as **`Tasks2`** in a development checkout.

What you need: a development install ([`README.md`](README.md), "Prerequisites") and the kpcrudy
reference `vendor/siensis/kpcrudy/docs/functions_EN.md` (`setRelation`, `setRelationNN`,
`filters`, `addOperation`).

## 1. Generate the module

```bash
php spark make:module Tasks2 --crud=tasks2_tasks
```

This writes `modules/Tasks2/` (manifest, routes, a `main` page with an ES-module component,
ca/es/en texts, seeders, a widget and a scheduled-task stub, tests and `CLAUDE.md`; the full list
is in [`creating-a-module.md`](../creating-a-module.md#1-generate-it)) and, because of `--crud`:

- a migration for the table `tasks2_tasks` with example columns, **already migrated**;
- the resource `tasks` (type `crud`) in the manifest, so the permissions will be
  `tasks2-tasks.<operation>`;
- `Controllers/Tasks.php`, written by `kpcrudy:make` and rewritten to the Core's way
  (`$this->crud('tasks2-tasks')` … `$this->renderCrud($crud)`), and its route
  `CrudFactory::routes(…, 'tasks2-tasks', 'backend/tasks2/tasks', …)`.

The example table is not the one we want. It was migrated in a batch of its own, so roll it back
before changing anything:

```bash
php spark migrate:rollback -f        # undoes the last batch: the tasks2_tasks example table
```

> Never edit a migration once it has been **released**; until then (this tutorial) you may. A
> released module changes its schema with a new migration (tutorial 13).

## 2. One migration per table

Replace the generated migration with three files in `modules/Tasks2/Database/Migrations/`, one per
table, in creation order (the date prefix orders them). Each has a `down()` that undoes its `up()`.

`2026-11-17-000001_CreateTasks2Projects.php` (the "1" side):

```php
final class CreateTasks2Projects extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('tasks2_projects');
    }

    public function down(): void
    {
        $this->forge->dropTable('tasks2_projects', true);
    }
}
```

`2026-11-17-000002_CreateTasks2Tasks.php`: the to-do tasks, with `project_id` (the 1-N relation),
`deleted_at` (kpcrudy's trash) and `completed_at` (set by the `complete` operation):

```php
$this->forge->addField([
    'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
    'project_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
    'title'        => ['type' => 'VARCHAR', 'constraint' => 190],
    'description'  => ['type' => 'TEXT', 'null' => true],
    'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'todo'],
    'due_on'       => ['type' => 'DATE', 'null' => true],
    'created_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
    'completed_at' => ['type' => 'DATETIME', 'null' => true],
    'created_at'   => ['type' => 'DATETIME', 'null' => true],
    'updated_at'   => ['type' => 'DATETIME', 'null' => true],
    'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
]);
$this->forge->addPrimaryKey('id');
$this->forge->addKey(['status', 'due_on']);
$this->forge->addKey('project_id');
$this->forge->addForeignKey('project_id', 'tasks2_projects', 'id', '', 'SET NULL');
$this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
$this->forge->createTable('tasks2_tasks');
```

`2026-11-17-000003_CreateTasks2Assignees.php`: the junction table of the N-N relation:

```php
$this->forge->addField([
    'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
    'task_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
    'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
]);
$this->forge->addPrimaryKey('id');
$this->forge->addUniqueKey(['task_id', 'user_id']);
$this->forge->addKey('user_id');
$this->forge->addForeignKey('task_id', 'tasks2_tasks', 'id', '', 'CASCADE');
$this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
$this->forge->createTable('tasks2_assignees');
```

Forge and the Query Builder only: no raw SQL. The finished module has the same tables (plus
labels, priority, visibility and attachments added in later tutorials):
`modules/Tasks/Database/Migrations/2026-11-17-100001_CreateTaskProjects.php` …
`2026-11-17-100004_CreateTaskAssignees.php`.

## 3. The manifest: resources, extra operation, grants, menu

Permissions are never written by hand: they come from `$resources` in
`modules/Tasks2/Config/Module.php`. Add the projects and the extra operation `complete`:

```php
public array $resources = [
    'main' => ['label' => 'Tasks2.res.main', 'type' => 'page'],
    'tasks' => [
        'label'  => 'Tasks2.res.tasks',
        'type'   => 'crud',
        'except' => ['import'],
        'extra'  => ['complete' => 'Tasks2.perm.complete'],
    ],
    'projects' => [
        'label'  => 'Tasks2.res.projects',
        'type'   => 'crud',
        'except' => ['import', 'trash', 'restore', 'purge'],
    ],
];
```

A `crud` resource gives `list`, `show`, `add`, `edit`, `delete`, `export`, `import`, `trash`,
`restore` and `purge`; `except` removes some, `extra` adds operations (lower case only). The
result is `tasks2-tasks.complete` and the rest ([`permissions.md`](../permissions.md)).

Who gets what on a new installation (`defaultGrants`, applied once when the module is installed;
afterwards administrators use the permission matrix):

```php
public array $defaultGrants = [
    'admin'  => ['tasks2-main.*', 'tasks2-tasks.*', 'tasks2-projects.*'],
    'editor' => [
        'tasks2-main.view',
        'tasks2-tasks.list', 'tasks2-tasks.show', 'tasks2-tasks.add', 'tasks2-tasks.edit', 'tasks2-tasks.complete',
        'tasks2-projects.list', 'tasks2-projects.show',
    ],
];
```

Menu entries point at a resource; each is shown only to users with its `list` (or `view`)
permission ([`menus.md`](../menus.md)):

```php
public array $menu = [
    ['resource' => 'main', 'label' => 'Tasks2.menu.main', 'icon' => 'bi bi-box', 'order' => 100],
    ['resource' => 'tasks', 'label' => 'Tasks2.menu.tasks', 'icon' => 'bi bi-check2-square', 'parent' => 'main', 'order' => 10],
    ['resource' => 'projects', 'label' => 'Tasks2.menu.projects', 'icon' => 'bi bi-folder2', 'parent' => 'main', 'order' => 20],
];
```

The finished manifest, with every other key used by the later tutorials, is
`modules/Tasks/Config/Module.php`.

## 4. Routes

`Config/Routes.php` already has the to-do task CRUD. Add the projects the same way (the route name
is the resource; `CrudFactory::routes()` registers every CRUD URL with its `can:` filter):

```php
\Modules\Core\Crud\CrudFactory::routes($routes, 'tasks2-projects', 'backend/tasks2/projects', '\Modules\Tasks2\Controllers\Projects::index');
```

Routes never query the database, and never `require` another file.

## 5. The projects CRUD

`Controllers/Projects.php`: a plain CRUD (tutorial 2 makes its name translated):

```php
final class Projects extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('tasks2-projects');
        $crud->setTable('tasks2_projects')
            ->setSubject(lang('Tasks2.project'), lang('Tasks2.projects'))
            ->fieldType('active', 'boolean')
            ->columns(['name', 'active'])
            ->fields(['name', 'active'])
            ->setRules(['name' => 'required|max_length[120]'])
            ->setDefault('active', 1)
            ->displayAs(['name' => lang('Tasks2.fields.name'), 'active' => lang('Tasks2.fields.active')]);

        return $this->renderCrud($crud);
    }
}
```

`$this->crud('<resource>')` returns a kpcrudy `Crud` wired to the Core: the permission resolver
(every button and every POST checked against `tasks2-projects.*`), masked ids, the activity log
(`AuditedCrud`) and the theme's layout. Never `new Crud()`.

## 6. The to-do tasks CRUD: relations, filters and a custom operation

`Controllers/Tasks.php`:

```php
$crud = $this->crud('tasks2-tasks');
$me   = (int) auth()->id();

$crud->setTable('tasks2_tasks')
    ->setSubject(lang('Tasks2.toDoTask'), lang('Tasks2.toDoTasks'))
    // 1-N: each to-do task belongs to one project.
    ->setRelation('project_id', 'tasks2_projects', 'name', ['where' => ['active' => 1], 'order' => 'name'])
    // N-N: the assignees, through the junction table tasks2_assignees.
    ->setRelationNN('assignees', 'tasks2_assignees', 'users', 'task_id', 'user_id', 'username', ['where' => ['active' => 1, 'deleted_at' => null], 'order' => 'username'])
    ->setRelation('created_by', 'users', 'username')
    ->fieldType('status', 'select', ['options' => self::statuses()])
    ->fieldType('description', 'textarea', ['attrs' => ['rows' => 5]])
    ->fieldType('due_on', 'date')
    ->fieldType('completed_at', 'readonly')
    ->columns(['title', 'project_id', 'status', 'due_on', 'assignees'])
    ->fields(['title', 'description', 'project_id', 'status', 'due_on', 'assignees'])
    ->showFields(['title', 'description', 'project_id', 'status', 'due_on', 'assignees', 'created_by', 'completed_at', 'created_at', 'updated_at'])
    ->searchFields(['title', 'description'])
    ->filters(['project_id', 'status', 'assignees', 'due_on'])
    ->setRules([
        'title'       => 'required|max_length[190]',
        'description' => 'permit_empty|max_length[5000]',
        'due_on'      => 'permit_empty|valid_date[Y-m-d]',
    ])
    ->setDefault('status', 'todo')
    ->displayAs(self::labels())
    ->beforeInsert(static fn (array $data): array => ['created_by' => $me] + $data)
    ->defaultOrder('id', 'desc');
```

- `setRelation()` shows a select of projects (only active ones can be chosen) and the project's
  name in the list. `setRelationNN()` adds the virtual field `assignees`: a multiple select, the
  names in the list, and the junction rows rewritten on save.
- `filters()` opens the "Filters" panel: a select per relation, the statuses, a date range.
- `created_by` is not a form field: `beforeInsert()` sets it from the signed-in user, so a forged
  value is never saved.
- The labels are language keys (`self::labels()` maps each field to `lang('Tasks2.fields.<field>')`):
  no text is hard-coded.

The custom operation is a row button and a POST route that kpcrudy checks against the resource's
`complete` permission before running the callback:

```php
$crud->addOperation('complete', static function (string $id): string {
    db_connect()->table('tasks2_tasks')->where('id', (int) $id)->update([
        'status'       => 'done',
        'completed_at' => Time::now()->toDateTimeString(),
    ]);

    return lang('Tasks2.completedOk');
}, [
    'label'      => lang('Tasks2.complete'),
    'icon'       => 'bi bi-check2-circle',
    'class'      => 'btn-outline-success',
    'confirm'    => lang('Tasks2.completeConfirm'),
    'permission' => 'complete',
    'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') !== 'done',
]);
```

`'permission' => 'complete'` must be declared in the manifest (`extra`): in development an
undeclared operation fails, and `permissions:check` reports it. `visible` only hides the button
for done to-do tasks; the permission is what protects the POST. The Core logs the operation in
the activity log.

The finished version, `modules/Tasks/Controllers/Tasks.php`, differs in two ways you will meet in
later tutorials: the project is a select of **translated** names
(`fieldType('project_id', 'select', ['options' => (new TaskRepository())->projectNames()])`,
tutorial 2) and `complete` calls `TaskRepository::setStatus()`, which also keeps the calendar in
step (tutorial 6).

## 7. Texts in three languages

Every label is a key in `Language/{ca,es,en}/Tasks2.php`, with the same keys in the three files
(`tests/unit/I18n/LanguageKeysTest.php` fails otherwise). The generated files already have
`menu`, `res`, `perm` and `settings`; add the new keys, for example in English:

```php
'menu'  => ['main' => 'Tasks2', 'tasks' => 'Tasks', 'projects' => 'Projects'],
'res'   => ['main' => 'Tasks2', 'tasks' => 'Tasks', 'projects' => 'Projects'],
'perm'  => ['complete' => 'Complete to-do tasks'],
// …
'toDoTask'        => 'To-do task',
'toDoTasks'       => 'To-do tasks',
'project'         => 'Project',
'projects'        => 'Projects',
'complete'        => 'Complete',
'completeConfirm' => 'Mark this to-do task as done?',
'completedOk'     => 'To-do task completed.',
'fields'   => ['title' => 'Title', 'description' => 'Description', 'project_id' => 'Project', /* … */],
'statuses' => ['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done'],
```

Do not reuse the generated key `task` (it is the scheduled-task stub's
`task.description`): the wording rule of the project calls these records "to-do tasks".

## 8. Sync and check

```bash
php spark modules:sync           # runs the 3 migrations, adds the permissions, applies the grants
php spark permissions:check      # manifests, catalogue, grants, every route has its filter
php spark permissions:list --module=Tasks2
```

Expected: `Modules\Tasks2: 3 migrations run`, `Installed Tasks2 0.1.0`, the 18 permissions of the
module (with `tasks2-tasks.complete` granted to admin and editor) and "Permissions OK".

Sign in as an administrator and open `backend/tasks2/tasks`: add a project, then a to-do task
with a due date and two assignees. The list shows the project name and the assignees, the
"Filters" panel filters by them, and the green button completes the to-do task.

## Tests

Write the tests next to the module, in `modules/Tasks2/Tests/Tasks2ModuleTest.php` (generated with
a permission test, a forged POST and the migrations up/down/up). Add one test that a user without
`tasks2-tasks.complete` cannot complete by posting directly, and one that a user with it can:

```php
public function testCompleteNeedsItsOwnPermission(): void
{
    $this->loginWith('tasks2-tasks.list', 'tasks2-tasks.show', 'tasks2-tasks.edit');
    db_connect()->table('tasks2_tasks')->insert(['title' => 'Revisar', 'status' => 'todo']);
    $id   = (int) db_connect()->insertID();
    $code = \Kpcrudy\Crud::maskId($id, 'tasks2_tasks');
    $list = 'backend/tasks2/tasks';

    $this->assertCrudRefused($this->send('POST', $list . '/complete/' . $code), $list, 'complete');
    $this->assertSame('todo', db_connect()->table('tasks2_tasks')->where('id', $id)->get()->getRow()->status);
}
```

```bash
vendor/bin/phpunit modules/Tasks2
```

The finished module's tests cover the same ground in `modules/Tasks/Tests/TasksModuleTest.php`:

- `testManifestIsValidAndTheModuleIsInstalledDisabled`
- `testScreensNeedTheirPermissions`, `testEveryGroupHasTheBoardAndAdminsEverything`
- `testAReadOnlyUserCannotWriteEvenWithForgedPosts` (add, edit, delete, `complete` and the bulk
  action posted without permission; nothing changes)
- `testCompleteNeedsItsOwnPermission`, `testTheCompleteOperationAndBulkAction`
- `testMigrationsGoDownAndUpAgain`

When you are done with the scratch module: `php spark modules:uninstall Tasks2 --yes`, delete
`modules/Tasks2` and run `php spark modules:sync`.

Next: [tutorial 2](02-extending-a-crud.md) extends this CRUD.
