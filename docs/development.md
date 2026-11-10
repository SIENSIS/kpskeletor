# Development environment

How to install, run and check kpskeletor while developing. Per-computer setup:
`deploy/setup-local.md` (local/XAMPP) and `deploy/setup.sh` (cloud setup script).

## Requirements

- PHP 8.2+ with `curl`, `fileinfo`, `gd`, `intl`, `json`, `mbstring`, `mysqli`, `openssl`,
  `pdo_sqlite`, `sodium`, `sqlite3`, `xml`, `zip`.
- Composer 2, Git.
- MariaDB 10.6+ (or MySQL 8) to run the app; SQLite works too. Tests use SQLite in memory.
- Node.js 20+ and Playwright (only for screenshots and end-to-end tests).

## Install

```bash
composer install
php spark app:install --env=development --db=sqlite --demo   # .env, keys, modules:sync, superadmin, demo data
npm ci                   # optional, Playwright
```

`app:install` asks for the rest (database, superadmin, 2FA); see `docs/commands.md`. By hand
instead: `cp env .env` (set `CI_ENVIRONMENT`, `app.baseURL`, `app.maskKey`, the database),
`php spark key:generate`, `php spark modules:sync --demo`. With an existing `.env`,
`php spark app:install --keep-env` only adds missing keys. To start again, delete `.env`,
`writable/install.lock` and the database (or its tables).

`.env` is never committed. The `env` template defaults to `CI_ENVIRONMENT = production`; set
`development` only on your own computer.

## Serve

```bash
php spark serve          # http://localhost:8080
```

### Apache virtual host (XAMPP)

The document root must be **`public/`**, never the project root (that would expose `.env`,
`writable/` and the source code).

1. `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

   ```apache
   <VirtualHost *:80>
       ServerName kpskeletor.test
       DocumentRoot "C:/projects/kpskeletor/public"
       <Directory "C:/projects/kpskeletor/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

2. Make sure `Include conf/extra/httpd-vhosts.conf` and `LoadModule rewrite_module …` are
   uncommented in `httpd.conf`.
3. Add `127.0.0.1 kpskeletor.test` to `C:\Windows\System32\drivers\etc\hosts` (as administrator).
4. Set `app.baseURL = 'http://kpskeletor.test/'` in `.env` and restart Apache.

`public/.htaccess` already sends every request to `index.php`.

## Checks (run before every commit)

```bash
composer test            # vendor/bin/phpunit (SQLite in memory)
composer analyse         # PHPStan level 6
composer cs              # php-cs-fixer dry run (composer cs-fix to fix)
```

Tests on MariaDB (as CI does): export the `database.tests.*` variables before PHPUnit:

```bash
env database.tests.DBDriver=MySQLi database.tests.hostname=127.0.0.1 \
    database.tests.database=ci4_test database.tests.username=ci4 \
    database.tests.password=ci4 vendor/bin/phpunit
```

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request: PHP 8.2, 8.3 and 8.4 with
`composer validate --strict`, `composer audit`, php-cs-fixer, PHPStan and PHPUnit on SQLite, plus
PHPUnit on MariaDB 11.4 with PHP 8.3. Dependabot (`.github/dependabot.yml`) proposes weekly
Composer and GitHub Actions updates.

## Notes

- `composer.json` sets `config.platform.php = 8.2.0` so the lock file installs on every
  supported PHP version. Raise it only when dropping PHP 8.2.
- In the cloud sessions the egress proxy may refuse GitHub API zip downloads; `deploy/setup.sh`
  then retries `composer install --prefer-source`. Packages that only ship dists (e.g.
  `phpstan/phpstan`) may need the Composer cache seeded from a git checkout (see
  `deploy-status/00-bootstrap/README.md`).
