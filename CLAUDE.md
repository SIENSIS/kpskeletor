# CLAUDE.md

Session prompts live in `deploy/` (run them in order: `deploy/next.md`); do not edit them unless asked. Each session merges the finished session branches into `main` first and ends with a report and screenshots in `deploy-status/NN-<slug>/`.

Modular CodeIgniter 4 application skeleton. The full specification is in `docs/specification.md`
(the build prompt); read the relevant section before changing a feature. `docs/decisions.md`
records the owner's decisions; they override the specification when they disagree.

## Required libraries (never replace them)

- **`siensis/kpcrudy` ^1.0**: every back-office CRUD screen (`Kpcrudy\Crud`), always through the
  Core: `CrudFactory::routes()` + `$this->crud('<resource>')` + `$this->renderCrud()`, never
  `new Crud()` (`docs/cruds.md`; `permissions:check` enforces it).
- **`siensis/kpapy` ^1.0**: every JSON API endpoint (`Kpapy\Resource`), routed under `api/v1/<path>`
  with the `kpapy` filter, `setResource()` = the CRUD's resource name, declared in the manifest
  `$api` (`docs/api.md`; the API core is the `Api` module).
- Install from Packagist only: `composer require siensis/kpcrudy:^1.0 siensis/kpapy:^1.0`. Never
  `dev-main`, a `repositories` entry or a local path. If Composer fails, stop and report it.
- **Read their docs in `vendor/` before using them**, not from memory:
  `vendor/siensis/<lib>/README_EN.md`, `docs/functions_EN.md` (every method and option),
  `docs/howto_EN.md` (recipes), `docs/commands_EN.md`. Their demos (`src/Demo/`) show working
  examples.
- Use their features instead of custom code: `kpcrudy:make` / `kpapy:make`, `addOperation()`,
  `hideFields()`, `where()`, `setTranslations()`, `multiselect`, permission resolvers, masked ids,
  signed URLs, uploads.
- Configure in `app/Config/Kpcrudy.php` / `app/Config/Kpapy.php` or extend their classes in
  `modules/Core`. **Never edit `vendor/`.** Missing features go to `docs/library-requests.md`.
- The two libraries are independent; the Core connects them: one shared `CrudPermissions`
  resolver (last parameter typed `object`), the same resource names, the same `app.maskKey`.

## Commands

```bash
composer install
php spark modules:sync            # migrations, seeds, permissions, settings, assets
php spark permissions:check       # manifests, catalogue, grants, Shield matrix, route filters
php spark assets:publish --watch  # while editing modules/*/Assets (--check: CI)
php spark csp:check               # no inline scripts, handlers or styles in views
vendor/bin/phpunit                # tests (SQLite)
vendor/bin/phpstan analyse        # level 6
vendor/bin/php-cs-fixer fix --dry-run --diff
php spark security:check          # before any production deploy
```

Run the tests, PHPStan and php-cs-fixer before every commit; show the results.

## Architecture and conventions

- Everything lives in **modules** (`modules/<Name>/`), described by a typed manifest
  `Config/Module.php` (resources, menus, settings, widgets, tasks, notifications, api, csp,
  outboundHosts). Never hard-code a module in the Core.
- Loading only through CodeIgniter discovery; never `require` route files; `Routes.php` never
  queries the database. Each module has its own namespace (`Modules\News` → `modules/News`) listed
  in the generated `app/Config/ModuleNamespaces.php` (written by `make:module`, `modules:sync` and
  `modules:install`; never edit it by hand). A module copied by hand needs `php spark modules:sync`.
- **Permissions:** `<module>-<resource>.<operation>` (e.g. `news-posts.edit`), generated from the
  manifest, never written by hand. Only Core and single-resource modules may override the prefix
  with the resource `'name'` (e.g. `backup.*`); duplicates are rejected and `Security`/`Update` are
  reserved module names. Every `backend/` route has a `can:` filter, personal screens included
  (their own resources, granted to every group by `defaultGrants`); every `api/v1/` route has the
  `kpapy` filter or is declared public. Default deny. `can:a,b` = any, `can:all,a,b` = all; the
  `backend` chain covers `backend/*` by itself. Check permissions only through
  `Authorization::for($user)` / `can()` / `CrudPermissions`, change grants only through
  `GrantManager` (`docs/rbac.md`); `php spark permissions:check` must pass.
- **Database:** one migration per table, Forge / Query Builder only, `down()` always implemented,
  released migrations never edited. Demo data only in `<Name>DemoSeeder` (extends
  `Modules\Core\Demo\DemoSeeder`) through `DemoData`; load it with `php spark modules:demo <Name>`.
  Module routes point at controllers (a disabled module answers 404 through its controller namespace).
- **Menus:** back-office entries only in the manifest `$menu` (resource or route + permission,
  `zone` `sidebar`/`settings`/`user`, `parent`, `order`); the menu is generated, filtered by
  permission and cached per group set (`docs/menus.md`). Security headers, rate limits
  (`ratelimit:<limit>`, `RateLimiter`), `safe_redirect()` and `OutboundClient`: `docs/security.md`,
  `docs/outbound-requests.md`.
