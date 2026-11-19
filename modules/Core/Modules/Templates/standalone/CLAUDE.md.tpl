# {{Name}} module (standalone repository)

> This module lives in its own repository and is developed inside a checkout of the kpskeletor
> skeleton, at `modules/{{Name}}/`. The skeleton's root `CLAUDE.md`, its commands
> (`/new-module`, `/check-module`) and its test suite then apply. Read this file first when you
> resume work, and keep it current.

**Purpose:** TODO: what the module manages, for whom, and what it adds to the back office, the
public site and the API.

## Working on it

```bash
git clone <skeleton repository> site && cd site
git clone <this repository> modules/{{Name}}     # or a git submodule
composer install
php spark modules:sync                             # namespace, migrations, permissions, settings, assets
php spark permissions:check
vendor/bin/phpunit modules/{{Name}}
```

Commit only inside `modules/{{Name}}/` (this repository). Never change the skeleton's Core or other
modules for this module's needs: write the request down and ask the skeleton's owner.

## The skeleton's rules (summary of its root `CLAUDE.md`)

- Everything lives in this folder: manifest `Config/Module.php` (resources, menus, default grants,
  settings, widgets, scheduled tasks, notifications, `$requires`, `$backup`, `$csp`,
  `$outboundHosts`, `$api`), routes discovered by CodeIgniter (`Routes.php` never queries the
  database), namespace `Modules\{{Name}}`.
- Back-office CRUD screens with **kpcrudy** through the Core (`CrudFactory::routes()`,
  `$this->crud('<resource>')`, `$this->renderCrud()`); JSON API endpoints with **kpapy** under
  `api/v1/<path>` (`kpapy` filter, `setResource()` = the CRUD's resource, declared in `$api`). Read
  their docs in `vendor/siensis/<lib>/` before using them; never edit `vendor/`.
- Permissions `{{name}}-<resource>.<operation>` generated from the manifest; every back-office route (`backend_path()`)
  has a `can:` filter; check them only through `Authorization::for($user)` / `can()`; default deny.
- One migration per table (Forge / Query Builder, `down()` always), demo data only in
  `{{Name}}DemoSeeder`.
- Texts only in `Language/{ca,es,en}/{{Name}}.php` (same keys); code, comments and docs in English.
- Views through the active theme (`BackendController::render()`, `theme_component()`); JavaScript
  as ES modules in `Assets/js/` (bare specifiers `core/…`, `{{name}}/…`); no inline `<script>`,
  `on*=` handlers or `style=""` (strict CSP).
- Uploads only through the Storage service; rich text through the Core's editor and
  `HtmlSanitizer`; external HTTP only through `OutboundClient` from scheduled tasks or commands;
  redirects with `safe_redirect()`; log actions with `activity_log()` / `security_log()`.
- PHP 8.2+, `declare(strict_types=1)`, typed code, PSR-12. Run the tests, PHPStan and
  php-cs-fixer of the skeleton before every commit.

## Module checklist (run `/check-module {{Name}}`)

- manifest complete; one migration per table with `down()`; demo data only in `{{Name}}DemoSeeder`;
- CRUDs with kpcrudy, API resources with kpapy; every operation declared in the manifest;
- `can:` on every back-office route, the `kpapy` filter on every API route;
- texts in `ca`, `es` and `en` (same keys); assets in `Assets/`, no inline JavaScript or styles;
- tests: permissions (with forged POSTs), migration up/down/up, API scopes, the module's own rules;
- this file and the docs updated; `modules:sync`, `permissions:check`, `phpunit` and
  `security:check` pass.

## Resources and permissions

- `{{name}}-main` (page: `view`): the module's screen `<back office>/{{name}}` (`backend_path('{{name}}')`).{{crudClaude}}{{apiClaude}}
- Default grants: admin `{{name}}-main.*`{{crudGrantClaude}}.

## Decisions

- None yet.

## Open tasks

- [ ] Describe the purpose above and replace the example screen, widget and scheduled task.
- [ ] Fill in `composer.json` (`name`, `license`) and `README.md`.
