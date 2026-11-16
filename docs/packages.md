# Modules screen, packages and uninstalling

Modules and themes are **code**: the web server never writes into `modules/`, `themes/` or
`app/Config/`. They are installed in one of three ways, all ending in the same validation and
`modules:sync` (specification "Installing and uninstalling modules and themes"; decisions
D-29-01 … D-29-09).

1. **Copy the folder** (git, SFTP, a deployment) and run `php spark modules:sync`, or press
   **Sync** in the Modules screen. A module found for the first time is enabled outside
   production; in production it is listed as *installed, disabled* until an administrator
   enables it, unless `packages.autoEnable = true` (D-29-02).
2. **A ZIP from the console:** `php spark modules:install <file.zip|url>` (themes:
   `themes:install`).
3. **Upload in the screen, install from the console** (staged install): a superadmin uploads
   the ZIP, checks what it contains and approves it; an operator (or a cron line) runs
   `php spark modules:install --pending`.

## The Modules screen

`backend/modules` (menu *Modules*, settings zone), three tabs:

| Tab | What it shows | Actions (permission) |
|---|---|---|
| Modules | Every module on disk or in the registry: icon, name, description, core badge, installed version (and the version of the files when they differ; the latest one when the Updates module found a newer version), status (*enabled*, *disabled*, *not synchronised*, *pending install/uninstall*, *error* with the manifest errors), dependencies and dependants, demo data | Enable/disable (`modules.toggle`), demo data load/reload/remove (`modules.demo`), Sync (`modules.sync`), Uninstall (`modules.uninstall` + re-authentication) |
| Packages | Staged installs and uninstall requests with their status and log | Upload (`modules.upload` + re-authentication), approve (`modules.upload` + re-authentication), discard (`modules.upload`, or `modules.uninstall` for an uninstall request) |
| Themes | Installed themes: name, version, author, parent, the screenshot `Assets/img/screenshot.png` when the theme ships one and its colours, the areas it is active for | Use for the back office / public site (`modules.toggle`), link to Settings › Theme for the options (`settings.core`), uninstall (`modules.uninstall` + re-authentication) |

Admins get `modules.list`, `modules.toggle` and `modules.sync`; `modules.upload`,
`modules.uninstall` and `modules.demo` are superadmin-only by default.

- **Disabling** hides the module at once (routes 404, menus, widgets, tasks, menu link types,
  permissions in the matrix) without deleting any data or grant. Core modules (`Core`, `Auth`,
  `Api`, `Users`, `Settings`, `Dashboard`) and modules that enabled modules require cannot be
  disabled (the message names them).
- **Enabling** runs the module's pending migrations and publishes its assets; when its
  dependencies are disabled the screen asks to enable them too.
- **Sync** is `modules:sync`. From the web it stops with a message when the generated
  `app/Config/ModuleNamespaces.php` must change and the web server cannot write it: run the
  command from the console.
- Every change is written to the activity log (`module_enabled`, `module_disabled`,
  `modules_synced`, `theme_activated`, `package_*`, `uninstall_requested`).

## Packages

A package is a ZIP with **one top folder**: a module (`News/Config/Module.php`, folder = module
name) or a theme (`corporate/Theme.php`, folder = theme slug).

```
News/
  Config/Module.php      ← manifest: literal name, version, requires…
  Config/Routes.php
  Controllers/…
  Database/Migrations/…
  Language/{ca,es,en}/News.php
  Assets/js/…
```

Validation (`Modules\Core\Packages\PackageValidator`), before anything is installed:

- the ZIP is at most `packages.maxPackageBytes` (20 MB; downloads are also limited by
  `Config\Outbound::$maxBytes`);
- a signature (`<file>.zip.minisig`, minisign) is checked against `packages.publicKeys`;
  without one the package is refused when `packages.requireSignature = true`;
- `SafeZip`: no absolute paths, `..`, backslashes or control characters (zip-slip), no symbolic
  links, at most `packages.maxFiles` entries, `packages.maxUncompressedBytes` uncompressed and a
  compression ratio of `packages.maxRatio` (zip bombs);
- only the extensions of `packages.extensions` (plus `LICENSE`, `README`, `CHANGELOG`, `NOTICE`,
  `AUTHORS`, `COPYING` and `.gitkeep`); never `phtml`, `phar`, `.htaccess`, `.env`…;
- the manifest is **read without running it** (`ManifestReader`: literal values only): class
  `Modules\<Name>\Config\Module` extending `ModuleManifest` (themes: `Themes\<Studly>\Theme`
  extending `ThemeManifest`), name equal to the folder, a semantic version; reserved names
  (`Security`, `Update`) and the core modules are refused (the Core is updated with
  `app:update`);
- every PHP file is syntax-checked with `php -l` (`PhpLint`, in a private folder).

The install (`PackageInstaller`) then checks dependencies and the Core version, refuses a module
of another **author** (`$author` in the manifest) and a downgrade or the same version unless
`--force`, and:

