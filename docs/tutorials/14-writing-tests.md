# Tutorial 14 — Writing tests

Every tutorial ended with its tests. This one explains how they are written, so you can write
the same ones for your module: a **permission test with a forged POST**, a **feature test** of a
page, a **migration up/down/up** test, and a test of a **JavaScript component's endpoint**.

The tests of the `Tasks` module are in `modules/Tasks/Tests/`; they run on SQLite (nothing to set
up) with the rest of the suite and in CI:

```bash
vendor/bin/phpunit modules/Tasks                      # the module
vendor/bin/phpunit modules/Tasks/Tests/BoardTest.php  # one file
vendor/bin/phpunit modules/Tasks --filter Forged      # matching test names
```

## 1. The base classes

| Class | Gives you |
|---|---|
| `Modules\Auth\Tests\AuthTestCase` | every module migrated, the permission catalogue and default grants synced, `createUser($email, $group)`, `login($email)`, `loginWith(...$permissions)` (a `user` with extra individual permissions, signed in), `send($method, $path, $params)` (session and CSRF token handled), `assertNotFound()`, `assertCrudRefused()` |
| `Modules\Dashboard\Tests\DashboardTestCase` | the above plus widgets: `$widgetModules`, `cards()`, `setGroupWidget()` |
| `Modules\Api\Tests\ApiTestCase` | `tokenFor($user, $scopes)`, `api($method, $path, $body, $token)`, `json($response)` |
| `Modules\Tasks\Tests\TasksTestCase` | the module's base: extends `DashboardTestCase` with `TasksFixtures` (`task()`, `project()`, `taskCode()`, `taskRow()`, `notificationsOf()`) and `body()` (the page as text) |

`make:module` writes `Tests/<Name>ModuleTest.php` on top of `AuthTestCase` with the first three
kinds of test below already in place; extend it as the module grows. Put fixtures in a trait
(`TasksFixtures`) and insert rows with the Query Builder: it is faster and clearer than going
through the forms.

## 2. A permission test with a forged POST

The back office hides buttons the user may not use, but a request can be forged. Test the
**server**: post directly to every write URL without the permission and check that nothing
changed.

```php
public function testAReadOnlyUserCannotWriteEvenWithForgedPosts(): void
{
    $this->loginWith('tasks-tasks.list', 'tasks-tasks.show');
    $id   = $this->task('Revisar', ['status' => 'todo']);
    $code = $this->taskCode($id);
    $list = 'backend/tasks/tasks';
    $rows = db_connect()->table('tasks')->countAllResults();

    $this->send('GET', $list)->assertOK();
    $this->assertCrudRefused($this->send('POST', $list . '/add', ['title' => 'Nova']), $list, 'add');
    $this->assertCrudRefused($this->send('POST', $list . '/edit/' . $code, ['title' => 'Canviada']), $list, 'edit');
    $this->assertCrudRefused($this->send('POST', $list . '/delete/' . $code), $list, 'delete');
    $this->assertCrudRefused($this->send('POST', $list . '/complete/' . $code), $list, 'complete');
    $this->assertCrudRefused($this->send('POST', $list . '/bulk', ['action' => 'complete', 'ids' => [$code]]), $list, 'bulk complete');

    $this->assertSame($rows, db_connect()->table('tasks')->countAllResults());
    $this->assertSame('Revisar', $this->taskRow($id)['title']);
    $this->assertSame('todo', $this->taskRow($id)['status']);
    $this->assertNull($this->taskRow($id)['deleted_at']);
}
```

- `loginWith()` grants **only** what the test names: start from too little and check the refusal.
- `assertCrudRefused()` checks kpcrudy's refusal (a redirect to the list with the "not allowed"
  message, no success message); a route with a `can:` filter answers **403**
  (`->assertStatus(403)`); a record the user may not see answers **404** (`assertNotFound()`).
- Always assert the **data** afterwards: a refusal message with a changed row is still a bug.
- Test each extra permission on its own: `testCompleteNeedsItsOwnPermission` gives `edit` but not
  `complete`.
- Hidden fields: post them and check they were not saved
  (`testTheEstimateIsHiddenWithoutItsPermission`).

## 3. A feature test of a page

Request the page as a real user and check what it shows **and what it does not**:

```php
public function testThePublicPageShowsOnlyPublicToDoTasks(): void
{
    $web    = $this->project('Nou web');
    $hidden = $this->project('Arxivat', ['active' => 0]);
    $this->task('Pública del web', ['visibility' => 'public', 'project_id' => $web, 'labels' => '["design"]']);
    $this->task('Privada del web', ['project_id' => $web]);
    $this->task('Pública d’un projecte inactiu', ['visibility' => 'public', 'project_id' => $hidden]);
    // …

    $page = $this->body('GET', 'tasks');
    $this->assertStringContainsString('Pública del web', $page);
    $this->assertStringNotContainsString('Privada del web', $page);
    $this->assertStringNotContainsString('projecte inactiu', $page);
    $this->assertStringContainsString('<link rel="canonical" href="' . site_url('tasks') . '"', $page);

    $this->assertNotFound('GET', 'tasks/project/arxivat');
}
```

