# kpskeletor

kp Web Skeletor: a modular CodeIgniter 4 application skeleton (back office, public site, JSON API).

## Quick start

Requirements: PHP 8.2+ (`intl`, `mbstring`, `sqlite3`/`mysqli`, `gd`, `zip`, `sodium`), Composer 2,
MariaDB 10.6+ / MySQL 8 or SQLite. The web server's document root is `public/`
(`docs/web-server.md`).

```bash
git clone <repository> kpskeletor && cd kpskeletor
composer install
php spark app:install
```

`app:install` asks for the site URL, the database (an existing, empty one) and the first
superadmin, writes `.env` with new keys (`CI_ENVIRONMENT = production`), runs `modules:sync`,
offers two-factor authentication and writes `writable/install.lock`. Without questions:

```bash
INSTALL_DB_PASSWORD=… INSTALL_ADMIN_PASSWORD=… php spark app:install --no-interaction \
    --url=https://intranet.example.com/ --db=mysql --db-name=kpskeletor --db-user=kpskeletor \
    --email=admin@example.com --2fa=email
```

On your own computer: `php spark app:install --env=development --db=sqlite --demo`, then
`php spark serve` (http://localhost:8080). Alternatively, open the site in a browser before
installing: every page leads to the web installer `/install`, which disappears once installed.
With `php spark serve` and `http://localhost` it opens over HTTP without a certificate; on a real
web server it is redirected to HTTPS (`docs/security.md`, "Installer").

### Cron

One line runs every scheduled task and the email queue (the installer prints it):

```
* * * * * cd /path/to/kpskeletor && php spark schedule:run >> /dev/null 2>&1
```

## Documentation

`docs/` (English): `commands.md` (every command), `development.md`, `security.md`,
`web-server.md` and one file per area (modules, themes, permissions, API…).
