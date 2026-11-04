# Commands

Every `php spark` command added by the skeleton. CodeIgniter's own commands (`migrate`, `routes`,
`serve`…) and the libraries' (`kpcrudy:*`, `kpapy:*`, `shield:*`) are documented by them.

## Modules

| Command | What it does |
|---|---|
| `make:module <Name>` | Generates an empty module in `modules/<Name>` (manifest, routes, ca/es/en language files, controller, view, asset folders, an example migration, the initial-data seeder, widget and scheduled-task stubs, a test) and adds its namespace to `app/Config/ModuleNamespaces.php`. Refuses invalid, reserved (`Security`, `Update`) and existing names. |
| `modules:sync` | Discovers `modules/*/Config/Module.php`, validates every manifest and dependency (on any error: lists them and changes nothing), rewrites `app/Config/ModuleNamespaces.php`, runs the migrations of Shield, Settings and kpapy and then of each module in dependency order, runs `<Name>Seeder` on a module's first install, upserts the `modules` registry and fires `modules.cacheInvalidate`. Idempotent: a second run prints "Nothing to do". |
| `modules:list` | Modules on disk and in the registry: code version, installed version, enabled, status (up to date, not installed, needs sync, folder missing, invalid manifest). |
| `modules:status` | Pending migrations per namespace (packages and modules). |

Run `php spark modules:sync` after `composer install`, after pulling new code and after copying a
module folder by hand (its namespace is only loadable once the sync has written it).