- Compare with `lang('Tasks.…')` instead of literal texts: the tests run in English
  (`app.defaultLocale=en`), but a test that uses the key survives a change of wording.
- Check side effects where they land: rows, `notifications`, `mail_queue`, `activity_log`,
  `events` (`testAValidRequestIsStoredNotifiedAndConfirmedByEmail`).
- For pages that must not contain inline code (CSP), assert it on the HTML
  (`ChildThemeTest`: no `<script>` without a nonce, no `on…="`, no `style="`).
- Dates: `Time::setTestNow(…)` and reset it in the same test (`WidgetsAndReminderTest`).
- Settings: `setting()->set(…)` and `setting()->forget(…)` at the end.

## 4. Migrations up, down and up

Every migration must undo itself. The generated test loads the module's migrations in order,
runs every `down()` in reverse, checks the tables are gone, runs every `up()` again and checks
they are back:

```php
public function testMigrationsGoDownAndUpAgain(): void
{
    $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
    $migrations = [];

    foreach ($files as $file) {
        $class = 'Modules\Tasks\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

        if (! class_exists($class, false)) {
            require_once $file;
        }

        $migrations[] = new $class(Database::forge('tests'));
    }

    $this->assertCount(7, $migrations, 'Six tables (0.1.0) and the started_at column (0.2.0, tutorial 13).');

    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }

    $this->db->resetDataCache();
    // … assertFalse(tableExists()) for each table, then up() again and assertTrue(…)
}
```

Count the migrations explicitly: a new one makes the test fail until you look at it. A migration
that changes data (tutorial 13) gets its own test with rows before and after
(`testTheVersionMigrationAddsStartedAtFillsItAndGoesBack`).

## 5. A JavaScript component's endpoint

The skeleton has no JavaScript test runner: a component is tested through **its endpoints and the
markup it receives**, which is where the rules live. The board component (`tasks/board`) reads
`backend/tasks/board/data` and posts to `backend/tasks/board/{code}/move`:

```php
public function testAUserSeesOnlyTheToDoTasksAssignedToThem(): void
{
    $pau   = $this->createUser('pau@example.com', 'user');
    $other = $this->createUser('laia@example.com', 'user');
    $this->task('La meva', [], [(int) $pau->id]);
    $this->task('D’una altra persona', [], [(int) $other->id]);
    $this->login('pau@example.com');

    $page = $this->body('GET', 'backend/tasks');
    $this->assertStringContainsString('data-component="tasks/board"', $page);

    $data = json_decode((string) $this->send('GET', 'backend/tasks/board/data')->response()->getBody(), true);
    $this->assertSame(['La meva'], array_column($data['columns']['todo'], 'title'));
    $this->assertTrue($data['columns']['todo'][0]['canMove'], 'An assignee may move their to-do task.');
}
```

and the move endpoint is tested like any write, including forged requests
(`testForgedMovesAreRefused`: another user's card 404, a card seen but not movable 403 and a
`security` log entry, an unknown status 422, a `GET` never moves). Send
`Accept: application/json` with `withHeaders()` to get the JSON answers the script receives.

What the browser does is covered elsewhere: `modules/Core/Tests/FrontendAssetsTest.php` checks
every ES module (bare specifiers, no relative imports) and `php spark csp:check` the views; the
pages are checked by hand or with Playwright (`deploy-status/screenshot.mjs` fails on any CSP
violation or horizontal scroll).

## 6. API scopes

With `ApiTestCase`, test a token **limited** to one scope against every other operation, and a
`*` token of a user who lacks a permission (the user is the ceiling):

```php
$list = $this->tokenFor($boss, ['tasks-tasks.list']);
$this->api('GET', 'api/v1/tasks/tasks', null, $list)->assertStatus(200);
$this->api('POST', 'api/v1/tasks/tasks', ['title' => 'Nova'], $list)->assertStatus(403);
$this->api('GET', 'api/v1/tasks/tasks')->assertStatus(401);
```

## Checklist

The module checklist of [`CLAUDE.md`](../../CLAUDE.md) asks for: permissions (including forged
POSTs), migration up/down/up, API scopes and the module's own rules. Before committing:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
php spark permissions:check
```

`/check-module Tasks` (Claude Code) runs the checklist on a module.

## Tests

The tests of this tutorial are the module's whole suite:

| Tutorial | Test file |
|---|---|
| 1, 2, 13 | `modules/Tasks/Tests/TasksModuleTest.php` |
| 3 | `modules/Tasks/Tests/BoardTest.php` |
| 4, 7, 8 | `modules/Tasks/Tests/WidgetsAndReminderTest.php` |
| 5, 12 | `modules/Tasks/Tests/PublicSiteTest.php` |
| 6 | `modules/Tasks/Tests/CalendarAndEventsTest.php` |
| 9 | `modules/Tasks/Tests/ChildThemeTest.php` |
| 10 | `modules/Tasks/Tests/AttachmentsTest.php` |
| 11 | `modules/Tasks/Tests/TasksApiTest.php` |

`tests/unit/Docs/TutorialsTest.php` keeps these tutorials in step with the code: every tutorial
is in the index, ends with a **Tests** section, and every test method and module file it names
exists.

```bash
vendor/bin/phpunit modules/Tasks tests/unit/Docs
```

Back to the [index](README.md).
