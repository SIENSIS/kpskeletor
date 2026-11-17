# Tutorial 11 — A JSON API for third parties

Expose the to-do tasks and projects to other programs (a mobile app, an ERP, a script) with
**kpapy**, under `api/v1/tasks/…`, with **the same permissions as the back office**: a token can
do the intersection of its scopes and its user's permissions. You will see the generator, the
`$api` declaration, filters and includes, a custom operation, personal tokens with scopes, the
app login with a second factor, the documentation page and the OpenAPI document.

Finished code:

| Piece | File |
|---|---|
| To-do tasks resource | `modules/Tasks/Controllers/Api/Tasks.php` (`api/v1/tasks/tasks`, `setResource('tasks-tasks')`) |
| Projects resource | `modules/Tasks/Controllers/Api/Projects.php` (`api/v1/tasks/projects`, translated) |
| Routes | `modules/Tasks/Config/Routes.php` (`Resource::routes(…, ['filter' => 'kpapy'])`) |
| Declaration | `$api` in `modules/Tasks/Config/Module.php` |

References: [`api.md`](../api.md) and `vendor/siensis/kpapy/docs/functions_EN.md`.

## 1. Generate the resource

For a **new** module, the generator writes the API resource together with the CRUD:

```bash
php spark make:module Books --crud=books_items --api
```

It runs `kpapy:make` for the table, writes `Controllers/Api/<Key>.php` with
`setResource('books-items')` (the CRUD's resource name), the route
`api/v1/books/items` with the `kpapy` filter, the `$api` entry and `Tests/BooksApiTest.php`
(tokens and scopes).

For a module that **already exists** (like `Tasks2` from tutorial 1), call the library command
with the same options the generator passes:

```bash
php spark kpapy:make tasks2_tasks --namespace 'Modules\Tasks2' --controller Api/Tasks \
    --route api/v1/tasks2/tasks --resource tasks2-tasks --filter kpapy --extends 'CodeIgniter\Controller'
```

It writes `modules/Tasks2/Controllers/Api/Tasks.php` and appends the route to the module's
`Config/Routes.php`:

```php
\Kpapy\Resource::routes($routes, 'api/v1/tasks2/tasks', '\\Modules\\Tasks2\\Controllers\\Api\\Tasks::index', ['filter' => 'kpapy']);
```

Then make the controller follow the project's conventions (`declare(strict_types=1);`, a `final`
class) and declare the resource in the manifest:

```php
/** API resources (tutorial 11, kpapy). */
public array $api = [
    'tasks'    => ['path' => 'tasks/tasks', 'handler' => '\Modules\Tasks\Controllers\Api\Tasks::index'],
    'projects' => ['path' => 'tasks/projects', 'handler' => '\Modules\Tasks\Controllers\Api\Projects::index'],
];
```

Rules: every API path is under `api/v1/<module>/…`; every route has the `kpapy` filter (or is
declared public); `setResource()` is the CRUD's resource, never a new permission set.
`php spark permissions:check` refuses an `api/v1` route without its filter.

## 2. The resource

```php
$api = (new Resource())
    ->setTable('tasks')
    ->setResource('tasks-tasks')
    ->softDeletes()
    ->setRelation('project_id', 'task_projects', ['slug', 'color'], ['as' => 'project'])
    ->setRelationNN('assignees', 'task_assignees', 'users', 'task_id', 'user_id', ['username'], ['where' => ['active' => 1, 'deleted_at' => null]])
    ->fieldType('status', 'select', ['options' => TasksCrud::options('statuses', TaskRepository::STATUSES)])
    ->fieldType('labels', 'multiselect', ['options' => TasksCrud::options('labels', TaskRepository::LABELS)])
    ->fieldType('due_on', 'date')
    ->fieldType('estimate_hours', 'decimal', ['scale' => 2])
    ->fieldType('files', 'virtual')
    ->callbackField('files', static fn ($value, array $row): array => AttachmentUploads::files(is_string($row['attachments'] ?? null) ? $row['attachments'] : null))
    ->fields(['id', 'title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility', 'files', 'started_at', 'completed_at', 'created_at', 'updated_at'])
    ->listFields(['id', 'title', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'visibility', 'completed_at'])
    ->writeFields(['title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility'])
    ->requiredFields(['title'])
    ->filters(['status', 'priority', 'visibility', 'project_id', 'labels', 'assignees'])
    ->searchFields(['title', 'description'])
    // … rules, defaults, hooks and the operation below
```

- **The same permissions.** `setResource('tasks-tasks')`: `GET` needs `tasks-tasks.list` /
  `show`, `POST` `add`, `PATCH` `edit`, `DELETE` `delete` (or `trash` with `softDeletes()`), the
  operation `complete` its own permission. The shared resolver of the Core (`CrudPermissions`)
  decides, as in the back office.
