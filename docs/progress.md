# Progress

Updated by every session (see `deploy/`). Status values: *in progress*, *done* (report written,
pull request open), *merged* (in `main`).

## Status

| Session | Title | Status | Branch | Pull request | Report |
|---|---|---|---|---|---|
| 00 | Bootstrap | merged | `claude/modest-goldberg-jjs8kp` | [SIENSIS/kpskeletor#1](https://github.com/SIENSIS/kpskeletor/pull/1) | [`deploy-status/00-bootstrap/`](../deploy-status/00-bootstrap/README.md) |
| 01 | Core kernel: modules, manifest and sync | merged | `claude/modest-goldberg-jjs8kp` | [SIENSIS/kpskeletor#4](https://github.com/SIENSIS/kpskeletor/pull/4) | [`deploy-status/01-core-modules/`](../deploy-status/01-core-modules/README.md) |
| 02 | Core kernel: catalogue, settings registry, enable/disable and demo data | merged | `claude/clever-lovelace-i36pbh` | [SIENSIS/kpskeletor#5](https://github.com/SIENSIS/kpskeletor/pull/5) | [`deploy-status/02-core-registry/`](../deploy-status/02-core-registry/README.md) |
| 03 | Themes and standard views | merged | `claude/tender-ride-z9y4vv` | [SIENSIS/kpskeletor#6](https://github.com/SIENSIS/kpskeletor/pull/6) | [`deploy-status/03-themes/`](../deploy-status/03-themes/README.md) |
| 04 | Front-end assets, JavaScript, CSP, dark mode, responsive and PWA | merged | `claude/funny-goldberg-9n764h` | [SIENSIS/kpskeletor#7](https://github.com/SIENSIS/kpskeletor/pull/7) | [`deploy-status/04-frontend/`](../deploy-status/04-frontend/README.md) |
| 05 | Authentication (Shield) and session hardening | merged | `claude/confident-turing-bbgi7g` | [SIENSIS/kpskeletor#8](https://github.com/SIENSIS/kpskeletor/pull/8) | [`deploy-status/05-auth/`](../deploy-status/05-auth/README.md) |
| 06 | Two-factor authentication and re-authentication | merged | `claude/ecstatic-davinci-goi31r` | [SIENSIS/kpskeletor#10](https://github.com/SIENSIS/kpskeletor/pull/10) | [`deploy-status/06-auth-2fa/`](../deploy-status/06-auth-2fa/README.md) |

## Decisions

- **06:** per-user 2FA through `UserTwoFactorAction` (Shield `login` action, conditional) with email codes hashed and sent by `SecurityMailer` instead of Shield's `Email2FA` class; tables `user_two_factor` (encrypted TOTP secret, last step) and `user_recovery_codes` (SHA-256); otphp + php-qrcode (SVG `data:` image); remember-me issued only after the 2FA step (`SessionAuthenticator`); settings `Auth.twoFactorPolicy` and `Auth.reauthMinutes`; `auth/reauth`, POSTs not replayed, JSON 401, `requireReauth()` via the `reauth` alias (D-06-01 … D-06-06). D-06-04 (a password login counts as re-authentication) decided by the owner as proposed (option A) and applied.

- **05:** own login/magic-link/reset controllers with hashed single-use tokens; `user_sessions` tracking checked by the global `authsession` filter (absolute lifetime, remember-me capped from its creation, remote logout deletes the session's remember-me token, idle lock); interim `AuthGroups` matrix and superadmin bypass of `can:`; `Auth.*` settings (minutes/days, defaults in `Config\Auth`); personal tokens marked `secret2 = 'personal'`; global CSRF; `session` + `forcereset` on `backend/*`; login lands on the profile; new-device notice skips the first device (D-05-01 … D-05-08).

- **04:** front-end classes in `modules/Core/Frontend/` (`ThemeAssetPublisher` folded into `AssetPublisher`); development republishes stale assets per request; manifest keys `<owner>::<path>` with reserved `core`/`theme`/`vendor`, bare import specifiers only; CSP details (`*-attr 'none'`, `img-src data:`, no report endpoint); module `$csp` applied by the `cspmodules` filter; colour mode in the `kp_theme` cookie until the profile exists; sidebar offcanvas < 768 px, icon-only 768–1199 px, `kp_sidebar` cookie; PWA settings `Core.pwa`/`Core.pwaStart`, icons in `writable/pwa/` (adds `settings.core`); CSRF meta only with an existing session; CodeIgniter's welcome page replaced by a themed home (D-04-01 … D-04-10).

- **03:** theme slug ↔ `Themes\<Studly>` namespace registered on demand; theme assets published by `modules:sync`/`make:theme` until `assets:publish`; error pages in the `blank` layout and themed/JSON 403 from `can:`; manifest `$styles`/`$scripts`; `Core.app.name` until site settings; `BackendController::render()` adds layout/breadcrumbs/page header; `--name=value` options (D-03-01, 03, 04, 06–09). D-03-02 (theme option storage) and D-03-05 (theme preview URL) decided by the owner as proposed and applied.

- **02:** one `module_declarations` table for every registry; manifest defaults through a read-only Settings handler; disabled modules answer 404 through the global `moduleenabled` filter; `modules.grants_applied` flag; Core resource `modules`; `DemoSeeder` base class and y/n confirmations; cache scopes with version keys; application log until the activity log exists (D-02-01 … D-02-08).

- **01:** module namespaces generated in `app/Config/ModuleNamespaces.php`; default-deny `can:` filter before RBAC; `make:module` creates an example migration; missing module folders are reported, not deleted; Core version 0.1.0 (D-01-01 … D-01-05).

- **Decisions 00:** the 56 open questions of `docs/plan.md` were answered with `deploy/decide.md` (9 decided by the owner, 47 accepted proposals) and applied to the specification, the session files, `CLAUDE.md` and the code; see `docs/decisions.md`. Bootstrap §3 (specification and `CLAUDE.md`) is done; `prompt_new.md`/`claude_suggest.md` no longer exist.

- **00:** PHPUnit 11.5 with `config.platform.php = 8.2.0` so one lock file installs on PHP
  8.2–8.4.
- **00:** tests on SQLite in memory by default; MariaDB selected with `database.tests.*`
  environment variables (CI job on PHP 8.3 + MariaDB 11.4).
- **00:** Playwright pinned to 1.63.0 in `package.json` (matches the cloud image's Chromium).
- **00:** Shield prerequisites set early (`csrfProtection = 'session'`, `auth`/`setting` helpers)
  so the app boots in development with the debug toolbar.

## Deviations from the specification

- **06:** the 2FA admin reset is a service + `twoFactorReset` event, without screen (session 15), notification (session 11) or activity entry (session 12); fixed a session 05 gap: Auth feature tests left a logged-in session that broke `BaseModelTest` depending on order (`AuthTestCase::tearDown()`).

- **05:** security events go to the application log until session 12; no avatar until the file picker (session 23); Shield's logout deletes all the user's remember-me tokens; fixed two Core tests that assumed Core was the only module.

- **04:** the logged-in user's colour mode is stored in the cookie until the Users module stores it in the profile (D-04-06); the axe/WCAG contrast test is left to the quality session; fixed the session 03 gap: `public/assets/core/` and the asset manifest are git-ignored.

- **03:** site name from the language line `Core.app.name` until session 14 (D-03-07); `Themes\` removed from `composer.json` autoload (themes register their namespace on demand, D-03-01); fixed the session 02 gap where `--demo=A,B` was ignored (D-03-09); `.gitignore` `vendor/` anchored so `public/assets/vendor/` is committed.

- **02:** enable/disable and demo-data actions are written to the application log, not the activity log (session 12, D-02-08); `modules:enable` does not publish assets yet (session 04); closure routes of a disabled module are not hidden (modules use controllers, D-02-03).

- **01:** none; session 01 decisions D-01-01 … D-01-05 (accepted proposals) are in `docs/decisions.md`.

- **00:** branch `claude/modest-goldberg-jjs8kp` (assigned by the environment) instead of
  `session-00-bootstrap`. Later sessions: find this session's branch through this table.

## Known issues

- **06:** email 2FA needs `email.*` in `.env` to be usable outside tests; no settings screen for the 2FA policy and the re-auth window until session 14; the authenticator-app method needs `encryption.key`.
- **05:** guests still see the default locale `en` (session 31); Auth feature tests migrate all namespaces per test (suite ~1 min).
- **04:** CodeIgniter's development error page keeps inert `onclick` toggles under the CSP (only `csp:check` exception); the PWA can only be enabled through the settings service until session 14; `deploy/setup.sh` stops when Composer cannot fetch `phpstan/phpstan` (seed `~/.cache/composer/files/phpstan/phpstan/<sha1(dist URL)>.zip` from a git checkout, then `composer install --prefer-source`).
- **03:** `CanFilterTest::testGuestIsForbidden` fails when run alone (needs the settings table migrated by an earlier test); passes in the full suite.
- **02:** `CI_ENVIRONMENT` in `.env` wins over a shell variable of the same name (CodeIgniter reads `$_ENV` first).
- Cloud egress proxy refuses `api.github.com` zip downloads; Composer uses git sources
  (`deploy/setup.sh` retries with `--prefer-source`). `phpstan/phpstan` (dist only) needed its
  zip seeded into Composer's cache from a git checkout.
- The 56 open questions from `docs/plan.md` are decided and applied (`docs/decisions.md`).

## Notes for the next session

- Session 07+: see `deploy-status/06-auth-2fa/README.md`: protect sensitive routes with `['can:…', 'reauth']` or `$this->requireReauth()`; add `force2fa` to the `backend` chain (after `forcereset`); session 10 reads `TwoFactor::method()`/`verifyTotp()`; session 11 notifies on `twoFactorReset`; session 15 adds the reset row operation and the policy column.
- Session 06+: see `deploy-status/05-auth/README.md` (2FA hooks in the login/magic-link controllers, reauth for remember-me sessions, interim matrix to replace in 07, `GroupRules`, `SessionTracker::privilegesChanged()`, `PasswordChanger::PERSONAL_TOKEN`) and `docs/auth.md`.
- Session 05+: JS in `modules/<Name>/Assets/js/`, imported as `core/…`/`<module>/…`, URLs with `asset()`; no inline code (`php spark csp:check`); `data-confirm`, `form_layout` `dirtyGuard`, `core/http.js`. Session 08: sidebar links use `<span class="nav-text">`; Settings › Security origins can reuse `ContentSecurity::apply()`. Session 09: `kpcrudy:confirm` → `core/ui/confirm.js`. Session 14: Settings UI for `Core.pwa*` and the new theme options. Session 15: store the colour mode in the profile (`ThemeMode` preference reader). See `deploy-status/04-frontend/README.md` and `docs/frontend.md`.
- Session 04: fold `ThemeAssetPublisher` into `assets:publish`; nonces on `partials/scripts.php`; allow the one nonce'd `<style>` of `partials/head.php`; dark values of theme options, theme switch, remember the sidebar and the cookie notice. Session 08: fill the backend `sidebar` region and the menu locations. Session 09: `setLayout(theme_layout('backend'), 'content')`. Session 14: Settings UI for `Theme.*` and theme options (`docs/themes.md`). See `deploy-status/03-themes/README.md`.
- Session 03+: read registries through `DeclarationRegistry` (disabled modules already hidden), cache with `CoreCache` scopes and invalidate only through `CacheInvalidator`. Session 04: add `assets:publish` to `modules:sync` (step 7) and `ModuleToggler::enable()`. Session 07: grants are in `group_permissions` (applied once per module); write Shield's `AuthGroups` from them. Session 12: replace the `log_message()` calls in `ModuleToggler` and `DemoDataManager` with activity log entries. Session 14: forms from `SettingsRegistry::forModule()`, values cast with `SettingCaster`.
- `bash deploy/setup.sh` starts MariaDB in a new container; `.env` must be recreated from `env`.
- Earlier notes (session 00): read `docs/decisions.md` before starting.
