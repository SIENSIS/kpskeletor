# Web server configuration

The document root is **`public/`** and nothing else: `app/`, `modules/`, `vendor/`, `writable/`
and `.env` live outside it. The web server user may write only `writable/`,
`public/assets/modules`, `public/assets/core`, `public/themes` and `public/uploads`
(D-00-33). Uploaded files are data, never code: nothing is executed in `public/uploads` (the
public disk of the Storage service, `docs/storage.md`) and `writable/storage` (the private disk)
is never served directly.

## Apache

`public/.htaccess` (CodeIgniter's front controller rules) and `public/uploads/.htaccess` (shipped
with the project) need `AllowOverride All` (or at least `FileInfo Options Indexes Limit` and
`AuthConfig` for `Require`). The upload folder's file turns PHP off, removes script handlers,
denies code, HTML and dotfiles, and adds `nosniff` and a sandboxing CSP (SVG as a download).

Virtual host:

```apache
<VirtualHost *:443>
    ServerName intranet.example.com
    DocumentRoot /var/www/kpskeletor/public

    <Directory /var/www/kpskeletor/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    # Never execute anything in the upload folder, even if .htaccess files are ignored.
    <Directory /var/www/kpskeletor/public/uploads>
        AllowOverride None
        Options -Indexes -ExecCGI -Includes
        RemoveHandler .php .phtml .phar .cgi .pl .py .sh .shtml
        <IfModule mod_php.c>
            php_admin_flag engine off
        </IfModule>
        <FilesMatch "\.(?i:php\d?|phtml|phar|pht|cgi|pl|py|sh|shtml|html?|xhtml|js|mjs)$">
            Require all denied
        </FilesMatch>
        Header always set X-Content-Type-Options "nosniff"
        Header always set Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'self'; frame-ancestors 'self'; sandbox"
        <FilesMatch "\.(?i:svgz?)$">
            Header always set Content-Disposition "attachment"
        </FilesMatch>
    </Directory>

    # Dotfiles (.env, .git, .htaccess) are never served.
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

With PHP-FPM (`SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"`), put the
handler inside a `<FilesMatch "\.php$">` of the `public` directory only, so it never applies to
`public/uploads`.

If the project must live under a shared document root (not recommended), deny the private
folders explicitly:

```apache
<DirectoryMatch "/(app|modules|vendor|writable|tests|deploy)/">
    Require all denied
</DirectoryMatch>
```

## Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name intranet.example.com;
    root /var/www/kpskeletor/public;
    index index.php;

    # Dotfiles (.env, .git, .htaccess…), except the ACME challenge.
    location ~ /\.(?!well-known/) { deny all; }

    # Public disk: static files only, never PHP, no sniffing, active types downloaded.
    location ^~ /uploads/ {
        location ~* \.(php\d?|phtml|phar|pht|cgi|pl|py|sh|shtml|html?|xhtml|js|mjs)$ { deny all; }
        add_header X-Content-Type-Options "nosniff" always;
        add_header Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'self'; frame-ancestors 'self'; sandbox" always;
        location ~* \.svgz?$ {
            add_header X-Content-Type-Options "nosniff" always;
            add_header Content-Disposition "attachment" always;
        }
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # Only the front controller runs PHP.
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ \.php$ { return 404; }
}
```

`^~ /uploads/` is matched before the regular-expression locations, so a `.php` file that reached
`public/uploads` is never passed to PHP-FPM. If the project is not behind its own `public/`
root, add `location ~ ^/(app|modules|vendor|writable|tests|deploy)/ { deny all; }`.

## Filesystem permissions

```bash
chown -R deploy:www-data /var/www/kpskeletor
find /var/www/kpskeletor -type d -exec chmod 0755 {} +
find /var/www/kpskeletor -type f -exec chmod 0644 {} +
chmod -R u=rwX,g=rwX,o= /var/www/kpskeletor/writable
chmod 0640 /var/www/kpskeletor/.env
```

The Storage service creates private folders `0750` and files `0640`, public ones `0755`/`0644`
(`app/Config/Storage.php`). Access control never relies on these permissions: every private file
is read through `files/private/{key}`.

## Check

```bash
curl -sI https://intranet.example.com/.env              # 403 or 404
curl -sI https://intranet.example.com/uploads/x.php     # 403 or 404, never 200
curl -sI https://intranet.example.com/writable/logs/    # 404
```
