# Session 00 — Bootstrap

Branch: `claude/modest-goldberg-jjs8kp` (assigned by the environment instead of
`session-00-bootstrap`) · Pull request: "Session 00: Bootstrap" · Date: 2026-11-04

## What was built

- The CodeIgniter 4.7.6 application (appstarter structure) in the repository root, with
  Shield 1.4.1, Settings 2.4.0, Translations 4.7.4, **siensis/kpcrudy 1.0.0** and
  **siensis/kpapy 1.0.0** (`^1.0`, from Packagist).
- Tooling: PHPUnit 11.5 (SQLite in memory, MariaDB through environment variables), PHPStan 2.3
  level 6 with `codeigniter/phpstan-codeigniter`, php-cs-fixer (PSR-12 + strict types).
- GitHub Actions CI (PHP 8.2/8.3/8.4 + MariaDB) and Dependabot.
- `docs/specification.md`, `CLAUDE.md`, `docs/plan.md` (sessions 01–36 and 56 open questions),
  `docs/progress.md`, `docs/library-requests.md`, `docs/development.md`.
- `deploy/setup.sh` (cloud setup script), `deploy/setup-local.md` (local/XAMPP checklist),
  `deploy-status/README.md` (report format) and `deploy-status/screenshot.mjs`.

No application features (as required).

## How it was built

### Environment found (cloud)

Claude Code on the web: Ubuntu 24.04 container, running as root.

| Item | Found | Action |
|---|---|---|
| PHP | 8.3.6 CLI | none |
| Extensions | intl, mbstring, json, openssl, fileinfo, zip, gd, sodium, sqlite3, pdo_sqlite, mysqli, curl, xml all present | none |
| Composer | 2.8.12 | none |
| Git | 2.43.0 | none |
| Node.js / Playwright | 22.23 / 1.63.0, Chromium pre-installed in `/opt/pw-browsers` | `package.json` pins `playwright@1.63.0` (same as the image) |
| MariaDB | not installed | `apt-get install mariadb-server` (10.11.14), databases `ci4app` (app) and `ci4_test` (MariaDB tests) |
| Packagist | reachable | none |
| GitHub | `git` over HTTPS works; **the proxy refuses `api.github.com` zip downloads** of repositories outside this session's scope (403) | Composer falls back to git sources (`--prefer-source`); `phpstan/phpstan` only ships dists, so its zip was rebuilt from a git checkout of the same commit and placed in Composer's cache. GitHub Actions is not affected. |

### Composer

- `composer.json`: name `siensis/kpskeletor`, MIT, PHP `^8.2`, `ext-*` requirements,
  `minimum-stability: stable`, `prefer-stable`, PSR-4 `Modules\\` → `modules/` and
  `Themes\\` → `themes/`, scripts `test`, `analyse`, `cs`, `cs-fix`.
- `config.platform.php = 8.2.0`: Composer first resolved PHPUnit 12 (PHP 8.3+), which would
  break the PHP 8.2 CI job; the platform pin keeps the lock file installable on 8.2–8.4.
- Shield prerequisites set now so the app boots with the debug toolbar: `Config\Security::$csrfProtection = 'session'`
  and the `auth`/`setting` helpers in `Config\Autoload`. The rest of `shield:setup` is
  session 05.

### Files

- `phpunit.xml.dist`, `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `.editorconfig`,
  `.gitattributes`, `.gitignore`, `env` (template, `CI_ENVIRONMENT = production`,
  `app.maskKey` placeholder), `.env` (local only, ignored).
- Tests: `tests/unit/HealthTest.php` (appstarter), `tests/feature/HomeTest.php` (`/` answers
  200), `tests/feature/DatabaseTest.php` (test database reachable; Shield, Settings and kpapy
  migrations run).
- `.github/workflows/ci.yml`, `.github/dependabot.yml`.

## Decisions and deviations

1. **Branch:** the environment assigned `claude/modest-goldberg-jjs8kp`; it is used instead of
   `session-00-bootstrap` (see plan, contradiction 1).
2. **PHPUnit 11.5** instead of 12 (PHP 8.2 support), via `config.platform.php`.
3. **App database:** MariaDB `ci4app` in this container (installed by `deploy/setup.sh`);
   SQLite stays documented as the fallback.
4. **`phpunit.xml.dist`** replaces the appstarter's `phpunit.dist.xml`; it also includes a
   `Modules` test suite (`modules/**/*Test.php`) for later sessions.
5. **appstarter files dropped:** its GitHub workflows, `builds` script and example tests/models.
   `LICENSE` is MIT with the project's copyright.
6. Libraries from the specification not installed yet (HTMLPurifier, Flysystem, TOTP, QR code):
   the sessions that need them install them.

## How to try it

```bash
bash deploy/setup.sh          # cloud: installs what is missing, composer install, npm ci
cp env .env                   # set CI_ENVIRONMENT=development, database, app.maskKey
php spark key:generate
php spark migrate --all
php spark serve               # http://localhost:8080 (CodeIgniter welcome page)
```

## Tests and checks

```
$ vendor/bin/phpunit --testdox
Database (Tests\Feature\Database)
 ✔ Database answers and migrations ran
Health
 ✔ Is defined app path
 ✔ Base url has been set
Home (Tests\Feature\Home)
 ✔ Home page answers 200
OK (4 tests, 7 assertions)

$ vendor/bin/phpunit   # with database.tests.* = MariaDB 10.11 (ci4_test)
OK (4 tests, 7 assertions)

$ vendor/bin/phpstan analyse
 [OK] No errors

$ vendor/bin/php-cs-fixer fix --dry-run --diff
Found 0 of 55 files that can be fixed

$ composer validate --strict
./composer.json is valid