- **Views and themes:** render through the active theme (`BackendController::render()` /
  `theme_view()`, `$this->extend($layout)`), compose the standard views and components
  (`theme_component('page_header'|'card'|'form_layout'…)`, `ui_badge()`, `ui_date()`…) and never
  hard-code a theme path; a theme overrides a module view in
  `themes/<slug>/Views/overrides/<Module>/…` and colours through CSS variables or option defaults
  (`docs/themes.md`, `php spark make:theme <Name>`).
- **Front end:** Bootstrap 5.3 served locally, native ES modules in `modules/<Name>/Assets/js`
  imported with bare specifiers (`core/…`, `<module>/…`; never relative paths), components by
  `data-component`, URLs with `asset('<owner>::<path>')` (`docs/frontend.md`). **No inline `<script>`, `on*=` handlers or `style=""`** (strict
  CSP with nonces). Never edit `public/assets/modules/…` (published copies).
- **Languages:** code, comments and docs in English; UI texts only in language files, complete in
  `ca`, `es` and `en` (same keys; CI checks it). Catalan is the default, Spanish the fallback.
- PHP 8.2+, `declare(strict_types=1)`, typed code, PSR-12.

## Developing a module

- **Start:** `/new-module <Name>` or `php spark make:module <Name> [--crud=<table>] [--api]`. Read
  `docs/creating-a-module.md` first; copy patterns from `modules/News` (reference module) and
  `modules/Tasks` (tutorial module), not from memory.
- **Work inside the module:** everything goes in `modules/<Name>/`. Do not change the Core,
  another module or a shared convention for this module's needs: if the Core must change, stop
  and ask.
- **Keep `modules/<Name>/CLAUDE.md` current:** purpose, resources and permissions, decisions,
  open tasks and how to test. Read it first when resuming work on a module.
- **Module checklist** (run `/check-module <Name>` before calling it done):
  - manifest complete: resources, menus, default grants, settings, `$requires`, `$backup`,
    `$csp`, `$outboundHosts`, `$api`;
  - one migration per table with `down()`; demo data only in `<Name>DemoSeeder`;
  - CRUDs with kpcrudy (`CrudFactory`), API resources with kpapy; every operation declared in
    the manifest;
  - `can:` on every back-office route, the `kpapy` filter on every API route;
  - texts in `ca`, `es` and `en` (same keys); no hard-coded strings;
  - assets in `Assets/`, ES modules, no inline JavaScript or styles;
  - tests: permissions (including forged POSTs), migration up/down/up, API scopes, the module's
    own rules;
  - docs and the module's `CLAUDE.md` updated;
  - `php spark modules:sync`, `php spark permissions:check`, `vendor/bin/phpunit` and
    `php spark security:check` pass.
- **Module in its own repository:** develop it inside a checkout of the skeleton
  (`modules/<Name>/`) so this file, the commands and the tests apply.

## Security rules (non-negotiable)

- Production is the default environment; never weaken a default to make something work.
- Every action is authorised on the server (resolver + record rules); masked ids are not access
  control; the UI only hides what the server also enforces.
- Sensitive actions require re-authentication (`reauth` filter).
- Authentication and sessions follow `docs/auth.md` (generic messages, rate limits, session
  tracking); after changing a user's groups or permissions call
  `SessionTracker::privilegesChanged()`; security emails go through `SecurityMailer`.
- No raw SQL with interpolated values; no `getPost()` wholesale into inserts; escape all output;
  HTMLPurifier for rich text; redirects only to internal paths (`safe_redirect()`).
- Uploads through the Storage service only (allow-lists, random names, no SVG/HTML by default).
- No outbound HTTP on regular requests; external calls only through `OutboundClient`
  (HTTPS + allow-list).
- Secrets only in `.env`; never commit real values, keys, passwords or tokens.
- Write security-relevant changes to the activity log, and add a test for every security rule
  you touch.

## Workflow

- Small, reviewable commits following the order of the session files in `deploy/` (it takes
  precedence over the order at the end of the specification); before each step, state the plan
  and the files; after it, run the checks.
- Branches: use the branch the environment assigns (or `session-NN-<slug>`) and record it in
  `docs/progress.md`; sessions are identified by the pull request title "Session NN: …". Never
  force-push; if `main` cannot be pushed, stop and ask the owner to merge the pull request.
- Keep `docs/` in sync with the code (including `docs/commands.md` for every new command) and
  update this file when a convention changes.
- Ask before: adding a dependency, changing a public convention (permission names, manifest keys,
  URLs), or anything that weakens security. Libraries named in the specification (TOTP, QR code,
  HTMLPurifier, Flysystem) and an MIT/BSD-licensed rich-text editor and calendar are
  pre-approved; install them in the session that uses them.
- Open questions go to `docs/decisions.md` (status Open); the owner answers them with
  `deploy/decide.md`.
