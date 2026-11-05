# Commands

Every `php spark` command added by the skeleton. CodeIgniter's own commands (`migrate`, `routes`,
`serve`…) and the libraries' (`kpcrudy:*`, `kpapy:*`, `shield:*`) are documented by them.

## Modules

| Command | What it does |
|---|---|
| `make:module <Name>` | Generates an empty module in `modules/<Name>` (manifest, routes, ca/es/en language files, controller, view, asset folders, an example migration, the initial-data seeder, a demo seeder, widget and scheduled-task stubs, a test) and adds its namespace to `app/Config/ModuleNamespaces.php`. Refuses invalid, reserved (`Security`, `Update`) and existing names. |
| `modules:sync [--prune] [--demo[=<Module,Module>]]` | Discovers `modules/*/Config/Module.php`, validates every manifest, dependency, permission name (duplicates refused) and `$api` route (on any error: lists them and changes nothing), rewrites `app/Config/ModuleNamespaces.php`, runs the migrations of Shield, Settings and kpapy and then of each module in dependency order, runs `<Name>Seeder` on a module's first install, upserts the `modules` registry and the permission catalogue (`permissions`), applies each module's `defaultGrants` with their implied permissions once (`group_permissions`), writes Shield's `AuthGroups.permissions` and `AuthGroups.matrix` settings from them, stores the settings definitions, widgets, tasks, notification types, menu link types, calendars and API resources (`module_declarations`), validates every theme in `themes/` (errors stop the sync before anything is written) runs `assets:publish` (Core, modules and themes, asset manifest), and invalidates the caches. Idempotent: a second run prints "Nothing to do". Permissions no module generates any more are only reported; `--prune` lists them and removes them (and their grants) after a y/n confirmation. `--demo` loads the demo data of every enabled module that has a demo seeder (or of the listed ones) after a confirmation; refused in production unless `Packages.allowDemoInProduction`. |
| `modules:list` | Modules on disk and in the registry: code version, installed version, enabled, status (up to date, not installed, needs sync, folder missing, invalid manifest) and whether demo data is loaded. |
| `modules:enable <Name> [--with-dependencies]` | Enables a disabled module: runs its pending migrations (it stays disabled if they fail), publishes its assets and invalidates the caches. If dependencies are disabled, asks whether to enable them too (`--with-dependencies` does it without asking). |
| `modules:disable <Name>` | Disables a module: its routes answer 404 and it disappears from the registries (widgets, tasks, notification types, menu link types, calendars, API) and from the enabled permissions; no data or grant is deleted. Refused for core modules (`Core`, `Auth`, `Users`, `Settings`) and while enabled modules depend on it. |
| `modules:demo <Name> [--remove\|--reload]` | Loads (idempotent), removes or reloads the demo data of an enabled module (`Modules\<Name>\Database\Seeds\<Name>DemoSeeder`), always after a y/n confirmation. Removing deletes only the recorded rows and files; rows other data depends on are kept and listed. Refused when `CI_ENVIRONMENT = production` unless `Packages.allowDemoInProduction` (`.env`: `packages.allowDemoInProduction = true`). |
| `modules:status` | Pending migrations per namespace (packages and modules). |

## Permissions

| Command | What it does |
|---|---|
| `permissions:list [--module=<Name>] [--group=<group>]` | The permission catalogue: name, module, description, whether its module is enabled, and the groups that hold it (through an atomic grant or a wildcard; `superadmin` holds every one). `--module` and `--group` filter it. See `docs/rbac.md`. |
| `permissions:check` | Fails (exit code 1) when a manifest is invalid or generates a duplicate name, a generated permission is not stored (run `modules:sync`), Shield's `AuthGroups` permissions/matrix do not match `group_permissions`, a `backend/` route has no `can:` filter or names a permission outside the catalogue, or an `api/v1/` route lacks kpapy's filter (`kpapy:login` only on `auth/token*`, `kpapy:public` only for GET). Warns about stored permissions no manifest generates, grants naming unknown permissions and grants missing the permissions they imply. Run it in CI and before a deploy. |

## Front end

| Command | What it does |
|---|---|
| `assets:publish [--watch] [--check]` | Copies `modules/Core/Assets/` → `public/assets/core/`, `modules/<Name>/Assets/` → `public/assets/modules/<name>/` and `themes/<slug>/Assets/` → `public/themes/<slug>/` with CodeIgniter's Publisher (allowed extensions only: never PHP or HTML; symbolic links skipped; stale copies removed) and writes the asset manifest `public/assets/manifest.json` (URL and SHA-256 prefix of every file, used by `asset()` and the import map). Idempotent. `--watch` keeps running and republishes when a source changes (development). `--check` writes nothing and exits with an error when a copy or the manifest is out of date (CI). Also run by `modules:sync`, `modules:enable` and `make:theme`; in development the pages republish stale assets by themselves (`assets.devRefresh`). See `docs/frontend.md`. |
| `csp:check` | Checks the views of `app/Views`, `modules/*/Views`, `themes/*/Views` and the generator templates for inline `<script>` code, `on*=""` handlers, `style=""` attributes, `javascript:` URLs and `<style>` elements (only the nonce'd theme-variables block of a theme's `partials/head.php` is allowed). Exits with an error and lists `file:line: problem` (CI). |

Options with a value accept both `--name=value` and `--name value`.

Run `php spark modules:sync` after `composer install`, after pulling new code and after copying a
module folder by hand (its namespace is only loadable once the sync has written it).

## Themes

| Command | What it does |
|---|---|
| `make:theme <Name> [--parent=default\|<slug>\|none]` | Generates a theme in `themes/<slug>` (`Corporate` → `corporate`, `TasksDemo` → `tasks-demo`): manifest `Themes\<Studly>\Theme`, `Assets/css/theme.css`, empty `Views/overrides`, `Views/components`, `Views/partials`, ca/es/en language files. A child (default: of `default`) inherits every layout; `--parent=none` creates a standalone theme with a minimal file per layout. Validates the theme and publishes its assets. Refuses invalid and existing names and missing parents. See `docs/themes.md`. |
