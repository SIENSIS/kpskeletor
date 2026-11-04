# Session 01 — Core kernel: modules, manifest and sync

Branch: `claude/modest-goldberg-jjs8kp` (assigned by the environment) · Pull request: "Session 01:
Core kernel: modules, manifest and sync" · Date: 2026-11-04

## What was built

- **Module loading through discovery:** one PSR-4 namespace per module (`Modules\News` →
  `modules/News`) in the generated `app/Config/ModuleNamespaces.php`, merged by
  `app/Config/Autoload.php` (D-00-03). CodeIgniter's discovery then finds each module's routes,
  registrar, commands and language files; nothing is scanned at runtime.
- **`modules/Core`:** the Core module with its manifest, `Config/Version.php` (Core 0.1.0) and the
  typed `ModuleManifest` base class (every key of the specification, with defaults, including the
  optional resource `'name'`).
- **Manifest validator:** clear errors naming the module and the key; reserved module names
  `Security`/`Update`; config classes named `Events`, `Routes`, `Registrar`, `Filters`, `Modules`
  or `Services` refused; safe CSP sources only; duplicate permission prefixes across modules
  refused.
- **Module registry** (`modules` table) with dependency and version checks (`^`, `~`,
  comparisons, `||`).
- **`modules:sync`** (steps 1–3 and 8 of "Module lifecycle"), **`modules:list`**,
  **`modules:status`** and **`make:module <Name>`**.
- **Base classes:** `BaseModel`, `BaseEntity`, `BackendController`, `FrontendController`
  (skeletons), and a default-deny **`can:`** filter (D-01-01).

## How it was built

### Design

`modules:sync` runs in this order and is idempotent:

1. `ModuleDiscovery` finds `modules/*/Config/Module.php`, registers each namespace in the running
   autoloader (once per folder) and loads the manifests; `ManifestValidator` validates each one;
   the synchroniser checks cross-module permission prefixes and `DependencyResolver` checks
   `$requires` and orders the modules (Core first, then topological, alphabetical ties). **Any
   error stops the sync before anything is written** (`SyncFailedException`).
2. `NamespaceFile` rewrites `app/Config/ModuleNamespaces.php` only when its content changes (atomic
   write, opcache invalidated).
3. `ModuleMigrator` runs the pending migrations of `CodeIgniter\Shield`, `CodeIgniter\Settings` and
   `Kpapy` as they ship (never rolled back), then of each module namespace in dependency order.
4. On a module's first install its `<Name>Seeder` runs, then the registry row is inserted;
   version changes update the row. Registry rows without a folder are reported as missing.
5. When something changed, `CacheInvalidator` fires `modules.cacheInvalidate` (later sessions
   clear their caches on it).

### Main files

| File | Role |
|---|---|
| `app/Config/Autoload.php`, `app/Config/ModuleNamespaces.php` | Static namespaces + generated per-module namespaces |
| `modules/Core/Config/ModuleManifest.php` | Typed manifest base class (`permissionPrefix()`) |
| `modules/Core/Config/Module.php`, `Config/Version.php`, `Config/Registrar.php` | Core manifest, Core version, `can` filter alias |
| `modules/Core/Modules/ManifestValidator.php`, `SemVer.php`, `DependencyResolver.php` | Validation |
| `modules/Core/Modules/ModuleDiscovery.php`, `DiscoveredModule.php`, `NamespaceFile.php` | Discovery and the generated file |
| `modules/Core/Modules/ModuleMigrator.php` | Pending/migrate/rollback per namespace |
| `modules/Core/Modules/ModuleSynchronizer.php`, `SyncReport.php`, `SyncFailedException.php`, `CacheInvalidator.php` | `modules:sync` |
| `modules/Core/Modules/ModuleGenerator.php`, `Modules/Templates/*.tpl` | `make:module` |
| `modules/Core/Commands/*.php` | `modules:sync`, `modules:list`, `modules:status`, `make:module` |
| `modules/Core/Models/BaseModel.php`, `ModuleModel.php`, `Entities/BaseEntity.php` | Models |
| `modules/Core/Controllers/BackendController.php`, `FrontendController.php` | Controller skeletons |
| `modules/Core/Filters/Can.php` | `can:<permission>` filter, default deny |
| `modules/Core/Language/{ca,es,en}/Core.php` | All texts (same keys, tested) |

### Tables

`modules` (id, name unique, version, enabled, installed_at, updated_at):
`modules/Core/Database/Migrations/2026-11-04-000001_CreateModules.php`.

### Routes and permissions

No Core routes yet. Generated modules get `GET backend/<name>` named `<name>-main` with the filter
`can:<name>-main.view` (403 until the RBAC session grants permissions).

### Commands

`make:module`, `modules:sync`, `modules:list`, `modules:status` (see `docs/commands.md`).

## Decisions and deviations

Recorded in `docs/decisions.md` as Accepted (proposal); none changes a public convention or
weakens security:

- **D-01-01** A default-deny `can:` filter now, so generated routes already follow the rule.
- **D-01-02** `make:module` generates a real example migration (`<name>_items`) instead of an
  empty stub.
