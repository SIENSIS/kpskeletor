---
description: Review a module against the module checklist and run its checks
argument-hint: <Name>
---

# /check-module $ARGUMENTS

Review the module `modules/$ARGUMENTS/` against the module checklist of the root `CLAUDE.md` (the
same list as `docs/creating-a-module.md`), run its checks and report what is missing. Do not fix
anything unless asked: report first.

If no name was given, ask for it. If `modules/<Name>/` does not exist, say so and stop.

## 1. Read

The module's `CLAUDE.md`, `Config/Module.php`, `Config/Routes.php`, its controllers, migrations,
seeders, language files, assets and tests.

## 2. Review each item (pass / fail / not applicable, with the file and line of every problem)

1. **`CLAUDE.md`** exists and is current: purpose (no `TODO` left), resources and permissions,
   decisions, open tasks, how to test.
2. **Manifest complete:** `$resources` (every `addOperation()` / bulk action / extra permission
   declared in `extra`, unused operations in `except`), `$menu`, `$defaultGrants`, `$settings`
   (with `rules`), `$requires`, `$backup`, `$csp`, `$outboundHosts`, `$api`; widgets, scheduled
   tasks and notifications declared; `ManifestValidator` passes (the module's manifest test).
3. **Database:** one migration per table, Forge / Query Builder only, `down()` implemented; no raw
   SQL with interpolated values; demo data only in `<Name>DemoSeeder` (extends
   `Modules\Core\Demo\DemoSeeder`).
4. **CRUDs and API:** CRUD screens with kpcrudy through `CrudFactory` (`CrudFactory::routes()`,
   `$this->crud()`, `$this->renderCrud()`; never `new Crud()`); API resources with kpapy
   (`setResource()` = the CRUD's resource, declared in `$api`).
5. **Routes:** a `can:` filter on every `backend/` route (personal screens too), the `kpapy`
   filter on every `api/v1/` route (or declared public); `Routes.php` never queries the database.
6. **Texts:** `Language/ca`, `es` and `en` with the same keys; no hard-coded UI strings in
   controllers, views or JavaScript; activity labels `<Module>.activity.<code>` for logged
   operations.
7. **Front end:** assets in `Assets/`, ES modules with bare specifiers, `data-component`; no inline
   `<script>`, `on*=` handlers or `style=""`; URLs with `asset()`.
8. **Security:** server-side checks for every action (record rules with `setPermission()` /
   `where()`, not only hidden buttons); uploads through the Storage service; rich text through
   `HtmlSanitizer`; redirects with `safe_redirect()`; outbound calls only through
   `OutboundClient`; actions logged with `activity_log()` / `security_log()`.
9. **Tests:** permissions including forged POSTs (`assertCrudRefused()`), migration up/down/up,
   API scopes (when it has an API), the module's own rules.
10. **Docs:** the module's `CLAUDE.md` and its documentation; new commands in `docs/commands.md`.

## 3. Run the checks

```bash
php spark modules:sync
php spark permissions:check
php spark csp:check
vendor/bin/phpunit modules/<Name>
vendor/bin/phpstan analyse modules/<Name>
vendor/bin/php-cs-fixer fix --dry-run --diff modules/<Name>
php spark security:check
```

`security:check` reports the whole site: only its failures caused by this module count (in a
development checkout, environment warnings such as HTTPS or `CI_ENVIRONMENT` are expected).

## 4. Report

A table with the ten items and the checks (pass / fail / n.a. and evidence), then the list of what
is missing in order of importance. End with **"Module <Name> complete"** only when every item and
every check passes; otherwise **"Module <Name> incomplete: N items"**.
