# Creating a module

Everything a site adds lives in a module, `modules/<Name>/`, described by its manifest. This page
is the short path; the full contract is in `docs/specification.md` ("The module contract") and the
reference modules are `modules/News` (reference) and `modules/Tasks` (tutorial module: its
`CLAUDE.md` maps every feature to its file; the tutorials in `docs/tutorials/` walk through it).

> Session 33 completes this page (architecture, every manifest key, examples). The checklist
> below is the one used by the root `CLAUDE.md` and `/check-module`.

## 1. Generate it

```bash
php spark make:module Books                         # empty module
php spark make:module Books --crud=books_items      # with a kpcrudy CRUD of a new table
php spark make:module Books --crud=books_items --api  # and its kpapy API resource
php spark make:module Books --standalone            # kept in its own repository (see below)
php spark modules:sync                              # namespace, migrations, permissions, settings, assets
```

With Claude Code, `/new-module Books "what it manages"` asks what is missing, runs `make:module`,
fills the module in and ends with `/check-module Books`.

`make:module` writes the manifest, routes, `ca`/`es`/`en` language files, a controller and view
with an ES-module component, a migration, the initial-data and demo seeders, a widget and a
scheduled-task stub, a feature test (permissions, forged POST, migrations up/down/up) and the
module's **`CLAUDE.md`** (purpose, resources and permissions, decisions, open tasks, how to test).
Keep that file current: a new Claude Code session reads it first when it resumes the module.

## 2. Fill it in

Work only inside `modules/<Name>/`. Do not change the Core, another module or a shared
convention for one module's needs: write the request down and ask.

- `Config/Module.php`: resources (permission names `<module>-<resource>.<operation>`, every extra
  operation declared), menus, default grants, settings, widgets, scheduled tasks, notifications,
  calendars, `$requires`, `$backup`, `$csp`, `$outboundHosts`, `$api`. `$enabledByDefault = false`
  installs the module disabled (D-32-01).
- CRUD screens with kpcrudy through the Core (`CrudFactory::routes()`, `$this->crud()`,
  `$this->renderCrud()`, `docs/cruds.md`); API resources with kpapy (`docs/api.md`).
- One migration per table with `down()`; demo data only in `<Name>DemoSeeder`.
- Texts only in `Language/{ca,es,en}/<Name>.php` with the same keys.
- JavaScript as ES modules in `Assets/js/`, no inline scripts, handlers or styles (`docs/frontend.md`).

## 3. Module checklist

- manifest complete: resources, menus, default grants, settings, `$requires`, `$backup`, `$csp`,
  `$outboundHosts`, `$api`;
- one migration per table with `down()`; demo data only in `<Name>DemoSeeder`;
- CRUDs with kpcrudy (`CrudFactory`), API resources with kpapy; every operation declared in the
  manifest;
- `can:` on every back-office route, the `kpapy` filter on every API route;
- texts in `ca`, `es` and `en` (same keys); no hard-coded strings;
- assets in `Assets/`, ES modules, no inline JavaScript or styles;
- tests: permissions (including forged POSTs), migration up/down/up, API scopes, the module's own
  rules;
- docs and the module's `CLAUDE.md` updated;
- `php spark modules:sync`, `php spark permissions:check`, `vendor/bin/phpunit` and
  `php spark security:check` pass.

`/check-module <Name>` reviews these items, runs the checks and reports what is missing.

## 4. A module in its own repository

To distribute a module as a ZIP package, keep it in its own repository and develop it inside a
checkout of the skeleton, so the root `CLAUDE.md`, the commands and the tests apply:

```bash
git clone <skeleton repository> site && cd site
php spark make:module Books --standalone      # or clone your module into modules/Books
composer install && php spark modules:sync
vendor/bin/phpunit modules/Books
```

`--standalone` also writes `composer.json` (`type: kpskeletor-module`, PSR-4 `Modules\Books\`),
`README.md` (installing and developing) and a `CLAUDE.md` that summarises the skeleton's rules
and the checklist. Commit only inside `modules/Books/` (it can be a git submodule of your
checkout). Packages are installed with `php spark modules:install` (`docs/packages.md`).
