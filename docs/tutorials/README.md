# Tutorials

Step-by-step guides for developers who extend the application. Each one builds a working piece of
the **`Tasks` tutorial module** (`modules/Tasks`): a small manager of to-do tasks with projects,
assignees, due dates and labels. The finished code of every tutorial is in the module, so you can
read it, run it and compare it with what you write. `modules/Tasks/CLAUDE.md` maps each tutorial
to its files and [`docs/tasks.md`](../tasks.md) describes the module as a whole.

> **Wording.** "To-do task" is a record of the `Tasks` module; "scheduled task" is a job of the
> scheduler (the module's `Tasks/` folder holds its scheduled tasks, as in every module).

## Prerequisites

- A development checkout of the skeleton (see "Installing" in [`README.md`](../../README.md)):

  ```bash
  composer install
  php spark app:install --env=development --db=sqlite --demo
  php spark serve
  ```

- The rules of the project in [`CLAUDE.md`](../../CLAUDE.md) and the guide
  [`creating-a-module.md`](../creating-a-module.md). The tutorials quote both and never repeat
  them in full.
- For tutorials 1, 2 and 11, the documentation of the two required libraries, read from
  `vendor/`: `vendor/siensis/kpcrudy/docs/functions_EN.md` and
  `vendor/siensis/kpapy/docs/functions_EN.md`.

## Trying the finished module

The module is installed **disabled** (`$enabledByDefault = false`). Enable it and load its demo
data:

```bash
php spark modules:enable Tasks
php spark modules:demo Auth           # demo users, if not loaded yet
php spark modules:demo Tasks          # two projects, eight to-do tasks, a request
```

Then sign in as a demo administrator and open `backend/tasks` (the board).

## Two ways to follow a tutorial

- **Read the finished code** in `modules/Tasks` while you read the tutorial (every excerpt is
  copied from it).
- **Build it yourself** in a scratch module. `modules/Tasks` already exists, so tutorials that
  start with `make:module` use the name `Tasks2` (tables `tasks2_…`, permissions
  `tasks2-…`). Delete the scratch module when you are done:
  `php spark modules:uninstall Tasks2 --yes`, then remove `modules/Tasks2` and run
  `php spark modules:sync`.

## The tutorials

| # | Tutorial | What it teaches | Needs |
|---|---|---|---|
| 1 | [A complete module from scratch with kpcrudy](01-module-from-scratch.md) | `make:module`, one migration per table, the manifest, a CRUD with a 1-N and an N-N relation, filters, a custom operation with its permission, `modules:sync` | — |
| 2 | [Extending an existing CRUD](02-extending-a-crud.md) | a translated field, a `multiselect`, `hideFields()` by permission, an `afterWrite()` hook, a bulk action declared in the manifest | 1 |
| 3 | [A new view with JavaScript](03-view-with-javascript.md) | a `page` resource, a view in the back-office layout, an ES module (`data-component`), a JSON endpoint, CSP | 1 |
| 4 | [A new widget](04-widget.md) | a server-side widget and an asynchronous one, options, per-group configuration | 1 |
| 5 | [A public page](05-public-page.md) | a public route, `FrontendController`, the public layout, SEO metadata, a menu link type | 1 |
| 6 | [Talking to other modules](06-talking-to-other-modules.md) | a notification type, the calendar (push and pull), listening to another module's event | 1, 2 |
| 7 | [A scheduled task](07-scheduled-task.md) | `TaskInterface`, `Schedule`, `withoutOverlapping()`, the result and output, its test | 6 |
| 8 | [A new setting](08-setting.md) | `$settings`, validation rules, `setting()`, the Settings screen | 7 |
| 9 | [A child theme](09-child-theme.md) | `make:theme`, colours and logo, overriding a module view, the theme's CSS | 5 |
| 10 | [Uploading files in a form](10-file-uploads.md) | the file-picker field, the private disk, a `FileAccessPolicy` | 1, 2 |
| 11 | [A JSON API for third parties](11-json-api.md) | `make:module --api`, kpapy resources, `$api`, filters and includes, a custom operation, tokens and scopes, the documentation page | 1 |
| 12 | [A public form with email](12-public-form.md) | a public form with captcha, validation, rate limit, a notification to reviewers, a confirmation email in the visitor's language | 5, 6 |
| 13 | [Releasing a new version of a module](13-releasing-a-version.md) | a schema-change migration, the version, `modules:sync`, a ZIP (signed), `modules:install`, the update notice | 1 |
| 14 | [Writing tests](14-writing-tests.md) | permission tests with forged POSTs, feature tests, migrations up/down/up, a JavaScript component's endpoint | any |
| 15 | [Generating a module with the code generator](15-generating-a-module.md) | Devtools' code generator: a definition, the preview, writing, `make:module --definition`, refining the generated code | 1, 2 |

**Recommended order:** 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8, then 9–13 in any order; 15 once 1 and 2 are done (it generates what they build by hand). Read 14 alongside
the others: every tutorial ends with the tests that cover it, and tutorial 14 explains how they
are built.

## Running the tests of the module

```bash
vendor/bin/phpunit modules/Tasks
```

The tests run on SQLite (no database to set up) and in CI with the rest of the suite. Each
tutorial ends with a **Tests** section naming the test methods that cover it.
