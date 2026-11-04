# Session 00 — Bootstrap: environment, project, libraries, CI and plan

> Run it by saying: **`executa deploy/00-bootstrap.md`** (or `run deploy/00-bootstrap.md`) in a
> Claude Code session on the new repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.

The repository contains only `prompt_new.md`, `claude_suggest.md` and the `deploy/` folder. This
session prepares **everything** so that every later session can work from the repository alone.
**No application features in this session.**

## 1. Branch

`git checkout main && git pull`, then create the branch `session-00-bootstrap` (if this
environment already assigned you a working branch, use that one and say so in the pull request).
This is the first session: there is nothing to merge yet. From session 01 on, every session starts
by merging the previous sessions' branches into `main`.

## 2. Environment check (and fix what you can)

First find out **where you are running** and say it:

- **Cloud** (Claude Code on the web, claude.ai/code): a Linux container that starts from scratch
  in every session;
- **Local** (Claude Code in VS Code or a terminal on my computer): typically Windows with
  **XAMPP** (PHP in `C:\xampp\php`, MariaDB in XAMPP); detect the OS and the PHP binary in use
  (`php -v`, `php --ini`, `where php` / `which php`).

Then:

1. Check: PHP 8.2 or later, extensions `intl`, `mbstring`, `json`, `openssl`, `fileinfo`, `zip`,
   `gd`, `sodium`, `sqlite3`/`pdo_sqlite`, `mysqli`, `curl`, `xml`; `composer --version`; `git`;
   network access to `repo.packagist.org` and `github.com` (`composer show -a siensis/kpcrudy`).
2. **Fix what is missing:**
   - **Cloud:** install it yourself (e.g. `apt-get install -y php8.x-intl` as root, or Composer's
     installer verifying its SHA-384 signature).
   - **Local:** **do not install or change anything on my computer without asking.** Show me the
     exact steps instead, and wait: for XAMPP, which lines of `php.ini` (the file shown by
     `php --ini`) to uncomment (`extension=intl`, `zip`, `gd`, `sodium`, `sqlite3`, `pdo_sqlite`,
     `mysqli`…), restarting the terminal (and Apache if used); the XAMPP version with PHP 8.2+ if it
     is older; Composer for Windows (getcomposer.org) if missing; PHP and Composer in the `PATH`
     of the terminal VS Code uses.
   - If the environment still cannot work (no permission, no network), **stop** and tell me exactly
     what it needs, so I can fix it and run this file again.
3. **Database:** the tests use SQLite (in memory). For running the app, use MariaDB when available
   (in XAMPP: create a database, e.g. `ci4app`, and a user with a password — ask me for the values
   or propose them, and write them only in `.env`); otherwise SQLite in `writable/`. CI covers
   MariaDB.
4. **Screenshots:** later sessions take screenshots with **Playwright** (Node.js + Chromium). Check
   `node -v` and `npx playwright --version`. Cloud: install them if missing
   (`npm i -D playwright && npx playwright install --with-deps chromium`). Local: ask me before
   installing Node.js; `npm i -D playwright && npx playwright install chromium` only touches the
   project folder and the user's Playwright cache. If it cannot be installed, sessions describe
   their screens in text.
5. **Serving the app:** `php spark serve` (http://localhost:8080) in both cases. Local with XAMPP,
   Apache can also serve it: explain in `docs/` how to point a virtual host at `public/` (never at
   the project root).
6. **Setup for later sessions:**
   - **Cloud:** write `deploy/setup.sh` (executable, idempotent: PHP extensions and Composer if
     missing, `composer install`, `npm ci` and Chromium when `package.json` exists) and tell me to
     paste it as the environment's **Setup script** (environment menu in the session's title bar →
     Edit → Setup script), so new sessions start ready.
   - **Local:** nothing to install per session; write `deploy/setup-local.md` with the checklist
     above (XAMPP extensions, Composer, Node.js, database, `.env`) for a new computer.
   - Write both files anyway, so the project can be developed in either place.

## 3. Specification and CLAUDE.md

1. Move `prompt_new.md` to `docs/specification.md`, dropping the first lines up to and including
   the first `---` (instructions for the human). Read it **completely**.
2. Create `CLAUDE.md` from `claude_suggest.md` without the HTML comment at the top, add this line
   under the title: "Session prompts live in `deploy/` (run them in order: `deploy/next.md`); do not
   edit them unless asked. Each session merges the finished session branches into `main` first and
   ends with a report and screenshots in `deploy-status/NN-<slug>/`." Delete `claude_suggest.md`.

## 4. CodeIgniter project

1. Create a CodeIgniter 4.7 app with the appstarter structure **in the repository root** without
   losing the existing files: `composer create-project codeigniter4/appstarter <tmp dir>`, then copy
   its files into the repository (do not overwrite `docs/`, `deploy/`, `CLAUDE.md`).
2. `composer.json`: name, description, licence MIT, PHP `^8.2`, the required extensions
   (`ext-intl`, `ext-mbstring`, …), `minimum-stability: stable`, `prefer-stable: true`, scripts
   (`test`, `analyse`, `cs`, `cs-fix`), and PSR-4 `Modules\\` → `modules/`, `Themes\\` → `themes/`.
