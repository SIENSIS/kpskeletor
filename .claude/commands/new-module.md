---
description: Create a new module with make:module, fill it in and check it
argument-hint: <Name> [description of what it manages]
---

# /new-module $ARGUMENTS

Create a new module of the skeleton. Arguments: the module name in StudlyCase, then optionally a
description of what it manages.

1. **Read first:** the root `CLAUDE.md` ("Developing a module"), `docs/creating-a-module.md` and the
   reference modules: `modules/News` (reference) and `modules/Tasks` (tutorial module; its
   `CLAUDE.md` maps every feature to its file). Copy their patterns, not your memory. Read the
   kpcrudy / kpapy docs in `vendor/siensis/<lib>/` before using them.
2. **Ask what is missing:** if the name is missing or not StudlyCase, ask for it. If no description
   was given, ask what the module manages (its records and fields), who uses it (groups), whether
   it needs a CRUD (which table), an API for third parties, a public page, widgets, scheduled
   tasks, notifications or settings. Do not guess the data model.
3. **Plan:** state the plan and the files (tables, resources and permission names
   `<module>-<resource>.<operation>`, routes, settings). Permission names, URLs and setting names
   are public conventions: list them and wait for confirmation before going on.
4. **Generate:** `php spark make:module <Name>` with `--crud=<table>` when the main records are a
   CRUD and `--api` when they need a JSON API (needs `--crud`); add `--standalone` when the module
   will live in its own repository. Never create the folder by hand.
5. **Fill it in** inside `modules/<Name>/` only (never change the Core or another module for this
   module: if the Core must change, stop and ask):
   - manifest `Config/Module.php`: resources (every extra operation declared), menus, default
     grants, settings, `$requires`, `$backup`, `$csp`, `$outboundHosts`, `$api`, widgets,
     scheduled tasks, notifications, calendars;
   - one migration per table (Forge, `down()` implemented); demo data only in
     `<Name>DemoSeeder` through `DemoData`;
   - CRUD controllers through `CrudFactory` (`$this->crud('<resource>')`, `$this->renderCrud()`),
     API resources with kpapy (`setResource()` = the CRUD's resource, `kpapy` filter);
   - language files `Language/{ca,es,en}/<Name>.php` with the same keys, no hard-coded texts;
   - assets in `Assets/` (ES modules, `data-component`), no inline scripts, handlers or styles;
   - tests in `Tests/`: permissions with forged POSTs, migration up/down/up, API scopes, the
     module's own rules;
   - the module's `CLAUDE.md`: purpose, resources and permissions, decisions, open tasks, how to
     test.
6. **Sync and test:** `php spark modules:sync`, `php spark permissions:check`,
   `vendor/bin/phpunit modules/<Name>`, `vendor/bin/phpstan analyse`,
   `vendor/bin/php-cs-fixer fix --dry-run --diff`, `php spark csp:check`.
7. **Finish with `/check-module <Name>`** and fix what it reports. Add any new command to
   `docs/commands.md` and any open question to `docs/decisions.md` (status Open).
