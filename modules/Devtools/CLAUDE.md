# Devtools module

> Read this file first when you resume work on this module, and keep it current (root `CLAUDE.md`,
> "Developing a module"). Full description: `docs/devtools.md`.

**Purpose:** development-only tools for building modules from the back office (D-41-01 …
D-41-03): the CRUD recipes (session 41), the code generator (session 42) and the package builder
(session 43). It must be impossible to use outside development or from another machine.

## Resources and permissions

- Single resource `tools` with the prefix override `devtools` (page + extra `write`):
  `devtools.view` (screens), `devtools.write` (anything that writes files or runs commands).
- Default grants: **none** (superadmins hold every permission; D-41-03).
- Menu: its own settings group `group.development` ("Desenvolupament", order 90, declared in the
  manifest; shown only while its entry is visible) with the entry `tools` (D-41-04); route name `devtools`.
- No tables, settings, widgets, tasks, notifications, API, CSP sources or outbound hosts.
- Manifest: `$developmentOnly = true`, `$enabledByDefault = false`, requires Core and Auth (`reauth`).

## The locks (never weaken one)

1. `Config/Routes.php`: routes only when `ENVIRONMENT === 'development'`, and for web requests
   only from the loopback with a local `Host` (D-41-05).
2. `$developmentOnly`: the Core's `DevelopmentOnly` rule in sync/enable/install/Modules screen.
3. `security:check` `installation.devtools` (Core `InstallationCheck`).
4. Filter `devtools` (`Filters/LocalOnly.php`, alias in `Config/Registrar.php`) on every route.
5. `Routing/DevtoolsRoutes.php`: `VIEW` = `devtools`, `can:devtools.view`; `WRITE` = `devtools`,
   `can:devtools.write`, `reauth`, `ratelimit:forms`; `DRAFT` = `devtools`, `can:devtools.view`
   only for the POSTs in `DRAFT_ROUTES` that change a session draft (D-42-02). Every route uses
   one of them (`DevtoolsLocksTest`).

## Main pieces

- `Routing/DevtoolsRoutes.php` (routes: `backend/devtools`, `recipes`, `docs/<page>`).
- `Controllers/Home.php` (tools + locks), `Recipes.php`, `Docs.php`; views `index`, `recipes`,
  `docs`, `_banner` (on every screen).
- `Recipes/RecipeBook.php` (cards: id, icon, source file, doc page + anchor, code; texts
  `Devtools.recipes.<id>.title|what|when`), `Recipes/DocPage.php` (docs as plain text with
  GitHub-style heading ids).
- `Assets/js/copy.js` (copy button, `data-component="devtools/copy"`).
- Code generator (session 42, `docs/devtools.md` "The code generator"): `Controllers/Generator.php`
  (`backend/devtools/generator`: form + buttons (DRAFT), `preview`, `definition.json`, `load` (DRAFT),
  `write` (WRITE), `result`), `Generator/WizardForm.php` (form ↔ definition, add/remove buttons),
  `Generator/Draft.php` (the definition in the session), `Generator/Diff.php` (line diff),
  `Generator/SparkRunner.php` (`modules:sync`, `modules:demo` in new processes), views
  `generator/{form,_table,preview,result}`, `Assets/js/generator.js`. The engine is the Core's
  (`Modules\Core\Generator`); the controller's `$testContext` / `$testRunner` are for tests only.

## Decisions

D-41-01 … D-41-03 (decided), D-41-04 (own menu group Desenvolupament), D-41-05 (routes not registered for
non-local requests; docs viewer); D-41-02 and D-42-01 (generator writes code; nikic/php-parser),
D-42-02 (DRAFT routes), D-42-03 (engine in the Core, Faker at generation time, session draft);
D-43-01 applies to session 43.

## Open tasks

- Generator: "alter" migrations for existing tables, N-N relations and multiselects (not
  generated yet; `docs/devtools.md`, "What it cannot generate").
- Session 43: package builder and signing (D-43-01).
- When a doc heading cited by a recipe changes, update `RecipeBook` (`RecipesTest` fails).

## How to test

```bash
vendor/bin/phpunit modules/Devtools                         # locks, filter, recipes, generator wizard
vendor/bin/phpunit modules/Core/Tests/Generator*Test.php modules/Core/Tests/GeneratedModuleChecksTest.php
vendor/bin/phpunit modules/Core/Tests/DevelopmentOnlyTest.php
```

The suite runs in `testing`, so the tests add the routes with `DevtoolsTestCase::withDevtoolsRoutes()`.
By hand: `.env` `CI_ENVIRONMENT = development`, `php spark modules:enable Devtools`,
`php spark serve`, sign in as a superadmin, `http://localhost:8080/backend/devtools`.
