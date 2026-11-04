#!/usr/bin/env bash
# Cloud environment setup script for kpskeletor (Claude Code on the web).
# Paste it as the environment's "Setup script" (environment menu -> Edit -> Setup script)
# or run it by hand: bash deploy/setup.sh. Idempotent: it only installs what is missing.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1

SUDO=""
if [ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1; then SUDO="sudo"; fi

# Repository root: the script's parent folder when run from the repo, else the current folder.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." 2>/dev/null && pwd || pwd)"
[ -f "$ROOT/composer.json" ] || ROOT="$(pwd)"

log() { printf '\n==> %s\n' "$*"; }

apt_updated=0
apt_install() {
    if [ "$apt_updated" -eq 0 ]; then $SUDO apt-get update -q >/dev/null; apt_updated=1; fi
    $SUDO apt-get install -y -q "$@" >/dev/null
}

# 1. PHP 8.2+ and extensions
if ! command -v php >/dev/null 2>&1; then
    log "Installing PHP"
    apt_install php-cli
fi
PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
php -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' || { echo "PHP $PHPV found; 8.2 or later is required." >&2; exit 1; }

missing=()
for ext in intl mbstring json openssl fileinfo zip gd sodium sqlite3 pdo_sqlite mysqli curl xml; do
    php -m | grep -qix "$ext" || missing+=("$ext")
done
if [ "${#missing[@]}" -gt 0 ]; then
    log "Installing PHP extensions: ${missing[*]}"
    pkgs=()
    for ext in "${missing[@]}"; do
        case "$ext" in
            pdo_sqlite|sqlite3) pkgs+=("php$PHPV-sqlite3") ;;
            mysqli)             pkgs+=("php$PHPV-mysql") ;;
            json|openssl|fileinfo|sodium) ;; # bundled with php-cli
            *)                  pkgs+=("php$PHPV-$ext") ;;
        esac
    done
    [ "${#pkgs[@]}" -gt 0 ] && apt_install "${pkgs[@]}"
fi

# 2. Composer (installer verified against its published SHA-384)
if ! command -v composer >/dev/null 2>&1; then
    log "Installing Composer"
    tmp="$(mktemp -d)"
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o "$tmp/composer-setup.php"
    actual="$(php -r "echo hash_file('sha384', '$tmp/composer-setup.php');")"
    if [ "$expected" != "$actual" ]; then echo "Composer installer signature mismatch" >&2; exit 1; fi
    $SUDO php "$tmp/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp"
fi

# 3. MariaDB (optional: used to run the app; tests use SQLite)
if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1; then
    log "Installing MariaDB server"
    apt_install mariadb-server || echo "MariaDB could not be installed; use SQLite (see env)."
fi
if command -v mysqld_safe >/dev/null 2>&1 && ! mysqladmin ping >/dev/null 2>&1; then
    log "Starting MariaDB"
    ( $SUDO mysqld_safe --user=mysql >/dev/null 2>&1 & )
    for _ in $(seq 1 20); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done
fi

# 4. Project dependencies
cd "$ROOT"
if [ -f composer.json ]; then
    log "composer install"
    # The cloud proxy can refuse GitHub API zip downloads; fall back to git sources.
    composer install --no-interaction --no-progress \
        || composer install --no-interaction --no-progress --prefer-source
fi

if [ -f package.json ] && command -v npm >/dev/null 2>&1; then
    log "npm ci and Playwright Chromium"
    npm ci --no-audit --no-fund
    # Reuse a pre-installed Chromium when the image provides one.
    if [ ! -d /opt/pw-browsers ]; then npx playwright install --with-deps chromium; fi
fi

log "Environment ready: PHP $PHPV, $(composer --version 2>/dev/null | head -1)"
