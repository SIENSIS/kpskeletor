# Tutorial 15 — Generating a module with the code generator

Tutorials 1 and 2 build the core of the `Tasks` module by hand. Here you generate a module very
like it — **`Tasks2`**: projects with a translated name, and to-do tasks with a project, a status,
a priority, a rich-text description, an estimate hidden without a permission, a custom operation
`complete`, the own-records rule and an API — from a **definition**, with the code generator of
the Devtools module ([`devtools.md`](../devtools.md#the-code-generator)). Then you compare what it
wrote with `modules/Tasks` and refine it.

What you need: a development install ([`README.md`](README.md), "Prerequisites") with
`CI_ENVIRONMENT = development` in `.env`, and the development packages (`composer install`).

## 1. Open the code generator

```bash
php spark modules:enable Devtools   # development only; installed disabled
php spark serve                     # http://localhost:8080 (Devtools answers only localhost)
```

Sign in as a **superadmin** and open *Configuració › Desenvolupament › Eines de
desenvolupament › Generador de codi* (`backend/devtools/generator`).

## 2. Describe the module

You can fill the steps by hand, or load the finished definition of this tutorial,
[`files/tasks2.definition.json`](files/tasks2.definition.json): in **Definition (JSON)** choose the
file (or paste it) and press **Load**. Go through the steps to see what each part means:

1. **Module:** a new module `Tasks2`, its description in three languages, the dependency on Auth.
2. **Table and columns:** `tasks2_projects` (name *translated*, colour, deadline, active) and
   `tasks2_todos` (title, description, `project_id` → `tasks2_projects` with ON DELETE SET NULL,
   status and priority as lists of values, due date, estimate as a decimal) with the trash. Try
   unticking *Nullable* on `project_id`: the SET NULL rule turns red at once, and **Check** gives
   the same error from the server.
3. **Demo data:** 3 projects and 8 to-do tasks; each column has a Faker generator (or the
   automatic one).
4. **CRUD:** the list columns, *rich text* for the description, the **own records** rule (users see
   and change only their to-do tasks unless they have `tasks2-todos.all`), the estimate *hidden
   without* `estimates`, and the custom operation `complete` with its confirmation. The *Recipe*
   links open the CRUD recipe of each option.
5. **API:** a kpapy resource `api/v1/tasks2/todos` with the CRUD's permissions as scopes.
6. **Menu and permissions:** the icons and the default grants (editors complete to-do tasks,
   users list, add and edit their own). The permission names are listed under the matrix:
   `tasks2-todos.list` … `tasks2-todos.complete`, `tasks2-todos.estimates`, `tasks2-todos.all`.

## 3. Preview and write

Press **Preview**: every file of the plan is listed in a tree, new files with their whole content
(the migrations, `Controllers/Todos.php`, `Controllers/Api/Todos.php`, the demo seeder, the tests…).
Read `Controllers/Todos.php`: it is the same kind of code as tutorial 1's, with the options you
chose (`setTranslations()` on the projects, `hideFields()` for the estimate, `where('created_by', …)`
for the rule, `addOperation('complete', …)`).

Press **Write**, tick *Load the demo data*: the password is asked again, the files are written,
`php spark modules:sync` and `php spark modules:demo Tasks2` run, and the result page shows the log.
`backend/tasks2/todos` is the new CRUD.

The same from the console, with the same engine:

```bash
php spark make:module --definition=docs/tutorials/files/tasks2.definition.json --dry-run
php spark make:module --definition=docs/tutorials/files/tasks2.definition.json
php spark modules:demo Tasks2
```

## 4. Refine it

The generator stops where your module's own logic starts. Compare with `modules/Tasks`:

- `complete` only returns a message: write what it does (tutorial 1 sets the status to `done`
  through a repository) and its test.
- Assignees are an N-N relation and labels a `multiselect`: the generator does not write them;
  add them as in tutorials 1 and 2 (a migration of their own, `setRelationNN()`).
- Add a table to the same module later with **Existing module**: the manifest, routes, language
  files and demo seeder are changed and shown as a diff; migrations are only added. To change a
  table that exists, write a new migration (tutorial 13).
- Update `modules/Tasks2/CLAUDE.md` (purpose, decisions) and run `/check-module Tasks2`.

When you are done, remove the scratch module: `php spark modules:uninstall Tasks2` and delete
`modules/Tasks2`.

## Tests

The generated module brings its own tests: `vendor/bin/phpunit modules/Tasks2` checks the screens'
permissions, forged posts, adding a row, the own-records rule, the hidden estimate, the API's
token and scopes, and the migrations up/down/up. The generator itself is covered by
`modules/Core/Tests/GeneratorEngineTest.php` (`testThePlanMatchesItsFixture`),
`modules/Core/Tests/GeneratedModuleChecksTest.php`
(`testTheGeneratedModulePassesTheChecksAndItsOwnTests`: a generated module passes `php -l`,
php-cs-fixer, PHPStan, `csp:check` and its own tests) and
`modules/Devtools/Tests/GeneratorWizardTest.php` (`testThePlanIsWrittenSyncedAndLogged`,
`testAFileChangedAfterThePreviewRefusesTheWrite`).

```bash
vendor/bin/phpunit modules/Core/Tests/GeneratorEngineTest.php modules/Devtools/Tests/GeneratorWizardTest.php
```

Back to the [index](README.md).