- **D-01-03** Registry rows whose folder disappeared are reported, not deleted (uninstall's job).
- **D-01-04** Allowed values: setting types, CSP directives/sources (https hosts, `'self'`,
  `'none'`, `blob:` only), relative API and backup paths.
- **D-01-05** Core version `0.1.0` during development; generated modules require `^0.1`.

Not done here (as planned): the module's `CLAUDE.md` from `make:module` and `/check-module`
(session 32, D-00-31); widget/task interfaces (the stubs are plain classes until sessions 11/16).

## How to try it

```bash
bash deploy/setup.sh            # cloud: starts MariaDB, composer install
php spark make:module Demo1
php spark modules:status
php spark modules:sync          # twice: the second run changes nothing
php spark modules:list
php spark serve                 # http://localhost:8080/backend/demo1 → 403 (default deny)
```

## Tests and checks

```
$ vendor/bin/phpunit
OK (74 tests, 195 assertions)

$ vendor/bin/phpunit   # database.tests.* = MariaDB 10.11
OK (74 tests, 195 assertions)

$ vendor/bin/phpstan analyse
 [OK] No errors

$ vendor/bin/php-cs-fixer fix --dry-run --diff
Found 0 of 98 files that can be fixed
```

New tests (`modules/Core/Tests/`): `ManifestValidatorTest` (31 invalid manifests, each error names
the key; reserved names; prefix override; special config files), `SemVerTest`,
`DependencyResolverTest`, `ModulesSyncTest` (first sync installs, second changes nothing, caches
invalidated once; new module's route discovered; upgrade recorded; invalid manifest changes
nothing), `CoreMigrationsTest` (up → down → up; package migrations never rolled back),
`ModuleGeneratorTest` (complete module, `php -l` on every file, same language keys, valid
manifest, refused names), `BaseModelTest` (author columns cannot be forged, soft delete),
`CanFilterTest` (alias registered, default deny), `LanguageFilesTest` (ca/es/en keys).

## Screenshots

This session has no screens. Console output, against an empty MariaDB database:

```
$ php spark make:module Demo1
Module Demo1 created in ROOTPATH/modules/Demo1.
Next: php spark modules:sync

$ php spark modules:status
+----------------------+--------------------------------------------+
| Namespace            | Pending migrations                         |
+----------------------+--------------------------------------------+
| CodeIgniter\Shield   | 2020-12-28-223112_create_auth_tables       |
| CodeIgniter\Settings | 2021-07-04-041948_CreateSettingsTable      |
| CodeIgniter\Settings | 2021-11-14-143905_AddContextColumn         |
| CodeIgniter\Settings | 2026-07-20-181246_ConvertSqlsrvValueColumn |
| Kpapy                | 2026-11-03-000001_CreateLoginChallenges    |
| Modules\Core         | 2026-11-04-000001_CreateModules            |
| Modules\Demo1        | 2026-11-04-000001_CreateDemo1Items         |
+----------------------+--------------------------------------------+

$ php spark modules:sync
CodeIgniter\Shield: 1 migration(s) run.
CodeIgniter\Settings: 3 migration(s) run.
Kpapy: 1 migration(s) run.
Modules\Core: 1 migration(s) run.
Modules\Demo1: 1 migration(s) run.
Demo1: initial data loaded.
Core 0.1.0 installed.
Demo1 0.1.0 installed.
Modules synchronised.

$ php spark modules:sync
Nothing to do: every module is up to date.

$ php spark modules:list
+--------+---------+-----------+---------+------------+
| Module | Version | Installed | Enabled | Status     |
+--------+---------+-----------+---------+------------+
| Core   | 0.1.0   | 0.1.0     | yes     | up to date |
| Demo1  | 0.1.0   | 0.1.0     | yes     | up to date |
+--------+---------+-----------+---------+------------+

$ php spark modules:sync
Synchronisation failed; nothing has been changed:
  - Demo1: $resources['main']['type'] must be one of: crud, page
  - Demo1: $requires['Core'] requires Core ^2.0, but version 0.1.0 is present

$ curl -i http://localhost:8080/backend/demo1
HTTP/1.1 403 Forbidden
```

The last `modules:sync` ran with a manifest edited to `'type' => 'pages'` and
`'Core' => '^2.0'`: nothing was written.

## Known issues

- The default locale is still `en` (CodeIgniter's); Catalan as the default comes with the
  languages/front-end sessions.
- `modules:sync` writes `app/Config/ModuleNamespaces.php`: in production the web server must not
  write there (D-00-33), so the sync runs from the console (the Modules screen will show the
  command when the file is not writable).
- MariaDB in the cloud container is not a service: `bash deploy/setup.sh` starts it.

## Notes for the next session

- Session 02 adds steps 4–7 of the sync (permission catalogue, grants, settings/widgets/tasks
  registration, demo data). Extend `ModuleSynchronizer::sync()` after the registry upsert and add
  the new entries to `SyncReport` so idempotency stays testable (`ModulesSyncTest`).
- Use `ModuleManifest::permissionPrefix()` for permission names (it honours `'name'`).
- Tests that run migrations themselves use `CleanDatabaseTrait` and `TempModulesTrait`
  (`modules/Core/Tests/`).
- When the widget/task interfaces exist, update `modules/Core/Modules/Templates/Widget.php.tpl`
  and `Task.php.tpl`.
