# Session 14 — Settings module and Settings › Security

Branch: `claude/happy-darwin-p8xstn` · Pull request: [SIENSIS/kpskeletor#18](https://github.com/SIENSIS/kpskeletor/pull/18) · Date: 2026-11-06

## What was built

- A **Settings** module (`backend/settings`) whose forms are generated from the manifests'
  `$settings`: one tab per module ("General" for the Core), a **Theme** tab (back-office and
  public themes, the options of a theme) and **Settings › Security**, with server-side validation
  and typed casting. Only overrides are stored; a value equal to the default is forgotten.
- Permissions: `settings.update` opens the screens; saving a module's tab needs
  `settings.<module>` (read-only without it; forged POSTs get 403); the theme tab needs
  `settings.core`; Settings › Security needs `settings.security` and a re-authentication.
- **Settings › Security**: minimum password length (never below `Config\Auth`), absolute,
  idle and remember-me lifetimes, re-auth window, login/email-link limits, captcha, new-device
  notice, 2FA policy, rate limits, **extra CSP origins per directive**, outbound allow-list,
  upload rules, activity retention, `Api.publicSpec`, `Api.corsOrigins`, `Api.appTokenDays`.
  Values that weaken the baseline (`'unsafe-inline'`, `*`, `data:`, wildcard scripts, IP
  outbound hosts, forbidden upload types, short passwords…) are refused.
- An origin added in the UI appears in the `Content-Security-Policy` header.
- Site-wide settings: site name (`Core.siteName`, used by the layouts, PWA, authenticator app,
  OpenAPI title) and display time zone (`Core.timezone`, used by `ui_date()`).
- Actions: send a test email (queued), clear caches, publish assets (security + re-auth).
- Every change is logged: `settings_changed` (activity), `security_settings_changed` /
  `security_settings_refused` (security), `test_email`, `caches_cleared`, `assets_published`.

## How it was built

- **Core** (`modules/Core`):
  - `Settings/SettingCaster.php`: new type `list` (one entry per line); `Modules/ManifestValidator.php`
    accepts it.
  - `Settings/SecurityRules.php`: validation rules `csp_sources`, `csp_script_sources`,
    `outbound_hosts`, `cors_origins`, `upload_types`, `min_config[Class.property]`, registered in
    `Config\Validation::$ruleSets` by `Config/Registrar.php::Validation()`.
  - `Filters/ContentSecurity.php`: adds the stored origins (`SETTINGS` map, `settingSources()`),
    re-checked with `SecurityRules::isAllowedCspSource()`.
  - `Config/Module.php`: `siteName`, `timezone`, `csp*Src` settings; groups (`general`, `pwa`,
    `scheduler`, `security.rateLimits|uploads|activity|outbound|csp`); `outboundHosts` → `list`.
  - `Helpers/ui_helper.php`: `site_name()`, `site_timezone()`; `ui_date()` converts to the site
    time zone. `lang('Core.app.name')` replaced by `site_name()` in the theme, PWA, 2FA and kpapy config.
- **Auth**: settings in `security.*` groups; `Validation/AuthRules.php` (`two_factor_policy`)
  registered by `Config/Registrar.php`; `min_config[Auth.minimumPasswordLength]`.
- **Api**: settings in `security.api`; `corsOrigins` → `list` + `cors_origins`.
- **Settings module** (`modules/Settings`, no tables):
  - `Config/Module.php`: resource `settings` (`name` override, every CRUD operation excluded,
    extras `update`, `security`), admin grant `settings.*`, two `settings`-zone menu entries.
  - `Config/Routes.php`: `backend/settings`, `…/security` (GET/POST, `can:settings.security` +
    `reauth`), `…/theme`, `…/actions`, `…/actions/{email,caches,assets}`, `…/(:segment)`.
  - `Services/SettingsEditor.php` (tabs, security fields, baseline, `save()`),
    `Services/ThemeSettings.php`, `Services/SettingsTabs.php`.
  - `Forms/Field.php` (type rules, casting), `Forms/FormGenerator.php` (views
    `Views/fields/{input,textarea,checkbox,select,group,_meta}.php`, validation).
  - `Controllers/Settings.php`, `Controllers/Actions.php`, views `form.php`, `actions.php`,
    `_tabs.php`, `emails/test.php`.
- Docs: new `docs/settings.md`; updated `CLAUDE.md`, `docs/{auth,security,storage,frontend,themes,scheduler,activity,outbound-requests,menus}.md`,
  module `CLAUDE.md` files.

## Decisions and deviations

- **D-14-01 (decided as proposed):** settings with `group` `security`/`security.<sub>` go to Settings ›
  Security; new setting type `list` (`Core.outboundHosts`, `Api.corsOrigins` changed from `json`).
- **D-14-02 (decided as proposed):** names `Core.siteName`, `Core.timezone`, `Core.cspScriptSrc`,
  `cspStyleSrc`, `cspImgSrc`, `cspFontSrc`, `cspConnectSrc`, `cspFrameSrc`, `cspMediaSrc`.
- D-14-03 (accepted): permissions/routes/actions (read-only tabs, theme = `settings.core`,
  publish assets = `settings.security` + re-auth, admin `settings.*`).
- D-14-04 (accepted): what counts as weakening; only overrides stored.
- D-14-05 (accepted): test email queued (not inline); "clear caches" only the Core scopes.
- D-14-06 (accepted): locales (31), maintenance mode (28), mail transport (`.env`), logo (theme
  option) not built here.
- Deviation: `docs/menus.md`'s example `settings.news` route replaced (module tabs share one
  parameterised route).

