# Commands

Every `php spark` command added by the skeleton. CodeIgniter's own commands (`migrate`, `routes`,
`serve`…) and the libraries' (`kpcrudy:*`, `kpapy:*`, `shield:*`) are documented by them.

## Modules

| Command | What it does |
|---|---|
| `make:module <Name>` | Generates an empty module in `modules/<Name>` (manifest, routes, ca/es/en language files, controller, view, asset folders, an example migration, the initial-data seeder, a demo seeder, widget and scheduled-task stubs, a test) and adds its namespace to `app/Config/ModuleNamespaces.php`. Refuses invalid, reserved (`Security`, `Update`) and existing names. |
| `modules:sync [--prune] [--demo[=<Module,Module>]]` | Discovers `modules/*/Config/Module.php`, validates every manifest, dependency, permission name (duplicates refused) and `$api` route (on any error: lists them and changes nothing), rewrites `app/Config/ModuleNamespaces.php`, runs the migrations of Shield, Settings and kpapy and then of each module in dependency order, runs `<Name>Seeder` on a module's first install, upserts the `modules` registry and the permission catalogue (`permissions`), applies each module's `defaultGrants` with their implied permissions once (`group_permissions`), stores the settings definitions, widgets, tasks, notification types, menu link types, calendars and API resources (`module_declarations`) and invalidates the caches. Idempotent: a second run prints "Nothing to do". Permissions no module generates any more are only reported; `--prune` lists them and removes them (and their grants) after a y/n confirmation. `--demo` loads the demo data of every enabled module that has a demo seeder (or of the listed ones) after a confirmation; refused in production unless `Packages.allowDemoInProduction`. |
| `modules:list` | Modules on disk and in the registry: code version, installed version, enabled, status (up to date, not installed, needs sync, folder missing, invalid manifest) and whether demo data is loaded. |
| `modules:enable <Name> [--with-dependencies]` | Enables a disabled module: runs its pending migrations (it stays disabled if they fail) and invalidates the caches. If dependencies are disabled, asks whether to enable them too (`--with-dependencies` does it without asking). |
| `modules:disable <Name>` | Disables a module: its routes answer 404 and it disappears from the registries (widgets, tasks, notification types, menu link types, calendars, API) and from the enabled permissions; no data or grant is deleted. Refused for core modules (`Core`, `Auth`, `Users`, `Settings`) and while enabled modules depend on it. |
| `modules:demo <Name> [--remove\|--reload]` | Loads (idempotent), removes or reloads the demo data of an enabled module (`Modules\<Name>\Database\Seeds\<Name>DemoSeeder`), always after a y/n confirmation. Removing deletes only the recorded rows and files; rows other data depends on are kept and listed. Refused when `CI_ENVIRONMENT = production` unless `Packages.allowDemoInProduction` (`.env`: `packages.allowDemoInProduction = true`). |
| `modules:status` | Pending migrations per namespace (packages and modules). |

Run `php spark modules:sync` after `composer install`, after pulling new code and after copying a
module folder by hand (its namespace is only loadable once the sync has written it).
