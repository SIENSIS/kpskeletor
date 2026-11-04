# Local setup checklist (Windows + XAMPP, or any computer)

Use this when you develop kpskeletor on your own computer (Claude Code in VS Code or a terminal).
Nothing here is installed per session: do it once per computer. Claude never installs or changes
anything on your computer without asking; it shows you these steps instead.

## 1. PHP 8.2 or later

- XAMPP with **PHP 8.2+** (XAMPP 8.2.x or later). Check in the terminal VS Code uses:

  ```bash
  php -v
  php --ini
  ```

  On Windows, `where php` must show `C:\xampp\php\php.exe` (or your PHP). If not, add
  `C:\xampp\php` to the user `PATH` and reopen VS Code.
- Open the `php.ini` shown by `php --ini` (XAMPP: `C:\xampp\php\php.ini`) and make sure these lines
  are **uncommented** (no leading `;`):

  ```ini
  extension=curl
  extension=fileinfo
  extension=gd
  extension=intl
  extension=mbstring
  extension=mysqli
  extension=openssl
  extension=pdo_sqlite
  extension=sodium
  extension=sqlite3
  extension=zip
  ```

  (`json` and `xml` are built in.) Close and reopen the terminal, and restart Apache from the
  XAMPP control panel if you use it. Check with `php -m`.

## 2. Composer

- Windows: install from <https://getcomposer.org/download/> (Composer-Setup.exe), choosing the
  XAMPP `php.exe`. Check with `composer --version` in a new terminal.
- Network access to `repo.packagist.org` and `github.com`.

## 3. Project

```bash
git clone https://github.com/SIENSIS/kpskeletor.git
cd kpskeletor
composer install
copy env .env        # Windows (cp env .env elsewhere)
php spark key:generate
```

Edit `.env`:

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
app.maskKey = <64 hex characters: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;">
```

## 4. Database (MariaDB from XAMPP)

Start MySQL/MariaDB in the XAMPP control panel, then create a database and a user (phpMyAdmin
or the console `C:\xampp\mysql\bin\mysql -u root`):

```sql
CREATE DATABASE ci4app CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'ci4app'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL ON ci4app.* TO 'ci4app'@'localhost';
```

Write the values **only** in `.env`:

```ini
database.default.hostname = localhost
database.default.database = ci4app
database.default.username = ci4app
database.default.password = choose-a-password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Without MariaDB, use SQLite (file `writable/database.db`):

```ini
database.default.DBDriver = SQLite3
database.default.database = database.db
```

Then: `php spark migrate --all` (later sessions: `php spark modules:sync`).

Tests always use SQLite in memory: `vendor/bin/phpunit` needs no database.

## 5. Serving the app

- `php spark serve` → <http://localhost:8080>
- Or Apache from XAMPP with a virtual host pointing at `public/` (see `docs/development.md`).

## 6. Screenshots (optional): Node.js and Playwright

- Install Node.js LTS from <https://nodejs.org> (ask before installing it if Claude proposes it).
- In the project folder (only touches the project and the user's Playwright cache):

  ```bash
  npm ci
  npx playwright install chromium
  ```

Without Node.js the sessions describe their screens in text.

## 7. Checks

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```