Both decisions were confirmed by the owner as built (`executa deploy/decide.md`, 2026-11-06).

## How to try it

```bash
php spark modules:sync
php spark modules:demo Auth
php spark serve
```

Log in at <http://localhost:8080/login> as `admin@example.com` / `Demo-kpskeletor-2026`
(password login also opens the 15-minute re-authentication window), then
`backend/settings` (General), `backend/settings/theme`, `backend/settings/security`,
`backend/settings/actions`. Add `https://images.example.com` to "Extra image origins" and check
the `Content-Security-Policy` header; try `'unsafe-inline'` in script-src (refused).

## Tests and checks

- `vendor/bin/phpunit`: **OK (663 tests, 3320 assertions)** (619 before the session).
  New: `modules/Settings/Tests/SettingsScreensTest.php` (permissions per tab, forged POSTs,
  casting, overrides forgotten, invalid values 422, security tab + re-auth, 19 refused weak
  values, `min_config`, a changed minimum password length enforced, the remember-me lifetime
  capping a re-created session, CSP origin in the header), `SettingsActionsTest.php` (test email
  queued not sent, clear caches, publish assets needs `settings.security`, theme options need
  `settings.core` and are stored per theme), `SettingsFormTest.php` (casting, type rules, groups,
  every declared rule exists, CSP filter ignores weak stored values), `SettingsModuleTest.php`.
  Updated expectations: `PermissionsCommandsTest` (32 permissions), `ShieldMatrixTest` (admin
  `settings.*`).
- `vendor/bin/phpstan analyse`: `[OK] No errors`.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: 0 of 446 files to fix.
- `php spark permissions:check`: `Permissions OK: 32 permission(s) in the catalogue, 102 route(s) checked.`
- `php spark csp:check`: no inline scripts, handlers or styles.
- `php spark assets:publish --check`: up to date.
- `php spark security:check`: not available yet (session 30).

## Screenshots

Playwright against `php spark serve`, demo data, device scale factor 2.

1. `screenshots/01-general-tab.png` — General tab after saving the site name "Institut Demo"
   (shown in the sidebar brand) and the time zone; "Changed / Default" hints.
2. `screenshots/02-security-tab.png` — Settings › Security, one card per module with sub-group
   headings, after adding an image origin.
3. `screenshots/03-security-refused.png` — `'unsafe-inline'` in script-src and `127.0.0.1` as an
   outbound host refused with their messages.
4. `screenshots/04-theme-tab.png` — theme tab: active themes and the default theme's options.
5. `screenshots/05-actions.png` — the three actions.
6. `screenshots/06-security-phone-dark.png` — Settings › Security at phone width in dark mode.

## Known issues and notes for the next session

- D-14-01 and D-14-02 were decided by the owner as proposed (Decisions 14, same branch).
- `Api\Tests\ApiResourceTest::testTheLoginIsLimitedPerIp` failed at random with a 500 (also in
  CI on `main` after session 13). Cause: kpapy hashed a dummy password made of `random_bytes(16)`
  for unknown emails, and bcrypt throws a `ValueError` on a null byte (about 6 % of processes).
  Fixed in kpapy 1.0.1 (hex-encoded dummy password) and installed here (`composer.lock`);
  recorded in `docs/library-requests.md`.
- Composer needed `phpstan/phpstan`'s zip seeded again (known issue 04).
- Session 15: move `Auth.twoFactorPolicy` to the `groups` table (and out of Settings ›
  Security); `SessionTracker::privilegesChanged()` is not needed by settings changes.
- Session 28: maintenance mode setting; session 30: show the final CSP and the security settings
  in the Security status page, `security:check`; session 31: locales in the General tab and the
  user's own time zone in `ui_date()`.
- New settings for later modules: put security-relevant ones in a `security.<sub>` group and
  refuse weak values with rules (`docs/settings.md`).
