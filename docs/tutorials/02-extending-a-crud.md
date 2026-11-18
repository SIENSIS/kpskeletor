# Tutorial 2 — Extending an existing CRUD

Tutorial 1 gave you a working CRUD. Here you add what most real screens need:

1. a **translated field** (the project's name and description in Catalan, Spanish and English);
2. a **`multiselect`** (labels stored in one column);
3. a field **hidden by permission** with `hideFields()` (the estimate);
4. an **`afterWrite()` hook** that runs after every write;
5. a **bulk action** whose permission is declared in the manifest.

Finished code: `modules/Tasks/Controllers/Projects.php` (1) and
`modules/Tasks/Controllers/Tasks.php` (2–5). Reference: the kpcrudy sections "Translations",
"Field types" (`multiselect`), `hideFields`, "Callbacks" (`afterWrite`) and `addBulkAction` in
`vendor/siensis/kpcrudy/docs/functions_EN.md`.

## 1. A translated field

Translated texts live in a second table, one row per record and language. The Tasks module keeps
the project's language-independent columns in `task_projects` (slug, colour, deadline, active)
and the texts in `task_project_translations`
(`Database/Migrations/2026-11-17-100002_CreateTaskProjectTranslations.php`):

```php
$this->forge->addField([
    'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
    'project_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
    'locale'      => ['type' => 'VARCHAR', 'constraint' => 10],
    'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
    'description' => ['type' => 'TEXT', 'null' => true],
]);
$this->forge->addPrimaryKey('id');
$this->forge->addUniqueKey(['project_id', 'locale']);
$this->forge->addForeignKey('project_id', 'task_projects', 'id', '', 'CASCADE');
$this->forge->createTable('task_project_translations');
```

In the CRUD, `setTranslations()` names that table, its two key columns, the translated fields and
the languages. The site's languages come from the Core (`ContentTranslations::LOCALES`), never a
hard-coded list:

```php
$crud->setTable('task_projects')
    ->setSubject(lang('Tasks.project'), lang('Tasks.projects'))
    ->setTranslations('task_project_translations', 'project_id', 'locale', ['name', 'description'], ContentTranslations::LOCALES, [
        'required'  => ['ca'],
        'types'     => ['name' => 'text', 'description' => 'textarea'],
        'rules'     => ['name' => 'required|max_length[120]', 'description' => 'permit_empty|max_length[2000]'],
        'fallbacks' => ['*' => ['ca']],
    ])
    ->columns(['name', 'color', 'ends_on', 'active'])
    ->fields(['name', 'description', 'slug', 'color', 'ends_on', 'active'])
```

- The form shows one tab per language; only Catalan is required (the default language,
  `docs/i18n.md`); empty languages are not stored.
- `fallbacks` shows the Catalan name in the list when the user's language has none.
- `name` and `description` are used in `columns()`, `fields()` and `searchFields()` like any other
  field.
- The form posts `kp_tr[<locale>][<field>]`; `$crud->postedTranslations()` gives them to a hook.
  The Tasks module uses it to build the slug from the Catalan name:

```php
->beforeInsert(static fn (array $data): array => self::slug($data, null, (string) ($crud->postedTranslations()['ca']['name'] ?? '')))
```

Other screens read the names through `TaskRepository::projectNames()` (the request language,
then Catalan, then the slug). That is why the finished to-do task form shows the project as a
`select` of those names instead of tutorial 1's `setRelation()`:

```php
->fieldType('project_id', 'select', ['options' => (new TaskRepository())->projectNames()])
```

## 2. A `multiselect`

Labels are a fixed list, so they need no table: kpcrudy stores the chosen values as JSON in one
column (`tasks.labels`, a `VARCHAR(255)`). The values are constants, the texts are language keys:

```php
// Services/TaskRepository.php
public const LABELS = ['bug', 'feature', 'docs', 'design', 'support'];

// Controllers/Tasks.php
->fieldType('labels', 'multiselect', ['options' => self::options('labels', TaskRepository::LABELS)])
->filters(['project_id', 'status', 'priority', 'labels', 'assignees', 'visibility'])
```

`self::options('labels', …)` returns `['bug' => lang('Tasks.labels.bug'), …]`. Values that are not
in the list are dropped on save, so a forged `labels[]=unknown` is never stored. Use
`setRelationNN()` instead when the values are records of another table (tutorial 1's assignees).

## 3. A field hidden by permission

The estimate in hours is for planners only. Declare a permission for it as an `extra` of the
resource (`Config/Module.php`):

```php
'tasks' => [
    'label'  => 'Tasks.res.tasks',
    'type'   => 'crud',
    'except' => ['import'],
    'extra'  => [
        'complete'  => 'Tasks.perm.complete',
        'estimates' => 'Tasks.perm.estimates',
    ],
],
```

and hide the field for everybody else, **after** configuring the CRUD:

```php
// Tutorial 2: the estimate exists only for users with `tasks-tasks.estimates`.
if (! $this->can('tasks-tasks.estimates')) {
    $crud->hideFields(['estimate_hours']);
}
```

`hideFields()` removes the field from the list, the details, both forms, search, filters, sorting
and the CSV export, and ignores it in a POST: a forged `estimate_hours=99` is not saved. That
server-side rule is what matters; not showing the input is a consequence. The API (tutorial 11)
does the same with kpapy's `hideFields()`.

## 4. An `afterWrite()` hook

`afterWrite()` runs after **every** write of the CRUD: add, edit, delete, trash, restore, purge,
custom operations and bulk actions, with the operation name and the record id. It is the right
place for side effects that must never be forgotten:

```php
// Tutorial 2: one hook after every write (form, bulk actions, trash): the calendar,
// the attachments index and the notification to the users just assigned.
->afterWrite(function (string $operation, string $id) use ($me): void {
    $taskId = (int) $id;
    DueDates::sync($taskId);

    if ($operation === 'insert' || $operation === 'update') {
        AttachmentUploads::sync($taskId);
        Assignees::notifyNew($taskId, $this->assigneesBefore[$taskId] ?? [], $me);
    }
})
```

- `DueDates::sync()` publishes or removes the calendar event (tutorial 6): a trashed to-do task
  leaves the calendar because the hook also runs on `trash`.
- `Assignees::notifyNew()` needs the assignees *before* the change; `beforeUpdate()` stores them
  in `$this->assigneesBefore` (see `beforeSave()` in the controller).
- `AttachmentUploads::sync()` mirrors the attachments for their access policy (tutorial 10).

The activity log is **not** written here: the Core's `AuditedCrud` already logs every CRUD write
and operation.

`beforeInsert()`/`beforeUpdate()` change the data before it is saved (they may also return a
string, an error shown to the user); `afterWrite()` is for what happens after.

## 5. A bulk action with its permission

A bulk action runs on the rows ticked in the list. Its permission is an operation of the
resource, declared in the manifest like any other (here the same `complete` as the row
operation of tutorial 1):

```php
// Tutorial 2: a bulk action with its permission declared in the manifest.
$crud->addBulkAction('complete', lang('Tasks.complete'), static function (array $ids): string {
    $repository = new TaskRepository();
    $rows       = db_connect()->table('tasks')->select('id')->whereIn('id', array_map('intval', $ids))->where('status !=', 'done')->get()->getResultArray();

    foreach ($rows as $row) {
        $repository->setStatus((int) $row['id'], 'done');
    }

    return lang('Tasks.bulkCompleted', [count($rows)]);
}, ['permission' => 'complete', 'confirm' => lang('Tasks.completeConfirm')]);
```

kpcrudy offers the action only to users with `tasks-tasks.complete`, and passes to the callback
only the ids that exist, are inside the CRUD's `where()` and on which the user holds the
permission, whatever the browser posted. The return value is shown as the message;
`Tasks.bulkCompleted` has a `{0}` placeholder in the three language files.

After changing the manifest, run `php spark modules:sync` (new permissions) and
`php spark permissions:check`.

> **Existing installations.** `defaultGrants` are applied once, when a module is installed. A
> permission you add later (like `tasks-tasks.estimates`) must be granted in the permission
> matrix (`backend/users/permissions`) on sites where the module was already installed.

## Tests

`modules/Tasks/Tests/TasksModuleTest.php`:

- `testTheEstimateIsHiddenWithoutItsPermission`: the field is in neither the form nor the details,
  and a forged estimate is not saved;
- `testTheEstimateIsShownWithItsPermission`;
- `testAddingAToDoTaskSetsTheCreatorNotifiesAssigneesAndPublishesTheDueDate`: the multiselect
  keeps only known labels (`['design', 'unknown']` → `["design"]`), the `afterWrite()` hook
  notifies the new assignee (never the user who assigned) and publishes the due date; editing
  without changing the assignees notifies nobody again; completing removes the event;
- `testTheCompleteOperationAndBulkAction` and `testAReadOnlyUserCannotWriteEvenWithForgedPosts`
  (the bulk action posted without the permission changes nothing);
- `testProjectsHaveTranslatedNamesAndAUniqueSlug`: only filled-in languages are stored, the
  Catalan name is required, slugs are unique.

```bash
vendor/bin/phpunit modules/Tasks/Tests/TasksModuleTest.php
```

Next: [tutorial 3](03-view-with-javascript.md), a screen with JavaScript.
