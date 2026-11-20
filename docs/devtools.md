# Development tools (Devtools)

`modules/Devtools` is a module that exists **only for development** (D-41-01 … D-41-03): the
home of tools that help to build modules from the back office. Session 41 built the module, its
locks and the **CRUD recipes**; session 42 the **code generator**; session 43 the **package
builder** and signing.

## Using it

```bash
# .env: CI_ENVIRONMENT = development (a development copy, never a server)
php spark modules:enable Devtools   # installed disabled ($enabledByDefault = false)
php spark serve                     # http://localhost:8080
```

Sign in as a **superadmin** and open *Configuració › Desenvolupament › Eines de desenvolupament*
(`backend/devtools`, under the back-office prefix):

- **CRUD recipes** (`backend/devtools/recipes`): one card per variant of a back-office CRUD and
  its API — plain CRUD, read-only, custom operations, re-authentication, record rules, own
  records, fields hidden by permission, translated fields, rich text, uploads and the file
  picker, export/import, trash, masked ids, the activity log, the kpapy resource, the menu entry
  and default grants. Each card says what it is and when to use it, shows real code copied from
  the skeleton (with the file it comes from and a copy button) and links the section of `docs/`
  it comes from (`backend/devtools/docs/<page>`, opened at the section's anchor; a plain-text viewer of `docs/*.md`).
  The catalogue is `Modules\Devtools\Recipes\RecipeBook`; `RecipesTest` checks that every cited
  file and doc heading exists, so a renamed section breaks the test, not the page.
- **Code generator** (`backend/devtools/generator`): a wizard that builds a module, or adds tables
  to an existing one, previews every file and writes them (below).
- **Packages** (`backend/devtools/packages`): builds the installable ZIP of a module or theme,
  validated as the installer validates it, with its SHA-256 and a minisign signature
  ("Building and signing packages", below).

## The code generator

A wizard (D-41-02, session 42) that generates as much of a module as it can — migrations, demo
data, the CRUD with the chosen options, the API, the menu, the permissions, the texts in ca/es/en
and the tests — following `CLAUDE.md`, shows **every file before writing it**, and writes only
after re-authentication. The engine lives in the Core (`Modules\Core\Generator`), so the console
uses the same one without Devtools:

```bash
php spark make:module --definition=library.json --dry-run   # list the plan
php spark make:module --definition=library.json             # write it, then modules:sync
```

### The wizard

`backend/devtools/generator` (`devtools.view`), one page of steps; every button posts the form and
the server keeps the **draft** in your session, so it works without JavaScript (the small
`devtools/generator` component only adds live help: inputs for the chosen type, the SET NULL
warning, the table prefix).

1. **Module:** a new one (name, description in ca/es/en, author, dependencies on installed
   modules) or an existing one.
2. **Table and columns** (one card per table, "Add a table" for more): the name (prefixed by
   `<module>_`), `created_at`/`updated_at`, the trash (`deleted_at`), and the columns with portable
   types only — text (VARCHAR with a length), long text, integer, big integer, decimal (precision,
   scale), yes/no, date, date and time, JSON (stored as TEXT), list of values (a VARCHAR with an
   `in_list` rule) — nullable, default, unique, index, foreign key (a table of this definition
   defined before, or an installed one, and its ON DELETE rule), translated (in
   `<table>_translations`, the Content convention). Below, the labels of the table and its columns
   in Catalan, Spanish and English.
3. **Demo data:** how many rows and, per column, a Faker generator (a sensible default from the
   name and type).
4. **CRUD:** on/off, read only, masked ids, the operations (list, details, add, edit, delete,
   trash, export, import), a matrix of columns × list / form / required / search / filter / file
   (Core file picker) / rich text (Core editor + `HtmlSanitizer`) / hidden without a permission,
   the record rule (every record; own records: `created_by`, added for you; records whose column
   is the user), and custom operations (`addOperation()`: key, icon, label and confirmation in
   ca/es/en, and **re-authentication**). Every option links its card in CRUD recipes.
5. **API:** a kpapy resource with the CRUD's resource name (its permissions are the token scopes),
   or a **public** one (no token, GET only, list and show: `api/v1/public/<module>/<key>`).
6. **Menu and permissions:** zone (sidebar under a resource of the module, or settings under one of
   the Core's groups, D-38-01), icon, order, and the default grants per group as a matrix of the
   operations; the permission names that will be generated are listed. The groups are the ones of
   the site where the generator runs, each shown with its title (Users › Groups, e.g.
   "Coordinador") and its internal name (`editor`). To grant a production site's own groups
   (pupils, families…) on a development copy, copy them first with `php spark groups:export` /
   `groups:import` (`docs/users.md`, D-42-04): no users or personal data travel.
7. **Preview** (`backend/devtools/generator/preview`): a tree of the files with a *new* / *changed*
   badge, the full content of new files and a diff of changed ones (plain, escaped `<pre>`), the
   permissions and the next steps. The definition can be downloaded as JSON and loaded again
   (file or pasted text, read in memory, never stored).
8. **Write** (`POST backend/devtools/generator/write`, `DevtoolsRoutes::WRITE`: `devtools.write`,
   re-authentication, `ratelimit:forms`): the plan is made again from the draft and refused when its
   fingerprint differs from the previewed one (a file changed on disk, or the definition); the
   files are written atomically (temporary name + rename; on a failure the files already written
   are put back); then `php spark modules:sync` and, if ticked, `php spark modules:demo <Name>`
   run in new processes; `composer dump-autoload` is not needed (modules load through
   `app/Config/ModuleNamespaces.php`). Logged as `activity_log('Devtools', 'generated')`. The
   result page shows the files, the commands' output and the commands to run the generated tests.

The form's buttons and loading a definition only change the draft: they use `DevtoolsRoutes::DRAFT`
(`devtools`, `can:devtools.view`: loopback and superadmin, but no re-authentication — a POST is
never replayed after it, the edits would be lost — and no `forms` limit of 10 an hour; D-42-02).

### What it generates

For a **new module**, `make:module`'s files (the templates of `modules/Core/Modules/Templates`;
without tables the output is exactly `make:module <Name>`'s), without the example table; then,
for every table, as for an existing module:

| File | Content |
|---|---|
| `Database/Migrations/<time>_Create<Table>.php` (+ `…Translations.php`) | Forge only, MariaDB and SQLite, `down()`; keys, unique keys, foreign keys; one migration per table |
| `Controllers/<Key>.php` | `$this->crud('<resource>')`, `setTable()`, field types, relations (`setRelation()` to the first text column of the other table, `users.username`), translations, rules (required, lengths, `in_list`, `valid_date`, `is_unique[…,id,{id}]`…), list/form/detail/export columns, search, filters, defaults, labels from the language files, `unset*()`/`readOnly()` for what the resource does not declare, `enableImport()`, `maskIds(false)`, the record rule with `where()` (and `hideFields()` of the rule's column) unless the user has `<resource>.all`, `hideFields()` without the field's permission, custom operations (with `$this->requireReauth()` when asked), rich text sanitised before kpcrudy reads the form, file fields with the Core's picker and `accepts()` |
| `Storage/<Key>Uploads.php` | a `CrudUploadHandler` (public disk, `modules/<module>`) when a column is a file |
| `Controllers/Api/<Key>.php` or `Public<Key>.php` | the kpapy resource: same resource name, field types, translations, write and required fields, the record rule (`where()` on writes too) and hidden fields; or the public read-only one |
| `Tests/<Key>CrudTest.php`, `Tests/<Key>ApiTest.php` | permissions (guest, no permission), forged posts (add, edit, delete, custom operations), adding a row, read only, own rows, hidden fields, re-authentication; the API's token and scopes, or the public resource |
| `Config/Module.php` (changed) | the resource (`except`, `extra`: custom operations, hidden-field permissions, `all` of the record rules), default grants, the menu entry, `$api`; description, author and `$requires` of a new module |
| `Config/Routes.php` (changed) | `CrudFactory::routes()` (the `backend` chain and `can:`), `Resource::routes(…, ['filter' => 'kpapy'])` or the public `GET` routes |
| `Language/{ca,es,en}/<Module>.php` (changed) | `res`, `menu`, `perm` and the table's texts (`<key>.singular`, `plural`, `fields`, `options`, `ops`, `api`) |
| `Database/Seeds/<Module>DemoSeeder.php` (changed) | a `seed<Table>()` method with the rows, written with `$demo->insert()` so `modules:demo --remove` removes them; the values are made with Faker **when the code is generated** and written as literals (the seeder needs no Faker, works on a `--no-dev` site and gives the same rows every time) |
| `CLAUDE.md` (changed) | a line per resource under "Resources and permissions" |

The PHP files are formatted with the project's php-cs-fixer rules before the preview, so the
preview is exactly what is written and the result passes `php-cs-fixer fix --dry-run`.

Existing files are changed with `nikic/php-parser` (D-42-01): the file is parsed, the array or
method is found, and only that part of the text changes (comments and alignment are kept); keys
that exist are never overwritten. A migration is never edited: changing a table that exists is a
new migration you write by hand (adding or dropping columns and indexes: the generator does not
write "alter" migrations yet).

### The definition (JSON)

The wizard and `make:module --definition` read the same JSON (`Modules\Core\Generator\Definition\
ModuleDefinition`); every key except the names is optional. Examples:
`modules/Core/Tests/Fixtures/Generator/{library,notes,zoo-existing}.json`.

```json
{
    "format": 1,
    "module": {
        "name": "Library",
        "existing": false,
        "description": {"ca": "Biblioteca", "es": "Biblioteca", "en": "Library"},
        "author": "me",
        "requires": {"Auth": "^0.2"}
    },
    "tables": [{
        "name": "library_books",
        "labels": {"singular": {"ca": "Llibre", "es": "Libro", "en": "Book"}, "plural": {"en": "Books"}},
        "timestamps": true,
        "softDeletes": true,
        "demoRows": 6,
        "columns": [
            {"name": "title", "type": "string", "length": 190, "translated": true, "label": {"ca": "Títol", "en": "Title"}, "faker": "sentence"},
            {"name": "status", "type": "enum", "options": ["available", "lent"], "default": "available"},
            {"name": "price", "type": "decimal", "precision": 8, "scale": 2, "nullable": true},
            {"name": "shelf_id", "type": "integer", "foreign": {"table": "library_shelves", "onDelete": "RESTRICT"}}
        ],
        "crud": {
            "enabled": true, "readOnly": false, "maskIds": true,
            "operations": ["list", "show", "add", "edit", "delete", "trash", "export", "import"],
            "columns": [], "fields": [], "required": [], "search": [], "filters": [],
            "customOperations": [{"key": "lend", "label": {"en": "Lend"}, "icon": "bi bi-box-arrow-right", "confirm": {"en": "Lend it?"}, "reauth": false}],
            "recordRule": "everyone|own|column", "ruleColumn": null,
            "hidden": {"price": "prices"},
            "uploads": [], "richText": []
        },
        "api": {"enabled": true, "public": false},
        "menu": {"enabled": true, "zone": "sidebar", "parent": "main", "icon": "bi bi-book", "order": 10},
        "grants": {"admin": ["*"], "editor": ["list", "show", "lend"]}
    }]
}
```

- Labels: a string (all languages) or `{"ca", "es", "en"}`; a missing language takes the English
  one, then the humanised name.
- Empty CRUD lists mean the defaults: every column in the form (but the owner column), the first
  five non-text columns in the list, required = not nullable without a default, search on the
  listed text columns, filters on lists of values, yes/no and foreign keys.
- `grants`: group => operations (`*` = all), the operations of the resource only.
- `"existing": true` adds the tables to `modules/<name>`.

### What it cannot generate (yet)

N-N relations (`setRelationNN()`), multiselects, tree CRUDs, bulk actions, widgets, scheduled
tasks, notifications, calendar entries, settings and public pages (copy them from the recipes and
`modules/Tasks`); "alter" migrations for tables that exist; the business logic of custom
operations (a `TODO` in the callback). The generated tests use id 1 (or a row they create) for a
required foreign key to a table outside the definition.

### The engine

| Class | Role |
|---|---|
| `Generator\Definition\*` | the definition: `ModuleDefinition`, `TableDefinition`, `ColumnDefinition`, `CrudDefinition`, `ApiDefinition`, `MenuDefinition`, `Texts` (JSON in and out) |
| `Generator\DefinitionValidator` | errors by path (`tables.0.columns.1.foreign.onDelete` => message), `Core.generator.errors.*` |
| `Generator\Engine` | definition → `Plan` (`PlannedFile`s: path, new content, content on disk); nothing written |
| `Generator\TableCode` | the code of one table; templates in `modules/Core/Generator/Templates/` |
| `Generator\SourceEditor` | format-preserving changes of existing PHP files (nikic/php-parser) |
| `Generator\DemoValues` | Faker values at generation time |
| `Generator\CodeFormatter` | php-cs-fixer on the plan (skipped without it) |
| `Generator\PlanWriter` | fingerprint check, atomic writes, rollback, the namespace file |
| `Generator\GeneratorContext` | the modules folder, installed manifests, tables, groups (tests give their own) |

Tests: `modules/Core/Tests/Generator{Engine,Validator,Write}Test.php` (plans against the fixtures
in `Fixtures/Generator/expected/`; regenerate them with `GENERATOR_FIXTURES=update vendor/bin/phpunit
modules/Core/Tests/GeneratorEngineTest.php` and review the diff), `GeneratedModuleChecksTest.php`
(the `library` and `zoo-existing` definitions generated into `modules/`: `php -l`,
php-cs-fixer, PHPStan, `csp:check` and **their own tests**), `modules/Devtools/Tests/
GeneratorWizardTest.php` and `tests/e2e/devtools-generator.spec.mjs`.

## Building and signing packages

Session 43, D-43-01. The same engine from the console and from the screen
(`Modules\Devtools\Packages`): `PackageSource` (the module or theme folder, its version read
from the manifest without running it), `PackageBuilder`, `PackageSigner`, `VersionBump`.

### From the console

```bash
php spark modules:package Tasks                                  # writable/devtools/packages/Tasks-0.2.0.zip + .sha256
php spark modules:package default --theme                        # a theme (themes/default)
php spark modules:package Tasks --out=dist --sign=~/.minisign/acme.key   # + Tasks-0.2.0.zip.minisig
```

Development only (`CI_ENVIRONMENT = development`); the Core modules (Core, Auth, Api, Users,
Settings, Dashboard) are not packaged: they travel in release archives (`app:update`). The
password of an encrypted key is asked without echo (`KP_SIGN_PASSWORD` for scripts); a relative
path is read from the shell's folder.

### What a package contains

- **one top folder** named like the module (`Tasks/…`) or the theme slug (`default/…`), files
  sorted, permissions 0644;
- left out without a word: every dotfile and dot folder (`.git`, `.env`, `.idea`, `.gitkeep`…),
  `node_modules/` and `writable/`;
- left out and **listed** as *left out*: editor and system files (`*~`, `*.swp`, `*.bak`,
  `*.orig`, `*.tmp`, `Thumbs.db`…) and every file whose extension `packages.extensions` would
  refuse (`*.sh`, `*.phar`, `*.scss`…);
- a **symbolic link** stops the build.

Then the ZIP is validated with the Core's `PackageValidator::validateAndLint()`, the installer's own
checks (single folder, manifest read without running it, name, semantic version, reserved and
core names, sizes, zip-slip, links, extensions, zip bombs, `php -l` of every PHP file): any error
removes it and nothing is left behind. `<zip>.sha256` is written in `sha256sum` format
(`sha256sum -c` checks it). Rebuilding a version removes its old signature. Local changes not
committed (`git status` of the folder) are a **warning** only: the package contains them.

### From the screen

*Devtools › Packages* lists the modules and themes with their version, a **Build package**
button (WRITE: `devtools.write`, re-authentication, the forms rate limit) with an optional
**raise the version** (patch, minor or major: the `$version` literal of the manifest and a new
section in the module's `CHANGELOG.md`; an `## [Unreleased]` section becomes the new version's),
the validation result (files, size, SHA-256, files left out, warnings) and the built packages with
download links for the ZIP, `.sha256` and `.minisig` (served by the controller from
`writable/devtools/packages/`, names matching `PackageStore::NAME_PATTERN` only, never from
`public/`), the minisign command for signing on another computer and, once signed, the public key.

### Signing

minisign-compatible (Ed25519, pre-hashed BLAKE2b `ED`, trusted comment `<Name> <version>`),
implemented with libsodium: the secret key file (`untrusted comment` + base64 of `Ed`, the KDF
`Sc` (scrypt, `sodium_crypto_pwhash_scryptsalsa208sha256`) or none (`minisign -G -W`), `B2`, salt,
limits, key id, secret key, checksum) is decrypted in memory with its password (a wrong password
fails the BLAKE2b checksum: a clear error), the package is signed, `<zip>.minisig` is written and
then **verified with the Core's `Modules\Core\Security\Minisign`** against the public key derived
from the secret key. The public key line (`RW…`) is shown, to put in `packages.publicKeys`
(`docs/installing-modules.md`, "Signatures"); `<zip>.json` keeps the public facts (key id, public
key, SHA-256). The signatures are byte for byte those of the minisign 0.11 CLI (Ed25519 is
deterministic; `Tests/Fixtures/minisign`).

### Signing from the local form (D-43-01)

*Sign* opens `backend/devtools/packages/sign`: the secret key (pasted, or a file read in memory)
and its password (the Core's show/hide button). Safeguards, each one tested
(`PackagesScreenTest`):

- the key and the password are never written anywhere: no temporary file of ours (PHP removes its
  own upload file at the end of the request), no session, no `withInput()`, no flash data, no
  log; the activity log records only "signed `<file>` with key id `<id>`"; they are wiped with
  `sodium_memzero` and are `#[\SensitiveParameter]` (never in a backtrace);
- **the debug toolbar never stores them**: the controller reads the fields and then empties the
  request's POST data, `$_REQUEST`, the uploaded files and the raw body before doing anything else
  (`Packages::scrub()`), because the toolbar's *after* filter writes the POST data of every
  request to `writable/debugbar/` whatever `collectVarData` says; a test posts a key and checks
  that no file under `writable/`, the session, the activity log and what the toolbar collects
  contain it;
- every exception is caught: a generic message, never the development error page;
- `autocomplete="off"` (and no spell checking) on both fields, `Cache-Control: no-store`, every
  Devtools lock (development, loopback, `devtools.write`, `reauth`, rate limit);
- **cleanup afterwards** (owner's addition): the POST answers a redirect to
  `backend/devtools/packages/signed`, which calls the services behind `php spark debugbar:clear`,
  `logs:clear`, `cache:clear` and `app:cache-clear` (no process is spawned), drops the session's
  flash and old-input data, **ends the session** (the user signs in again) and logs `cleanup`.
  The form says so. Clearing the cache also resets the rate-limit counters of this development
  copy (D-43-02).

## The locks

Every lock has a test (`modules/Devtools/Tests/DevtoolsLocksTest.php`, `LocalOnlyTest.php`,
`modules/Core/Tests/DevelopmentOnlyTest.php`, `PackagesTest`).

| # | Lock | Where |
|---|---|---|
| 1 | Routes only when `ENVIRONMENT === 'development'` (a constant, no query): elsewhere every Devtools URL answers 404 | `modules/Devtools/Config/Routes.php` |
| 2 | Manifest `$developmentOnly = true`: `modules:sync`, `modules:enable`, `modules:install` and the Modules screen refuse to install or enable it outside development; a sync there disables it (security log `devonly_disabled`); `$enabledByDefault = false` | `ModuleManifest`, `ManifestValidator`, `DevelopmentOnly`, `ModuleToggler`, `ModuleSynchronizer`, `PackageValidator`, `ModuleOverview` |
| 3 | `security:check` → `installation.devtools`: **error** when a development-only module is enabled outside development, information in development | `InstallationCheck` |
| 4 | Local requests only: client `REMOTE_ADDR` in `127.0.0.0/8` or `::1` (proxy headers are never trusted) **and** `Host` `localhost` or a loopback address; otherwise 404. The routes are not registered for other web requests (so no login redirect reveals them), and the `devtools` route filter checks again | `Config/Routes.php`, `Filters/LocalOnly.php` |
| 5 | Resource `devtools` (single resource, prefix override): `devtools.view` (screens) and `devtools.write` (writes files or runs commands), granted to nobody — superadmins hold every permission. Write routes use `DevtoolsRoutes::WRITE`: `devtools`, `can:devtools.write`, `reauth`, `ratelimit:forms`; the generator's draft-only POSTs (`DRAFT_ROUTES`) use `DevtoolsRoutes::DRAFT`: `devtools`, `can:devtools.view` (D-42-02) | `Config/Module.php`, `Routing/DevtoolsRoutes.php` |
| 6 | Packaging: it is part of the repository and of release archives (`docs/updates.md`), made inert by 1–4 | `docs/security.md` |

The menu entry is in its own settings group, **Desenvolupament** (`group.development`, declared by
the module, D-41-04): a group is shown only while one of its entries is visible, so nobody but a
superadmin on a development copy ever sees it.

Every Devtools screen shows the banner "Development tools: never enabled in production".

## Adding a tool

- Add its routes in `DevtoolsRoutes::define()`: screens with `self::VIEW`, anything that writes
  files or runs commands with `self::WRITE` (`DevtoolsLocksTest::testEveryDevtoolsRouteHasItsLocks`
  checks every route under `backend/devtools`); a POST that only changes a draft in the session
  may use `self::DRAFT` and must be added to `DRAFT_ROUTES` (the test pins the list).
- Controllers extend `BackendController`; views include `Modules\Devtools\Views\_banner`.
- Log writes with `activity_log('Devtools', …)`; never weaken a lock to make a tool work.
