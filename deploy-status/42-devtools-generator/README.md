# Session 42 — Devtools: visual code generator with preview

Branch: `claude/eloquent-archimedes-ljsp0q` (assigned by the environment) · Pull request:
[SIENSIS/kpskeletor#60](https://github.com/SIENSIS/kpskeletor/pull/60) · Date: 2026-11-20. Decisions: D-41-02 and D-42-01 applied; D-42-02, D-42-03
accepted (`docs/decisions.md`).

## What was built

- **Devtools › Code generator** (`backend/devtools/generator`, development only, superadmins, from
  localhost): a wizard that builds a module — or adds tables to an existing one — with
  1. the module (new: name, description ca/es/en, author, dependencies; or an existing module),
  2. tables and columns (portable types only: text with length, long text, integer, big integer,
     decimal(p,s), yes/no, date, date and time, JSON, list of values → VARCHAR + `in_list`;
     nullable, default, unique, index, foreign key with ON DELETE, translated → `<table>_translations`;
     timestamps, trash) and their labels in ca/es/en,
  3. demo data (rows, a Faker generator per column with a default from name/type),
  4. the CRUD (operations or read only; list/form/required/search/filter/file/rich-text/hidden
     matrix; record rule everyone / own rows / a column; custom operations with label, icon,
     confirmation and **re-authentication**; masked ids; each option links its CRUD recipe),
  5. the API (kpapy with the CRUD's resource, or public read-only),
  6. menu and default grants (zone, D-38-01 group, parent, icon, order; grants matrix per group
     with the generated permission names),
  7. a **preview** of every file (tree with *new*/*changed* badges, full content of new files,
     diff of changed ones), the JSON definition to download and load again,
  8. **write** (devtools.write + reauth): refused if a file changed after the preview; atomic
     writes; `modules:sync`, optionally `modules:demo`; activity log `Devtools/generated`; a
     result page with the log, next steps and the commands to run the generated tests.
- **One engine** in the Core (`modules/Core/Generator`), shared with
  `php spark make:module --definition=<file.json> [--dry-run]`. Plain `make:module` keeps its
  exact output (`ModuleGenerator::files()`; verified byte for byte against the pre-refactor
  generator, and by `testAModuleWithoutTablesIsMakeModulesOutput`).
- The generated module **passes its own tests**, `php -l`, php-cs-fixer, PHPStan, `csp:check` and
  `permissions:check` (checked in the test suite for three definitions, and by hand after writing
  `Library` from the UI).
- Tutorial 15 ("Generating a module with the code generator", a `Tasks2`-like module,
  `docs/tutorials/files/tasks2.definition.json`), docs and `CHANGELOG.md`.

## How it was built

**Core — `modules/Core/Generator/`** (the engine; no Devtools dependency):

| Class | Role |
|---|---|
| `Definition/{ModuleDefinition,TableDefinition,ColumnDefinition,CrudDefinition,ApiDefinition,MenuDefinition,Texts}` | the definition value objects, JSON in/out (`fromJson()`, `toJson()`) |
| `DefinitionValidator` | errors by path (`tables.0.columns.1.foreign.onDelete` => message, `Core.generator.errors.*`): names, prefixes, existing tables/resources, types, lengths (190 for indexed VARCHAR), defaults per type, enum values, translated columns, foreign keys (SET NULL on NOT NULL…), CRUD lists, custom operations, record rules, menu zone/group/parent/icon, grants against the resource's operations, dependencies |
| `Engine` | definition → `Plan` (`PlannedFile`: path, new content, content on disk); new module = `make:module`'s files minus the example table + every table applied like for an existing module (one code path) |
| `TableCode` + `Templates/*.tpl` | migrations (+ translations), CRUD controller, API controller (private or public), uploads handler, CRUD/API tests, manifest entries, routes, language values, demo seeder method, `CLAUDE.md` line |
| `SourceEditor` | nikic/php-parser: finds the array/property/method in the existing file and splices only that text (keeps comments and alignment; never overwrites keys) |
| `DemoValues` | Faker values computed at generation time, written as literals (D-42-03) |
| `CodeFormatter` | php-cs-fixer on the plan (so the preview is what is written) |
| `PlanWriter` | fingerprint check, on-disk hash check, temp file + rename, rollback, `ModuleNamespaces.php` |
| `PhpExport`, `Code`, `GeneratorContext`, `GeneratorException`, `InvalidDefinition` | helpers |

`modules/Core/Commands/MakeModule.php`: `--definition`, `--dry-run`. `modules/Core/Modules/ModuleGenerator.php`:
`files()` (the plan of `make:module`), `generate()` writes it. Language: `Core.generator.*`,
`Core.make.*` (ca/es/en).

**Devtools — `modules/Devtools/`:** `Controllers/Generator.php`, `Generator/{WizardForm,Draft,Diff,SparkRunner}.php`,
views `generator/{form,_table,preview,result}.php`, `Assets/js/generator.js` (live help only),
`Routing/DevtoolsRoutes.php` (`DRAFT`, `DRAFT_ROUTES`), language `Devtools.generator.*`,
`Devtools.activity.generated`.

**Routes** (development, local only; `DevtoolsLocksTest` checks every one):

| Route | Filters |
|---|---|
| `GET backend/devtools/generator`, `GET …/preview`, `GET …/definition.json`, `GET …/result` | VIEW (`devtools`, `can:devtools.view`) |
| `POST backend/devtools/generator` (buttons), `POST …/load` | DRAFT (`devtools`, `can:devtools.view`; D-42-02) |
| `POST backend/devtools/generator/write` | WRITE (`devtools`, `can:devtools.write`, `reauth`, `ratelimit:forms`) |

No tables, settings or permissions added (171 permissions). New dev dependency: `nikic/php-parser ^5`
(D-42-01, `docs/dependencies.md`). Command: `make:module --definition` (`docs/commands.md`).

## Decisions and deviations

- **D-42-02 (accepted):** the wizard's buttons and "load" only change the session draft: they use
  `DevtoolsRoutes::DRAFT` (no re-authentication — a POST is never replayed, edits would be lost —
  and no `forms` limit of 10/hour). Writing stays WRITE. The lock test pins both lists.
- **D-42-03 (accepted):** engine in the Core; Faker at generation time (seeders need no Faker);
  one server-rendered wizard with the draft in the session (works without JavaScript; the
  re-authentication round trip returns to the preview URL); the fingerprint covers the definition
  and existing files (new files' migration names carry the time); plain escaped `<pre>`, no
  highlighter.
- **Not built (recorded as next steps):** "alter" migrations for existing tables; N-N relations,
  multiselects, bulk actions, widgets, tasks, notifications, public pages; the business logic of
  custom operations (a `TODO`). Enum option labels default to the humanised value in all three
  languages (edit the language files).
- `ContentLanguagesTest` now also allows `modules/Core/Generator/Definition/Texts.php`: the shipped
  UI languages of generated language files (not content languages) are named there only.

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --url=http://localhost:8080/ --db=sqlite --demo --2fa=none --email=admin@example.com --no-interaction
php spark modules:enable Devtools
php spark serve
# sign in as the superadmin → Configuració › Desenvolupament › Eines de desenvolupament › Generador de codi
# Definition (JSON) → load modules/Core/Tests/Fixtures/Generator/library.json → Vista prèvia → Escriu
php spark make:module --definition=docs/tutorials/files/tasks2.definition.json --dry-run
```

## Screenshots

Playwright against `php spark serve`, demo data, superadmin, device scale factor 2, the
`library.json` definition loaded (no CSP violation, no JS error, no horizontal scroll):

| File | Caption |
|---|---|
| `screenshots/01-devtools-home.png` | Devtools home with the new tool "Generador de codi" |
| `screenshots/02-step1-module.png` | Step 1: new/existing module, name, author, description ca/es/en, dependencies |
| `screenshots/03-step2-table-columns.png` | Step 2: table name, timestamps, trash, columns with types, sizes, defaults, options and foreign keys |
| `screenshots/04-step3-demo-data.png` | Labels in ca/es/en and step 3: demo rows and a Faker generator per column |
| `screenshots/05-step4-crud.png` | Step 4: CRUD options, operations, the columns × list/form/required/search/filter/file/rich-text/hidden matrix, record rule |
| `screenshots/06-step4-custom-operations.png` | Custom operations: key, icon, re-authentication, label and confirmation in three languages |
| `screenshots/07-step5-api-step6-menu-permissions.png` | Steps 5 and 6: API, menu, default grants matrix and the generated permission names |
| `screenshots/08-validation-errors.png` | Validation: ON DELETE SET NULL on a NOT NULL column, listed and next to the field |
| `screenshots/09-step7-preview.png` | Preview: file tree with *nou* badges, permissions, write form, next steps |
| `screenshots/10-preview-new-file.png` | Preview: the generated CRUD controller in full |
| `screenshots/11-write-result.png` | After writing (re-authenticated): files, `modules:sync` and `modules:demo` output, next steps |
| `screenshots/12-generated-crud.png` | The generated `library-books` CRUD with its demo rows (export, import, trash, custom operations) |
| `screenshots/13-generated-readonly-crud.png` | The generated read-only `library-shelves` CRUD |
| `screenshots/14-existing-module.png` | An existing module (Library) with a new table `library_loans` |
| `screenshots/15-preview-diff-existing-module.png` | Preview of the existing module: diff of `Config/Module.php` (resource, grants, menu), *canviat* badges |
| `screenshots/16-wizard-phone.png` | The wizard at phone width (390 px) |
| `screenshots/17-preview-phone.png` | The preview at phone width |
| `screenshots/18-wizard-dark.png` | The wizard in dark mode |
| `screenshots/19-preview-dark.png` | The preview in dark mode |

(After the screenshots, the docs link of step 1 moved from the card header into its body (axe
colour contrast) and the "Recepta" links got the recipe title as hidden text.)

## Tests and checks

New tests: `modules/Core/Tests/GeneratorEngineTest.php` (plans of `library`, `notes`,
`zoo-existing` against `Fixtures/Generator/expected/`; existing module keeps its lines and only
adds migrations; never overwrites; JSON round trip; same fingerprint across a second; free text
never becomes code; no tables = `make:module`'s output), `GeneratorValidatorTest.php` (7 groups of
rules), `GeneratorWriteTest.php` (format-preserving edits, conflicts, broken files, methods; write
refused after a change and with another fingerprint, atomic, no temp files),
`GeneratedModuleChecksTest.php` (`library`, `zoo-existing` and tutorial 15's `tasks2` written into
`modules/`: `php -l`, php-cs-fixer, PHPStan, `csp:check` and their own generated tests — permissions,
forged posts, add, read only, own rows, hidden fields, re-authentication, API scopes/public,
migrations up/down/up), `modules/Devtools/Tests/GeneratorWizardTest.php` (permissions, buttons,
errors, preview, diff, JSON download/load, write needs `devtools.write` and re-authentication,
refused after a change, written + synced + logged), `DevtoolsLocksTest` (DRAFT routes, views in
subfolders), `tests/e2e/devtools-generator.spec.mjs`.

Results (SQLite, PHP 8.3):

| Check | Result |
|---|---|
| `vendor/bin/phpunit` | **OK (1514 tests, 20679 assertions)**, 12 min 19 s; after the follow-up **OK (1520 tests, 20734 assertions)** (baseline at the start: 1481 tests) |
| `vendor/bin/phpstan analyse` | No errors (level 6) |
| `vendor/bin/php-cs-fixer fix --dry-run` | 0 of 1089 files to fix |
| `php spark modules:sync` | OK |
| `php spark permissions:check` | Permisos correctes: 171 permisos, 356 rutes (with a generated `Library`: 190 permissions, 370 routes) |
| `php spark csp:check` | No inline scripts, handlers or styles |
| `php spark assets:publish --check` | Up to date |
| `npm run e2e` (development demo site, Devtools and Tasks enabled) | **200 passed**, 10 skipped (the existing "once at desktop" tests); the generator spec passes at phone/tablet/desktop, light/dark, axe clean |
| Generated `Library` (from the UI) | its 19 tests pass; `modules:demo Library` loads and `--remove` removes its 27 rows |

## Follow-up after the owner's review (same branch)

- **CI fix:** "Checks (PHP 8.2)" failed: Faker's seeded sequence differs between PHP 8.2 and 8.3/8.4,
  so the demo rows of the plan fixtures changed. The fixtures are now made without Faker
  (`GeneratorContext(faker: false)`: numbered values, the same on every PHP version); real plans
  still use Faker.
- **Group titles (D-42-04):** the generator's grants matrix shows each group's title (Users ›
  Groups, e.g. "Coordinador") next to its internal name (`editor`).
- **`groups:export` / `groups:import` (D-42-04):** copy a site's groups (titles, descriptions,
  2FA policy, optionally grants; never users or personal data) to a development copy without a
  full backup (`docs/users.md`). The import keeps wildcard grants, skips permissions the site does
  not have, never changes `superadmin`, asks for confirmation, and is logged
  (`groups_imported`, `grants_changed`). Tests: `modules/Core/Tests/GroupTransferTest.php`,
  `GeneratorWizardTest::testTheGrantsShowEachGroupsTitleAndName`.

## Known issues and notes for the next session

- The draft lives in the PHP session: one draft per session; "Download the definition" keeps it.
- Migration names carry the time of the plan: the written name can differ from the previewed one
  by a few seconds (the fingerprint ignores new files on purpose).
- The LCS diff may show a closing `],` as moved when an item is appended after a multi-line item
  (the written file is correct and formatted).
- Required foreign keys to tables outside the definition: the generated tests use the first row
  of that table (or id 1); the demo seeder uses `firstId()`.
- Session 43 (packages): use `DevtoolsRoutes::WRITE` for anything that writes; `DRAFT` only for
  session drafts (add the path to `DRAFT_ROUTES`).
- Cloud environment: Composer needed the known issue 04 workaround again (`phpstan/phpstan`'s zip
  seeded into Composer's cache from a shallow clone of tag 2.3.5, then
  `env -u COMPOSER_AUTH composer install --prefer-source`); `npm ci` worked with
  `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1` (pre-installed Chromium).
