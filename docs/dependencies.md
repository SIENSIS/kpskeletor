# Dependencies

PHP libraries come from Composer (Packagist only); the few front-end libraries are vendored in
`public/assets/vendor/` with their checksums. There is no JavaScript build: `package.json` only
holds the development tools (Playwright for the screenshots of `deploy-status/`).

## Composer constraints

`composer.json` requires PHP `^8.2` and the extensions `curl`, `fileinfo`, `gd`, `intl`, `json`,
`mbstring`, `openssl`, `sodium` and `zip` (`mysqli` or `sqlite3` are suggested, one per database
driver). `config.platform.php = 8.2.0` makes the lock file install on every supported PHP version;
raise it only when dropping PHP 8.2. `minimum-stability` is `stable`.

| Package | Constraint | Why |
|---|---|---|
| `codeigniter4/framework` | `^4.7` | The framework. |
| `codeigniter4/shield` | `^1.4` | Authentication, groups and permissions ([`auth.md`](auth.md)). |
| `codeigniter4/settings` | `^2.4` | Runtime settings ([`settings.md`](settings.md)). |
| `codeigniter4/translations` | `^4.7` | The framework's own messages in Catalan and Spanish. |
| `siensis/kpcrudy` | `^1.3` | Every back-office CRUD ([`cruds.md`](cruds.md)). 1.3 is the minimum because the skeleton uses `where(Closure)` and the `attrs` option of translations. |
| `siensis/kpapy` | `^1.2` | Every JSON API endpoint ([`api.md`](api.md)). 1.2 is the minimum because the skeleton uses the time zones of `datetime` fields and `where(Closure)`. |
| `spomky-labs/otphp` | `^11.3` | TOTP for the authenticator-app 2FA (pre-approved). |
| `chillerlan/php-qrcode` | `^5.0` | The QR code of the 2FA enrolment, drawn locally (pre-approved). |
| `ezyang/htmlpurifier` | `^4.18` | Rich-text sanitising (`HtmlSanitizer`, pre-approved). |
| `league/flysystem`, `league/flysystem-local` | `^3.29` | The Storage service's disks ([`storage.md`](storage.md), pre-approved). |

Development: `phpunit/phpunit` `^11.5`, `phpstan/phpstan` `^2.3` with
`codeigniter/phpstan-codeigniter` `^2.2`, `friendsofphp/php-cs-fixer` `^3.95`,
`fakerphp/faker` `^1.24` and `mikey179/vfsstream` `^1.6`.

Rules (`CLAUDE.md`):

- kpcrudy and kpapy are installed from Packagist only: never `dev-main`, a `repositories` entry or
  a local path, and never edited in `vendor/`. Features they lack go to
  [`library-requests.md`](library-requests.md).
- **Ask the owner before adding a dependency.** Pre-approved: the libraries the specification
  names (TOTP, QR code, HTMLPurifier, Flysystem) and an MIT/BSD-licensed rich-text editor and
  calendar (Quill and FullCalendar, below); install them in the session that uses them.
- Constraints use the caret (`^x.y`) at the lowest version whose features the code uses; raise the
  minimum in `composer.json` (and say why in this table) when code starts using a newer feature.

## `composer.lock`

`composer.lock` is committed: every installation, CI run and update installs exactly the locked
versions (`composer install`; production: `composer install --no-dev`, which `app:update` runs).
Change it only on purpose:

```bash
composer update siensis/kpcrudy --with-dependencies   # one package
composer validate --strict                            # composer.json and the lock agree
```

then run the tests, PHPStan and php-cs-fixer ([`development.md`](development.md)) and commit
`composer.json` and `composer.lock` together.

## Security advisories

- **CI** (`.github/workflows/ci.yml`) runs `composer validate --strict` and `composer audit` on
  every push and pull request; an advisory fails the build.
- **On a server**, `php spark security:audit` runs `composer audit --locked --format=json` and
  stores the result with its date in `writable/security/composer-audit.json`;
  `php spark security:audit --from=<file>` stores the JSON output of an audit run elsewhere (CI)
  instead. The daily task "Security checks and composer audit" (04:43) runs it too.
- `php spark security:check` (group `dependencies`) and the Security status page
  (`backend/security`) report the last audit: advisories are an error; never run, failed or older
  than `Config\ProductionCheck::$auditMaxAgeDays` (7 days) is a warning. See
  [`security.md`](security.md) and [`commands.md`](commands.md).