1. makes a full backup (the Backup module answers the `packageBackup` event; password from
   `backup.password`/`BACKUP_PASSWORD` or `--password`; without the Backup module the command
   stops unless `--no-backup`);
2. turns maintenance mode on (left on if it already was);
3. extracts to `writable/packages/stage-*` and runs `php -l`;
4. copies the folder next to its target as a hidden folder and renames it into place (the
   previous version is renamed aside);
5. runs `modules:sync` in a **new PHP process** (the running one may hold the previous version's
   classes); `--enable` then enables the module and its disabled dependencies; `--demo` loads its
   demo data;
6. on any failure puts the previous folder back and runs `modules:sync` again; turns maintenance
   mode off.

```
$ BACKUP_PASSWORD=… php spark modules:install Glossary-1.0.0.zip
Package: module Glossary 1.0.0
  Author:          ACME
  Permissions:     glossary-main.view
  …
Install Glossary 1.0.0? [n, y]: y
Making a backup…
Maintenance mode on.
Extracting and checking the PHP files…
Running modules:sync…
Glossary 1.0.0 installed.
```

### Staged installs

1. *Packages › Upload a package*: the ZIP (and optionally its `.minisig`) is validated, stored
   as `writable/packages/<random>.zip` (0640) and listed as *waiting for approval*.
2. The details page shows the manifest (author, description, dependencies, the permissions,
   menus, widgets and tasks it adds), the file list and whether it can be installed.
3. *Approve* (re-authentication): the file's SHA-256 is checked again and the package becomes
   *pending*. *Discard* deletes the file.
4. `php spark modules:install --pending` installs it (or runs an uninstall request), stores the
   log and notifies the approver (`PackageProcessed`). Uploaded and pending entries expire after
   `packages.pendingDays` (7).

Cron line for an operator account that may write the code folders (separate from the web
server's `schedule:run`):

```
*/5 * * * * cd /var/www/site && php spark modules:install --pending >> writable/logs/packages.log 2>&1
```

## Uninstalling

`php spark modules:uninstall <Name> [--keep-data]`, or *Uninstall* in the screen (a pending
request run by `modules:install --pending`):

1. backup and maintenance mode;
2. the module is disabled; its migrations are rolled back unless `--keep-data`;
3. its calendar entries are deleted through the Core's calendar contract
   (`service('calendar')->purgeModule()`, the Events module) unless the data is kept; the
   `moduleUninstalling` event: Menus removes the customisations of its entries and the public menu items
   of its link types, Dashboard its widgets in the group layouts;
4. the Core removes its permissions and every group or user grant naming them (wildcards
   included), its settings, scheduled tasks, notification preferences, declarations and registry
   row (and, unless `--keep-data`, its notifications and demo records);
5. its folder is deleted and `modules:sync` removes its namespace and published assets.

Core modules and modules other installed modules require cannot be uninstalled. Themes:
`php spark themes:uninstall <slug>`: never the active theme of either area, the fallback theme
or a parent of another theme; its folder, assets and option values are removed.

## Configuration (`Config\Packages`, `.env` `packages.*`)

| Key | Default | Meaning |
|---|---|---|
| `allowDemoInProduction` | false | Demo data may be loaded/removed in production |
| `autoEnable` | false | Enable modules found by `modules:sync` in production too (D-29-02) |
| `publicKeys` | [] | Trusted publisher keys (minisign public keys) |
| `requireSignature` | false | Refuse unsigned packages |
| `maxPackageBytes` | 20 MB | Largest ZIP |
| `maxUncompressedBytes` | 100 MB | Largest uncompressed total |
| `maxFiles` | 5000 | Most entries |
| `maxRatio` | 100 | Highest compression ratio of an entry over 1 MB |
| `extensions` | php, json, md, css, js, images, fonts… | Allowed file extensions |
| `pendingDays` | 7 | Life of uploaded/pending entries |
| `lintTimeout` | 20 | Seconds per `php -l` |
| `phpBinary` | `PHP_BINARY` (or `php` under FPM) | PHP used for `php -l` and `modules:sync` |

## Extension points (Core events)

| Event | Object | Who answers |
|---|---|---|
| `packageBackup` | `Modules\Core\Packages\PackageBackup` (`done($file)` / `failed($error)`) | Backup: a full backup, origin `package` |
| `moduleUninstalling` | `Modules\Core\Packages\ModuleUninstalling` (module, keepData, permissions, menu keys, link types, widget ids; `note()`) | Menus, Dashboard |
| `moduleVersions` | `Modules\Core\Packages\ModuleVersions` (`set($module, $version)`) | Updates (its last check) |

A module that keeps data about other modules listens to `moduleUninstalling` in its
`Config/Events.php` (discovered even while it is disabled, so it must check its own tables).

## Tests

`modules/Core/Tests/PackagesTest.php` (reader, validation and refusals, signatures, install,
upgrade, rollback, backups, auto-enable, uninstall with and without data, staged operations) and
`modules/Core/Tests/ModulesScreenTest.php` (permissions and forged POSTs, re-authentication,
enable/disable with dependencies and 404s, uninstall requests, approval, themes tab).