$ composer audit
No security vulnerability advisories found.

$ composer install   # fresh clone
OK, then vendor/bin/phpunit: OK (4 tests)

$ curl -o /dev/null -w '%{http_code}' http://localhost:8080/   # php spark serve, development
200

$ node deploy-status/screenshot.mjs --shot "/=01-home" --shot "/=02-home-phone@390x844:dark:full"
200  http://localhost:8080/  ->  …/01-home.png
200  http://localhost:8080/  ->  …/02-home-phone.png
```

CI: see the pull request checks.

### `php spark`

```

CodeIgniter v4.7.6 Command Line Tool - Server Time: 2026-11-04 10:50:14 UTC+00:00

Cache
  cache:clear       Clears the current system caches.
  cache:info        Shows file cache information in the current system.

CodeIgniter
  config:check      Check your Config values.
  env               Retrieves the current environment, or set a new one.
  filter:check      Check filters for a route.
  help              Displays basic usage information.
  list              Lists the available commands.
  namespaces        Verifies your namespaces are setup correctly.
  optimize          Optimize for production.
  phpini:check      Check your php.ini values in production environment.
  publish           Discovers and executes all predefined Publisher classes.
  routes            Displays all routes.
  serve             Launches the CodeIgniter PHP-Development Server.

Database
  db:create         Create a new database schema.
  db:seed           Runs the specified seeder to populate known data into the
                    database.
  db:table          Retrieves information on the selected table.
  migrate           Locates and runs all new migrations against the database.
  migrate:refresh   Does a rollback followed by a latest to refresh the current
                    state of the database.
  migrate:rollback  Runs the "down" method for all migrations in the last batch.
  migrate:status    Displays a list of all migrations and whether they've been
                    run or not.

Encryption
  key:generate      Generates a new encryption key and writes it in an `.env`
                    file.

Generators
  make:cell         Generates a new Controlled Cell file and its view.
  make:command      Generates a new spark command.
  make:config       Generates a new config file.
  make:controller   Generates a new controller file.
  make:entity       Generates a new entity file.
  make:filter       Generates a new filter file.
  make:migration    Generates a new migration file.
  make:model        Generates a new model file.
  make:scaffold     Generates a complete set of scaffold files.
  make:seeder       Generates a new seeder file.
  make:test         Generates a new test file.
  make:transformer  Generates a new transformer file.
  make:validation   Generates a new validation file.

Housekeeping
  debugbar:clear    Clears all debugbar JSON files.
  logs:clear        Clears all log files.

Kpapy
  kpapy:demo        Installs, resets or removes the kpapy demo (a small library
                    API with a web console).
  kpapy:make        Generates the controller method and the routes of an API
                    resource from a database table.
  kpapy:openapi     Builds the OpenAPI 3.1 document of the API routes.
  kpapy:publish     Copies the kpapy configuration or a language file into the
                    project to customise them.
  kpapy:routes      Lists the API resources (Resource::routes()) with their
                    resource name and permission catalogue.
  kpapy:token       Creates an access token for a user (e.g. for a
                    server-to-server integration).

Kpcrudy
  kpcrudy:checkurl  Checks the signature of a URL and shows when it expires.
  kpcrudy:demo      Installs, resets or removes the kpcrudy demo application.
  kpcrudy:make      Generates the controller method and route of a CRUD from a
                    database table.
  kpcrudy:maskid    Shows the masked code of one or more ids, as they appear in
                    the URLs of a CRUD with maskIds().
  kpcrudy:publish   Copies the configuration, a theme or the texts of a language
                    into the project, to adapt them.
  kpcrudy:signurl   Signs a URL so that it can be opened without changes (and,
                    optionally, only until it expires).
  kpcrudy:unmaskid  Shows the real id of one or more masked codes taken from the
                    URLs of a CRUD with maskIds().

Settings
  settings:clear    Clears all settings from persistent storage.
  settings:publish  Publish Settings config file into the current application.

Shield
  shield:hmac       Encrypt/Decrypt secretKey for HMAC tokens.
  shield:model      Generate a new UserModel file.
  shield:setup      Initial setup for CodeIgniter Shield.
  shield:user       Manage Shield users.

Translation
  lang:find         Find and save available phrases to translate.
  lang:sync         Synchronize translation files from one language to another.

Worker Mode
  worker:install    Install FrankenPHP worker mode by creating necessary
                    configuration files
  worker:uninstall  Remove FrankenPHP worker mode configuration files
```

## Screenshots

None needed for this session (no screens yet); `screenshot.mjs` was tested against the welcome
page.

## Plan summary

`docs/plan.md` describes sessions 01–36 (goal, files, tables, commands, tests, dependencies,
risks) and ends with **56 contradictions or open questions**, each with a proposal; 13 are marked
**(decide)** because they change a public convention or security (module discovery namespaces,
`Config\Modules`, password-length setting, remember-me, "my" screens, permission names, web server
writing code/config, release signing, private news reader…).

## Known issues

- Cloud proxy: GitHub API zip downloads refused (see the environment table). `deploy/setup.sh`
  retries with `--prefer-source`; if a dist-only package changes version, seed the cache the
  same way or ask for `api.github.com` to be allowed in the environment's network policy.
- MariaDB in the container is started by `deploy/setup.sh` (`mysqld_safe`); there is no service
  manager.

## Notes for the next session

- Read `docs/plan.md` section 01 and contradictions 1, 3, 5, 10 and 12 before starting: module
  discovery needs one PSR-4 namespace per module (CodeIgniter only discovers `Config/Routes.php`
  at namespace roots).
- `.env` is local; recreate it from `env` in a new container (MariaDB user `ci4app`, or SQLite).
- Branch naming: record the real branch name in `docs/progress.md`.
