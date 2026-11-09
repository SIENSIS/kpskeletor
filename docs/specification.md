## Role and goal

You are a senior PHP / CodeIgniter 4 architect. Build **a reusable, themeable application
skeleton** on CodeIgniter 4. A new business application is started by cloning it and adding
modules. Everything the application does lives in **modules**. The skeleton provides:

1. **Module system:** discovery, enable/disable, install/upgrade (migrations, seeds, assets),
   a typed module manifest, and a common base that every module shares.
2. **Authentication** with `codeigniter4/shield`, with **2FA by email or by an authenticator
   app (TOTP)**, chosen by each user.
3. **Central, atomic RBAC.** Modules declare **resources** (one per CRUD or screen) in their
   manifest; the skeleton generates one permission per operation (`news-posts.edit`), registers
   them in Shield and enforces them through **one central permission resolver in the Core,
   shared by kpcrudy (web) and kpapy (API)**. Default deny.
4. **Automatic back-office menus**, built from the manifests (several entries, levels and zones
   per module), plus a **menu editor** for the public site.
5. **Themes:** a theme system with a default **Bootstrap 5** theme (back office, public site and
   authentication layouts).
6. **Modular front-end assets:** no JavaScript inside the HTML; ES modules in external files,
   shipped by each module and published to `public/`.
7. **Configuration** in files (CI4 `Config` classes + `.env`), plus runtime settings stored
   with `codeigniter4/settings`, declared by modules and editable from a generated UI.
8. **Cross-module services:** notifications, scheduled tasks, activity log, dashboard widgets,
   file storage and versioning.