- **Ids** are the same masked codes as in the back office (`app.maskKey`).
- **`writeFields()`** is an allow-list: `created_by`, `completed_at` or `attachments` posted by a
  client are ignored. `files` is read-only (attach files in the back office).
- **Filters and includes:** `?filter[status]=todo&filter[labels]=bug`, `?q=poster`,
  `?include=project,assignees`, `?sort=-due_on`, `?per_page=50`.
- **Hidden by permission**, like the CRUD: `$api->hideFields(['estimate_hours'])` when the user
  lacks `tasks-tasks.estimates`.
- **Hooks** do what the CRUD's hooks do: `beforeInsert()` sets `created_by` from the token's user,
  `afterWrite()` syncs the calendar and notifies new assignees (tutorials 2 and 6). Keep that
  logic in services (`TaskRepository`, `Assignees`, `DueDates`) so the CRUD and the API share it.

## 3. A custom operation

```php
->addOperation('complete', static function (?string $id, ?array $row, Resource $api): array {
    if (! is_array($row) || ($row['status'] ?? '') === 'done') {
        $api->fail(lang('Tasks.api.alreadyDone'));
    }

    (new TaskRepository())->setStatus((int) $row['id'], 'done');

    return ['status' => 'done'];
}, ['description' => lang('Tasks.api.complete')])
```

`POST api/v1/tasks/tasks/{id}/complete` checks `tasks-tasks.complete` (declared in the manifest
in tutorial 1) before calling it, and answers 404 for records the user cannot see.

## 4. Tokens and scopes

**Personal tokens.** Profile › My API tokens (`backend/profile/api-tokens`, re-authentication):
a name, scopes and an expiry; the secret is shown once. From the console:

```bash
php spark kpapy:token admin@example.com --name "Tutorial" --scopes "tasks-tasks.*" --days 7
php spark kpapy:token admin@example.com --name "Read only" --scopes "tasks-tasks.list" --days 7
```

Scopes are permission names, `<resource>.*` or `*`. A token never exceeds its user: a
`tasks-tasks.*` token of an editor cannot delete, because editors cannot.

```bash
TOKEN=…   # the secret printed by kpapy:token
curl -sg 'http://localhost:8080/api/v1/tasks/tasks?include=project,assignees&filter[status]=todo' \
    -H "Authorization: Bearer $TOKEN"
curl -s -X POST http://localhost:8080/api/v1/tasks/tasks -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/json' -d '{"title": "From the API", "status": "doing"}'
curl -s -X POST "http://localhost:8080/api/v1/tasks/tasks/<id>/complete" -H "Authorization: Bearer $TOKEN"
```

(`-g` stops curl from reading `[` and `]` as a pattern.) Expected: `200` with `data`, `meta` and
`links`; `201` with the new to-do task (its `started_at` set, because it starts in "doing");
`200 {"data": {"status": "done"}}`. With the read-only token the `POST` answers
`403 forbidden`; without a token, `401 unauthorized` (problem+json bodies).

**App login with a second factor.** An app signs in with `POST api/v1/auth/token` (email,
password, device name), receives a challenge, and exchanges it with the emailed or authenticator
code at `POST api/v1/auth/token/verify` for a token with the user's permissions
([`api.md`, "App login with a second factor"](../api.md#app-login-with-a-second-factor)).

## 5. The documentation page and the OpenAPI document

`modules:sync` writes the OpenAPI document of every declared resource
(`writable/api/openapi.json`, never served). Settings › **API documentation**
(`backend/api/docs`, permission `api.docs`) shows it: paste a token, open
`GET /api/v1/tasks/tasks`, fill the filters, **Send**, and **Copy as curl**. The document itself
is downloadable at `backend/api/docs/openapi.json` for Postman or code generators; `describe()`
gives each resource its summary and description in the user's language.

## Tests

`modules/Tasks/Tests/TasksApiTest.php` (base `Modules\Api\Tests\ApiTestCase`: `tokenFor($user,
$scopes)`, `api($method, $path, $body, $token)`):

- `testATokenLimitedToListCanListButNotWrite` (the same masked id as the back office; `POST`,
  `PATCH`, `DELETE` and `complete` refused with 403; no token 401)
- `testTheUsersPermissionsAreTheCeiling` (a `*` token of a user without the permission: 403)
- `testWritesFiltersIncludesAndTheCompleteOperation` (`created_by` from the token and a forged one
  ignored, the new assignee notified and the due date in the calendar, filters and includes,
  `started_at`/`completed_at` following the status, `complete` and its 409 when already done)
- `testTheEstimateFollowsItsPermission`
- `testProjectsInTheRequestedLanguage` (`?lang=ca|es|en|*`)

```bash
vendor/bin/phpunit modules/Tasks/Tests/TasksApiTest.php
```

Next: [tutorial 12](12-public-form.md), a public form with email.