3. Required packages:

   ```bash
   composer require codeigniter4/shield codeigniter4/settings codeigniter4/translations siensis/kpcrudy:^1.0 siensis/kpapy:^1.0
   composer require --dev phpunit/phpunit phpstan/phpstan codeigniter/phpstan-codeigniter friendsofphp/php-cs-fixer fakerphp/faker mikey179/vfsstream
   ```

   If kpcrudy or kpapy cannot be installed, **stop and report the error**: never continue without
   them or replace them. Commit `composer.lock`.
4. Other libraries named in the specification (`ezyang/htmlpurifier`, `league/flysystem`, a TOTP
   library, a QR code library) are installed by the sessions that use them.
5. Read the documentation of the two libraries in `vendor/siensis/kpcrudy/` and
   `vendor/siensis/kpapy/` (`README_EN.md`, `docs/functions_EN.md`, `docs/howto_EN.md`,
   `docs/commands_EN.md`).

## 5. Tooling

- `env` template without real values, `.env` for local work (ignored), `CI_ENVIRONMENT` default
  `production` in the template; SQLite test database in `phpunit.xml.dist`, and a MariaDB
  configuration selected by environment variables (for CI).
- `phpstan.neon.dist` (level 6, `codeigniter/phpstan-codeigniter`), `.php-cs-fixer.dist.php`
  (PSR-12 + strict types), `.editorconfig`, `.gitattributes`.
- `.gitignore`: `.env`, `vendor/`, `writable/*` except the `index.html` files and folder keepers,
  `public/assets/modules/`, `public/themes/`, `.phpunit.cache/`, `build/`.
- A smoke test: the app boots and `/` answers 200.

## 6. Continuous integration (GitHub Actions)

`.github/workflows/ci.yml`, on pushes and pull requests:

- matrix PHP 8.2, 8.3, 8.4; Composer cache;
- `composer validate --strict`, `composer audit`;
- php-cs-fixer dry run, PHPStan;
- PHPUnit on SQLite, and on MariaDB (service container) for PHP 8.3;
- `.github/dependabot.yml` for Composer and GitHub Actions.

Later sessions add jobs (CSP check, assets, checksums, secret scanning, Playwright). Make the
workflow green.

## 7. Status reports (`deploy-status/`)

Create `deploy-status/README.md`, the format every session follows:

- one folder per session: `deploy-status/NN-<slug>/` (same slug as `deploy/NN-<slug>.md`);
- `README.md` in it with the sections **What was built**, **How it was built** (design, main
  classes and files, tables, routes, permissions, commands), **Decisions and deviations**, **How
  to try it** (commands, URLs, demo users), **Tests and checks** (results), **Screenshots**
  (each image with a caption), **Known issues** and **Notes for the next session**;
- `screenshots/` with PNG files `01-<what>.png`, … taken with Playwright against `php spark serve`
  with demo data (device scale factor 2; phone width and dark mode when relevant);
- a script `deploy-status/screenshot.mjs` (Playwright) that takes a list of URLs, viewport sizes and
  output names, logs in with a given user when needed, and saves the images; every session reuses it.

Then write this session's report in `deploy-status/00-bootstrap/README.md` (environment found and
fixed, packages installed, CI, plan summary; no screenshots needed: include the output of
`php spark` and of the checks).

## 8. Plan and progress

1. **`docs/plan.md`:** for every session file in `deploy/` (01–36): goal, the files, tables,
   commands and tests it will create, dependencies, risks. Flag anything in the specification that
   looks contradictory and propose a solution for each.
2. **`docs/progress.md`:** sections "Status" (a table: session, title, status, pull request),
   "Decisions", "Deviations from the specification", "Known issues", "Notes for the next session".
   Add session 00 as "done" with a link to `deploy-status/00-bootstrap/`.
3. **`docs/library-requests.md`:** empty template (library, missing feature, why, workaround).

## Done when

- `composer install` works from scratch; `vendor/bin/phpunit`, PHPStan and php-cs-fixer pass;
  the CI workflow is green on the pull request.
- `CLAUDE.md`, `docs/specification.md`, `docs/plan.md`, `docs/progress.md`, `deploy/setup.sh`,
  `deploy/setup-local.md`,
  `deploy-status/README.md`, `deploy-status/screenshot.mjs` and `deploy-status/00-bootstrap/README.md`
  exist; `composer.json` requires `siensis/kpcrudy` and `siensis/kpapy` with `^1.0`.

## Finish

1. Show the output of the checks.
2. Commit in small commits, push the branch and open a pull request
   "Session 00: Bootstrap" with a summary. Session 01 will merge it into `main` when it starts
   (review it before if you want).
3. Reply with: where you ran (cloud or local) and what the environment needed, what I must
   configure (the cloud setup script, or the local steps), the plan summary, the
   contradictions or doubts found in the specification (with your proposal for each), anything I
   must decide, and the next step: "run `deploy/next.md`".