## Dependabot

`.github/dependabot.yml` checks weekly:

- `composer` (root folder): at most 5 open pull requests, development dependencies grouped in one
  pull request (`dev-tools`);
- `github-actions` (the CI workflow's actions).

A Dependabot pull request runs the whole CI (audit, style, PHPStan, tests on PHP 8.2–8.4 and
MariaDB). Review the changelog of the package, especially for kpcrudy, kpapy, Shield and the
framework, before merging. Dependabot does not watch the vendored front-end libraries: check them
by hand when updating the skeleton.

## Vendored front-end libraries

They live in `public/assets/vendor/<library>/` (committed; never published or changed by
`assets:publish`) and are described by `public/assets/vendor/vendor.json`:

| Library | Version | Licence | Used by |
|---|---|---|---|
| `bootstrap` | 5.3.8 | MIT | Every theme (CSS and the JS bundle). |
| `bootstrap-icons` | 1.13.1 | MIT | Icons (font). |
| `quill` | 2.0.3 | BSD-3-Clause | The rich-text editor (`core/forms/editor`, loaded on demand). |
| `fullcalendar` | 7.1.3 | MIT | The Events module's calendar (`events/calendar`, loaded on demand). |

`vendor.json` has one entry per library folder:

```json
{
    "quill": {
        "version": "2.0.3",
        "licence": "BSD-3-Clause",
        "source": "https://registry.npmjs.org/quill/-/quill-2.0.3.tgz",
        "homepage": "https://quilljs.com/",
        "files": {
            "LICENSE": "<sha256>",
            "quill.js": "<sha256>",
            "quill.snow.css": "<sha256>"
        }
    }
}
```

- `version`: `x.y.z`; `licence`: the SPDX name; `source`: the HTTPS address of the exact archive
  the files came from; `homepage`;
- `files`: **every** file in the library's folder (paths relative to it, sorted), with its SHA-256.
  The licence file is always one of them.

Who reads it:

- `asset('vendor::<library>/<path>')` returns the file's URL with `?v=` and the first 12
  characters of its checksum (`AssetManifest::vendorUrl()`), so a new version busts the cache;
- `Modules\Core\Tests\VendorAssetsTest` fails when a file is missing, extra or changed, or when
  `version`, `licence` or `source` are invalid;
- `php spark security:check` (`dependencies.vendor`, an error) compares the checksums on the
  server, so a modified file in production is reported.

### Adding or updating a library

1. **Ask first** (a new library is a dependency) unless it is pre-approved. The licence must be
   MIT/BSD-compatible, and the library must work under the strict CSP: no `eval`, no inline
   `<style>`/`<script>` it injects itself (changing styles through the CSSOM is fine), no CDN.
2. Download the published archive (for npm packages
   `https://registry.npmjs.org/<name>/-/<name>-<version>.tgz`) and check it against the registry's
   integrity hash.
3. Copy only the files the page needs (minified build, its CSS, source maps if wanted, extra
   locales) **and the licence file** into `public/assets/vendor/<library>/`. For an update, remove
   the old files first so nothing stale stays.
4. Write the checksums:

   ```bash
   cd public/assets/vendor/<library> && find . -type f | sed 's|^\./||' | sort | xargs sha256sum
   ```

   and update the library's entry in `vendor.json` (`version`, `licence`, `source`, `homepage`,
   `files` sorted by path).
5. Load it from a component with `asset('vendor::<library>/<file>')` (on demand, like the editor and
   the calendar) or, for a theme-wide library, from the theme's layout; never with a relative path
   or an external URL.
6. Run `vendor/bin/phpunit --filter VendorAssetsTest`, `php spark csp:check`, open the pages that
   use it in light and dark mode with the browser console open (no CSP violation) and run
   `php spark security:check --only=dependencies`.
7. Update the table above and the document of the feature ([`frontend.md`](frontend.md),
   [`themes.md`](themes.md)), and record a new library in `docs/decisions.md`.

## Library requests

When kpcrudy or kpapy cannot do something through their public API, the gap, its reason and the
temporary workaround in the Core are recorded in [`library-requests.md`](library-requests.md)
(longer write-ups in `docs/library-requests/`); the workaround is removed once a release of the
library provides the feature and `composer.json` requires that release.