9. **CRUD screens with [kpcrudy](https://github.com/SIENSIS/kpcrudy)** whenever possible.
10. **A JSON API core** built with [kpapy](https://github.com/SIENSIS/kpapy), an independent
    REST API library for CodeIgniter 4 that the skeleton integrates: basic endpoints (profile,
    news, events, notifications) secured with Shield access tokens, and modules can expose their
    own resources.
11. **Responsive** on phones, tablets and desktops, and installable as a minimal PWA.
12. i18n, security defaults, CLI tooling, tests and documentation.

The skeleton ships these modules:

| Module | Purpose |
|--------|---------|
| `Core` | Kernel: module registry, RBAC, menus, themes, assets, settings services, notifications, scheduler, widgets, storage, security status, base classes |
| `Auth` | Login, logout, password reset / magic link, 2FA (email or TOTP app), profile and sessions |
| `Users` | Users, groups and the permission matrix |
| `Settings` | Generated settings UI, grouped by module |
| `Dashboard` | Back-office home with widgets, configurable per group and per user |
| `Activity` | Searchable activity (audit) log, built with kpcrudy |
| `Pages` | Content pages for the public site (and private pages) |
| `Menus` | Menu editor for the public site, and overrides of the back-office menu |
| `Files` | File manager with the basic operations, and a file picker for other modules |
| `News` | **Reference module**: public, private and group news, and notices on the dashboard |
| `Events` | Event calendar with administration, calendar views, iCal feed and a widget |
| `Updates` | Shows when a new version of the core (or of a module) is available; updating is a console command |
| `Backup` | Backup and restore of the whole application, from the console and from the UI |
| `Tasks` | **Tutorial module**, disabled by default: holds the working code of every tutorial in `docs/tutorials/` |

## Technical baseline

* **PHP 8.2+**, `declare(strict_types=1)` everywhere, typed properties and return types,
  PSR-12, PHPStan level 6 (with `codeigniter/phpstan-codeigniter`), php-cs-fixer.
* **CodeIgniter 4.7.x** (latest patch), `codeigniter4/shield` ^1.x, `codeigniter4/settings`
  ^2.x, **`siensis/kpcrudy` ^1.0** and **`siensis/kpapy` ^1.0** (see "Required libraries" below:
  they are mandatory and must not be replaced).
* **Other libraries (small and well maintained):** a TOTP library (e.g. `spomky-labs/otphp`)
  and a QR code generator (e.g. `chillerlan/php-qrcode`, rendered as SVG, no external service),
  `ezyang/htmlpurifier` for HTML input, `league/flysystem` behind the storage interface (local
  adapter only by default).
* **Dependencies:**
  * Use caret constraints, `minimum-stability: stable`, and declare the PHP extensions in
    `composer.json` (`intl`, `mbstring`, `json`, `openssl`, `fileinfo`, `zip`, `gd`, …).
  * Run `composer audit` in CI.
* **Databases:** MySQL/MariaDB and SQLite (tests). Only use Forge / Query Builder, so
  migrations stay portable.
* **Front end:** Bootstrap 5.3 + Bootstrap Icons, served locally (no CDN). No AdminLTE and no
  jQuery in the skeleton's own code. JavaScript as native ES modules (see "Front-end assets and
  JavaScript"). Third-party front-end libraries (rich-text editor, calendar) are vendored with a
  manifest of versions and licences, and must have permissive licences (MIT/BSD).
* **Languages:** **Catalan is the default language** of the application (`defaultLocale = 'ca'`,
  also for new users and the public site) and **Spanish is the fallback** for any text missing in
  the requested language. English stays the language of the code, the comments, the
  documentation and the language-file keys, and is the last resort.
  * **Fallback chain:** requested locale → locale without region (`ca-ES` → `ca`) → `es` →
    `en`. CodeIgniter's `Language` class falls back to `en` only, so the Core provides its own
    `Language` service (configurable chain in `Config\App` / a setting) used for every module
    and for kpcrudy's texts.
  * Supported locales `ca`, `es`, `en`, negotiated from the user's profile, then the browser,
    then the default; the site can restrict the available ones (setting).
* **Every module shipped with the skeleton (Core included) is complete in Catalan, Spanish and
  English:**
  * language files (`Language/en|ca|es/<Name>.php`) with the same keys in the three languages;
  * email and notification templates, menu labels, permission and setting descriptions, widget
    titles, CLI messages shown to users, validation messages (CodeIgniter's own through
    `codeigniter4/translations`, plus a Catalan file where that package has none) and the
    kpcrudy texts;
  * the theme's strings and the user guide.
  No hard-coded text. A CI test checks that the three languages have exactly the same keys.
  **Content** (pages, news, events) can be multilingual per module, with kpcrudy's
  `setTranslations()`, with Catalan as the required language. Third-party modules should ship
  at least Catalan or Spanish; missing texts follow the fallback chain.

## Required libraries: kpcrudy and kpapy

Two libraries by the same author are **the foundation of every screen and every endpoint** of
the skeleton. They are mandatory: never replace them with hand-written code or with another
package, and never copy their code into the project.

| Package | Used for | Documentation (inside `vendor/` once installed) |
|---|---|---|
| [`siensis/kpcrudy`](https://packagist.org/packages/siensis/kpcrudy) `^1.0` | Every back-office CRUD screen: lists, forms, details, filters, export/import, custom operations, permissions, masked ids, translations | `vendor/siensis/kpcrudy/README_EN.md`, `docs/functions_EN.md`, `docs/howto_EN.md`, `docs/commands_EN.md`, `docs/new_theme_EN.md` |
| [`siensis/kpapy`](https://packagist.org/packages/siensis/kpapy) `^1.0` | Every JSON API endpoint: resources, Shield tokens and scopes, app login with 2FA, ETags, OpenAPI and the documentation page with "Try it" | `vendor/siensis/kpapy/README_EN.md`, `docs/functions_EN.md`, `docs/howto_EN.md`, `docs/commands_EN.md` |

**Rules for whoever builds the skeleton (an AI assistant or a developer):**

1. **Install them first**, before any CRUD or API code, from Packagist:

   ```bash
   composer require codeigniter4/shield codeigniter4/settings siensis/kpcrudy:^1.0 siensis/kpapy:^1.0
   ```

   Never use `dev-main`, a `repositories` entry or a local path. If Composer cannot install them
   (network, version conflict), **stop and report the error**: do not continue without them.
2. **Read their documentation from `vendor/` before using them**, not from memory: the READMEs and
   `docs/functions_EN.md` (every method and option), `docs/howto_EN.md` (recipes) and
   `docs/commands_EN.md`. Their integrated demos (`php spark kpcrudy:demo install`,
   `php spark kpapy:demo install`, code in `src/Demo/`) show working examples of every feature;
   install them in a scratch database when in doubt, never in the skeleton's own database.
3. **Use their features instead of custom code:** CRUDs from `Kpcrudy\Crud` (generated with
   `php spark kpcrudy:make --namespace "Modules\<Name>"`), API resources from `Kpapy\Resource`
   (`php spark kpapy:make`); permissions through their `PermissionResolver` interfaces; custom
   operations with `addOperation()`; `hideFields()`, `where()`, `setTranslations()`,
   `multiselect`; their masked ids, signed URLs, CSP-friendly views and translations (but not
   their upload field types, which bypass the Storage service: see "CRUDs with kpcrudy"). Write custom code only for what they do not cover (the screens listed as
   hand-written in "CRUDs with kpcrudy").
4. **Configure, extend, never patch:** configuration goes in `app/Config/Kpcrudy.php` and
   `app/Config/Kpapy.php` (or the Core's config classes that extend them); behaviour is changed by
   extending their classes in the Core (e.g. `CrudFactory`, the shared resolver, a 2FA provider).
   Files in `vendor/` are never edited.
5. **Missing features:** if something cannot be done with their public API, do not work around
   it with copies of their code: write it down in `docs/library-requests.md` (library, what is
   missing, why, the temporary solution in the Core) so it can be added to the library.
6. **The two libraries are independent of each other.** The skeleton is what connects them: one
   shared permission resolver, the same resource names and the same `app.maskKey` (see "JSON
   API").
7. **Updates:** stay on `^1.0`; `composer update siensis/kpcrudy siensis/kpapy` brings compatible
   versions; read their `CHANGELOG.md` before a major upgrade.
8. Record these rules in the project's **`CLAUDE.md`** (see below) so every later session
   follows them too.

**`CLAUDE.md`:** the repository has a `CLAUDE.md` at its root with the standing rules for AI
assistants (required libraries, conventions, security rules, how to run the tests). Create it in
the first commit (a suggested version is provided with this prompt) and keep it up to date when a
convention changes. Save this whole specification as **`docs/specification.md`** in the same
commit: `CLAUDE.md` points to it, so later sessions can read the section they are working on.

**AI-assisted module development.** The skeleton is meant to be extended by new Claude Code
sessions that only know what the repository tells them, so it ships:

* **`CLAUDE.md` at the root** with a "Developing a module" section: how to start
  (`make:module`), the reference modules (`News`, `Tasks`), the **module checklist** (below) and
  what not to do (changing the Core for a single module's need without asking).
* **One `CLAUDE.md` per module** (`modules/<Name>/CLAUDE.md`), generated by `make:module` from a
  template: purpose of the module, its resources and permissions, decisions taken, open tasks
  and how to test it. Claude Code reads it when working inside that folder, so a new session can
  continue where the previous one stopped. It is kept up to date with the module and excluded
  from module ZIP packages only if the author chooses so (option of the packager).
* **Project commands** in `.claude/commands/` (Claude Code slash commands, Markdown files with
  the steps):
  * `/new-module <Name> [description]`: asks what the module manages if not given, runs
    `make:module` (with `--crud` / `--api` when it applies), fills in the manifest, migrations,
    language files and the module's `CLAUDE.md`, and ends with `/check-module`;
  * `/check-module <Name>`: reviews the module against the checklist, runs its checks and
    reports what is missing.
* **The module checklist** (the same list in `CLAUDE.md`, `/check-module` and
  `docs/creating-a-module.md`): complete manifest (resources, menus, default grants, settings,
  `$requires`, `$backup`, `$csp`, `$outboundHosts`, `$api`); one migration per table with
  `down()`; CRUDs with kpcrudy and API resources with kpapy; `can:` on every back-office route and
  the `kpapy` filter on every API route; texts in `ca`, `es` and `en`; a `<Name>DemoSeeder` when
  useful; no inline JavaScript; tests (permissions with forged POSTs, migration up/down/up, API
  scopes); the module's `CLAUDE.md` and documentation; and `modules:sync`,
  `permissions:check`, the test suite and `security:check` passing.
* **Modules developed in their own repository** (to be distributed as ZIP packages): the
  developer works on a checkout of the skeleton with the module in `modules/<Name>/` (a git
  submodule or a separate clone), so the root `CLAUDE.md`, the commands and the tests are
  available; `make:module --standalone` also writes a `composer.json`, a `README.md` and the
  module's own `CLAUDE.md` with a summary of the skeleton's rules and how to test the module
  inside the skeleton. Documented in `docs/creating-a-module.md`.

## Directory layout

```
app/                      Minimal: Config overrides, BaseController, Common.php
modules/
  Core/  Auth/  Users/  Settings/  Dashboard/  Activity/
  Pages/  Menus/  Files/  News/  Events/  Updates/  Backup/  Tasks/
themes/
  default/                The default Bootstrap 5 theme (views, assets, theme manifest)
public/
  assets/core/            Published core assets (JS/CSS)
  assets/modules/<name>/  Published module assets (never edited by hand)
  assets/vendor/          Vendored libraries (Bootstrap, icons, editor, calendar)
  themes/<theme>/         Published theme assets
  uploads/                Public files of the file manager (images and documents marked public)
writable/
  storage/                Private files (outside public/)
  backups/                Backups (outside public/)
docs/                     Documentation (see the list at the end)
tests/                    PHPUnit (unit + feature), SQLite test DB
```

* **Autoloading:** `Modules\\` → `modules/` and `Themes\\` → `themes/` (PSR-4), registered once
  in `app/Config/Autoload.php`, plus **one PSR-4 namespace per module** (`Modules\News` →
  `modules/News`) read from a generated static file, `app/Config/ModuleNamespaces.php`, which
  `Autoload.php` merges. The file is written by `make:module`, `modules:sync` and
  `modules:install` (never edited by hand); a module folder copied by hand becomes loadable after
  `php spark modules:sync`. Do **not** build the namespace list dynamically at runtime (no scan of
  `modules/` per request).
* **Discovery:** CodeIgniter's module discovery (routes, filters, languages, commands,
  registrars, services) over those per-module namespaces is the **only** loading mechanism (it
  only looks at `<namespace root>/Config/Routes.php` and the like, hence one namespace per
  module). Never `require` module route files by hand as well.

## The module contract

Every module is a folder `modules/<Name>/` with this structure:

```
Config/Module.php          REQUIRED: the manifest (extends Modules\Core\Config\ModuleManifest)
Config/Routes.php          Routes; CRUD routes with CrudFactory::routes() (adds 'backend' + 'can:<resource>.list'), other back-office routes declare their 'can:' filter
Config/Registrar.php       Optional: merges into core configs (filters, etc.)
Controllers/  Models/  Entities/  Services/
Views/                     Module views (a theme can override them)
Language/<locale>/<Name>.php
Database/Migrations/  Database/Seeds/<Name>Seeder.php
Assets/js/  Assets/css/  Assets/img/    Front-end assets, published to public/assets/modules/<name>/
Widgets/                   Dashboard widgets (implement WidgetInterface)
Tasks/                     Scheduled tasks (implement TaskInterface)
Notifications/             Notification types (implement NotificationType)
```

The **manifest** is a typed PHP class: the single source of truth for everything the skeleton
needs to know. Use typed arrays, validated on sync (fail loudly with clear messages). The module
names `Security` and `Update` are reserved (their settings permissions would clash with
`settings.security` and `settings.update`):

```php
final class Module extends ModuleManifest
{
    public string $name        = 'News';
    public string $version     = '1.0.0';
    public string $icon        = 'bi bi-newspaper';
    public array  $requires    = ['Core' => '^1.0'];          // module dependencies

    /** Optional: where Updates looks for new versions of this module (see Updates) */
    public ?string $repository = null;                        // e.g. 'github:acme/news-module'

    /**
     * Resources: one per CRUD or screen (not per table). Permission names are
     * "<module>-<resource>.<operation>" (lower case), e.g. "news-posts.edit". Optional 'name'
     * overrides the generated prefix, only for Core resources and single-resource modules
     * (e.g. 'name' => 'files' gives "files.list"); duplicates in the catalogue are refused.
     *
     *  - type 'crud': kpcrudy's operations (list, show, add, edit, delete, export, import,
     *    trash, restore, purge) minus 'except', plus 'extra'
     *  - type 'page': only 'view' plus 'extra'
     *  - extra: custom operations (addOperation), action / bulk action permissions, and any
     *    other atomic permission the module checks itself (e.g. a field hidden with hideFields())
     */
    public array $resources = [
        'posts' => [
            'label'  => 'News.res.posts',
            'type'   => 'crud',
            'except' => ['import'],
            'extra'  => ['publish' => 'News.perm.publish', 'author' => 'News.perm.seeAuthor'],
        ],
        'categories' => [
            'label'  => 'News.res.categories',
            'type'   => 'crud',
            'except' => ['trash', 'restore', 'purge', 'import'],
        ],
        'stats' => ['label' => 'News.res.stats', 'type' => 'page'],
        'read'  => ['label' => 'News.res.read',  'type' => 'page'],   // reader screen for every user
    ];

    /**
     * Default grants per group (atomic names or Shield wildcards), first install only. They are
     * completed with the implied permissions (see "RBAC"); the validator warns about incomplete sets.
     */
    public array $defaultGrants = [
        'admin'  => ['news-posts.*', 'news-categories.*', 'news-stats.*', 'news-read.*', 'news-api.*'],
        'editor' => ['news-posts.list', 'news-posts.show', 'news-posts.add', 'news-posts.edit', 'news-posts.publish', 'news-categories.list', 'news-read.view', 'news-api.*'],
        'user'   => ['news-read.view', 'news-api.*'],
    ];

    /**
     * Back-office menu entries. With 'resource', the route (named after the resource) and the
     * permission (".list", or ".view" for pages) come from the resource. Other entries give
     * 'route' and 'permission'. 'zone' places the entry (sidebar by default, settings, user
     * menu...); 'parent' nests it.
     */
    public array $menu = [
        ['resource' => 'posts',      'label' => 'News.menu.news', 'icon' => 'bi bi-newspaper', 'order' => 20],
        ['resource' => 'categories', 'label' => 'News.menu.categories', 'parent' => 'posts'],
        ['resource' => 'stats',      'label' => 'News.menu.stats',      'parent' => 'posts'],
        ['route' => 'settings.news', 'label' => 'News.menu.settings', 'zone' => 'settings', 'permission' => 'settings.news'], // generated, see "Configuration and settings"
    ];

    /** Link types the module offers to the public menu editor (e.g. "a news category") */
    public array $menuLinks = [\Modules\News\Menu\CategoryLink::class];

    /**
     * Runtime settings (codeigniter4/settings): key => definition; UI is generated.
     * Types: string, text, int, float, bool, select, email, url, color, json, list (one entry
     * per line). 'group' is a heading on the module's tab; 'security' or 'security.<sub>'
     * moves the setting to Settings › Security (settings.security + re-authentication).
     */
    public array $settings = [
        'perPage'       => ['type' => 'int',  'default' => 20,   'rules' => 'is_natural_no_zero', 'label' => 'News.settings.perPage'],
        'noticesOnHome' => ['type' => 'bool', 'default' => true, 'label' => 'News.settings.noticesOnHome'],
    ];

    /** Dashboard widgets, scheduled tasks and notification types contributed by the module */
    public array $widgets       = [\Modules\News\Widgets\Notices::class, \Modules\News\Widgets\LatestNews::class];
    public array $tasks         = [\Modules\News\Tasks\ExpireNotices::class];
    public array $notifications = [\Modules\News\Notifications\NewNotice::class];

    /** Content the module contributes to backups beyond its tables (paths relative to storage) */
    public array $backup = ['files' => ['news']];

    /** Calendars the module publishes events to ('permission' optional), and on-the-fly event providers (see Events) */
    public array $calendars         = ['notices' => ['label' => 'News.cal.notices', 'color' => '#dc3545']];
    public array $calendarProviders = [];

    /** API resources (kpapy) under /api/v1: metadata checked against Config/Routes.php (see "JSON API") */
    public array $api = ['posts' => ['path' => 'news', 'handler' => '\Modules\News\Api\Posts::index']];

    /** Extra CSP sources and outbound hosts the module needs (see "Security baseline") */
    public array $csp           = [];                         // e.g. ['frame-src' => ['https://www.youtube-nocookie.com']]
    public array $outboundHosts = [];                         // e.g. ['api.example.com']
}
```

### Common module base

All modules share the same foundation, so they look and behave the same and a new module only
writes what is specific to it:

* **Base classes** in Core: `BackendController` (layout, breadcrumbs, `can()`, `crud()` /
  `renderCrud()`, flash/toast helpers, JSON responses), `FrontendController` (public layout,
  page metadata/SEO, theme regions), `BaseModel` (timestamps, soft deletes, `created_by` /
  `updated_by`, audit hooks), `BaseEntity`, and `ModuleManifest`.
* **Standard views** (overridable by the theme): page header with title, breadcrumbs and action
  buttons; CRUD container; form layout; empty state; tabs; cards; the 403/404/500 pages. Modules
  compose them instead of writing their own HTML frames.
* **View components** (`view_cell`s or small view helpers): badge, status, avatar, date,
  file link, user link, pagination info.
* **Conventions:** language file `Language/<locale>/<Name>.php` with `title`, `menu.*`, `res.*`,
  `perm.*`, `settings.*` keys; route names `<module>-<resource>`; table prefix optional; assets
  under `Assets/`.
* **`make:module <Name> [--crud=<table>]`** generates the complete empty module (manifest,
  routes, language files, base controller, views, asset folders, migration stub, a widget stub, a
  scheduled-task stub, a test) from templates that follow these conventions, and adds the module's
  namespace to `app/Config/ModuleNamespaces.php`. With `--crud`, it also generates a kpcrudy
  controller from an existing table (reusing `kpcrudy:make --namespace "Modules\<Name>" --extends
  "Modules\Core\Controllers\BackendController" --as <module>-<resource> --filter backend`, then
  rewriting the generated route line to `CrudFactory::routes()` and the constructor to
  `CrudFactory::make()`) and adds the resource to the manifest. With `--api`, it also generates the kpapy resource (reusing
  `kpapy:make`) and declares it in `$api`. It always writes the module's `CLAUDE.md` from a
  template (see "AI-assisted module development"); with `--standalone`, also the files needed to
  develop the module in its own repository.

### Database schema: migrations per table

The database is **never created from a dump or a single SQL file**. The Core and every module
build and evolve their own tables with CodeIgniter migrations:

* **Each module owns its tables** and its migrations live in `Database/Migrations/` of that
  module (namespace `Modules\<Name>\Database\Migrations`), so installing, upgrading or
  uninstalling a module only touches its own schema. The Core does the same for the kernel
  tables (modules, permissions, grants, groups, settings, notifications, tasks, activity log,
  files…). The migrations shipped by `CodeIgniter\Shield`, `CodeIgniter\Settings` and `Kpapy`
  are run as they ship, by `modules:sync` before the modules'; uninstalls and rollbacks never
  touch them.
* **One migration per table** for its creation (`2026-01-10-000001_CreateNewsCategories`,
  `…000002_CreateNews`), in dependency order so foreign keys can be created; a junction table
  gets its own migration after both sides.
* **Every later change is a new migration** (add a column, an index, rename…). A migration that
  has been released is never edited; the module version is bumped when its schema changes.
* **Forge and Query Builder only** (no raw, engine-specific SQL), so the same migrations work on
  MariaDB/MySQL and SQLite; table names use the configured `DBPrefix`.
* **`down()` is always implemented** and reverses exactly its `up()` (needed by
  `modules:uninstall` and by rollbacks after a failed upgrade).
* Indexes and foreign keys are declared explicitly (`ON DELETE` behaviour chosen on purpose);
  timestamps and soft-delete columns follow the `BaseModel` conventions.
* **Data is not schema:** required initial data (default categories, settings, the module's
  menu customisations if any) goes in an idempotent `<Name>Seeder` run on first install;
  **demo/sample data** goes in a separate `<Name>DemoSeeder` (see "Demo data" below). Data
  changes needed by an upgrade go in a migration that uses the Query Builder.
* Migrations run per module through `modules:sync` (also from the Modules screen); the
  registry stores each module's installed version, and `modules:status` shows pending
  migrations per module.
* **CI test:** for every module, migrate up → down → up on SQLite and MariaDB, and check that
  the uninstall leaves no table of that module behind.

### Demo data

Each module may ship a `<Name>DemoSeeder` with realistic sample data, to try the module quickly.
Demo data is written in **a single language, Catalan** (the default language); it does not need
translations, even in modules with multilingual content (only the Catalan row is filled).

* **Tracked, so it can be removed:** demo seeders insert through a `DemoData` helper that records
  every row it creates (`demo_records`: module, table, primary key, created_at) and every file
  it stores. "Remove demo data" deletes exactly those rows (in reverse order, respecting
  foreign keys) and files, leaving real data untouched; rows that real data now depends on are
  reported instead of deleted.
* **Idempotent:** loading the demo data twice does not duplicate it (the seeder checks the
  registry first); "Reload" removes and loads it again.
* **Where it can be loaded:**
  * **Interactive install:** `php spark app:install` asks "Load demo data? [y/N]" and lets you
    pick the modules; non-interactive with `--demo` / `--demo=News,Events`.
  * **Console:** `php spark modules:install <zip> --demo`, `modules:demo <Name> [--remove|--reload]`,
    `modules:sync --demo`.
  * **Modules screen (UI):** "Load demo data", "Reload" and "Remove demo data" buttons per module,
    and a staged ZIP install has a "load demo data" checkbox. The screen shows which modules
    have demo data loaded (and a banner "This site contains demo data" in the back office).
* **Safety:** permission `modules.demo` (superadmin by default); with
  `CI_ENVIRONMENT=production` the buttons are hidden and the commands refuse unless the setting
  `Packages.allowDemoInProduction` is enabled (see "Package validation" for `Config\Packages`); a confirmation is always required; demo users get
  random passwords shown once (never fixed passwords); everything is written to the activity
  log. Backups mark whether they contain demo data.

### Module lifecycle

`php spark modules:sync`, and the same action from the back office, must:

1. Discover the module folders, validate the manifests and check dependencies and versions;
   rewrite `app/Config/ModuleNamespaces.php` when the set of modules changed.
2. Run pending migrations (first those of Shield, Settings and kpapy, then per module namespace),
   and the seeder on first install.
3. Upsert the module registry (`modules`: name, version, enabled, installed_at, updated_at).
4. Generate and upsert the permission catalogue from the manifests' `resources`
   (`permissions`: module, resource, operation, name, description), honouring a resource's
   `'name'` and refusing duplicate names. Remove permissions of deleted modules or resources only
   after confirmation (`--prune`).
5. Apply `defaultGrants` (completed with the implied permissions) only on first install of a
   module, and rewrite Shield's computed groups, permissions and matrix (see "RBAC").
6. Register settings definitions and their defaults (no values are overwritten), widgets, tasks,
   notification types and menu link types; check that each `$api` entry matches a route in the
   module's `Config/Routes.php`.
7. **Publish assets** (`assets:publish`): module `Assets/` → `public/assets/modules/<name>/`,
   theme assets → `public/themes/<theme>/`, core assets → `public/assets/core/`.
8. Invalidate the caches: permissions, menus, settings, widgets, asset manifest.

Other requirements:

* **Commands:** `modules:list`, `modules:status` (pending migrations), `modules:demo`,
  `modules:enable <Name>`, `modules:disable <Name>`,
  `modules:install`, `modules:uninstall`, `themes:install`, `themes:uninstall` (a disabled
  module's routes answer 404 and its menu, permissions, widgets and tasks are hidden),
  `make:module <Name>` and `assets:publish`.
* **Sync must be idempotent and safe** to run on every deploy.
* **Never derive permissions** by parsing route handlers, and never require a runtime scan just
  to make a new route reachable (a new module needs one `modules:sync`, which writes its
  namespace to the generated file).

### Modules screen (back office)

A hand-written screen in Core (`modules.*` permissions) to manage modules without the console:

* **List** of every module found: name, description, version (installed and, if Updates knows
  it, the latest available), status (*enabled*, *disabled*, *pending install/uninstall*,
  *error*), dependencies and dependants, and whether it is a core module.
* **Enable / disable** with one button and a confirmation:
  * **disabling** hides the module at once (routes 404, menus, widgets, tasks, menu link types,
    permissions in the matrix) without deleting any data or grant, so enabling it again
    restores everything as it was;
  * dependencies are respected: a module cannot be disabled while enabled modules depend on it
    (the screen names them), and enabling a module offers to enable its disabled dependencies
    too; core modules (`Core`, `Auth`, `Api`, `Users`, `Settings`, `Dashboard`) cannot be disabled;
  * enabling a module with pending migrations runs them (database only, no code is written) and
    publishes its assets, like `modules:sync` for that module; errors leave it disabled and are
    shown with the log;
  * the change invalidates the caches (permissions, menus, widgets, routes) and is written to
    the activity log.
* **Demo data** buttons per module (load, reload, remove; see "Demo data").
* **Sync** button (the same as `modules:sync`), **upload** of a ZIP for a staged install, and
  **uninstall** (creates a pending uninstall), as described below.
* **Themes** tab: installed themes, the active theme for the back office and for the public site
  (switch with one click, with a preview), and the theme options.
* Permissions: `modules.list`, `modules.toggle`, `modules.sync`, `modules.upload` and
  `modules.uninstall` (the last two superadmin only by default).

### Installing and uninstalling modules and themes

Modules and themes are **code** (PHP classes and views), so the web server never writes into
`modules/` or `themes/`. The web server user may write only `writable/`, `public/assets/modules`,
`public/assets/core`, `public/themes` and `public/uploads` (publishing assets when a module is
enabled or from Settings); everything else, the project root and `.env` included, is written only
from the console. `docs/security.md` documents the risk of published JavaScript and
`security:check` verifies the folder permissions. Three ways to install them, all ending in the same validation and
`modules:sync`:

1. **Copy the folder** (the usual way): upload `modules/<Name>/` or `themes/<name>/` (SFTP, git,
   deployment) and run `php spark modules:sync`, or press "Sync" in the Modules screen. New
   modules are detected and listed as *installed, disabled* until an admin enables them
   (setting: enable automatically).
2. **ZIP from the console:** `php spark modules:install <file.zip|url> [--enable] [--force]`
   (themes: `themes:install`). It:
   * validates the package (see below) and shows what it contains;
   * checks dependencies and the core version, and refuses a downgrade unless `--force`;
   * makes a backup (Backup module), enters maintenance mode, extracts to a temporary folder,
     moves it into place atomically (replacing the previous version on upgrade), runs
     `modules:sync`, and leaves maintenance mode; on failure it restores the previous folder.
3. **Upload in the UI, install from the console (staged install):**
   * a superadmin (`modules.upload`, with a recent 2FA re-authentication) uploads a ZIP in the
     Modules screen; it is stored in `writable/packages/` (never in a code folder);
   * the package is validated and the screen shows its manifest: name, version, author,
     dependencies, the permissions, menus, widgets and tasks it will add, and its file list;
   * the superadmin approves it, and it becomes **pending**; it is installed by
     `php spark modules:install --pending`, run by an operator or by the scheduler with a system
     user that can write into the code folders (documented cron line, separate from
     `schedule:run` if the web user cannot write code); the UI shows the status
     (pending, installed, failed with the log) and notifies the superadmin;
   * pending packages can be discarded; they expire after a configurable time.

**Package validation** (shared by 2 and 3): a ZIP with a single top folder; a valid manifest
(`Config/Module.php` or the theme manifest) whose name matches the folder and does not clash
with an existing module from another vendor; no absolute paths, `..` segments or symbolic links
(zip-slip protection); allowed file extensions only; maximum size and number of files, and a
maximum **uncompressed** size and compression ratio (zip bombs); a `<url>` source is only
downloaded over HTTPS from an allowed host (see "Outbound requests"); PHP files
syntax-checked (`php -l`) before installing; optional **signature check** (packages signed by a
trusted publisher key listed in `Modules\Core\Config\Packages`, overridable by
`app/Config/Packages.php` and `.env`; CodeIgniter's own `Config\Modules` is never extended),
required when a setting asks for it. Reserved module names (`Security`, `Update`) are refused.

**Uninstalling:** `php spark modules:uninstall <Name> [--keep-data]` (and a button that creates
a pending uninstall, like staged installs): makes a backup, disables the module, rolls back its
migrations unless `--keep-data`, removes its permissions, grants, settings, menu
customisations, widgets, tasks and published assets, and finally deletes its folder. Core
modules and modules other modules depend on cannot be uninstalled. Themes: `themes:uninstall`
(the active theme cannot be removed).

**Themes from the UI:** choosing the active theme and editing its options (logo, colours,
fonts, footer…) is always available, because those are settings, not code.

## Authentication (Shield)

* Use Shield's session authenticator with these defaults:
  * registration **disabled**, email activation if it is enabled;
  * magic-link login;
  * per-IP **and** per-account throttling of login attempts;
  * remember-me, limited by its own lifetime (see "Session handling");
  * forced password reset;
  * `strong_password` validators, **local only** (Shield's composition, dictionary and
    personal-information validators; never the "pwned passwords" API, which would be an outbound
    call); minimum length **8** by default (setting `Auth.minimumPasswordLength`, Shield's own
    name), and the documentation recommends **12**. Shield reads `config('Auth')` directly, so the
    Core copies the stored setting into the shared `Config\Auth` instance early in every request; a
    test checks that a password shorter than the setting is refused.
* **Two-factor authentication**, chosen by each user in their profile:
  * **Email** (Shield's `Email2FA` logic), or **authenticator app** (TOTP, RFC 6238: Google
    Authenticator, Microsoft Authenticator, Aegis, 1Password…), implemented as a Shield
    authentication action.
  * Shield has a single global `login` action, so the Core sets its own `UserTwoFactorAction`
    there, which dispatches to the email logic, the TOTP action or none according to the user's
    method; the kpapy 2FA providers read the user's method the same way.
  * Enabling TOTP: show a QR code (generated locally as SVG) and the secret, and require a valid
    code before saving. The secret is stored **encrypted** (CI4 Encryption).
  * **Recovery codes** (10 single-use codes, stored hashed), shown once and regenerable.
  * Allow a small time drift (±1 step) and reject reused codes.
  * **Policy per group** (setting): 2FA optional or required. A user in a required group is
    forced to set it up after login.
  * Admins can reset a user's 2FA (logged in the activity log, and the user is notified).
* **Captcha** (optional, setting): a local image or arithmetic captcha rendered with GD (no
  external service), with the answer hashed in the session, enforced in every environment,
  compared with `hash_equals` and invalidated after one check. Never skipped because the
  environment is `development`.
* **Generic messages** for login and reset, so users can't be enumerated.
* **Auth views** use the theme's authentication layout.
* **Session handling:**
  * cookies `HttpOnly`, `Secure` in production and `SameSite=Lax`;
  * regenerate the session id on login, logout and any privilege change (groups, permissions,
    impersonation);
  * optional idle-timeout lock screen (setting) **and** an absolute session lifetime (setting,
    e.g. 12 hours) that ends the session; remember-me may re-create it, but only up to its own
    lifetime (security setting, e.g. 14 days), after which the user must log in again, and
    sensitive actions still require re-authentication (tests cover expiry with and without
    remember-me);
  * changing or resetting the password ends every other session and revokes the tokens obtained
    by app logins; the user's personal API tokens are listed with the option to revoke them;
  * an email notice when the account signs in from a new device or browser (remembered by a
    hashed device cookie), with a link to "my sessions";
  * a "my sessions" list with remote logout (resource `auth-sessions`, granted to every group).
    Any geo lookup is **local only** (no third-party HTTP calls per request).
* **Security emails** (magic links, password resets, 2FA and API email codes, new-device notices)
  are sent inline with a short timeout and logged; the queued email channel of "Notifications"
  does not apply to them.
* **Re-authentication ("sudo mode"):** sensitive actions ask again for the password and, when
  the user has 2FA, a 2FA code; the confirmation lasts 15 minutes (setting) for that session.
  A login with the password (and its 2FA step) opens that window; a magic-link login, a session
  re-created by remember-me and unlocking the idle lock do not.
  It protects: the permission matrix, groups and individual permissions, impersonation,
  resetting another user's 2FA, creating personal API tokens, downloading and restoring backups,
  enabling/disabling, uploading and uninstalling modules, and the security settings. A
  `reauth` filter (`['filter' => 'reauth']`) and a `$this->requireReauth()` helper make it
  reusable by modules.
* **Groups:** `superadmin`, `admin`, `editor`, `user`, stored in a Core `groups` table (name,
  title, description, reserved, 2FA policy) and mirrored into `setting('AuthGroups.groups')`,
  like the matrix (see "RBAC").
  * `superadmin` bypasses RBAC.
  * The group cannot be renamed or deleted, and only a superadmin can assign it.

## RBAC (centralised, automatic, atomic)

kpcrudy decides each operation separately (list, show, add, edit, delete, export, import,
trash, restore, purge, custom operations and action permissions), and the skeleton keeps that
granularity instead of collapsing it into read/create/update/delete.

* **Permission names:** `<module>-<resource>.<operation>`, lower case (`news-posts.edit`,
  `news-categories.delete`, `news-posts.publish`). Two levels on purpose: `news-posts.*` grants a
  whole resource in one go (Shield also matches prefix and multi-segment wildcards; a test checks
  that `news-posts.*` does not match `news-postsx.edit`). A resource may declare `'name'` in the
  manifest to override the generated prefix, only for Core resources and single-resource modules
  (`settings.update`, `files.list`, `backup.restore`, `modules.toggle`, `security.check`,
  `api.docs`); the validator refuses duplicate names in the catalogue.
* **The catalogue is generated**, never written by hand: for each resource, kpcrudy's operations
  (`type: crud`) or `view` (`type: page`), minus `except`, plus `extra`. `search`, `filters`
  and `bulk` are not separate permissions: the resolver treats them as part of `list`.
* **Group grants** live in a table (`group_permissions`: group, permission), the source of truth.
  They may be atomic names or Shield wildcards. Shield reads its groups, permissions and matrix
  with `setting('AuthGroups.…')` and refuses unknown names, so after every sync or grant change
  the Core computes them from the catalogue, the `groups` table and `group_permissions` and writes
  them with `setting()->set('AuthGroups.…')` (cached by Settings); a test checks that both agree.
* **Personal screens** (profile, my sessions, my activity, notifications, dashboard home…) are
  resources of their own (`auth-profile`, `auth-sessions`, `activity-mine`, `core-notifications`,
  `dashboard`…) granted to every group through `defaultGrants`, so the rule "every `backend/`
  route has `can:`" has no exceptions.
* **Implied permissions:** when a grant is saved (including `defaultGrants`), add the
  permissions it presupposes with
  `Kpcrudy\Crud::impliedPermissions()` (`edit` → `list`, `show`; `restore`/`purge` → `trash`;
  `export` → `list`...), so the matrix can never hold a useless combination.
* **Users** can also get individual permissions (Shield's `addPermission()`, stored in its
  `auth_permissions_users` table).
* **One resolver for every CRUD and every API resource:** the Core provides `CrudPermissions`,
  shared by kpcrudy and kpapy (see "JSON API" for how it implements both interfaces), with
  `$denyByDefault = true`:
  * it maps `(resource, operation)` to `Authorization::for($user)->can("{$resource}.{$operation}")`
    (`search`/`filters`/`bulk` → `.list`); superadmins bypass it, but a name outside the
    catalogue of the enabled modules is refused to everybody (D-07-03). The user comes from kpapy's
    `Context` (API) or the session (web); iCal feeds, notifications by permission, scheduled
    tasks and the CLI pass the user explicitly, never relying on a session;
  * kpcrudy and kpapy call it for every operation, button, action, bulk action and custom
    operation, and cache the answers that do not depend on a record;
  * **masked ids are not access control:** every request is still checked by the resolver and
    the record rules, whatever id it carries;
  * **in development it fails loudly** when a CRUD asks about an operation that is not in the
    catalogue: it compares `$crud->permissionCatalogue()` with the manifest once per resource
    and throws an exception naming the missing operations. `php spark permissions:check`
    reports the same for every CRUD route, so the manifest and the code cannot drift apart.
* **Own records** are not permissions:
  * users that must only *see* their own records → kpcrudy `where('author_id', user id)`
    (it applies to list, details, forms, deletion, bulk actions and export);
  * users that see everything but only *change* their own → a per-record rule in the
    controller (`setPermission('edit', fn (?array $row) => ...)`), which kpcrudy combines with
    the resolver (the resolver denies first; the rule can only restrict).
* **Sensitive fields:** there are no per-field permissions. A module declares an `extra`
  permission (`news-posts.author`) and the controller calls kpcrudy's `hideFields([...])` when
  the user lacks it: the field disappears from list, details, forms, search, filters, export and
  import, and it is never saved.
* **Enforcement on routes:** `CrudFactory::routes()` adds `can:<resource>.list` to every CRUD
  route (kpcrudy then checks each operation); other back-office routes declare their own `can:`
  filter:
  ```php
  $routes->group('backend/news', ['filter' => ['backend', 'can:news-stats.view']], ...);
  ```
  * Provide a `can` filter (accepts several permissions, any/all): `can:a.x,b.y` passes with any
    of them, `can:all,a.x,b.y` needs all of them (D-07-02).
  * The `backend` filter chain covers: authentication, forced reset, forced 2FA setup,
    disabled-module check, maintenance mode, activity log, idle lock and throttling.
  * **Default deny:** a back-office route without a `can:` filter fails a CI test (a
    `RoutesSecurityTest` that walks every route under `backend/`); the same test checks that
    every route under `api/v1/` has the `kpapy` filter (`kpapy`, `kpapy:optional` or
    `kpapy:hmac`), `kpapy:login` (only kpapy's `auth/token*` login routes) or is explicitly
    declared public (`kpapy:public`, GET only, read-only, rate-limited).
* **Inside controllers and views:** use `can('news-posts.publish')` (helper), `$this->can(...)`
  or `$crud->isAllowed('publish', $row)`. The UI only hides what the server also enforces.
* **Escalation rules**, all covered by tests:
  * users cannot edit their own groups or permissions;
  * a user can only grant permissions they hold;
  * reserved group names;
  * the last superadmin cannot be removed.
* **Permission matrix screen:** one block per module, one row per resource, one column per
  group, with the atomic operations as checkboxes (and "all" = the resource wildcard).
  Quick-selection buttons ("read only", "all") only tick checkboxes: nothing but atomic names
  and wildcards is stored. Saving is atomic.

## Menus

* **Back-office menus are automatic:** built from all **enabled** manifests (`$menu`). A module
  may contribute several entries, at several levels (`parent`) and in several zones (`zone`:
  `sidebar` by default, `settings`, `user`...).
  * Entries linked to a `resource` take the route (named after the resource, e.g.
    `news-posts`) and the permission (`.list`, or `.view` for pages) from it.
  * Filter by permission and sort by `order`. A parent without visible children and without a
    permission of its own is hidden.
  * Mark the active entry from the current route name; breadcrumbs come from the same tree.
  * Cache per group set; invalidate on sync, on module enable/disable and on grant changes.
* **Customisation of the back-office menu** (edited in the `Menus` module, stored apart, never
  changing the manifests): reorder, rename, change icons, hide entries, **add custom links**
  and **relocate entries** (move them to another parent, zone or a custom group). See
  "Modules › Menus" for the rules.
* **Public menus** are fully edited in the `Menus` module (see "Modules") and placed in the
  theme's menu locations.
* The two kinds of menu are different things: **public menus are created by hand**; the
  **back-office menu is generated** from the modules and can only be *customised* on top.

## Themes

* **A theme** is a folder `themes/<name>/` with a manifest class `Themes\<Name>\Theme`:
  name, version, author, parent theme (optional), **layouts** (`backend`, `public`, `auth`,
  `blank`, `print`), **regions** (header, sidebar, footer, home blocks…), **menu locations**
  (`primary`, `footer`, `social`…), the assets it loads, and its **options** (logo, colours,
  fonts, dark mode, footer text) declared like settings and edited from the `Settings` UI. Their
  values are stored with codeigniter4/settings as `Theme.<option>` with the context
  `theme:<slug>` (e.g. `Theme.primaryColor` for `theme:corporate`); until a value is saved, the
  default declared in the theme (or inherited from its parent) applies.
* **Structure:** `Views/layouts/`, `Views/partials/`, `Views/components/`, `Views/overrides/<Module>/…`
  (module view overrides), `Assets/css`, `Assets/js`, `Assets/img`, `Language/`.
* **View resolution:** active theme override → parent theme override → module view → Core
  standard view. A `theme_view()` / renderer locator implements it; modules never hard-code
  a theme path.
* **Back office and public site** may use different themes (settings `Theme.backend`,
  `Theme.public`). Users may switch light/dark mode (see "Light and dark mode" below).
* **Default theme** (`themes/default`), Bootstrap 5.3:
  * back-office layout: collapsible sidebar (offcanvas on mobile, remembers its state), top bar
    with notifications bell, user menu and theme switch, breadcrumbs, toasts;
  * public layout: header with the `primary` menu, footer menu, content area with optional
    sidebar region, SEO meta tags, cookie notice (setting);
  * authentication layout: centered card with the site logo;
  * all colours as CSS custom properties (Bootstrap variables), so the theme options only change
    variables; accessible contrast (WCAG AA) and keyboard navigation;
  * kpcrudy integration: `bootstrap5` theme of kpcrudy, with the layout's section mode.
* **Light and dark mode** (every theme, the default one included):
  * three choices: **light**, **dark** and **auto** (follows the operating system with
    `prefers-color-scheme`; the default), using Bootstrap 5.3's colour modes (`data-bs-theme`);
  * covers the back office, the public site and the authentication pages; logged-in users store
    their choice in their profile, anonymous visitors in a cookie; a switch in the top bar (back
    office) and in the public header; the admin can hide the switch and force a mode (theme
    option);
  * **no flash of the wrong colours:** the server already prints `data-bs-theme` on `<html>` from
    the preference or the cookie; for `auto`, a tiny external script in `<head>` (with the nonce,
    loaded before the stylesheets, no inline code) applies the mode before the first paint and
    reacts to system changes;
  * theme options have light and dark values where it matters (primary colour, logo for dark
    backgrounds); all colours are CSS custom properties defined per mode, so child themes
    inherit both modes by only overriding variables;
  * third-party and embedded components follow the mode: kpcrudy, the calendar, the rich-text
    editor, widget charts, the file manager; the 2FA QR code is always shown on a white
    background so apps can read it;
  * WCAG AA contrast in both modes, checked by an automated accessibility test (e.g. axe) on
    the main pages in light and dark.
* **Responsive design** (every theme and every screen of every module):
  * reference widths: phone (≥ 360 px), tablet (≥ 768 px, portrait and landscape), desktop
    (≥ 1200 px); mobile-first CSS and **no horizontal page scroll** at any width;
  * navigation: the sidebar becomes an offcanvas on phones and an icon-only collapsible bar on
    tablets; the top bar compacts (icons for the bell and the user menu); long breadcrumbs are
    shortened;
  * kpcrudy lists: the table scrolls inside its container, each CRUD can mark **priority
    columns** (the others are hidden on phones), an optional **card view** on phones, filters in
    a collapsible panel and row actions grouped in a "⋯" menu;
  * forms: one column on phones, touch-sized inputs, the right keyboards (`type="email"`,
    `tel`, `number`, `date`) and save buttons always reachable;
  * special screens: the calendar defaults to list/day view on phones; dashboard widgets stack in
    one column and drag-and-drop works with touch; the file manager grid adapts and uploads
    accept the camera/gallery; the menu editor offers "up", "down" and "indent" buttons besides
    drag and drop;
  * touch: targets of at least 44 × 44 px, nothing that only works on hover, modals full-screen
    on phones;
  * performance on mobile: responsive images (`srcset`, file-manager thumbnails), lazy-loaded
    images and widgets, JavaScript loaded only where a component needs it;
  * tests: Playwright screenshots of the main screens at phone, tablet and desktop widths, in
    light and dark mode, failing on horizontal overflow.
* **Minimal PWA:** a web app manifest (name, icons from the site logo, theme colours for light
  and dark mode, `display: standalone`, start URL the back office or the public home) so the site
  can be installed on a phone's home screen and opens full-screen. A minimal service worker only
  where required for installability, **without offline caching of pages or data** (it never
  caches authenticated responses). The Core routes `/manifest.webmanifest` and `/sw.js` (a
  service worker only controls its own folder, so it is served from the root), and generates PNG
  icons (192 and 512 px) with GD whenever the logo changes. Enabled by a setting.
* **`make:theme <Name> [--parent=default]`** creates a child theme that only overrides what it
  needs. Theme assets are published like module assets.
* **Theme preview** for theme authors: `GET /theme-preview/<layout>` (`backend`, `public`, `auth`,
  `blank`, `print`, `errors-403|404|500`, optional `?theme=<slug>`) shows every layout and
  standard component with sample content. It reads no data and answers 404 unless
  `CI_ENVIRONMENT = development` and `theme.preview = true` (off by default).

## Front-end assets and JavaScript

**No JavaScript inside the HTML** (no inline `<script>` code, no `on*=""` attributes, no
`style=""`): this keeps the code maintainable and allows a strict Content-Security-Policy.

* **Exceptions:** the nonce'd `<script>`/`<style>` blocks rendered by kpcrudy and kpapy's own
  views (overriding them would copy library code; a library request asks for external assets),
  and one nonce'd `<style>` in the layout with the theme option variables (or a generated CSS
  file). The CI check covers the project's views (`app/`, `modules/`, `themes/`).
* **Styles without `style=""`:** dynamic widths (progress bars…) are set from JavaScript through
  the CSSOM or with utility classes; the vendored editor and calendar must work under the
  enforced CSP without `unsafe-inline` (tested); HTMLPurifier drops `style` attributes and maps
  alignment to classes.
* **Where the files live:** each module keeps its own assets in `modules/<Name>/Assets/`, so a
  module is self-contained. `assets:publish` (CodeIgniter's `Publisher`, run by `modules:sync`
  and on deploy) copies them to `public/assets/modules/<name>/`; the core's to
  `public/assets/core/`; themes' to `public/themes/<theme>/`. The web server serves static files;
  the copies in `public/` are never edited by hand (git-ignored; a test checks they are in sync).
* **Format:** native **ES modules** (`<script type="module">`), no bundler required. The layout
  prints an **import map** (with the CSP nonce), generated from the asset manifest, that lists
  **every published JS file with its hashed URL** under the prefixes `core/` (→
  `/assets/core/js/`), `<module>/` (→ `/assets/modules/<module>/js/`) and `theme/` (the active
  theme's JS), so imported files are cache-busted too (a test checks that the map changes when a
  file changes). Modules import shared code: `import { confirm } from 'core/ui/confirm.js'`.
* **Organisation of the core JS** (`modules/Core/Assets/js/`):
  * `app.js`: entry point; starts the components found on the page;
  * `http.js`: `fetch` wrapper with the CSRF header and token refresh, JSON errors → toasts;
  * `ui/`: `confirm.js`, `toast.js`, `modal.js`, `sidebar.js`, `tabs-memory.js`, `theme-switch.js`;
  * `forms/`: `editor.js` (rich text), `file-picker.js`, `date.js`, `dirty-guard.js`;
  * `components.js`: the component registry.
* **Components by data attributes:** markup declares behaviour, JavaScript reacts.
  `<div data-component="events/calendar" data-options='{"view":"month"}'>` → `app.js` imports
  `events/calendar.js` on demand (dynamic `import()`) and calls its `mount(element, options)`.
  Page data that is too big for attributes goes in `<script type="application/json"
  id="…">` (not executable, allowed by CSP).
* **kpcrudy:** use its `kpcrudy:confirm` event so CRUD confirmations use the theme's modal.
* **Helper:** `asset('events::js/calendar.js')` / `asset('core::css/app.css')` returns the
  published URL with a version query (file hash from an asset manifest written on publish).
* **Vendored libraries** in `public/assets/vendor/` (Bootstrap, Bootstrap Icons, the rich-text
  editor, the calendar library) with a `vendor.json` listing name, version, licence, source and
  the **SHA-256 of every file**; CI fails if a file does not match (see `docs/dependencies.md`).
* **Development mode:** `php spark assets:publish --watch` republishes changed files, and with
  `CI_ENVIRONMENT=development` the asset helper can serve a module's file directly when it is
  newer than the published copy, so edits show up without running the command each time.
* **Optional later:** a bundler (Vite/esbuild) can be added on top without changing this
  structure (sources stay in `Assets/`, output goes to the same public folders).

## Configuration and settings

* **Static configuration:** CI4 `Config` classes. Every module may ship
  `Config/<Name>.php`, overridable from `.env` (`<name>.<key>`). CodeIgniter gives some file
  names a special meaning, so the validator refuses module config classes named `Events`,
  `Routes`, `Registrar`, `Filters`, `Modules` or `Services` (the Events module uses
  `Config/Calendar.php`).
* **Secrets:** only in `.env`, never in tracked files. Provide an `env` template with no real
  values.
* **Runtime settings:** `codeigniter4/settings`, namespaced per module (`setting('News.perPage')`).
  * Definitions come from the manifests (type, default, validation rules, label, help, group).
    The Core registers the manifest defaults so `setting('News.perPage')` returns the default
    before any value is saved (a test checks every declared key).
  * The `Settings` module **generates the forms** from those definitions, one tab per module
    (and one for the active theme's options), with server-side validation and typed casting.
  * Permissions: `settings.update` opens the Settings UI; for every module that declares
    settings, `modules:sync` also generates **`settings.<module>`** (lower case, e.g.
    `settings.news`), required to change that module's tab. Security settings (headers, CSP,
    outbound hosts, password length, 2FA policy) need `settings.security` and a re-authentication.
* **Site-wide settings** (Core): site name (`Core.siteName`, empty = the default name), logo
  (theme option), default locale, available locales, time zone (`Core.timezone`, the zone dates
  are shown in; they are stored in UTC), maintenance mode, idle lock, captcha on/off, 2FA policy
  per group, mail transport, themes.
* **Settings › Security** (Core, `settings.security` + re-authentication): minimum password
  length (`Auth.minimumPasswordLength`), session lifetimes (absolute and remember-me), re-authentication window, rate limits, extra CSP origins per
  directive (`Core.cspScriptSrc`, `cspStyleSrc`, `cspImgSrc`, `cspFontSrc`, `cspConnectSrc`,
  `cspFrameSrc`, `cspMediaSrc`, `list` settings), the outbound allow-list (`Core.outboundHosts`),
  upload rules (SVG, re-encoding) and `Api.publicSpec`. A setting belongs to this tab when its
  manifest `group` is `security` or `security.<sub>`.
  File configuration (`Config\…` and `.env`) sets the defaults; the UI only stores overrides,
  and values that would weaken the baseline (e.g. `unsafe-inline`) are refused.

## Cross-module services (Core)

### Notifications

* `notify(User|int|array $recipients, NotificationType $notification)` or by group / permission
  (`notifyGroup('admin', …)`, `notifyPermission('backup.restore', …)`, which checks each user
  with `Authorization::for($user)`).
* **Notification types** are classes declared by modules (`$notifications` in the manifest):
  key, title, icon, default channels, a `render()` for the bell and an email template.
* **Channels:** `database` (the bell) and `email` (queued or sent by the scheduler, never
  inline in the request; transactional security emails are not notifications, see
  "Authentication"). Users choose per type which channels they want (preferences page);
  some types can be marked mandatory.
* **UI:** bell with unread count in the top bar, dropdown with the latest, "mark all as read",
  and a full list page (kpcrudy, `where(user_id)`; resource `core-notifications`, granted to
  every group).
* **Examples:** a new private notice (News), an event reminder (Events), a failed backup
  (Backup), a new version available (Updates), 2FA reset by an admin (Auth).

### Scheduled tasks

* A **single cron entry** runs everything: `* * * * * php /path/spark schedule:run`.
* Naming: modules keep scheduled tasks in `Tasks/` and the tutorial module is also called
  `Tasks`; docs and language keys always say "scheduled task" (or "to-do task" in the tutorial
  module), never just "task".
* Modules register tasks in their manifest (`$tasks`): classes implementing `TaskInterface`
  with `name()`, `schedule()` (cron expression or helpers: `daily('03:00')`, `hourly()`,
  `everyMinutes(15)`), `run(): TaskResult`, and options such as `withoutOverlapping()` (lock)
  and `onlyInEnvironment()`.
* `schedule:list` (next runs), `schedule:run [--task=…] [--force]`, and a back-office page with
  the last run, duration, result and output of each task (`scheduled_tasks` / `task_runs`
  tables), with a "run now" action (permission).
* Failures are logged, notified to admins and do not stop other tasks. Tasks of disabled modules
  do not run.
* A fallback for hosts without cron (optional setting): a "poor man's cron" triggered by
  back-office requests at most once a minute, run after the response is sent.

### Activity log

* Every back-office write action (kpcrudy `afterWrite()` hooks, custom operations, logins,
  permission changes, settings changes, backups, restores, 2FA changes) is written to
  `activity_log`: user, module, resource, action, record id, summary, changes (before/after,
  with secrets masked by key pattern `/pass|pwd|secret|token|key|csrf|otp/i`), IP, user agent,
  date.
* **Security events** go to the same log with a `security` type: failed logins and 2FA codes,
  throttling, permission denials (403), re-authentications, token creation and revocation, use
  of a token from a new IP, refused uploads and packages.
* **Append-only:** no screen or API edits or deletes entries; only the retention task purges
  them. Values are stored as data (never interpolated into log lines), so user input cannot
  forge entries; the file logs escape line breaks.
* The `Activity` module shows it with **kpcrudy** (see "Modules").
* The Core writes the log (`ActivityLogger`, `activity_log()`/`security_log()`; kpcrudy writes,
  custom operations and exports through the Core's `AuditedCrud`); each module logs its own
  events; a failed login keeps the typed name only if it is an email (D-12-01 … D-12-05,
  `docs/activity.md`).
* Retention by the Core setting `Core.activityRetentionDays` (default 365 days, 1–3650), purged
  daily by the Core scheduled task `PurgeActivityLog` (also while the Activity module is
  disabled).

### Dashboard widgets

* Widgets implement `WidgetInterface` (Core, `Modules\Core\Widgets`; base classes `Widget` and
  `AsyncWidget`): `id()` (`<module>.<name>`, e.g. `core.quick-links`), `title()`, `icon()`,
  `permission()` (null: the dashboard's own permission is enough), `sizes()` (allowed sizes
  among `small`, `medium`, `large`, `full`; the first is the default), `settings()` (optional
  per-group options typed `int`, `bool`, `string`, `select`, `menu`, `links`), `render(array $options):
  string` (server-side HTML) or, for widgets that load asynchronously (`AsyncWidgetInterface`),
  `data()` plus `renderData()` or their own ES component.
* The registry collects them from the enabled manifests (`$widgets`; `modules:sync` refuses a
  class that does not implement the interface). A widget is only shown if the user has its
  permission **and** it is enabled for one of the user's groups (see Dashboard).
* A failing widget is isolated: the error is logged and an error card is rendered; slow widgets
  load asynchronously from `backend/dashboard/widgets/<id>/data` (403 unless the widget is
  available to the user, rate limited) and are cached per user for a few minutes.

### Storage

* A `Storage` service (Flysystem) with two disks: `public` (`public/uploads`) and `private`
  (`writable/storage`, served only by `files/private/{key}`, a random 128-bit key, through a
  controller that checks permissions: until the folder permissions of "Files" only the uploader
  and superadmins; anything not allowed is a 404). Modules never use
  paths directly, so another adapter (S3, SFTP) can be added later.
* Uploads: extension allow-list **and** MIME check, size limit, random file names (the original
  name is kept in the database), and no execution in upload folders. The global rules are Core
  settings, `Core.uploadTypes` (comma-separated extensions) and `Core.uploadMaxSize` (MB, default
  10); each field can only narrow them.
  * **SVG and HTML are refused by default** (they can carry scripts); the setting
    `Core.uploadAllowSvg` (off by default) allows SVG only through a sanitiser.
  * **Images are re-encoded by default** (avatars and public images), which strips metadata and
    payloads hidden in the file; a field option can turn it off for that field.
  * **Private files that are not images or PDF** are served with
    `Content-Disposition: attachment`; every file is served with its stored MIME type and
    `X-Content-Type-Options: nosniff`.
* **Redirects:** "return to" parameters and post-login redirects only accept internal paths
  (open-redirect protection), with a shared `safe_redirect()` helper.
* **Filesystem level:** `writable/storage` and `writable/backups` are outside `public/`, only
  accessible to the web server user (folders `0750`, files `0640`, or stricter, e.g. the
  libraries' `0600` key files; CodeIgniter's logger writes `0640`), never executable and never
  served directly. Application access control never relies on operating-system permissions:
  every read of a private file goes through the permission-checking controller.
* **Folder permissions** (ACL) and the **file picker** rules are described in "Modules › Files";
  they apply to both disks.

### Versioning

* The core version lives in `modules/Core/Config/Version.php` (semantic versioning), module
  versions in their manifests. The Core's `about` command and the footer of the back office
  show them.

## CRUDs with kpcrudy

* **Every list/form screen over database tables uses kpcrudy** (see "Required libraries"). Hand-written screens are only
  for things that are not CRUDs (dashboard, permission matrix, settings forms, file manager,
  calendar view, menu tree editor).
* **One CRUD = one resource**, not one table: a CRUD may manage several tables (main table,
  1-N and N-N relations, a translations child table), and the same table may be managed by two
  CRUDs with different permissions (e.g. an admin list and a read-only view).
* **Core integration** provides:
  * `Config\Kpcrudy`: theme `bootstrap5`, `maskIds = true`, local assets, `uploadPath` set to a
    non-public, unused folder (kpcrudy's and kpapy's upload fields bypass the Storage service, so
    modules never use them: file fields use the file picker backed by the Storage service, see
    "Files"; the gap is in `docs/library-requests.md`), `layout` = the theme's back-office layout with `layoutSection =
    'content'`, `showTitle = false` (the layout prints title and breadcrumbs), `flashKeys`
    mapped to the layout's toasts, `permissionResolver = CrudPermissions::class` and
    `denyByDefault = true`.
  * `CrudFactory::make(string $resource)` (e.g. `'news-posts'`): a `Crud` with
    `setResource('news-posts')`. Nothing else is mapped by hand: the resolver answers every
    operation.
  * `CrudFactory::routes(RouteCollection $routes, string $resource, string $path, string $handler)`:
    `Crud::routes()` with the `backend` and `can:<resource>.list` filters and the route name
    `<resource>` (`as`), used by the menus and `url_to()`.
  * `BackendController::crud()` / `renderCrud()` / `can()`.
* **Use kpcrudy's features instead of custom code:**
  * custom operations (duplicate, publish, send...) with `addOperation()`, on the CRUD's own
    route, declared as `extra` permissions of the resource (no extra routes);
  * action and bulk action `permission` options, also declared as `extra`;
  * `hideFields()` for sensitive data, `where()` for "own records only";
  * `setTranslations()` for multilingual content (kpcrudy lists show an empty cell when a
    translation is missing, so `listLocale` is the user's locale only when that locale is
    complete, otherwise `ca`), `multiselect` for lists of values (relations that must be queried,
    e.g. "visible to any of my groups", use a junction table with `setRelationNN()`);
  * `afterWrite()` for the activity log and cache invalidation;
  * the `kpcrudy:confirm` event to use the theme's confirmation modal;
  * strict CSP: kpcrudy's templates carry the nonce and have no inline handlers (their nonce'd
    blocks are the accepted exception, see "Front-end assets and JavaScript").
* **Ids in URLs are masked** and every custom action is checked server-side.

## JSON API (kpapy)

The application exposes a versioned JSON API under `/api/v1`, built with **kpapy**, an
independent library (resources described with a fluent API; see its README and `docs/`). kpcrudy
and kpapy do not depend on each other: the skeleton is what makes them share permissions and ids.

* **One resolver shared by both libraries:** the Core's `CrudPermissions` implements both
  `Kpcrudy\PermissionResolver` and `Kpapy\PermissionResolver` and is set in
  `Config\Kpcrudy::$permissionResolver` and `Config\Kpapy::$permissionResolver` (both with
  `denyByDefault = true`). The two interfaces pass a different object as the last argument
  (`Crud` / `Resource`), so the method declares it as `object`, which satisfies both. API
  resources use the same resource names (`setResource('news-posts')`), so a grant in the
  permission matrix applies to the web and the API. Superadmins bypass the resolver, but their
  **tokens are still limited by their scopes**.
* **Same masked ids in the web and the API:** both libraries use the same masking algorithm, so
  a record has the same id in both **only if they get the same key**. The Core sets
  `Config\Kpcrudy::$maskKey` and `Config\Kpapy::$maskKey` from one `.env` value
  (`app.maskKey`, 64 random hex characters generated by the installer, included in backups,
  checked by `security:check`). Never leave it empty: both libraries would fall back to
  CodeIgniter's encryption key (ids change when it is rotated) and then to their own key files;
  the application refuses to boot in production when it is empty. Masked ids also depend on a
  context (the table name by default), so never set a custom `maskIds($context)` on one side
  only. A test checks that a record has the same id in both.
* **Authentication:** Shield access tokens (`Authorization: Bearer`), never cookies; the API
  route group is excluded from CSRF.
  * **Personal tokens** are created (after re-authentication) and revoked in the user's web
    profile (name, scopes, expiry, last use and IP shown; the secret is shown once). Admins can
    list and revoke any user's tokens. Permission to create tokens: `api.tokens`.
  * **Scopes:** permission names or wildcards; a token can do the **intersection** of its scopes
    and the user's permissions.
  * **Login for apps** (`POST /api/v1/auth/token` → `POST /api/v1/auth/token/verify`): checks the
    password with Shield's throttling and then asks for **the 2FA method the user has active**
    (email code or TOTP app, or a recovery code). **Users without 2FA** receive a **verification
    code by email** (single use, short expiry, limited attempts); the email names the device, IP
    and time, and has a link to deny the sign-in. Implemented as a Core `TwoFactorProvider`
    registered in `Config\Kpapy::$twoFactorProviders`; kpapy's built-in `web` method (code shown
    in the web profile) is **not** used. Logout revokes the token; tokens from app logins expire
    (setting) and can be refreshed.
* **Core endpoints** (each one a kpapy resource with the same visibility rules as the web):
  * `me`: profile, preferences, language, avatar URL (signed, private);
  * `news`: list and details (public, private and group news the user can see), `POST
    news/{id}/read` (mark as read), notices for the user;
  * `events`: events by date range and calendars (published and provider events), with
    `restricted` audiences respected;
  * `notifications`: list, unread count, mark one/all as read;
  * `public/news` and `public/events`: public read-only endpoints without a token (rate-limited).
* **Modules expose their own resources** with routes in their own `Config/Routes.php` under
  `api/v1/<path>` (written by `kpapy:make` or `make:module --api`), and declare them in the
  manifest (`$api`: path and the handling controller method). `$api` is metadata: `modules:sync`
  and `permissions:check` check that routes and manifest match, disabled modules answer 404
  through the module filter, and the permission catalogue is checked the same way as for CRUDs
  (an undeclared operation fails in development).
* **Cross-cutting:** rate limiting per token and per IP (settings), CORS allow-list (setting,
  empty by default), problem+json errors translated (ca/es/en), ETags and `304`, `If-Match` for
  writes supported (`412` on mismatch) but not required by default, every write and every login
  in the activity log.
* **Documentation (back office only):** kpapy's `DocsController` is registered at
  `backend/api/docs` with the `backend` and `can:api.docs` filters, `docsInProduction = true`
  and `docsTryIt = true`: the page lists every endpoint and lets admins **try** requests with
  their own token (or signing in like an app), and `backend/api/docs/openapi.json` serves the
  OpenAPI 3.1 document. Nothing is public by default: the setting `Api.publicSpec` (off) also
  publishes the document at `/api/v1/openapi.json` for third-party integrators.
  `kpapy:openapi --output` builds a static copy. Documented in `docs/api.md` with an example.

## Modules

### Auth

As described in "Authentication". Profile page (resource `auth-profile`, granted to every
group): name, email, avatar (file picker in `upload` mode, stored on the **private** disk in the
user's personal space), language, time zone, theme mode, password change, 2FA method (none /
email / app) with recovery codes, notification preferences, and "my sessions". Shield's `users`
table has none of the profile fields, so Auth owns a `user_profiles` table (one migration: name,
language, time zone, theme mode, avatar, an optional `birth_date`).

### Users

* Users list and edit form with kpcrudy (Shield identities handled through hooks: the email
  lives in `auth_identities`; password changes go through Shield).
* Row operations (`addOperation`): ban/unban, force password reset, reset 2FA, log out
  everywhere, impersonate.
* **Impersonation:** superadmin only, never of another superadmin, after re-authentication,
  limited in time (setting, e.g. 30 minutes), with a visible banner and a way back; sensitive
  actions (re-authentication, password, 2FA, tokens) are refused while impersonating; start and
  end are written to the activity log.
* Groups CRUD with kpcrudy over the Core `groups` table (mirrored into
  `setting('AuthGroups.groups')`), the permission matrix (hand-written), and the 2FA policy per
  group. A new group receives the personal-screen grants every group has.
* Names (generated by the convention): permissions `users-users.{list,show,add,edit,ban,
  forcereset,reset2fa,logout,impersonate,access}`, `users-groups.{list,show,add,edit,delete}`,
  `users-matrix.{view,edit}`; URLs `backend/users`, `backend/users/access/<id>`,
  `backend/users/groups`, `backend/users/permissions` and `POST auth/impersonation/stop`; the
  time limit is the setting `Users.impersonationMinutes` (30). No user deletion (ban instead).

### Settings

The generated forms described above (one tab per module and one for the theme), plus actions:
"send a test email", "clear caches", "publish assets" (into `public/assets/…` and
`public/themes/` only, see "Installing and uninstalling modules and themes").

### Dashboard

* The dashboard home is `backend` (resource `dashboard`: `dashboard.view`, granted to every
  group); a login and an impersonation land on it. The Dashboard is a core module: it cannot be
  disabled (a user who wants an empty home hides every widget).
* **Configurable from the UI, per group** (`backend/dashboard/groups[/<group>]`,
  `dashboard.configure`, granted to `admin`): for each group, the admin chooses which widgets are
  available, their default order and size, and their options; changes are written to the
  activity log. A widget the group layout does not mention is enabled with its defaults. A
  widget not enabled for any of the user's groups is never shown (also not through its async
  URL); when several of the user's groups enable it, the first group (reserved groups first)
  gives its order, size and options.
* **Per user:** within what their groups allow, each user can reorder, resize (within the
  widget's sizes) and hide widgets (drag and drop with mouse, touch or keyboard, saved at once as
  a user preference; a form without JavaScript at `backend/dashboard/customize`), and "restore
  the group layout".
* **Example widgets (shipped):**
  * *Notices* (News): unread private notices and warnings, with "mark as read";
  * *Latest news* (News);
  * *Upcoming events* (Events): next events visible to the user, with a link to the calendar;
  * *System version* (Updates): installed core version, and a badge when a newer one exists;
  * *Backups* (Backup): last backup, its result and size, next scheduled run;
  * *Scheduled tasks* (Core): failing tasks;
  * *Quick links* (Core): shortcuts chosen and ordered per group by the administrator: screens
    of the back-office menu (shown only to users who can open them) and free links with one
    label and an `https://` address (opened in a new tab) or an internal path, shown to every
    user of the group (at most `Core.widgetMaxLinks` links, a Core setting: default 20, 1–100);
  * *Users summary* (Users): active users, users online, accounts without 2FA.

### Activity

* A read-only **kpcrudy** CRUD over `activity_log` (`readOnly()`): columns date, user, module,
  action, record and summary; filters by user, module, action and date range; search;
  CSV export; the details page shows the before/after changes as a table (`callbackColumn`).
* "My activity" screen for every user: its own resource `activity-mine` (granted to every group),
  the same CRUD with `where('user_id', me)`.
* IP and user agent hidden with `hideFields()` from users without `activity-log.network`.
* Permissions: `activity-log.list`, `activity-log.show`, `activity-log.export`,
  `activity-log.network`; purge only by the retention task.

### Pages

* Pages for the public site: title, slug (unique per parent and language), parent (tree),
  template (from the theme's page templates), body (rich text, sanitised with HTMLPurifier,
  which drops `style` attributes and maps alignment to classes),
  summary, featured image (file picker), status (draft / published / scheduled with
  `publish_at`), **visibility** (public, logged-in users, selected groups), SEO fields (meta
  title, description, canonical, noindex), order, author, timestamps, soft deletes.
* Multilingual with `setTranslations()` (title, slug, body, summary, SEO fields).
* **Revisions:** every save keeps a revision (who, when, diff); restore a revision as a custom
  operation.
* Back office with kpcrudy (list with tree indentation, filters by status and visibility,
  preview operation, duplicate operation), and the home page chosen in settings.
* Public routes: `/{slug}` and nested slugs, a 404 that uses the theme, XML sitemap, and
  `noindex` for non-public pages. Private pages require login and the right group.
* Offers a menu link type "page" to the menu editor.

### Menus

The module manages two different things, in two separate screens.

**1. Public menus (public site): full control.**

* Create menus, assign them to the theme's menu locations (`primary`, `footer`…), and edit their
  items in a **tree editor** (drag and drop to reorder and nest, keyboard accessible).
* Item types: page, URL, route, and the link types offered by modules (`$menuLinks`: a news
  category, the events calendar…). Each item: label (translatable), icon, target, CSS class,
  visibility (everyone, guests only, logged-in users, groups).
* Rendering: `menu('primary')` returns the tree for the current user and language, marks the
  active item, and is cached per menu, language and group set.

**2. Back-office menu: customisation on top of the generated menu.**

The back-office menu is still generated from the manifests and filtered by permissions; the
editor stores a layer of **customisations** that is applied on top of it. The same tree editor
shows the generated entries (marked with their module) and the custom ones.

* **Retouch generated entries:** reorder, rename (translatable), change the icon, hide.
* **Relocate generated entries:** move an entry (with its children) under another parent, to
  another zone (`sidebar`, `settings`, `user`…) or into a **custom group** (a heading or
  collapsible group created in the editor, e.g. "Content" with Pages, News and Events).
* **Custom links:** add entries that no module provides:
  * types: internal route or path of the back office, external URL, a public page, or a
    module link type (`$menuLinks`);
  * label (translatable), icon, target (same tab / new tab, external links always
    `rel="noopener noreferrer"`), position (parent, zone, order);
  * URLs are validated: only `https://`, `http://` or internal paths and routes (never
    `javascript:`, `data:` or protocol-relative `//`); the same rule applies to public menu
    items;
  * **visibility:** groups and/or a required permission (picked from the catalogue). A link to
    an internal route takes that route's `can:` permission by default; the editor cannot make
    a link visible to users who would get a 403.
* **Rules that keep the menu consistent:**
  * customisations reference entries by a stable key (`<module>.<resource>` or the route name),
    never by position;
  * permissions always win: hiding or relocating never grants access, and a relocated entry is
    still filtered by its permission;
  * a new module's entries appear in their default place until someone relocates them;
  * customisations of a disabled or removed module are kept but inactive (and listed as
    "orphaned", with an option to delete them); a custom group without visible entries is
    hidden;
  * cycles are impossible (an entry cannot be moved under its own descendants);
  * "Reset" per entry or for the whole menu goes back to the generated menu.
* **Optional per group:** a customisation can apply to everyone or only to selected groups
  (e.g. editors see "Content" first); the most specific one wins.
* Cached per group set together with the generated menu; invalidated on any change.

**Permissions:** `menus-public.*` (list, add, edit, delete) and `menus-backend.*` (view,
customise, links, reset), as atomic operations.

### Files

* A **file manager** over the Storage service, with the basic operations: browse folders,
  create/rename/delete folders, upload (multiple, drag and drop, progress), rename, move,
  delete (to a trash, with restore), download, preview (images, PDF, text), copy public URL.
* Metadata in a `files` table (disk, path, original name, mime, size, dimensions, checksum,
  uploaded_by, visibility public/private, alt text, tags); the folder tree is virtual (paths).
* Search and filters by type, date and tag; list and grid views.
* Rules: extension and MIME allow-lists from the Core settings (`Core.uploadTypes`,
  `Core.uploadMaxSize`, `Core.uploadAllowSvg`), size limits, quotas, image thumbnails
  generated on upload, path traversal impossible (all paths normalised and resolved inside the
  disk root).

**Folder permissions (ACL).** Folders are registered in a `folders` table (disk, path, owner,
quota) and carry their own permissions, **inherited by subfolders** unless a subfolder defines
its own (`folder_permissions`: folder, subject, rights):

* **Subjects:** a group, a user, or "all logged-in users".
* **Rights:** `view` (see and download), `upload`, `edit` (rename, move), `delete`, `manage`
  (edit the folder's permissions).
* **RBAC is the ceiling, the ACL decides where:** `files.list` is needed to open the file
  manager at all; inside it, each folder's ACL decides what the user can do. `files.acl` allows
  editing folder permissions, `files.all` gives access to every folder (administrators);
  superadmins bypass everything.
* **Predefined spaces:**

  | Space | Path | Default rights |
  |---|---|---|
  | **Personal** | `users/{user_id}/` (private disk) | The owner has every right; nobody else (except `files.all`) |
  | **Modules** | `modules/<module>/` | Only the module's code writes there (Storage service); read-only or hidden in the file manager, as the module declares |
  | **Shared** | created by administrators | The rights assigned to groups or users |

* **Personal space in the file manager:** users can see and use their personal folder as a small
  personal drive. It is enabled per group with a setting (`Files.personalSpaceGroups`); users of
  other groups only upload through fields (avatar, attachments) and never open the file manager.
* **Quotas** per user (personal space) and per shared folder.
* **Serving private files:** the controller checks the folder's `view` right before sending a
  file. Exception: a file attached to a record (e.g. the attachment of a private event) follows
  the visibility of that record; the owning module decides, through a `FileAccessPolicy` it
  registers for its folder.

**File picker** (`data-component="core/forms/file-picker"`), used by any module field. Each
field declares how it works:

| Option | Meaning | Example (avatar) |
|---|---|---|
| `mode` | `upload` (only upload), `browse` (only choose), `both` | `upload` |
| `disk` | `public` or `private` | `private` |
| `folder` | target folder, with variables | `users/{user_id}/avatar` |
| `types`, `maxSize`, `maxFiles` | restrictions for this field | `jpg,png,webp`, 2 MB, 1 |
| `browseRoot` | where the user may choose from (`browse` / `both`) | — |

* **The field grants the upload, not the file manager:** a user needs no `files.*` permission to
  upload their avatar. The server issues a **signed upload token** for each field (target
  folder, disk, types, size, expiry); an upload is only accepted with a valid token, so the user
  cannot change the folder or the type. The target folder is created on demand with the
  space's default rights.
* In `browse` / `both` mode, what the user can see and upload is limited by `browseRoot` **and**
  the folder ACL (e.g. editors choose a news cover from `news/` and their personal space).
* **Images inside rich text:** the rich-text editor has an "Insert image" button that opens the
  picker (`mode` `both`, images only: JPG, PNG, WebP, GIF; public disk, folder
  `modules/<module>` of the content, e.g. `modules/news`; the Storage service's rules and
  re-encoding) and inserts the image with an alt text; pasted or dropped images go through the
  same signed upload. HTMLPurifier then keeps only images of the site itself (internal
  `/uploads/…` URLs or the site's hosts); external images are removed. Used by News and Pages.
* **Avatars** are stored on the **private** disk: they are only served to logged-in users (the
  personal folder's `avatar` subfolder grants `view` to "all logged-in users"), with long cache
  headers and a versioned URL so they stay fast.

**Permissions:** `files.list`, `files.upload`, `files.edit` (rename/move), `files.delete`,
`files.folders`, `files.acl`, `files.all`, `files.private`.

### News (reference module)

* Tables `news` (title, slug, summary, body, cover image, category, status draft / published /
  scheduled, `published_at`, `expires_at`, author, timestamps, soft deletes) and
  `news_categories`; multilingual with `setTranslations()`.
* **Visibility:** `public` (public site), `private` (logged-in users only) or `groups`
  (selected groups, in a `news_groups` junction table edited with kpcrudy's `setRelationNN()`,
  so "visible to any of my groups" is a portable query).
* **Type:** `article` or `notice`. Notices have a **priority** (info, warning, critical), can be
  **pinned**, and expire (`expires_at`). They appear in the *Notices* dashboard widget of the
  users who can see them; publishing a private notice sends a notification to its audience.
* **Read receipts:** `news_reads` (user, news, read_at); "mark as read" from the widget and the
  detail page of the reader screen; authors can see how many users have read a notice (`news-posts.readers`).
* Back office with kpcrudy: resources `posts` (with `extra`: `publish` for the publish
  operation and bulk action, `author` which hides the author fields with `hideFields()`,
  `readers`) and `categories`; authors only change their own posts (per-record rule), and a
  "my posts" screen shows the `where()` pattern. HTML sanitised with HTMLPurifier in a
  `before*` hook, slug hook, cover image through the file picker.
* **Reader screen:** a read-only back-office screen (resource `news-read`, `type: page`,
  permission `news-read.view` granted to every group) that lists the posts the user may see, with
  the detail page and "mark as read"; ordinary users never need `news-posts.list`.
* **API:** resource `news-api` (`list`, `show`, granted to every group; `api/v1/news`): the
  posts the token's user may read, with the reader screen's visibility rules. Settings
  `News.perPage` (public list) and `News.feedItems` (RSS).
* **Images inside the body:** the editor's "Insert image" button opens the file picker (see
  "Files"); the body keeps only images of the site itself.
* Public routes (`/news`, `/news/{slug}`, `/news/category/{slug}`, RSS feed) for public news;
  private news only in the back office (reader screen) and the dashboard.
* Widgets *Notices* and *Latest news*, a task that expires notices, two settings, the menu link
  type "news category", and the `news.notices` calendar (notices with an event date are published
  to the calendar with their visibility, `groups` mapped to `restricted`, and no calendar
  permission; see "Calendar API").

### Events (calendar)

* Tables `events` (title, description, start, end, all day, location, category, colour, URL,
  visibility, status, organiser, `calendar`, `source_module`, `source_key`, `source_url`,
  timestamps, soft deletes), `event_categories` and `event_audience` (event, type group|user,
  id); multilingual with `setTranslations()`.
* **Visibility:** `public` (everyone, also the public site and public feeds), `private` (any
  logged-in user) or `restricted` (only its **audience**: any combination of groups **and**
  users, e.g. group `admin` + user John; a user sees the event if they are in any of the groups
  or listed by name).
* **Recurrence** (basic): daily, weekly (days of week), monthly, yearly, with an end date or a
  number of occurrences, and exceptions (skipped dates). Occurrences are expanded when queried
  for a date range, not stored.
* **Administration** with kpcrudy (list, filters by category, date range and visibility,
  duplicate operation, CSV import/export; kpcrudy does not import translated fields, so a
  `callbackImport()` writes the Catalan translation).
* Configuration in `Config/Calendar.php` (a `Config/Events.php` would be loaded by CodeIgniter as
  an event-listener file).
  * **Form:** a visibility selector; when `restricted`, a groups field (multiselect) and a users
    field (searchable, AJAX), saved to `event_audience`. The groups offered are only those the
    user may assign (an editor cannot create an event only for superadmins), validated
    server-side too.
  * **What administrators see:** in the **calendar view, widgets and iCal feeds** everyone,
    administrators included, only sees the events whose audience includes them (an event "only
    for John" does not appear in every admin's agenda). In the **administration list**, users
    with `events-events.all` see and manage every event, with a filter "visible to me / all";
    without it, the list is limited to their audience with kpcrudy's `where()`.
* **Calendar view** (back office and public site): month, week, day and list views, filtered by
  category, using a vendored MIT-licensed calendar library through a `data-component` (JSON
  feed endpoint by date range, respecting visibility and permissions); click to open the event.
* **iCal (RFC 5545), two different features:**
  * **One-off export** of an event (`.ics` download): the client imports a static copy; later
    changes do not reach it.
  * **Subscribable feeds** (read-only, one-way): external clients (Synology Calendar, Google,
    Outlook, Apple, Thunderbird) poll the URL periodically and show changes, new events and
    deletions on their next refresh. Two-way sync (CalDAV) is out of scope.
    * **Public feed** per category (or all categories): public events only, no login.
    * **One personal feed per user** with every event they can see (public, private and their
      groups' events), filterable by category from the profile, so one subscription is enough.
      Clients cannot log in, so the URL carries a **random secret token** (stored hashed),
      which the user can copy, **regenerate** (invalidating the previous URL) or revoke. The
      feed is evaluated with that user's current permissions on every request (a user who loses
      access stops seeing the events). The last access is shown in the profile and logged.
  * **Robust format:** stable `UID` per event, `SEQUENCE` and `LAST-MODIFIED` so clients detect
    changes; cancelled or deleted events published as `STATUS:CANCELLED` for a grace period (e.g.
    30 days) so they also disappear from clients that already had them; recurrences as `RRULE` /
    `EXDATE` (not expanded); proper time zones (`VTIMEZONE`, the site time zone); line folding
    and escaping per RFC 5545; validated with an iCal validator in tests.
  * **Refresh hint:** `REFRESH-INTERVAL;VALUE=DURATION:PT1H` and `X-PUBLISHED-TTL:PT1H`
    (configurable), honoured by many clients. The refresh delay ultimately depends on the client.
  * **Performance:** `ETag` / `Last-Modified` with `304 Not Modified` when nothing changed, a
    limited range (e.g. 3 months back, 12 months ahead, configurable), and a short cache per
    feed; rate-limited per token.
  * **In the profile:** "Copy URL" and a `webcal://` link, short instructions for Synology,
    Google, Outlook and Apple, the category filter, and "Regenerate".
  * **Reminders in the client (optional):** reminders the user asked for in the application are
    also included in their personal feed as `VALARM`.
* **Reminders:** users can ask for a reminder; a scheduled task sends the notifications.
* Widget *Upcoming events*, menu link type "calendar".

**Calendar API for other modules.** Other modules (courses, bookings, tasks, HR…) put their
dates in the same calendar, with the same views, permissions, iCal feeds and reminders. Two ways,
both supported:

| | **Publish (push)** | **Provider (pull)** |
|---|---|---|
| How | The module calls the calendar service and the event is **stored** in `events` | The module registers a provider that **returns events on the fly** for a date range; nothing is stored |
| For | Events created at a given moment: a course session, a booking, a meeting | Dates that already live in the module's data: task deadlines, holidays, birthdays |

* **Publishing API** (idempotent per module + key; call it on every change):

  ```php
  $calendar = service('calendar');

  $calendar->publish('courses', "session-{$id}", new CalendarEvent(
      title: $session['title'], start: $start, end: $end, location: $room,
      calendar: 'courses.sessions',                 // a calendar declared in the manifest
      visibility: 'restricted', groups: ['students', 'teachers'], users: [$teacherId],
      url: $detailUrl,                              // link back to the source record
      reminders: true,
  ));

  $calendar->unpublish('courses', "session-{$id}");  // when deleted or cancelled
  ```

  * Published events are **locked** in the calendar administration: shown with a badge
    "Managed by Courses" and an "Edit in Courses" link, never edited there, so the calendar and
    the source module cannot disagree.
  * The audience can combine groups and **specific users** (personal events, e.g. an
    appointment), with the same rules as events created by hand.
  * A module can register an `EventAccessPolicy` for its calendar when access must be decided
    per event (e.g. only the users enrolled in a course).
* **Providers:** classes implementing `CalendarProvider` (`calendar()`, `events(DateTimeInterface
  $from, DateTimeInterface $to, User $user): iterable<CalendarEvent>`), declared in
  `$calendarProviders`. They must return **stable keys** (used as iCal `UID`) and only events the
  user may see; results are cached per user and range for a few minutes.
* **Calendars:** each module declares its calendars in the manifest (`$calendars`: label, colour,
  and an optional required permission; without it, each event's visibility decides). In the calendar view each user toggles the calendars they want to see
  (saved as a preference); the personal iCal feed can be filtered by calendars as well as
  categories. Categories remain for events created by hand.
* **Consistency:** events and providers of a disabled module are hidden; uninstalling a module
  deletes its published events; `php spark calendar:resync <module>` asks the module for all its
  events again (`CalendarSource::all()`) to repair drift. The calendar emits CodeIgniter events
  (`calendar.created`, `calendar.updated`, `calendar.deleted`) other modules can listen to.
  Reminders and iCal work the same for published and provided events.
* **Examples in the skeleton:**
  * **News (push):** a notice may have an optional event date; saving it publishes the event to
    the `news.notices` calendar with the notice's visibility, and unpublishing or deleting the
    notice removes it.
  * **Users (pull):** a "Birthdays" provider (read-only, enabled by a setting, from the optional
    `user_profiles.birth_date`, only the day and month, only to logged-in users) shows the provider mode without storing anything.

### Updates

* **Shows, never applies.** The UI only tells that a new version exists: dashboard widget,
  a notification to admins and a page with the installed version, the latest version and its
  release notes. Updating is done **from the console**.
* **Source:** the core repository is configured in `Config\Updates` (e.g.
  `github:owner/repo` or a GitLab URL, plus an optional token in `.env` for private
  repositories); modules may declare their own `$repository` in the manifest.
* **Checking:** a scheduled task (daily) or the "check now" button / `php spark updates:check`
  queries the latest release (tags following semantic versioning), compares it with the
  installed version, and caches the result. **No outbound HTTP on regular requests.** Network
  errors are logged and shown, never break the dashboard.
* **Updating:** `php spark app:update` guides the process: checks for local changes, puts the
  application in maintenance mode, makes a backup (Backup module), updates the code (`git pull`
  of the tag, or downloads the release archive), and **verifies it before applying anything**:
  the archive's SHA-256 **and** its **Ed25519 signature** (libsodium, minisign-compatible) with
  the public key shipped in `Config\Updates` (or a signed git tag, `git verify-tag`, for `git pull`
  installs); an unverified release is refused. The owner generates the key pair on their own
  computer and the private key never leaves it (never in the repository or CI);
  `docs/updates.md` documents the signing procedure for every release and key rotation. Then it runs `composer
  install --no-dev`, `modules:sync` (migrations, assets), clears caches, and leaves maintenance
  mode. On failure it stops, keeps maintenance mode, and explains how to restore the backup.
  `--dry-run` shows the plan.
* Downloads only over HTTPS from the allowed hosts (see "Outbound requests"); `git` and
  `composer` use their own clients, run only from the console, and are documented as exceptions
  in `docs/outbound-requests.md`.
* Permissions: `updates.view`, `updates.check`.

### Backup

* **Content of a backup:** the database (all tables, portable dump generated with the Query
  Builder in chunks, or `mysqldump` when available), the files of both storage disks,
  `writable/uploads` (only if not empty), the module registry and versions, **`.env` and the encryption key**, and
  the extra paths declared by modules (`$backup`). A `manifest.json` records the application
  and module versions, date, contents and SHA-256 checksums.
* **Always encrypted with a password.** Because the backup contains the encryption key and
  other secrets, a password is **mandatory**: the archive cannot be created without one, and the
  password is never stored; scheduled backups use a password from `.env` (`backup.password`).
  * **Authenticated, streaming encryption** (archives may not fit in memory): libsodium
    `crypto_secretstream_xchacha20poly1305`, or chunked AES-256-GCM with a tag per chunk and a
    header MAC; the format version is recorded in the archive header. A modified file is detected
    before anything is restored.
  * **Key derivation:** Argon2id (`sodium_crypto_pwhash`) or PBKDF2-SHA256 with at least 600,000
    iterations and a random salt stored in the header.
  * **Password strength:** at least 12 characters (checked in the UI and the console).
  * Backup files are created `0640` in `writable/backups`; restoring extracts with the same
    zip-slip and zip-bomb checks as module packages.
* **Console and UI** with the same service:
  * `php spark backup:create [--only=db|files] [--password]`, `backup:list`,
    `backup:restore <file> [--only=db|files] [--with-env]`, `backup:verify <file>`, `backup:prune`;
    `--password` takes no value: it asks for the password with a hidden prompt (never on the
    command line, where it would end up in the shell history and the process list);
    non-interactive runs read `backup.password` from `.env` or an environment variable;
  * a back-office page: list of backups (date, size, type, result, who), create now,
    download (re-authentication required), verify, delete, upload a backup file, and restore.
* **Storage and retention:** in `writable/backups` (outside `public/`), with retention rules
  (keep the last N daily/weekly/monthly) applied by a scheduled task; optional copy to another
  Storage disk.
* **Restore** (superadmin only, `backup.restore`):
  * requires re-authentication, the backup password and a confirmation typed by the user;
  * verifies checksums and compatibility (core and module versions; refuses a newer backup on
    an older installation);
  * makes an automatic safety backup first, enters maintenance mode, restores database and
    files (optionally only one of them), runs `modules:sync`, and leaves maintenance mode;
  * keeps the current `.env` (database credentials and base URL of the new server) and, after a
    confirmation, merges only the secrets from the backup (`encryption.key`, `app.maskKey`,
    `backup.password`); `--with-env` restores the whole `.env` (console only). From the UI, when
    the project root is not writable by the web server (the recommended setup), the merged `.env`
    content is shown to copy;
  * long restores from the UI run as a background job with progress; the console is the
    recommended way for large sites.
* Notifications on success/failure of scheduled backups; everything goes to the activity log.
* Permissions: `backup.list`, `backup.create`, `backup.download`, `backup.delete`,
  `backup.restore`.

## Security baseline (must be on by default)

* **Requests and headers** (all configurable, see "Configuring headers and CSP" below):
  * CSRF (session based, regenerate) on every POST; no blanket exemptions (only the API group,
    which never accepts cookies);
  * **CSP with nonces** everywhere (back office and public site), with a strict default:
    `default-src 'self'`; `script-src 'self' 'nonce-…'`; `style-src 'self' 'nonce-…'`;
    `img-src 'self' data:`; `connect-src 'self'`; `object-src 'none'`; `base-uri 'self'`;
    `form-action 'self'`; `frame-ancestors 'none'`; never `unsafe-inline` or `unsafe-eval`. No
    inline scripts, handlers or style attributes in any module or theme (the exceptions are listed
    in "Front-end assets and JavaScript");
  * a Core `SecurityHeaders` filter driven by `Config\SecurityHeaders`, which replaces
    CodeIgniter's `secureheaders` filter (it hard-codes other values; never stack both):
    `Strict-Transport-Security` (production, `max-age` ≥ 1 year),
    `Referrer-Policy: strict-origin-when-cross-origin`, `X-Content-Type-Options: nosniff`,
    `Permissions-Policy` (camera, microphone, geolocation… disabled), `X-Frame-Options: DENY`
    for old browsers;
  * HTTPS-only cookies in production, `HttpOnly`, `SameSite=Lax`;
  * `forceGlobalSecureRequests` in production;
  * `allowedHostnames` configured.
* **Configuring headers and CSP:** the defaults live in `Config\ContentSecurityPolicy` and
  `Config\SecurityHeaders` (Core), overridable from `.env`. Modules add what they need in their
  manifest (e.g. the calendar's feed URLs are `'self'` already; a video module may add
  `frame-src https://www.youtube-nocookie.com`), and admins add extra allowed origins per
  directive in **Settings › Security** (`settings.security` + re-authentication; settings
  `Core.csp<Directive>`, e.g. `Core.cspImgSrc`), validated as origins (no wildcards for scripts
  and styles). The final policy is shown read-only in the Security status
  page. `docs/security.md` explains each header, the defaults and examples (embedding a video, a
  map, an external analytics script, a CDN for fonts), and a `Content-Security-Policy-Report-Only`
  mode to test a change before enforcing it.
* **Rate limits** (Throttler, values in settings), each one tested:
  * login (per IP and per account), password reset, magic link, 2FA code verification and
    recovery codes, re-authentication;
  * API: `auth/token` and `auth/token/verify` (per IP and per account), requests per token, per IP
    for public endpoints;
  * iCal feeds per token, upload tokens per user, public forms (with captcha), async widget URLs.
* **Environment:** production by default. The installer writes `CI_ENVIRONMENT=production`;
  development features (toolbar, verbose errors) only when explicitly enabled.
* **Uploads:** see "Storage". Web server snippets for Apache and Nginx in the docs to forbid
  execution in upload and storage folders and to deny `.env`, `writable/` and dotfiles.
* **Data handling:**
  * no raw SQL with interpolated values;
  * no `getPost()` passed wholesale to inserts (models declare `allowedFields`, kpcrudy and
    kpapy only accept their declared fields);
  * escape everything in views;
  * HTML input sanitised with HTMLPurifier (configured per field: pages allow more than news);
  * redirects only to internal paths (`safe_redirect()`).
* **Dangerous capabilities:** restoring backups is superadmin-only, re-authenticated and fully
  logged; updating the code is **console only**; modules and themes are installed by copying
  their folder or from the console, and a ZIP uploaded in the UI is only **staged** (validated,
  approved, then installed by a console command or a privileged scheduled job): the web server
  never writes code and writes only `writable/`, `public/assets/modules`, `public/assets/core`,
  `public/themes` and `public/uploads`. There is no file editor and no self-update from the
  browser.
* **API:** token authentication only (the API never reads the session cookie, and no web route
  is exempted from CSRF), scopes intersected with permissions (also for superadmins), API login
  with the user's active 2FA or an email code, rate limits per token and IP, CORS closed by
  default, no stack traces outside development, documentation only in the back office.
* **Secrets:**
  * only in `.env` (encryption key, `app.maskKey`, `backup.password`, mail and repository
    tokens), never in tracked files; the `env` template has no real values;
  * TOTP secrets encrypted (CI4 Encryption);
  * API tokens, recovery codes, iCal feed tokens and remembered-device cookies stored **hashed**;
    iCal tokens random, regenerable and rate-limited;
  * backups always encrypted with a password;
  * `docs/security.md` explains how to rotate the encryption key, the mask key (it changes every
    masked id) and the tokens.
* **Outbound requests** (SSRF protection): **no outbound HTTP on regular requests**; update
  checks, `modules:install <url>`, email and any module that calls an external service go
  through a Core `OutboundClient` that only allows **HTTPS** to hosts in an **allow-list**,
  refuses private and loopback IP ranges (also after DNS resolution and redirects), and has
  timeouts and a maximum response size. The allow-list is configured in a file
  (`Config\Outbound`, overridable from `.env`), extended by modules in their manifest
  (`$outboundHosts`, shown before enabling the module) and editable in **Settings › Security**
  (`settings.security` + re-authentication); every request is logged. SMTP is not HTTP: the mail
  service checks the SMTP host against the same allow-list. `git` and `composer` (console-only
  `app:update`) use their own clients and are documented exceptions. Documented in
  `docs/outbound-requests.md` with examples (GitHub releases for Updates, an SMTP relay, a
  module calling a weather API).
* **Supply chain:** `composer.lock` committed, `composer audit` in CI and in `security:check`,
  Dependabot (Composer and GitHub Actions), checksums of vendored front-end libraries
  (`vendor.json`), secret scanning in CI (e.g. gitleaks). Documented in `docs/dependencies.md`.
* **Security status (`security:check`):** see "Installer and CLI" and the Security status page
  below.

### Security status page and `security:check`

A read-only checklist to move an installation to production safely, available in the console
and in the back office (Core, permission `security.check`, superadmin and admin by default):

* **`php spark security:check [--probe=<url>] [--json] [--only=<group>]`** runs every check and
  prints its status (ok, warning, error), what it means and how to fix it; it exits with a
  non-zero code when there are errors, so it can run in a deployment pipeline. Checks:
  * **configuration:** `CI_ENVIRONMENT=production`, debug toolbar and detailed errors off,
    `forceGlobalSecureRequests`, secure cookies, `allowedHostnames`, CSP enforced (not only
    report-only) without `unsafe-*`, HSTS, CORS of the API not open to `*`;
  * **secrets:** encryption key and `app.maskKey` set (and the same for kpcrudy and kpapy),
    `backup.password` set when scheduled backups are on, `.env` not readable by other users,
    no secret found in tracked files;
  * **installation:** `writable/install.lock` present (web installer closed), no demo data
    loaded, permissions of `writable/` (`0750` folders, `0640` files; stricter modes accepted),
    the web server user can write only `writable/`, `public/assets/modules`, `public/assets/core`,
    `public/themes` and `public/uploads` (not the project root, `.env`, `modules/` or `themes/`),
    `public/` contains no `.env`, `writable` or PHP files outside `index.php`;
  * **accounts:** superadmins without 2FA (warning), groups where 2FA is optional (info), the
    minimum password length (warning below 12), users with personal tokens without expiry
    (info);
  * **dependencies:** the last `composer audit` result (it has no offline mode: the daily task or
    CI runs it and stores the result with its date; the check reports "last checked on …" and
    warns when it is older than 7 days) and the `vendor.json` checksums;
  * **outbound:** the allow-list, and hosts added by modules;
  * **`--probe=<url>`** (optional, requests to the site itself, never to third parties, through a
    dedicated client limited to the configured base URL host and the site's own
    `allowedHostnames`, with plain HTTP allowed only for the redirect check; not the
    `OutboundClient`, which refuses loopback and private addresses): the
    security headers are present; `/.env`, `/writable/…` and `/modules/…` are not served; a PHP
    file in `uploads/` is not executed (a harmless probe file created and removed); an external
    `return` URL is not followed (open redirect); the login is throttled; HTTP redirects to
    HTTPS.
* **Security status page:** the same checks (without `--probe`, which needs a deliberate
  action: a "run external probe" button with re-authentication), grouped, each with its
  status, explanation and fix, plus the effective CSP and headers. A dashboard widget
  "Security status" (admins) shows the summary and links to the page. Results are cached and
  refreshed daily by a scheduled task, which notifies superadmins when a check turns red.
* Documented in `docs/security.md` (production checklist) and `docs/commands.md`.

## Installer and CLI

* **`php spark app:install`** (non-interactive with `--options`, interactive otherwise):
  * writes `.env` and generates the encryption key;
  * runs `modules:sync` (migrations, seeds, assets);
  * asks whether to load demo data, and for which modules (`--demo` when non-interactive);
  * creates the first superadmin (and offers to set up 2FA);
  * prints the cron line for `schedule:run`;
  * writes `writable/install.lock`, which is ignored by git.
* `app:install` also generates `app.maskKey` (shared by kpcrudy and kpapy) and, at the end,
  runs `security:check` and prints its summary.
* **Optional web installer:** available only while the application has not been configured,
  by the web or by the console: it answers 404 as soon as `.env` or `writable/install.lock`
  exists, and also refuses to run when the database already has tables. It warns when the
  page is not served over HTTPS. It uses the same service as the CLI. When the project root is
  not writable by the web server (the recommended setup), it shows the `.env` content for the
  operator to copy instead of writing it.
* **Commands** (all documented in `docs/commands.md`):
  * modules and themes: `modules:sync|list|status|enable|disable|install|uninstall|demo`,
    `themes:install|uninstall`, `make:module`, `make:theme`, `assets:publish [--watch]`;
  * permissions and security: `permissions:list|check`, `security:check`;
  * scheduler: `schedule:run|list`;
  * backups and updates: `backup:create|list|restore|verify|prune`, `updates:check`,
    `app:update`;
  * calendar and API: `calendar:resync <module>`, `kpapy:openapi`, `kpapy:routes`,
    `kpapy:token`;
  * application: `app:install`, `about` (Core) and `app:cache-clear` (Core caches plus
    CodeIgniter's cache; CodeIgniter's own `cache:clear` cannot be replaced, so modules listen to
    a `cache.cleared` event).

## Quality, tests and delivery

* **PHPUnit:**
  * unit tests for manifest validation, the permission catalogue, implied permissions and the
    resolver (including the "undeclared operation" failure), the menu builder and public menu
    rendering, theme view resolution, the asset manifest and import map, settings casting,
    the scheduler (cron expressions, overlapping), notifications routing, TOTP verification
    (time drift, reuse), backup manifest/encryption/verification, version comparison;
  * feature tests for login with both 2FA methods and recovery codes, throttling and
    escalation rules, a route-security test, News permissions and visibility (public, private,
    groups) and notices on the dashboard, widget visibility per group, file manager operations,
    folder ACL inheritance, upload tokens (a tampered folder or type is refused), private
    avatars not served to guests, path traversal attempts, the iCal feeds (valid RFC 5545
    output, a regenerated token invalidates the old URL, permission changes are reflected,
    `304` when unchanged), the calendar API (publish/unpublish idempotency, locked
    published events, providers filtered by user, events of disabled modules hidden), the core
    API (token scopes, also for superadmin tokens; API login with each 2FA method and with the
    email code for users without 2FA, refused with a wrong or reused code; the same masked id as
    the web; problem+json errors; rate limits; the documentation page only for `api.docs`),
    restricted events (a
    group + user audience is visible only to them, also for admins in the calendar and feeds;
    `events-events.all` sees them in the administration list; an editor cannot target groups
    they may not assign), backup create/verify/restore on SQLite, module
    enable/disable, and module install/upgrade/uninstall from a ZIP (including staged
    installs and refused packages: zip-slip paths, symbolic links, invalid manifests), and demo
    data load/remove (only demo rows are removed; refused in production by default);
  * **security tests:** re-authentication is required by every protected action (and expires);
    a password change ends other sessions and revokes app-login tokens; impersonation cannot
    target a superadmin or reach sensitive actions; SVG/HTML uploads are refused and private
    files are served with `nosniff` (and `attachment` when not images); an external
    `return`/redirect URL is refused; a `javascript:` menu link is refused; every response has
    the CSP and security headers; the route-security test covers `backend/` and `api/v1/`; each
    rate limit answers `429`; outbound requests to a non-allowed host, a private IP or plain HTTP
    are refused; zip bombs are refused; a modified backup fails authentication before restoring;
    `security:check` reports errors on a development configuration and passes on a hardened one;
    a password shorter than `Auth.minimumPasswordLength` is refused; a session re-created by
    remember-me never outlives the remember-me lifetime; every declared setting returns its
    manifest default; Shield's computed `AuthGroups` settings match `group_permissions`.
* **GitHub Actions:** PHP 8.2/8.3/8.4, composer validate + audit, php-cs-fixer (dry run),
  PHPStan, PHPUnit with SQLite and MariaDB, a check that published assets are up to date, a CSP
  check (no inline scripts/handlers/styles in the views of `app/`, `modules/` and `themes/`), the `vendor.json` checksums, secret
  scanning (e.g. gitleaks), and Dependabot for Composer and GitHub Actions.

## Documentation (English)

* `README.md` with quick start and the cron line;
* `CHANGELOG.md` (Keep a Changelog, semantic versioning) and `LICENSE`;
* `docs/architecture.md`: modules, Core services, request lifecycle, caching;
* `docs/creating-a-module.md`: step by step with News as the example (manifest, resources,
  menus, settings, widgets, tasks, notifications, assets, tests), the **module checklist**, how
  to work with Claude Code (`/new-module`, `/check-module`, the module's `CLAUDE.md`, what the
  cloud environment needs: PHP 8.2+ with `intl`, `mbstring`, `sqlite3`, `gd`, `zip`, Composer,
  network access to Packagist and GitHub, and a setup script that runs `composer install`),
  developing a module in its own repository, and how to package it as a ZIP (and sign it);
* `docs/installing-modules.md`: the three ways to install modules and themes (folder, console
  ZIP, staged upload), package validation and signatures, the cron line for pending installs,
  upgrading, uninstalling and what happens to the data;
* `docs/permissions.md`: resources, names (and the `'name'` override), wildcards (how Shield
  matches them), implied permissions, personal screens granted to every group, own records,
  hidden fields, the matrix;
* `docs/themes.md` (includes responsive rules and the PWA manifest): theme structure and manifest, layouts, regions, menu locations, options,
  view overrides, child themes, `make:theme`;
* `docs/frontend.md`: assets, publishing, import map, components with `data-component`, the
  core JS API (`http`, `confirm`, `toast`, file picker), CSP rules, vendoring libraries, and a
  **step-by-step tutorial "A new view with JavaScript"** that builds a complete example in a
  module (the "task board" page of the `Tasks` tutorial module), showing:
  * the route, the controller method (`BackendController`) and the view, which uses the theme
    layout and the standard page header;
  * the markup with `data-component="<module>/board"`, its options in `data-options` and larger
    data in a `<script type="application/json">` block (no inline code);
  * the component file `modules/<Name>/Assets/js/board.js` as an ES module exporting
    `mount(element, options)`, and how it **imports** shared code with the import-map prefixes
    (`import { http } from 'core/http.js'`, `import { confirm } from 'core/ui/confirm.js'`,
    `import { toast } from 'core/ui/toast.js'`) and other files of the same module with relative
    paths (`import { renderCard } from './board/card.js'`);
  * calling a JSON endpoint of the module with `http` (CSRF handled), with permission checks on
    the server, and updating the page;
  * a module CSS file and how the view asks the layout to load it (`asset()` in the `head`
    section), and translations passed to JavaScript (a `data-i18n` JSON block from the
    language files);
  * **syncing assets:** running `php spark assets:publish` (or `modules:sync`) after creating or
    changing files in `Assets/`, what it copies where, the version hash in the URLs, and the
    development workflow (a `--watch` mode or "publish on request" in development so edits show
    up without running the command each time; never editing `public/assets/modules/…`);
  * debugging: the import map printed by the layout, browser devtools, typical errors (a missing
    publish, a wrong prefix, a CSP violation) and how to fix them;
  * a minimal test (a feature test for the endpoint, and optionally a JS unit test).
  The same tutorial is linked from `docs/creating-a-module.md`;
* `docs/scheduler.md`: the cron line for Linux hosts and shared hosting, writing a task,
  schedules, overlapping, logs, the fallback without cron, troubleshooting;
* `docs/notifications.md`: notification types, channels, user preferences, sending from a
  module;
* `docs/widgets.md`: writing a widget, sizes, options, async widgets, per-group configuration,
  and a **step-by-step tutorial "A new widget"** with two complete examples in a module:
  * a **simple server-side widget** ("My pending tasks", in the `Tasks` module): the class implementing
    `WidgetInterface` (`id()`, `title()` from the language files, `permission()`, `sizes()`,
    `settings()` with one option such as "number of items", `render()` using a widget view and
    the standard card component), its declaration in the manifest (`$widgets`), and how it
    appears in the per-group configuration and the user's dashboard after `modules:sync`;
  * an **asynchronous widget with JavaScript** ("Task activity chart", in the `Tasks` module): `data()` returning JSON
    from a permission-checked endpoint, the view with `data-component="<module>/activity-chart"`,
    the ES module in `Assets/js/` that imports `core/http.js`, renders the chart and refreshes it,
    loading and error states, and the per-user cache;
  * translations in ca/es/en, isolating failures (what the user sees when the widget throws),
    publishing its assets, and a test (the widget is hidden without its permission or when its
    group does not have it enabled, and its async URL answers 403);
* `docs/api.md`: the API for client developers: authentication (personal tokens, scopes, app
  login with 2FA or the email code), the core endpoints, conventions (envelope, pagination,
  filters, includes, errors, ETags), rate limits and CORS, how a module exposes a resource with
  kpapy, and **the API documentation page**: where it is (`backend/api/docs`), the `api.docs`
  permission, trying requests with "Try it", downloading the OpenAPI document, and publishing it
  with the `Api.publicSpec` setting — with a worked example (listing news with a personal token,
  as a "Try it" request and as the copied `curl` command);
* `docs/commands.md`: reference of **every** `spark` command of the skeleton (purpose, options,
  an example and its output, the permission or environment it needs), linked from the other
  documents instead of repeating them;
* `docs/calendar.md`: publishing events from a module, providers, calendars, visibility and
  access policies, resync, iCal feeds;
* `docs/files.md`: disks, upload rules, folder permissions and predefined spaces, personal
  space, private files and access policies, the file picker (modes, upload tokens), quotas,
  web server rules;
* `docs/backup-restore.md`: what is included, passwords and key handling, console and UI usage,
  retention, restoring on a new server, disaster recovery checklist;
* `docs/updates.md`: release process (tags, release notes, signing the release archive with the
  owner's Ed25519 key, key rotation), checking for updates, `app:update` step by step, rolling
  back;
* `docs/security.md`: the security baseline; a **threat model** (who attacks what: anonymous
  visitors, logged-in users trying to escalate, stolen tokens or passwords, malicious module
  packages or uploads, a compromised server) and the control that answers each threat; 2FA
  (policy and recovery), re-authentication, sessions and passwords (8 minimum, 12 recommended);
  **configuring headers and CSP** with examples; rate limits; web server configuration (Apache
  and Nginx), the folders the web server may write and the risk of published JavaScript;
  `app.maskKey` (its fallbacks and why it must be set); the **production checklist** with `security:check` and the Security status page;
  **incident response** (revoke tokens and sessions, rotate the encryption key and `app.maskKey`,
  restore a backup, read the activity log);
* `docs/outbound-requests.md`: why outbound calls are restricted (SSRF), the `OutboundClient`,
  the allow-list in `Config\Outbound`, in module manifests and in Settings › Security, SMTP hosts,
  the console-only exceptions (`git`, `composer`) and the `--probe` client, with examples;
* `docs/dependencies.md`: Composer constraints, `composer audit`, Dependabot, vendored
  front-end libraries (`vendor.json`, checksums, licences) and how to add or update one;
* `docs/user-guide.md`: back-office user manual (profile, 2FA, dashboard, notifications, files,
  pages, menus, news, events, subscribing to the calendar from external clients) — also shipped in Catalan and Spanish.

### Tutorials (`docs/tutorials/`)

Step-by-step guides for developers who extend the application. Each one builds a working piece
of the **`Tasks` tutorial module** (a small task manager: projects, tasks, assignees, due
dates), so the finished code can be read and run; the module is disabled by default and
enabled from the Modules screen (with demo data) to try it. `docs/tutorials/README.md` is the
index: what each tutorial teaches, prerequisites and the recommended order. Every tutorial ends
with the tests that cover it, and the `Tasks` module's tests run in CI.

1. **A complete module from scratch with kpcrudy:** `make:module Tasks`, migrations per table,
   the manifest (resources, menus, default grants, ca/es/en texts), a CRUD with a 1-N relation
   (project) and an N-N relation (assignees), filters, a custom operation (`complete`) with its
   permission, and `modules:sync`.
2. **Extending an existing CRUD:** a translated field, a `multiselect` (labels), `hideFields()`
   depending on a permission, an `afterWrite()` hook, and a bulk action with its permission
   declared in the manifest.
3. **A new view with JavaScript** (the task board; see `docs/frontend.md`, linked from here).
4. **A new widget** (server-side and asynchronous; see `docs/widgets.md`, linked from here).
5. **A public page:** a route, `FrontendController`, a view in the public layout, SEO metadata,
   and a menu link type (`$menuLinks`) so the page can be added to a public menu.
6. **Talking to other modules:** a notification type sent to the assignees, publishing due dates
   to the calendar (push) or offering them as a provider (pull), and listening to an event of
   another module.
7. **A scheduled task:** a daily reminder of overdue to-do tasks: schedule, `withoutOverlapping()`,
   result and output in the scheduled tasks screen, and its test.
8. **A new setting:** declaring it in `$settings`, validation, reading it with `setting()`, and
   seeing it in the Settings UI.
9. **A child theme:** `make:theme`, changing colours and logo, overriding a view of the `Tasks`
   module, and adding the theme's own CSS. The finished theme ships as `themes/tasks-demo/`
   (child of `default`), kept in the repository as tutorial material (themes live in `themes/`,
   not inside the module).
10. **Uploading files in a form:** a file-picker field (module folder, allowed types), attachments
    on the private disk served with permissions, and a `FileAccessPolicy` that follows the
    task's visibility.
11. **A JSON API for third parties:** exposing the `Tasks` resources with kpapy (`make:module
    --api`, `$api` in the manifest), the same permissions as the CRUD, filters and includes,
    a custom operation, a personal token with scopes, the app login with 2FA, testing it with
    the API documentation page, and the OpenAPI document.
12. **A public form with email:** a "request a task" form on the public site with captcha,
    validation, storage, a notification to admins and a confirmation email (ca/es/en).
13. **Releasing a new version of a module:** a schema-change migration, bumping the version,
    building (and signing) the ZIP, installing it with `modules:install`, and how the update
    notice appears.
14. **Writing tests:** a permission test with a forged POST, a view/feature test, a migration
    up/down/up test, and a test for a JavaScript component's endpoint.

## Pitfalls to avoid

* **Routes:** loading module routes twice (discovery + manual `require`); a generated
  `Routes.php` that queries the database (it breaks `spark` on an empty database); redirecting
  to routes that do not exist (`redirect('403')`).
* **Permissions:** flat read/create/update/delete permissions per module, which hide kpcrudy's
  per-operation granularity; permissions inferred from route options; pages that must be
  "scanned" before they work; scanners that never update existing rows; custom routes for
  actions that should be `addOperation()`s.
* **Security shortcuts:** trusting masked ids as access control; leaving `app.maskKey` empty
  (ids differ between web and API); calling external services from a request or without the
  `OutboundClient`; serving uploaded SVG/HTML inline; "return to" parameters that accept
  external URLs; publishing the API documentation without a permission.
* **Unsafe defaults:** installer defaulting to `development`; captchas skipped in development;
  admin passwords in tracked files; backups without a password; updates from the browser.
* **Front end:** inline scripts and handlers; editing published assets instead of the module
  sources; loading every module's JavaScript on every page; CDNs in production.
* **Outbound calls:** per-request calls to external services (GeoIP, update checks).
* **Mixed languages** in code comments and UI: English for code and docs; language files for
  UI.

## Deliverables and acceptance criteria

1. **Fresh install:** a fresh clone installs with one command on MariaDB and SQLite, and the
   back office and public site work with all the modules and the default theme.
2. **Adding a module:** `make:module Events2` (or copying `modules/News`, renaming) and
   `modules:sync` produce a working module with menu, permissions, settings, assets, a widget
   and a task, without touching any core file; it includes `modules/Events2/CLAUDE.md`, and
   `/check-module Events2` reports it complete once its tests are written.
3. **Enable/disable/install:** disabling a module removes its menus, routes (404), widgets, tasks
   and menu link types; enabling it restores them. A module packaged as a ZIP installs with
   `modules:install` and with a staged upload from the UI, and `modules:uninstall` removes it
   cleanly; a ZIP with a `..` path, a symbolic link or an invalid manifest is refused.
4. **Permissions:** a user with only `news-posts.list` and `news-posts.show` sees the News list
   without write buttons, and gets 403 or "not allowed" on every write URL and custom operation,
   also via forged POSTs; a user without `news-posts.author` never sees the author fields (list,
   details, export); adding an `addOperation()` without declaring it fails in development.
5. **2FA:** a user can enable either email or app 2FA, log in with it and with a recovery code;
   a group policy forces setup.
6. **Dashboard:** an admin hides a widget for a group and its users no longer see it (nor its
   async URL); a user reorders their widgets and restores the group layout. Private notices
   appear only to their audience.
7. **Themes and assets:** a child theme overriding one view and one colour works without
   copying the rest; no page has inline scripts (the CSP check passes).
8. **Backup:** a backup created from the console restores from the UI (and the other way round)
   on a clean installation with the password; a wrong password or a tampered file is refused.
9. **Updates:** with a newer tag in the configured repository, the dashboard shows the new
   version after `updates:check`, and `app:update --dry-run` shows the plan.
10. **API and devices:** a token limited to `news-posts.list` can list news but gets `403` on
    any write; a user without 2FA only obtains a token after typing the code sent to their
    email; the same record has the same masked id in the web and in the API; the API
    documentation page is only reachable with `api.docs`; the main screens have no horizontal
    overflow at phone and tablet widths, and the site can be installed as a PWA.
11. **CI:** all checks pass, and `composer audit` reports no known vulnerabilities;
    `composer.json` requires `siensis/kpcrudy` and `siensis/kpapy` with `^1.0` (no `dev-main`,
    no `repositories` entry), every CRUD is a `Kpcrudy\Crud` and every API endpoint a
    `Kpapy\Resource`.
12. **Security:** on a fresh production install, `php spark security:check --probe=<site>` passes
    with no errors (and fails on a development configuration); the Security status page shows
    the same results; sensitive actions ask for re-authentication; the response headers match
    the documented defaults, and an origin added in Settings › Security appears in the CSP.
13. **Documentation:** the documents listed above exist and match the code; every tutorial in
    `docs/tutorials/` can be followed from a fresh install, and the `Tasks` module (enabled with
    its demo data) contains the finished code and passes its tests.

Work in small, reviewable commits, in the order of the session prompts in `deploy/` (session 00:
`CLAUDE.md`, `docs/specification.md` and `composer require` of the required libraries; then the
Core kernel, assets and themes, Auth, RBAC, menus and security, the kpcrudy and kpapy
integrations, the cross-module services, the MVP modules and installer, the remaining modules,
`security:check`, i18n, the `Tasks` tutorial module, docs, tutorials, quality and release).
Before each step, state the plan and the files you will create. After each step, run the tests
and show the results.
