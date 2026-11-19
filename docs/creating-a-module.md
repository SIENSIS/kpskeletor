# Creating a module

Everything a site adds lives in a module, `modules/<Name>/`, described by its manifest
`Config/Module.php`. This page walks through a module step by step with **News**
(`modules/News`, the reference module) as the example; the full contract is in
`docs/specification.md` ("The module contract"). The `Tasks` tutorial module
([`tasks.md`](tasks.md); its `CLAUDE.md` maps every feature to its file) shows the pieces News
does not have, such as JavaScript components and an asynchronous widget. The
[tutorials](tutorials/README.md) build that module piece by piece, from `make:module` to
releasing a new version (tutorial 13 is the worked example of section 9).

Related guides: [`cruds.md`](cruds.md) (kpcrudy screens), [`api.md`](api.md) (kpapy resources),
[`permissions.md`](permissions.md), [`menus.md`](menus.md), [`settings.md`](settings.md),
[`widgets.md`](widgets.md#tutorial-a-new-widget) (tutorial "A new widget"),
[`frontend.md`](frontend.md#tutorial-a-new-view-with-javascript) (tutorial "A new view with JavaScript"),
[`scheduler.md`](scheduler.md), [`notifications.md`](notifications.md),
[`calendar.md`](calendar.md), [`i18n.md`](i18n.md), [`commands.md`](commands.md) (every command
and option).

## 1. Generate it

```bash
php spark make:module Books                           # empty module
php spark make:module Books --crud=books_items        # with a kpcrudy CRUD of a new table
php spark make:module Books --crud=books_items --api  # and its kpapy API resource (needs --crud)
php spark make:module Books --standalone              # kept in its own repository (section 8)
php spark modules:sync                                # migrations, permissions, settings, assets
```

`make:module` (templates in `modules/Core/Modules/Templates/`) writes:

| File | What it is |
|---|---|
| `Config/Module.php` | the manifest: a `main` page resource, default grants for `admin`, a menu entry, a `perPage` setting, the widget and the task |
| `Config/Routes.php` | the `main` screen with its `can:` filter |
| `Controllers/Home.php`, `Views/index.php`, `Assets/js/<name>.js` | a `BackendController` screen with an ES-module component (`data-component`) |
| `Language/{ca,es,en}/<Name>.php` | the texts, same keys in the three languages |
| `Database/Migrations/…`, `Database/Seeds/<Name>Seeder.php`, `<Name>DemoSeeder.php` | a migration with `down()`, initial data, demo data |
| `Widgets/<Name>Widget.php`, `Tasks/<Name>Task.php` | a dashboard widget and a scheduled-task stub |
| `Tests/<Name>ModuleTest.php` | screen permission, forged POST, migrations up/down/up |
| `CLAUDE.md` | purpose, resources and permissions, decisions, open tasks, how to test |

`--crud=<table>` migrates the table, runs `kpcrudy:make` and rewrites its controller and route
to `CrudFactory`; `--api` runs `kpapy:make` for the same table (`api/v1/<module>/<key>`,
`setResource()` = the CRUD's resource), declares it in `$api` and adds `Tests/<Name>ApiTest.php`
(token and scopes). `make:module` also writes the generated `app/Config/ModuleNamespaces.php`; run
`modules:sync` afterwards. Outside production the new module is enabled by the sync.

Work only inside `modules/<Name>/`. Do not change the Core, another module or a shared
convention for one module's needs: write the request down and ask.

## 2. The manifest, key by key

`Config/Module.php` extends `Modules\Core\Config\ModuleManifest`. Every key is typed and has a
default; `ManifestValidator` checks them on `modules:sync` and stops with a message naming the
key. News' values:

| Key | Type (default) | News |
|---|---|---|
| `$name` | string | `'News'` (StudlyCase, equal to the folder; `Security` and `Update` are reserved) |
| `$version` | string (`'0.0.0'`) | `'0.1.0'` (semantic version) |
| `$icon` | string (`'bi bi-box'`) | `'bi bi-newspaper'` (Bootstrap Icons) |
| `$description` | string (`''`) | not set; a language key (`<Module>.module.description`) or text shown on the Modules screen and by a package |
| `$author` | string (`''`) | not set; a package may only replace an installed module of the same author |
| `$requires` | name => constraint | `['Core' => '^1.0', 'Auth' => '^0.2', 'Dashboard' => '^0.1']` |
| `$enabledByDefault` | bool (`true`) | default; `false` installs the module disabled (the `Tasks` module, D-32-01) |
| `$repository` | ?string (`null`) | not set; where Updates looks for new versions (`'github:acme/news-module'`, [`updates.md`](updates.md)) |
| `$resources` | key => definition | `posts`, `categories`, `mine` (crud), `read` (page), `api` (crud, for the API resource) |
| `$defaultGrants` | group => grants | see section 3 |
| `$menu` | list of entries | the reader with "All posts", "My posts", "Categories" under it |
| `$menuLinks` | list of classes | `[Menu\CategoryLink::class]` (link type of the public menu editor, [`menus.md`](menus.md)) |
| `$settings` | key => definition | `perPage`, `feedItems` |
| `$widgets` | list of classes | `[Widgets\Notices::class, Widgets\LatestNews::class]` |
| `$tasks` | list of classes | `[Tasks\ExpireNotices::class]` |
| `$notifications` | list of classes | `[Notifications\NewNotice::class]` |
| `$backup` | array | `['files' => ['modules/news']]` (Storage folders backed up beyond its tables) |
| `$calendars` | key => definition | `['notices' => ['label' => 'News.cal.notices', 'color' => '#dc3545', 'source' => NoticeCalendar::class]]` |
| `$calendarProviders` | list of classes | `[]` (on-the-fly `CalendarProvider`s) |
| `$api` | key => [path, handler] | `news` (`api/v1/news`) and `public-news` (`api/v1/public/news`) |
| `$csp` | directive => sources | `[]` (covers and images are served by the site) |
| `$outboundHosts` | list of hosts | `[]` (the RSS feed is generated, not fetched) |

Every key must be declared even when empty when it matters for the checklist (`$backup`, `$csp`,
`$outboundHosts`, `$api`): an explicit `[]` with a comment says the question was answered.

## 3. Resources and permissions

```php
public array $resources = [
    'posts' => [
        'label'  => 'News.res.posts',
        'type'   => 'crud',
        'except' => ['import'],
        'extra'  => ['publish' => 'News.perm.publish', 'author' => 'News.perm.author', 'readers' => 'News.perm.readers'],
    ],
    'read' => ['label' => 'News.res.read', 'type' => 'page'],
    // …
];
```

- One resource per CRUD or screen. A `crud` resource gets kpcrudy's operations (`list`, `show`,
  `add`, `edit`, `delete`, `export`, `import`, `trash`, `restore`, `purge`) minus `except`; a
  `page` gets `view`; both add their `extra` operations (custom operations, bulk actions,
  sensitive fields such as `news-posts.author`).
- Names are generated: `news-posts.edit`, `news-read.view`, plus `settings.news` because News
  has settings. Every operation a CRUD asks about must be declared, or `CrudPermissions` throws
  `UndeclaredPermissionException` outside production and `permissions:check` fails.
- `$defaultGrants` are applied once, at the first sync (with their implied permissions):

  ```php
  public array $defaultGrants = [
      'admin'  => ['news-posts.*', 'news-categories.*', 'news-mine.*', 'news-read.*', 'news-api.*'],
      'editor' => ['news-posts.list', 'news-posts.show', 'news-posts.add', 'news-posts.edit', 'news-posts.publish',
                   'news-categories.list', 'news-categories.show', 'news-mine.*', 'news-read.*', 'news-api.*'],
      'user'   => ['news-read.*', 'news-api.*'],
      // superadmin: news-read.*, news-api.*
  ];
  ```

Names, wildcards, the `'name'` override, own records and hidden fields: [`permissions.md`](permissions.md).

## 4. Routes, controllers and views

`Config/Routes.php` is discovered by CodeIgniter and never queries the database:

```php
CrudFactory::routes($routes, 'news-posts', 'backend/news/posts', '\Modules\News\Controllers\Posts::index');

$routes->group('backend/news', ['namespace' => 'Modules\News\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Reader::index', ['filter' => 'can:news-read.view', 'as' => 'news-read']);
});

Resource::routes($routes, 'api/v1/news', '\Modules\News\Controllers\Api\Posts::index', ['filter' => 'kpapy']);
```

- **CRUD screens** (`Controllers/Categories.php`): extend `BackendController`, build the CRUD
  with `$this->crud('news-categories')`, return `$this->renderCrud($crud)` ([`cruds.md`](cruds.md)).
  `CrudFactory::routes()` adds the `backend` chain and `can:<resource>.list`.
- **Other screens** (`Controllers/Reader.php`): `$this->render()` through the active theme with
  the standard components ([`themes.md`](themes.md)); every `backend/` route has `can:`.
- **API** (`Controllers/Api/Posts.php`): a `Kpapy\Resource` with `setResource('news-api')`,
  routed under `api/v1/` with the `kpapy` filter and declared in `$api` ([`api.md`](api.md)).
  A public endpoint uses `kpapy:public` (GET only), like `api/v1/public/news`.
- **Public pages** (`Controllers/PublicNews.php`): no `can:`; rate-limit feeds
  (`ratelimit:feeds`).

## 5. The other pieces

**Database.** One migration per table (`Database/Migrations/`, Forge, `down()` implemented);
News has six tables and one later migration (`AddEventDateToNews`) instead of an edited one.
Initial data in `<Name>Seeder`; demo data only in `<Name>DemoSeeder` (extends
`Modules\Core\Demo\DemoSeeder`, through `DemoData`), loaded with `php spark modules:demo News`.

**Menus** ([`menus.md`](menus.md)):

```php
public array $menu = [
    ['resource' => 'read', 'label' => 'News.menu.read', 'icon' => 'bi bi-newspaper', 'order' => 20],
    ['resource' => 'posts', 'label' => 'News.menu.posts', 'icon' => 'bi bi-journal-text', 'order' => 1, 'parent' => 'read'],
];
```

An entry names a `resource` (its list/view permission) or a `route` plus a `permission`; `zone`
is `sidebar` (default), `settings` or `user`. An entry in the `settings` zone goes in one of the
Core's groups with `parent`: `group.access` (users and access), `group.security` (security
settings and logs), `group.api` (API), `group.customisation` (how the back office looks) or
`group.system` (configuration and maintenance); see [`menus.md`](menus.md), "Groups of the
settings zone".

**Settings** ([`settings.md`](settings.md)): declared with `type`, `default`, `rules`, `label`,
`help` and an optional `group`; the Settings module generates the form (permission
`settings.news`) and the code reads `setting('News.perPage')`.

**Widgets** ([`widgets.md`](widgets.md)): `Widgets/LatestNews.php` extends
`Modules\Core\Widgets\Widget` with `id()` (`news.latest`), `title()`, `permission()`
(`news-read.view`), `settings()` (a `limit` option) and `render()` with a widget view. An
asynchronous widget extends `AsyncWidget` (the `Tasks` module's activity chart).

**Scheduled tasks** ([`scheduler.md`](scheduler.md)): `Tasks/ExpireNotices.php` implements
`TaskInterface` (`name()`, `schedule()` → `Schedule::everyMinutes(5)->withoutOverlapping()`,
`run()` → `TaskResult`); one cron line runs `php spark schedule:run`.

**Notifications** ([`notifications.md`](notifications.md)): `Notifications/NewNotice.php`
extends `NotificationType` (`title()`, `description()`, `icon()`, `render()` →
`NotificationMessage`); `Services/Publisher.php` sends it with `notifyPermission()` or
`notifyGroup()`, never an email inline.

**Calendars** ([`calendar.md`](calendar.md)): declare `$calendars` and publish on every change
through the Core: `Services/NoticeCalendar::sync()` calls `service('calendar')->publish()` or
`unpublish()` after each write and is the calendar's `source` for `calendar:resync`. Never
write to `events` directly.

**Assets** ([`frontend.md`](frontend.md)): CSS, images and ES modules in `Assets/`, published to
`public/assets/modules/<name>/` by `php spark assets:publish` (or `modules:sync`; `--watch` while
editing). Components are mounted by `data-component="<module>/<file>"`, URLs come from
`asset('<module>::<path>')`, no inline scripts, handlers or styles (`php spark csp:check`).
Password inputs get a show/hide button from the Core by themselves (`frontend.md`, "Password
fields"; `data-no-reveal` to opt out). News ships no JavaScript; the `Tasks` board (`Assets/js/board.js`, `Assets/css/tasks.css`) is
the worked example of the tutorial "A new view with JavaScript".

**Texts**: only in `Language/{ca,es,en}/<Name>.php` with the same keys (`res.*`, `perm.*`,
`menu.*`, `settings.*`, `activity.<code>`…).

**Activity**: CRUD writes, custom operations and exports are logged by the Core; other actions
with `activity_log('News', 'notices_updated', […])` (label `News.activity.notices_updated`).

**Tests** (`Tests/`, run with `vendor/bin/phpunit modules/News`; base class
`Tests/NewsTestCase.php` on `Modules\Auth\Tests\AuthTestCase`, [`development.md`](development.md)):

| News test | Covers |
|---|---|
| `NewsModuleTest` | the manifest is valid, migrations up/down/up, module rules (sanitiser, slugs, link type) |
| `PostsCrudTest` | permissions, forged POSTs, own posts only, the hidden author field, notifications |
| `NewsApiTest`, `PublicNewsApiTest` | tokens, scopes, readable posts only, public endpoint |
| `WidgetsAndTaskTest` | widgets per audience and permission, the task, demo data load/remove |
| `NoticeCalendarTest`, `ReaderTest`, `PublicSiteTest`… | calendar, reader screen, public site |

## 6. Module checklist

The same list as the root `CLAUDE.md`; `/check-module <Name>` reviews it.

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

## 7. Working with Claude Code

- **`/new-module <Name> [description]`** (`.claude/commands/new-module.md`): reads the root
  `CLAUDE.md`, this page and the reference modules, asks what is missing (data model, users,
  CRUD, API, widgets, tasks, settings), states the plan and waits for confirmation of the public
  names (permissions, URLs, settings), runs `make:module`, fills the module in, syncs and tests,
  and ends with `/check-module`.
- **`/check-module <Name>`** (`.claude/commands/check-module.md`): reviews the module against
  the checklist (pass / fail / not applicable with file and line), runs `modules:sync`,
  `permissions:check`, `csp:check`, PHPUnit, PHPStan, php-cs-fixer and `security:check`, and
  reports "Module <Name> complete" or what is missing. It does not fix anything unless asked.
- **The module's `CLAUDE.md`**: purpose, resources and permissions, decisions, open tasks and
  how to test (see `modules/News/CLAUDE.md`). A new session reads it first when it resumes the
  module; keep it current.
- **Cloud environment** (Claude Code on the web): PHP 8.2+ with `intl`, `mbstring`, `sqlite3`
  (and `pdo_sqlite`), `gd`, `zip` (plus the extensions listed in
  [`development.md`](development.md)), Composer 2, network access to Packagist and GitHub (for
  `composer install`), and a setup script that runs `composer install`. `deploy/setup.sh` is that
  script: paste it as the environment's setup script; it installs what is missing (PHP
  extensions, Composer with its checksum, optionally MariaDB) and runs `composer install`, retrying
  with `--prefer-source` when the proxy refuses GitHub zip downloads. Tests need no database
  server (SQLite in memory).

## 8. A module in its own repository

To distribute a module, keep it in its own repository and develop it inside a checkout of the
skeleton, so the root `CLAUDE.md`, the commands and the tests apply:

```bash
git clone <skeleton repository> site && cd site
php spark make:module Books --standalone      # or: git clone <module repository> modules/Books
composer install && php spark modules:sync
vendor/bin/phpunit modules/Books
```

`--standalone` adds three files from `modules/Core/Modules/Templates/standalone/`:

- `composer.json`: `type: kpskeletor-module`, PSR-4 `Modules\Books\` → the folder, `extra.kpskeletor`
  with the module name and the Core constraint (replace `your-vendor` and the licence);
- `README.md`: requirements, installing (package or development checkout), developing;
- `CLAUDE.md`: the module's notes plus a summary of the skeleton's rules and the checklist.

Commit only inside `modules/Books/` (it can be a git submodule of your checkout); never change
the skeleton's Core for the module.

## 9. Packaging as a ZIP and signing it

There is no packager command yet: build the ZIP and its signature by hand. What the installer
accepts is described in [`installing-modules.md`](installing-modules.md) ("Packages").

**Layout.** One top folder named like the module (`Books/`, the manifest at
`Books/Config/Module.php`, class `Modules\Books\Config\Module`, `$name = 'Books'`, a semantic
`$version`; the manifest must use literal values because it is read without being run). Only
the extensions of `packages.extensions` (`php`, `json`, `md`, `txt`, `css`, `js`, `mjs`, `map`,
images, fonts, `po`/`mo`/`pot`, `xml`, `csv`, `yml`/`yaml`) and the bare names `LICENSE`,
`README`, `CHANGELOG`, `NOTICE`, `AUTHORS`, `COPYING`, `.gitkeep`. Any other dot file
(`.git/`, `.gitignore`, `.htaccess`, `.env`), `phtml`, `phar` or a symbolic link refuses the
package. Keep it under 20 MB (100 MB uncompressed, 5000 files). Set `$author` so later versions
can replace it.

```bash
cd modules
zip -r Books-1.0.0.zip Books -x '*/.*'          # no .git, .gitignore or other dot files
unzip -l Books-1.0.0.zip | head                 # one top folder: Books/
```

**Signing** with [minisign](https://jedisct1.github.io/minisign/) (Ed25519):

```bash
minisign -G -p acme.pub -s acme.key              # once: the publisher's key pair (keep acme.key secret)
minisign -S -s acme.key -m Books-1.0.0.zip -t "Books 1.0.0"   # writes Books-1.0.0.zip.minisig
minisign -V -p acme.pub -m Books-1.0.0.zip       # check it
```

Publish `Books-1.0.0.zip` with `Books-1.0.0.zip.minisig` next to it. The site trusts the
publisher by adding the public key (the base64 line of `acme.pub`, or the whole file) to
`$publicKeys` in `app/Config/Packages.php`; `packages.requireSignature = true` (`.env`) refuses
unsigned packages. `modules:install` finds `<zip>.minisig` next to a file or a URL, or takes
`--signature=<file>`; the upload form accepts the `.minisig` with the ZIP. Both minisign formats
(the default prehashed `ED` and the legacy `Ed`) are accepted; the trusted comment is verified.

Install it with `php spark modules:install Books-1.0.0.zip` or through the staged upload of the
Modules screen ([`installing-modules.md`](installing-modules.md)).
