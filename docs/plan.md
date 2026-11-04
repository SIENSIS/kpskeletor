# Build plan

This plan turns `docs/specification.md` into 36 working sessions (`deploy/01-*.md` …
`deploy/36-*.md`), after the bootstrap session `deploy/00-bootstrap.md`. It lists, per session,
what will be built, where, and what can go wrong. It is a forecast: each session records the
real decisions and deviations in `docs/progress.md`, and session 19 revises this file.

## How sessions work

- **One session = one Claude Code run** started with `executa deploy/next.md` (or a specific
  file). Each session file has the same frame: *Before starting* (merge the finished session
  branches into `main` in order with `merge --no-ff`, run the checks after each merge, mark them
  *merged* in `docs/progress.md`, push `main`, create the session branch), *Goal*, *Scope*,
  *Out of scope*, *Done when*, *Working rules* and *Finish* (checks, report with screenshots in
  `deploy-status/NN-<slug>/`, `docs/progress.md`, commits, pull request "Session NN: …").
- **Branches:** `session-NN-<slug>`, or the branch the environment assigns (then the pull request
  title "Session NN: …" identifies it). See contradiction 1.
- **Interrupted sessions** continue with `deploy/continue.md NN`; review feedback is applied with
  `deploy/review-fixes.md NN` (branch `session-NN-fixes` when already merged).
- **Every session** reads `CLAUDE.md`, this plan, `docs/progress.md`, the previous report and the
  specification sections its file lists; keeps texts in `ca`/`es`/`en`; adds tests for every
  rule; runs PHPUnit, PHPStan (level 6) and php-cs-fixer before committing.
- **Versions found at bootstrap:** CodeIgniter 4.7.6, Shield 1.4.1, Settings 2.4.0,
  `siensis/kpcrudy` 1.0.0 and `siensis/kpapy` 1.0.0 (both published on 2026-11-03).

## Milestones

| Milestone | After session | Meaning |
|---|---|---|
| **1 — MVP** | 19 | Core kernel, themes/front end, Auth + 2FA, RBAC, menus, security baseline, kpcrudy/kpapy integration, notifications, scheduler, activity, storage, settings, users, dashboard, the News reference module and the installer, reviewed against acceptance criteria 1, 2, 4, 5, 6, 7 |
| **2 — Full functionality** | 30 | Pages, Menus editor, Files (manager + picker), Events (+ iCal and calendar API), complete core API, Backup, Updates, module packages and `security:check` |
| **3 — Release** | 36 | i18n pass, `Tasks` tutorial module and AI tooling, documentation, tutorials, end-to-end quality and CI, acceptance 1–13, version 1.0.0 |

## Dependency overview

```
00 bootstrap
└─ 01 modules/manifest ─ 02 registry/catalogue/settings/demo
   ├─ 03 themes ─ 04 front end/CSP
   ├─ 05 auth ─ 06 2FA/reauth
   └─ 07 RBAC (needs 02, 05, 06) ─ 08 menus + security baseline (needs 03, 04)
      ├─ 09 kpcrudy (needs 03, 04, 07) ─ 10 kpapy (needs 06, 07, 09)
      ├─ 11 notifications/scheduler (09) ─ 12 activity (09, 11)
      ├─ 13 storage (07, 08) ─ 14 settings UI (06, 08, 09)
      ├─ 15 users/matrix (06, 07, 09, 11) ─ 16 dashboard (04, 07, 15)
      └─ 17 News (09–16) ─ 18 installer (01–17) ─ 19 MVP review
20 Pages (09, 13, 04 editor) ─ 21 Menus (08, 20)
22 Files manager (13) ─ 23 picker/avatars (22, 05, 17)
24 Events (09, 04, 11, 16) ─ 25 iCal + calendar API (24, 17, 15) ─ 26 core API (10, 17, 24, 11)
27 Backup (13, 11, 06) ─ 28 Updates (08, 27) ─ 29 module packages (02, 27, 08) ─ 30 security:check (all)
31 i18n ─ 32 Tasks + tooling ─ 33 docs ─ 34 tutorials ─ 35 quality/CI ─ 36 release
```

Paths below use the conventions of the specification: modules in `modules/<Name>/` with
namespace `Modules\<Name>`, migrations named `YYYY-MM-DD-HHMMSS_CreateX`, tests in
`tests/unit/...` and `tests/feature/...`. Exact names may change; the session records them.

---

## 01 — Core kernel: modules, manifest and sync (`deploy/01-core-modules.md`)

- **Goal:** the module system works end to end with an empty module: discovery, typed manifest,
  validation, registry, per-module migrations and `modules:sync` (steps 1–3 and 8).
- **Files:**
  - `app/Config/Autoload.php` (`Modules\` → `modules/`, `Themes\` → `themes/`; see contradiction 3);
  - `modules/Core/Config/Module.php`, `modules/Core/Config/Version.php`,
    `modules/Core/Config/ModuleManifest.php` (every manifest key typed, with defaults);
  - `modules/Core/Modules/ManifestValidator.php`, `ModuleDiscovery.php`, `ModuleRegistry.php`,
    `DependencyResolver.php` (semver constraints), `ModuleSynchronizer.php`,
    `CacheInvalidator.php` (hook only);
  - `modules/Core/Models/BaseModel.php`, `modules/Core/Entities/BaseEntity.php`,
    `modules/Core/Controllers/BackendController.php`, `FrontendController.php` (skeletons);
  - `modules/Core/Commands/ModulesSync.php`, `ModulesList.php`, `ModulesStatus.php`,
    `MakeModule.php` + templates in `modules/Core/Commands/Generators/Views/module/*.tpl.php`;
  - `modules/Core/Language/{ca,es,en}/Core.php`.
- **Tables:** `modules` (name, version, enabled, installed_at, updated_at) —
  `modules/Core/Database/Migrations/…_CreateModules.php`.
- **Commands:** `modules:sync`, `modules:list`, `modules:status`, `make:module <Name>`.
- **Tests:** `ManifestValidatorTest` (each invalid key named), `ModulesSyncTest` (idempotent, second
  run changes nothing), `CoreMigrationsTest` (up/down/up on SQLite), `MakeModuleTest`.
- **Depends on:** 00.
- **Risks:** CodeIgniter discovers routes, commands, registrars and events only at the root of
  each registered namespace, so a single `Modules\` namespace does **not** find
  `modules/News/Config/Routes.php` (contradiction 3); migrations of Shield, Settings and kpapy must
  also run (contradiction 12); `make:module` stubs must already satisfy acceptance criterion 2
  (widget and task stubs, contradiction 31); a module named `Events` must not ship
  `Config/Events.php` (contradiction 4).

## 02 — Core kernel: catalogue, settings registry, enable/disable and demo data (`deploy/02-core-registry.md`)

- **Goal:** everything `modules:sync` registers from the manifests (steps 4–6), enable/disable with
  dependency rules, demo-data infrastructure and one cache-invalidation service.
- **Files:**
  - `modules/Core/Permissions/CatalogueBuilder.php` (crud ops − `except` + `extra`, `view` for
    pages), `PermissionCatalogue.php`;
  - `modules/Core/Settings/SettingsRegistry.php`, `SettingDefinition.php`, `SettingCaster.php`
    (typed casting, manifest defaults when no stored value);
  - `modules/Core/Registry/{WidgetRegistry,TaskRegistry,NotificationTypeRegistry,MenuLinkRegistry,CalendarRegistry,ApiRegistry}.php`
    (declarations only);
  - `modules/Core/Filters/ModuleEnabled.php` (404 for routes of disabled modules);
  - `modules/Core/Demo/DemoData.php`, `DemoDataManager.php`;
  - `modules/Core/Cache/CacheInvalidator.php` (permissions, menus, settings, widgets, assets);
  - `modules/Core/Commands/ModulesEnable.php`, `ModulesDisable.php`, `ModulesDemo.php`.
- **Tables:** `permissions` (module, resource, operation, name, description), `default_grants_applied`
  or a flag in `modules`, `demo_records` (module, table, primary key, created_at, file path),
  `group_permissions` storage for `defaultGrants` (enforcement in 07); Settings' own `settings` table.
- **Commands:** `modules:enable <Name>`, `modules:disable <Name>`, `modules:demo <Name>
  [--remove|--reload]`, `modules:sync --demo`, `modules:sync --prune`.
- **Tests:** catalogue generation per resource type, `--prune` asks first, default grants applied
  once, setting casting, disable → 404 / enable → 200, core modules cannot be disabled, dependants
  block disabling, demo load twice = no duplicates, remove keeps real rows, refusal in production.
- **Depends on:** 01.
- **Risks:** `setting('Modules.allowDemoInProduction')` resolves to CodeIgniter's own
  `Config\Modules` (contradiction 5); defaults from manifests are not seen by `setting()` unless the
  Core wraps it (contradiction 6); default grants should go through implied permissions
  (contradiction 21); Core permission names do not follow `<module>-<resource>` (contradiction 18).

## 03 — Themes and standard views (`deploy/03-themes.md`)

- **Goal:** theme system with the default Bootstrap 5.3 theme and the standard views/components.
- **Files:**
  - `modules/Core/Themes/ThemeManifest.php`, `ThemeRegistry.php`, `ViewLocator.php`
    (`theme_view()`: theme override → parent → module view → Core view), `Helpers/theme_helper.php`;
  - `themes/default/Theme.php`, `themes/default/Views/layouts/{backend,public,auth,blank,print}.php`,
    `Views/partials/`, `Views/components/`, `Assets/css/theme.css`, `Language/{ca,es,en}/Theme.php`;
  - `modules/Core/Views/standard/{page_header,crud_container,form,empty_state,tabs,card}.php`,
    `modules/Core/Views/errors/{403,404,500}.php`, `modules/Core/Cells/{Badge,Status,Avatar,Date,FileLink,UserLink,PaginationInfo}.php`;
  - `public/assets/vendor/bootstrap/…`, `public/assets/vendor/bootstrap-icons/…`,
    `public/assets/vendor/vendor.json` (name, version, licence, source, SHA-256 per file);
  - `modules/Core/Commands/MakeTheme.php` + templates.
- **Tables:** none (settings `Theme.backend`, `Theme.public`, theme options via Settings).
- **Commands:** `make:theme <Name> [--parent=default]`.
- **Tests:** view resolution order, child theme overriding one view and one colour, missing view
  falls back, `vendor.json` checksums.
- **Depends on:** 01, 02.
- **Risks:** theme option colours must reach the page without `style=""` (contradiction 25);
  `Themes\` autoload has the same discovery limits as modules (contradiction 3); child theme of the
  `Tasks` tutorial (contradiction 32).

## 04 — Front-end assets, JavaScript, CSP, dark mode, responsive and PWA (`deploy/04-frontend.md`)

- **Goal:** asset pipeline, core ES modules and a strict nonce-based CSP from the start.
- **Files:**
  - `modules/Core/Assets/AssetPublisher.php` (CodeIgniter `Publisher`), `AssetManifest.php`
    (hashes → `public/assets/manifest.json`), `Helpers/asset_helper.php` (`asset('mod::js/x.js')`),
    `ImportMap.php`;
  - `modules/Core/Commands/AssetsPublish.php` (`--watch`);
  - `modules/Core/Assets/js/app.js`, `components.js`, `http.js`, `ui/{confirm,toast,modal,sidebar,tabs-memory,theme-switch}.js`,
    `forms/{dirty-guard,date,editor,file-picker}.js` (editor/picker stubs), `theme-mode.js` (head);
  - `app/Config/ContentSecurityPolicy.php` (nonces, report-only switch),
    `modules/Core/Pwa/ManifestController.php`, `public/sw.js` (no caching);
  - `tests/csp/CspViewsCheck.php` or `bin/check-csp`, CI jobs in `.github/workflows/ci.yml`.
- **Tables:** none (settings: PWA on/off, theme mode default).
- **Commands:** `assets:publish [--watch]`; routes `manifest.webmanifest`, `sw.js`, a demo page
  with a `data-component`.
- **Tests:** asset manifest and import map, `asset()` versioning, CSP header on every response,
  no inline code in views (CI), published assets in sync, `vendor.json` checksums.
- **Depends on:** 03.
- **Risks:** kpcrudy/kpapy views contain nonce'd inline `<script>`/`<style>` (contradiction 24);
  Bootstrap components that need `style` (progress bars, dynamic widths) and third-party editors/
  calendars that inject styles (contradiction 25); cache busting of ES modules imported through the
  import map (contradiction 26); service worker scope and PWA requirements (contradiction 27);
  CSRF regeneration with several tabs/fetch calls.

## 05 — Authentication (Shield) and session hardening (`deploy/05-auth.md`)

- **Goal:** login, logout, password reset, magic link and hardened sessions.
- **Files:**
  - `app/Config/Auth.php`, `app/Config/AuthGroups.php` (groups `superadmin`, `admin`, `editor`,
    `user`), `app/Config/Session.php`, `app/Config/Cookie.php`;
  - `modules/Auth/Config/{Module,Routes}.php`, `modules/Auth/Controllers/{LoginController,ProfileController,SessionsController}.php`,
    `modules/Auth/Services/{SessionTracker,DeviceRecognizer,LoginThrottle,Captcha}.php`,
    `modules/Auth/Filters/{IdleLock,AbsoluteLifetime}.php`;
  - auth views in `themes/default/Views/overrides/Auth/…` using the `auth` layout;
  - `modules/Auth/Language/{ca,es,en}/Auth.php`, email templates.
- **Tables:** Shield's tables (migrated as shipped); `user_sessions` (user, session id hash, device,
  ip, last_seen, revoked_at); `known_devices` (user, hashed cookie, first_seen); `user_profiles`
  (name, locale, timezone, theme mode — see contradiction 37).
- **Commands:** routes `login`, `logout`, `login/magic-link`, `reset-password`, `backend/profile`,
  `backend/profile/sessions`.
- **Tests:** generic messages, per-IP and per-account throttling (429), captcha in every
  environment and single-use, session id regenerated, password change ends other sessions and
  revokes app tokens, idle lock and absolute lifetime, new-device email, superadmin assignment
  rules.
- **Depends on:** 01–04.
- **Risks:** "my sessions" and remote logout need a session store that can be enumerated (database
  handler or the tracking table checked on every request); remember-me versus absolute lifetime
  (contradiction 14); Shield sends magic-link and reset emails inline, while the spec says email is
  never inline (contradiction 15); `Auth.minPasswordLength` is not Shield's property name
  (contradiction 7); Shield's per-account throttling does not exist out of the box.

## 06 — Two-factor authentication and re-authentication (`deploy/06-auth-2fa.md`)

- **Goal:** 2FA by email or TOTP chosen per user, recovery codes, policy per group, sudo mode.
- **Files:**
  - `modules/Auth/Authentication/Actions/UserTwoFactorAction.php` (per-user dispatch to
    email or TOTP), `TotpAction.php`; `modules/Auth/Services/{TotpService,RecoveryCodes,TwoFactorPolicy}.php`;
  - `modules/Auth/Controllers/TwoFactorController.php` (setup with QR SVG, confirm, recovery codes);
  - `modules/Core/Filters/Reauth.php` (`reauth`), `BackendController::requireReauth()`,
    `modules/Auth/Controllers/ReauthController.php`;
  - `composer require spomky-labs/otphp chillerlan/php-qrcode` (pre-approved: contradiction 34).
- **Tables:** `user_totp` (user, encrypted secret, last_step, confirmed_at), `user_recovery_codes`
  (user, hash, used_at), `group_2fa_policy` or a setting per group.
- **Commands:** routes `backend/profile/2fa`, `auth/2fa/verify`, `auth/reauth`.
- **Tests:** login with email 2FA, TOTP and a recovery code; ±1 step drift accepted, reused code
  refused; forced setup for required groups; admin reset hook; reauth required and expiring after
  15 minutes; reauth asks 2FA when enabled.
- **Depends on:** 05.
- **Risks:** Shield allows a single global `login` action, so per-user choice needs a dispatching
  action (contradiction 13); QR must render on white in dark mode; the TOTP secret must be readable
  by kpapy's `TotpProvider` in session 10 (same storage, same last-step to refuse reuse across web
  and API).

## 07 — Central RBAC (`deploy/07-rbac.md`)

- **Goal:** atomic permissions enforced everywhere through one resolver, default deny.
- **Files:**
  - `modules/Core/Permissions/CrudPermissions.php` (implements `Kpcrudy\PermissionResolver` and
    `Kpapy\PermissionResolver`, last parameter `object`), `Authorization.php` (user-aware checks,
    superadmin bypass), `ShieldMatrixBuilder.php` (catalogue + grants → `AuthGroups` permissions and
    matrix, cached), `EscalationGuard.php`;
  - `modules/Core/Filters/{Can,Backend}.php` (`can:` any/all; `backend` chain), `Helpers/can_helper.php`;
  - `modules/Core/Commands/{PermissionsList,PermissionsCheck}.php`;
  - `tests/feature/Security/RoutesSecurityTest.php`.
- **Tables:** `group_permissions` (group, permission); Shield `auth_permissions_users` for
  individual permissions; `groups` (if groups become data, contradiction 38).
- **Commands:** `permissions:list`, `permissions:check`.
- **Tests:** resolver maps search/filters/bulk → list, superadmin bypass, undeclared operation
  throws in development, implied permissions on save, every escalation rule, a `backend/` route
  without `can:` fails the suite.
- **Depends on:** 02, 05, 06.
- **Risks:** Shield checks group permissions through `setting('AuthGroups.matrix')` and
  `addPermission()` throws for names missing from `AuthGroups.permissions`, so the generated
  catalogue must be pushed into those settings (contradiction 19); Shield has no superadmin bypass
  of its own; the resolver must work without a session (iCal feeds, CLI, tasks; contradiction 20);
  "my …" screens and the profile need a permission every user holds (contradiction 17).

## 08 — Back-office menus and the security baseline (`deploy/08-menus-security.md`)

- **Goal:** generated back-office menus and the rest of the security baseline.
- **Files:**
  - `modules/Core/Menus/{MenuBuilder,MenuEntry,Breadcrumbs}.php`, sidebar/top bar partials in
    `themes/default/Views/partials/`;
  - `app/Config/SecurityHeaders.php`, `modules/Core/Filters/SecurityHeaders.php`;
  - `modules/Core/Security/{RateLimiter,SafeRedirect}.php`, `Helpers/redirect_helper.php`
    (`safe_redirect()`);
  - `modules/Core/Http/OutboundClient.php`, `app/Config/Outbound.php`.
- **Tables:** none (`outbound` log goes to the activity log in 12; until then to the file log).
- **Commands:** none new.
- **Tests:** menu filtered by permission and enabled modules, parent hidden without visible
  children, active entry and breadcrumbs, cache per group set; headers on every response (HSTS
  only in production); open redirect refused; outbound refused for HTTP, non-allowed host,
  private/loopback IP after DNS and redirects, oversize response.
- **Depends on:** 02, 03, 07.
- **Risks:** CodeIgniter's `secureheaders` filter hard-codes `X-Frame-Options: SAMEORIGIN` and
  `Referrer-Policy: same-origin` (contradiction 28); DNS rebinding between check and connect (pin
  the resolved IP with cURL `CURLOPT_RESOLVE`); SMTP cannot go through an HTTPS-only client
  (contradiction 29).

## 09 — kpcrudy integration (`deploy/09-kpcrudy.md`)

- **Goal:** every CRUD is a kpcrudy `Crud` integrated with the theme, RBAC and activity hooks.
- **Files:**
  - `app/Config/Kpcrudy.php` (theme `bootstrap5`, `maskIds = true`, `maskKey` from `app.maskKey`,
    local Bootstrap URLs, `layout` = theme backend layout, `layoutSection = 'content'`,
    `showTitle = false`, `flashKeys` → toasts, `permissionResolver = CrudPermissions::class`,
    `denyByDefault = true`, upload paths);
  - `modules/Core/Crud/CrudFactory.php` (`make()`, `routes()`), `BackendController::crud()` /
    `renderCrud()`; `modules/Core/Assets/js/ui/kpcrudy-confirm.js` (listens to `kpcrudy:confirm`);
  - `env` (`app.maskKey =` with a comment), `phpunit.xml.dist` test key, boot check in
    `modules/Core/Config/Events.php` (refuse production boot without the key);
  - `MakeModule --crud=<table>` calling `kpcrudy:make --namespace "Modules\<Name>"`;
  - a sample module under `tests/_support/Modules/Sample/` (or `modules/Sample`, disabled).
- **Tables:** sample tables for the tests only.
- **Commands:** `make:module <Name> --crud=<table>`.
- **Tests:** acceptance criterion 4 on the sample CRUD: read-only user sees no write buttons, forged
  POSTs to add/edit/delete/custom operation answer 403, `hideFields()` field absent from list,
  details and export, `where()` limits rows, undeclared `addOperation()` throws in development.
- **Depends on:** 03, 04, 07.
- **Risks:** `kpcrudy:make` writes `Crud::routes()` (not `CrudFactory::routes()`) into the module's
  `Config/Routes.php` and extends `<ns>\Controllers\BaseController` (contradiction 9); kpcrudy
  templates carry inline nonce'd code (contradiction 24); uploads bypass the Storage service
  (contradiction 23); `setTranslations()` lists show an empty cell when the list locale has no
  translation (contradiction 40).

## 10 — kpapy integration and API authentication (`deploy/10-kpapy.md`)

- **Goal:** API core with shared permissions and ids, tokens, app login with 2FA or email code and
  the back-office docs page.
- **Files:**
  - `app/Config/Kpapy.php` (shared resolver, `maskKey`, `denyByDefault`, `corsOrigins = []`, rate
    limits, `twoFactorMethods = ['totp', 'email', 'emailcode']` without `web`,
    `twoFactorProviders`);
  - `modules/Core/Api/TwoFactor/{CoreTotpProvider,CoreEmailProvider,EmailCodeProvider}.php`
    (extend kpapy's providers: secret and last step from Auth, recovery codes, device/IP/time and a
    deny link);
  - `modules/Core/Config/Routes.php` (`api/v1` group with `kpapy`, `AuthRoutes::register()`,
    `DocsController::routes($routes, 'backend/api/docs', ['filter' => ['backend', 'can:api.docs']])`),
    `modules/Core/Api/{Me,ApiRouteRegistrar}.php`;
  - `modules/Auth/Controllers/TokensController.php` (personal tokens with reauth, scopes, expiry;
    admin list).
- **Tables:** Shield `auth_identities` (tokens), kpapy `kpapy_login_challenges` (`migrate -n Kpapy`,
  contradiction 12), `api_email_codes` if the Core provider keeps its own.
- **Commands:** `kpapy:openapi`, `kpapy:routes`, `kpapy:token` (library); routes
  `api/v1/auth/token`, `…/verify`, `…/refresh`, `api/v1/me`, `api/v1/me/tokens`,
  `backend/api/docs`, `backend/api/docs/openapi.json`, `api/v1/openapi.json` (when `Api.publicSpec`).
- **Tests:** token limited to `x.list` gets 403 on writes (also a superadmin's), login with TOTP,
  email 2FA, recovery code and email code for users without 2FA, wrong or reused code refused, same
  masked id in web and API, problem+json, 429 on limits, docs only with `api.docs`.
- **Depends on:** 06, 07, 09.
- **Risks:** `modules:sync` "registering" routes conflicts with discovery-only, DB-free routes
  (contradiction 8); `kpapy:make` writes routes into the module's `Routes.php` with a controller
  method, while the manifest declares a handler class (contradiction 8); the route-security test
  must accept `kpapy:login` (contradiction 30); `requireHttps` refuses plain-HTTP requests in
  production, also behind proxies without `proxyIPs`.

## 11 — Notifications and scheduler (`deploy/11-notifications-scheduler.md`)

- **Goal:** cross-module notifications and a single-cron scheduler.
- **Files:**
  - `modules/Core/Notifications/{Notifier,NotificationType,Channels/DatabaseChannel,Channels/EmailChannel}.php`,
    helpers `notify()`, `notifyGroup()`, `notifyPermission()`;
  - `modules/Core/Controllers/NotificationsController.php` (bell JSON, mark read, preferences),
    list CRUD with `where('user_id', me)`; `modules/Core/Assets/js/ui/bell.js`;
  - `modules/Core/Scheduler/{TaskInterface,Schedule,CronExpression,TaskRunner,TaskResult,Lock}.php`,
    `modules/Core/Commands/{ScheduleRun,ScheduleList}.php`, `PoorMansCron` filter;
  - `modules/Core/Tasks/SendQueuedEmails.php`; scheduled tasks page (kpcrudy read-only + "run now").
- **Tables:** `notifications`, `notification_preferences`, `mail_queue`, `scheduled_tasks`,
  `task_runs`.
- **Commands:** `schedule:run [--task=…] [--force]`, `schedule:list`; routes
  `backend/notifications`, `backend/schedule`.
- **Tests:** routing by user/group/permission, preferences and mandatory types, email queued and
  sent by the task, cron expressions and helpers, `withoutOverlapping`, failing task isolated and
  notified, disabled module's tasks skipped, 2FA-reset notification wired.
- **Depends on:** 02, 07, 09.
- **Risks:** "poor man's cron after the response" needs `fastcgi_finish_request()` (absent with
  `php spark serve`/mod_php); `notifyPermission()` must evaluate permissions for users who are not
  logged in (contradiction 20); locks on shared hosting (file locks vs DB locks).

## 12 — Activity log and Activity module (`deploy/12-activity.md`)

- **Goal:** append-only activity and security log, browsable with kpcrudy.
- **Files:**
  - `modules/Core/Activity/{ActivityLogger,SecretMasker}.php`, listeners in
    `modules/Core/Config/Events.php` (Shield login events, kpapy `kpapy.auth.*` events, reauth,
    403s, tokens);
  - `modules/Core/Tasks/PurgeActivityLog.php`;
  - `modules/Activity/Config/{Module,Routes}.php`, `modules/Activity/Controllers/Activity.php`
    (read-only CRUD, `callbackColumn` before/after table, "My activity"), language files.
- **Tables:** `activity_log` (user, module, resource, action, record id, summary, changes JSON, type,
  IP, user agent, created_at).
- **Commands:** routes `backend/activity`, `backend/activity/mine`.
- **Tests:** no edit/delete routes, masking by key pattern, line breaks escaped in file logs,
  retention task, `activity-log.network` hides IP/UA, every write of sessions 05–10 logged.
- **Depends on:** 05–11.
- **Risks:** "My activity for every user" needs a resource everyone holds (contradiction 17);
  volume (index on created_at, user_id; chunked purge).

## 13 — Storage service and upload rules (`deploy/13-storage.md`)

- **Goal:** safe file storage used by every module.
- **Files:**
  - `modules/Core/Storage/{Storage,Disk,UploadValidator,ImageReencoder,SvgSanitizer}.php`,
    `app/Config/Storage.php` (disks `public` → `public/uploads`, `private` → `writable/storage`);
  - `modules/Core/Controllers/PrivateFileController.php` (permission check, MIME, `nosniff`,
    `attachment` for non-image/PDF);
  - `docs/web-server.md` (Apache `.htaccess` and Nginx snippets);
  - `composer require league/flysystem` (pre-approved).
- **Tables:** `stored_files` minimal (disk, path, original name, mime, size, checksum) unless the
  Files `files` table is created here and extended in 22.
- **Commands:** route `files/private/(:segment)`.
- **Tests:** traversal refused, SVG/HTML refused, re-encoding strips EXIF, private file served only
  through the controller with the right headers, permissions `0750`/`0640`.
- **Depends on:** 07, 08.
- **Risks:** the folder ACL arrives in 22 (temporary permission rule); `public/uploads` is not in
  `.gitignore` and `writable/uploads` vs storage disks is ambiguous (contradiction 39); kpcrudy and
  kpapy upload fields write to their own paths (contradiction 23).

## 14 — Settings module and Settings › Security (`deploy/14-settings.md`)

- **Goal:** generated settings UI with per-module permissions and the security tab.
- **Files:**
  - `modules/Settings/Config/{Module,Routes}.php`, `modules/Settings/Controllers/Settings.php`,
    `modules/Settings/Forms/FormGenerator.php`, views per field type;
  - `modules/Core/Settings/SecuritySettings.php` (validation that refuses weakening values),
    CSP/headers/outbound merging of setting overrides;
  - actions: test email, clear caches, publish assets.
- **Tables:** none new (Settings' `settings` table).
- **Commands:** routes `backend/settings`, `backend/settings/<module>`, `backend/settings/security`.
- **Tests:** `settings.update` opens the UI, `settings.<module>` needed per tab, `settings.security`
  + reauth, `unsafe-inline` refused, an added CSP origin appears in the header, typed casting.
- **Depends on:** 02, 06, 08, 09.
- **Risks:** "publish assets" from the web makes the web user write executable JS into `public/`
  (contradiction 33); a module named `Security` or `Settings` produces clashing
  `settings.<module>` names (contradiction 18).

## 15 — Users module and permission matrix (`deploy/15-users.md`)

- **Goal:** user and group administration with the atomic permission matrix.
- **Files:**
  - `modules/Users/Config/{Module,Routes}.php`, `modules/Users/Controllers/{Users,Groups,PermissionMatrix}.php`,
    `modules/Users/Services/{Impersonation,UserIdentityHooks}.php`, impersonation banner partial;
  - `modules/Users/Widgets/UsersSummary.php` (or in 16).
- **Tables:** `groups` (name, label, reserved, 2FA policy) if groups are data (contradiction 38);
  `impersonations` (or session data + activity log).
- **Commands:** routes `backend/users`, `backend/groups`, `backend/permissions`.
- **Tests:** escalation (cannot edit own groups, grant only held permissions, reserved names, last
  superadmin), impersonation (superadmin only, never another superadmin, time limit, sensitive
  actions refused), matrix saves only atomic names and wildcards, implied permissions added.
- **Depends on:** 06, 07, 09, 11.
- **Risks:** kpcrudy over `users` with email in `auth_identities` (search/sort on email needs a
  join or view); Shield groups live in config (contradiction 38); regenerating the session of
  *another* user after a privilege change requires a per-user "session version".

## 16 — Dashboard and widgets (`deploy/16-dashboard.md`)

- **Goal:** widget framework, per-group configuration and per-user layout.
- **Files:**
  - `modules/Core/Widgets/{WidgetInterface,WidgetRegistry,WidgetRenderer}.php`;
  - `modules/Dashboard/Config/{Module,Routes}.php`, `modules/Dashboard/Controllers/{Dashboard,GroupLayout,WidgetData}.php`,
    `modules/Dashboard/Assets/js/dashboard.js` (drag and drop with touch);
  - Core widgets `modules/Core/Widgets/{ScheduledTasks,QuickLinks}.php`, `modules/Users/Widgets/UsersSummary.php`.
- **Tables:** `dashboard_group_widgets` (group, widget, enabled, order, size, options),
  `dashboard_user_layouts` (user, layout JSON).
- **Commands:** routes `backend/` (home), `backend/dashboard/widgets/(:segment)/data`,
  `backend/dashboard/groups`.
- **Tests:** widget hidden without permission or group enablement, async URL 403, failing widget
  renders an error card, user reorder and "restore group layout".
- **Depends on:** 04, 07, 15.
- **Risks:** acceptance criterion 6 also needs News notices (session 17); per-user cache
  invalidation when grants change.

## 17 — News module (reference module) (`deploy/17-news.md`)

- **Goal:** the reference module every other module copies; passes the whole module checklist.
- **Files:** `modules/News/Config/{Module,Routes,News}.php`, `Controllers/{Posts,Categories,MyPosts,Stats,PublicNews,Feed}.php`,
  `Api/Posts.php`, `Models/`, `Widgets/{Notices,LatestNews}.php`, `Tasks/ExpireNotices.php`,
  `Notifications/NewNotice.php`, `Menu/CategoryLink.php`, `Database/Seeds/{NewsSeeder,NewsDemoSeeder}.php`,
  `Language/{ca,es,en}/News.php`, `Views/`, `Assets/`, `CLAUDE.md` (stub until 32);
  `composer require ezyang/htmlpurifier` (pre-approved).
- **Tables:** `news_categories`, `news_category_translations`, `news`, `news_translations`,
  `news_groups` (junction, contradiction 36), `news_reads`.
- **Commands:** routes `backend/news/posts`, `backend/news/categories`, `backend/news/mine`,
  `backend/news/stats`, public `news`, `news/(:segment)`, `news/category/(:segment)`,
  `news/feed.xml`, API `api/v1/news`.
- **Tests:** acceptance 4 on News (read-only user, forged POSTs, `news-posts.author` hidden), own
  posts rule, visibility public/private/groups, notices on the dashboard per audience, read
  receipts, expiry task, migration up/down/up, API list/details with visibility.
- **Depends on:** 09–16.
- **Risks:** rich-text editor arrives in 20 (body is a plain textarea until then, contradiction 35);
  how ordinary users read a private news item (contradiction 42); `groups` vs `restricted`
  visibility naming (contradiction 41); Catalan-only demo data shows empty titles in es/en lists
  (contradiction 40).

## 18 — Installer and CLI completion (`deploy/18-installer.md`)

- **Goal:** a fresh clone installs with one command.
- **Files:** `modules/Core/Install/{Installer,EnvWriter,KeyGenerator}.php`,
  `modules/Core/Commands/{AppInstall,About}.php`, `modules/Core/Controllers/WebInstaller.php` +
  views, `docs/commands.md` (first version).
- **Tables:** none new (runs every migration; first superadmin).
- **Commands:** `app:install [--options] [--demo[=…]]`, `about`, cache-clear hook; route `install`
  (only while unconfigured).
- **Tests:** install on SQLite from scratch, `.env` written with `CI_ENVIRONMENT=production`,
  encryption key and `app.maskKey` generated, lock file written, web installer 404 when `.env` or
  lock exists or DB has tables.
- **Depends on:** 01–17.
- **Risks:** CodeIgniter already has `cache:clear`, and discovery keeps the first command with a
  given name, so a module cannot override it (contradiction 43); the web installer must write
  `.env` in the project root (contradiction 33); session 00 creates a local `.env`, which disables
  the web installer in development.

## 19 — Checkpoint: MVP review (`deploy/19-mvp-review.md`)

- **Goal:** make sessions 00–18 solid before the remaining modules.
- **Files:** fixes across the code; `docs/progress.md` (review table); `docs/plan.md` (updated);
  `CLAUDE.md` (conventions, after asking).
- **Tables:** none new.
- **Commands:** none new.
- **Tests:** acceptance criteria 1, 2, 4, 5, 6, 7 walked through; route-security, CSP, headers,
  rate-limit and reauth-coverage tests reviewed; gaps fixed with tests.
- **Depends on:** 00–18.
- **Risks:** criterion 2 requires `modules/Events2/CLAUDE.md` and `/check-module`, which only exist
  after session 32 (contradiction 31); refactors here ripple into every later module.

## 20 — Pages module (`deploy/20-pages.md`)

- **Goal:** content pages for the public site.
- **Files:** `modules/Pages/Config/{Module,Routes}.php`, `Controllers/{Pages,PublicPages,Sitemap}.php`,
  `Services/{PageTree,Revisions,PageResolver}.php`, `Menu/PageLink.php`, seeders, language files;
  rich-text editor vendored in `public/assets/vendor/<editor>/` with `vendor.json`,
  `modules/Core/Assets/js/forms/editor.js`; theme page templates.
- **Tables:** `pages`, `page_translations`, `page_groups`, `page_revisions`.
- **Commands:** routes `backend/pages`, public `(:any)` nested slugs (lowest priority),
  `sitemap.xml`.
- **Tests:** private pages need login and group, sitemap lists only public pages, noindex, slug
  unique per parent and language, revision restore, scheduled publication, HTMLPurifier.
- **Depends on:** 04, 09, 13, 16 (home), 17 (patterns).
- **Risks:** a catch-all slug route shadows routes of modules loaded later (use route priority,
  reserved slugs); editors and sanitised content with `style` attributes vs CSP
  (contradiction 25); licence of the editor (MIT/BSD only; e.g. TinyMCE 7 is GPL); tree indentation
  is not a kpcrudy feature (library request).

## 21 — Menus module: public menus and back-office customisation (`deploy/21-menus.md`)

- **Goal:** public menu editor and the customisation layer of the back-office menu.
- **Files:** `modules/Menus/Config/{Module,Routes}.php`, `Controllers/{PublicMenus,BackendMenu}.php`,
  `Services/{MenuRenderer,CustomisationApplier,UrlValidator}.php`, `Assets/js/tree-editor.js`,
  helper `menu('primary')`.
- **Tables:** `menus`, `menu_items`, `menu_item_translations`, `menu_item_groups`,
  `backend_menu_customisations` (key, group scope, parent, zone, order, label, icon, hidden),
  `backend_menu_links`.
- **Commands:** routes `backend/menus/public`, `backend/menus/backend`.
- **Tests:** relocated entry still filtered by permission, `javascript:`/`data:`/`//` refused,
  cycles impossible, orphaned customisations listed, reset, per-group customisation precedence,
  cache invalidation.
- **Depends on:** 08, 20, 02.
- **Risks:** stable keys for generated entries (`<module>.<resource>` vs route name); keyboard-
  accessible tree editor without a library.

## 22 — Files module: file manager and folder ACL (`deploy/22-files-manager.md`)

- **Goal:** file manager over the Storage service with inherited folder permissions.
- **Files:** `modules/Files/Config/{Module,Routes}.php`, `Controllers/{FileManager,FileApi}.php`,
  `Services/{FolderAcl,QuotaService,ThumbnailService,Trash}.php`, `Policies/FileAccessPolicy.php`
  (interface + registry), `Assets/js/file-manager.js`.
- **Tables:** `files` (disk, path, original name, mime, size, dimensions, checksum, uploaded_by,
  visibility, alt, tags, deleted_at), `folders` (disk, path, owner, quota), `folder_permissions`
  (folder, subject type/id, rights).
- **Commands:** routes `backend/files`, `backend/files/api/*` (JSON).
- **Tests:** ACL inheritance and override, subjects (group, user, all logged-in), `files.all`,
  traversal, quotas, trash/restore, personal space per group setting.
- **Depends on:** 13, 07, 04.
- **Risks:** upload progress bars without `style=""` (contradiction 25); keeping `files` rows and
  disk contents consistent on failures.

## 23 — Files module: file picker, upload tokens and avatars (`deploy/23-files-picker.md`)

- **Goal:** reusable file picker for every module field.
- **Files:** `modules/Core/Assets/js/forms/file-picker.js` (complete), `modules/Files/Services/UploadToken.php`
  (signed: folder, disk, types, size, expiry), `modules/Files/Controllers/PickerController.php`,
  avatar handling in `modules/Auth/Controllers/ProfileController.php`, News cover switched to the
  picker.
- **Tables:** none new (uses `files`, `folders`, `folder_permissions`; avatar reference in
  `user_profiles`).
- **Commands:** routes `files/picker/*`, `files/avatar/(:segment)`.
- **Tests:** tampered token (folder/type) refused, avatar needs no `files.*`, avatars not served to
  guests, versioned URL and cache headers.
- **Depends on:** 22, 05, 17.
- **Risks:** placeholder names `{id}` vs `{user_id}` (contradiction 44); kpcrudy has no picker field
  type (use `callbackField`).

## 24 — Events module: calendar, administration and audiences (`deploy/24-events-core.md`)

- **Goal:** event calendar with restricted audiences.
- **Files:** `modules/Events/Config/{Module,Routes,Calendar}.php` (not `Config/Events.php`,
  contradiction 4), `Controllers/{Events,Categories,CalendarView,CalendarFeed}.php`,
  `Services/{Recurrence,AudienceResolver}.php`, `Widgets/UpcomingEvents.php`, `Tasks/SendReminders.php`,
  `Menu/CalendarLink.php`, seeders; calendar library vendored (MIT) + `Assets/js/calendar.js`.
- **Tables:** `event_categories`, `event_category_translations`, `events`, `event_translations`,
  `event_audience`, `event_reminders`, `event_exceptions` (or a JSON column).
- **Commands:** routes `backend/events`, `backend/events/categories`, `backend/calendar`, public
  `calendar`, JSON feed `calendar/feed`.
- **Tests:** restricted audience visible only to its groups/users (also for admins in the calendar),
  `events-events.all` in the list, editor cannot target forbidden groups, recurrence expansion.
- **Depends on:** 04, 09, 11, 15, 16.
- **Risks:** CSV import of translated fields (contradiction 40); a searchable AJAX users field is not
  a kpcrudy feature (library request); calendar library CSP compatibility.

## 25 — Events module: iCal feeds and calendar API (`deploy/25-events-ical-api.md`)

- **Goal:** subscribable feeds and push/pull calendar API.
- **Files:** `modules/Events/Ical/{IcalWriter,Folding,VTimezone}.php`, `Controllers/IcalFeed.php`,
  `Services/{CalendarService,ProviderRegistry}.php`, `modules/Core/Calendar/{CalendarEvent,CalendarProvider,EventAccessPolicy,CalendarSource}.php`,
  `Commands/CalendarResync.php`, profile UI; News push in `modules/News/Services/NoticeCalendar.php`;
  `modules/Users/Calendar/BirthdaysProvider.php`.
- **Tables:** `ical_tokens` (user, hash, categories/calendars filter, last_access, revoked_at);
  `events` already has source columns; birthday column (contradiction 37).
- **Commands:** `calendar:resync <module>`; routes `ical/public/(:segment).ics`,
  `ical/user/(:segment).ics`, `events/(:segment)/export.ics`.
- **Tests:** RFC 5545 validation, regenerated token invalidates the old URL, permission change
  reflected, `304`, publish/unpublish idempotent, locked published events, providers filtered by
  user, disabled module events hidden.
- **Depends on:** 24, 17, 15, 07.
- **Risks:** News calendar permission `news-posts.list` hides notices from ordinary readers
  (contradiction 41); feeds evaluate permissions without a session (contradiction 20).

## 26 — Core API endpoints (`deploy/26-core-api.md`)

- **Goal:** the complete core API.
- **Files:** `modules/News/Api/Posts.php` (+ `read` operation, notices), `modules/Events/Api/Events.php`,
  `modules/Core/Api/Notifications.php`, `modules/News/Api/PublicNews.php`,
  `modules/Events/Api/PublicEvents.php`; API catalogue check in `CrudPermissions`/`permissions:check`.
- **Tables:** none new.
- **Commands:** routes `api/v1/news`, `api/v1/news/{id}/read`, `api/v1/events`,
  `api/v1/notifications`, `api/v1/public/news`, `api/v1/public/events`.
- **Tests:** each endpoint with visibility rules, scopes (also superadmin), public rate limits,
  undeclared API operation fails in development, acceptance 10 (API part).
- **Depends on:** 10, 17, 24, 25, 11.
- **Risks:** resource names shared with CRUDs (`news-posts`) but different routes; "OpenAPI
  generation on `modules:sync`" writes a file the web can serve (keep it in `writable/`).

## 27 — Backup module (`deploy/27-backup.md`)

- **Goal:** encrypted backups and restores from the console and the UI.
- **Files:** `modules/Backup/Config/{Module,Routes,Backup}.php`, `Services/{BackupService,DatabaseDumper,ArchiveCrypto,RestoreService,Retention}.php`,
  `Commands/{BackupCreate,BackupList,BackupRestore,BackupVerify,BackupPrune}.php`,
  `Controllers/Backups.php`, `Tasks/{ScheduledBackup,PruneBackups}.php`, `Widgets/Backups.php`,
  `Notifications/BackupResult.php`.
- **Tables:** `backups` (file, size, type, result, created_by, demo flag), `background_jobs` (for
  UI restores with progress).
- **Commands:** `backup:create [--only=db|files]`, `backup:list`, `backup:restore <file>`,
  `backup:verify <file>`, `backup:prune`; routes `backend/backups`.
- **Tests:** create/verify/restore on SQLite, wrong password and tampered file refused before any
  write, newer backup refused, zip-slip/zip-bomb, password ≥ 12, files `0640`.
- **Depends on:** 13, 11, 06, 16, 08.
- **Risks:** AES-256-GCM in PHP is one-shot (memory for large archives; contradiction 46);
  restoring `.env` onto another server (contradiction 45); `--password` on the command line
  (contradiction 47); `mysqldump` output is not portable to SQLite.

## 28 — Updates module and app:update (`deploy/28-updates.md`)

- **Goal:** update notices in the UI and verified updates from the console.
- **Files:** `modules/Updates/Config/{Module,Routes,Updates}.php`, `Services/{ReleaseChecker,VersionComparator,SignatureVerifier}.php`,
  `Commands/{UpdatesCheck,AppUpdate}.php`, `Tasks/CheckUpdates.php`, `Widgets/SystemVersion.php`,
  `Notifications/NewVersion.php`, `Controllers/Updates.php`.
- **Tables:** none (results cached; optional `update_checks`).
- **Commands:** `updates:check`, `app:update [--dry-run]`; route `backend/updates`.
- **Tests:** version comparison, unverified/unsigned release refused, network error does not break
  the dashboard, dry-run plan.
- **Depends on:** 08, 27, 11, 16.
- **Risks:** no release-signing process is defined for the skeleton itself (contradiction 48);
  `git`/`composer` make outbound calls outside `OutboundClient` (contradiction 29).

## 29 — Modules screen, ZIP installs and uninstall (`deploy/29-module-packages.md`)

- **Goal:** manage modules and themes from the UI and the console.
- **Files:** `modules/Core/Controllers/{Modules,Themes}.php`, `modules/Core/Packages/{PackageValidator,PackageInstaller,StagedPackages,Uninstaller,SignatureCheck}.php`,
  `modules/Core/Commands/{ModulesInstall,ModulesUninstall,ThemesInstall,ThemesUninstall}.php`.
- **Tables:** `module_packages` (staged: file, manifest, status, approved_by, expires_at, log),
  `pending_uninstalls`.
- **Commands:** `modules:install <zip|url> [--enable] [--force] [--demo]`, `modules:install --pending`,
  `modules:uninstall <Name> [--keep-data]`, `themes:install`, `themes:uninstall`; routes
  `backend/modules`, `backend/modules/themes`.
- **Tests:** acceptance 3: enable/disable effects, ZIP install/upgrade/uninstall, staged install,
  refused packages (zip-slip, symlink, invalid manifest, zip bomb, bad extension, failed `php -l`).
- **Depends on:** 02, 27, 08, 06, 11.
- **Risks:** installing a module needs its namespace registered (contradiction 3); signature keys
  "in `Config\Modules`" collide with CodeIgniter's class (contradiction 5); enabling from the UI
  publishes assets with the web user (contradiction 33).

## 30 — security:check and the Security status page (`deploy/30-security-check.md`)

- **Goal:** production-readiness checklist in the console and the back office.
- **Files:** `modules/Core/Security/Checks/{ConfigurationCheck,SecretsCheck,InstallationCheck,AccountsCheck,DependenciesCheck,OutboundCheck,ProbeCheck}.php`,
  `modules/Core/Commands/SecurityCheck.php`, `modules/Core/Controllers/SecurityStatus.php`,
  `modules/Core/Widgets/SecurityStatus.php`, `modules/Core/Tasks/SecurityCheckDaily.php`.
- **Tables:** none (cached results).
- **Commands:** `security:check [--probe=<url>] [--json] [--only=<group>]`; route
  `backend/security`.
- **Tests:** fails (non-zero exit) on a development configuration, passes on a hardened one,
  probe checks with a fake site, page needs `security.check`, probe needs reauth.
- **Depends on:** all previous sessions.
- **Risks:** `composer audit` has no offline mode (contradiction 49); the probe targets the site
  itself, which `OutboundClient` refuses (loopback/HTTP; contradiction 50); CodeIgniter's own log
  files are `0644` by default (contradiction 51).

## 31 — Internationalisation pass (`deploy/31-i18n.md`)

- **Goal:** complete and consistent `ca`/`es`/`en` everywhere.
- **Files:** `modules/Core/Language/Language.php` (fallback requested → base → `es` → `en`),
  service override in `app/Config/Services.php`, `modules/Core/I18n/LocaleNegotiator.php`,
  `modules/Core/Language/ca/Validation.php` (and other system files missing in Catalan),
  `tests/unit/I18n/LanguageKeysTest.php`.
- **Tables:** none.
- **Commands:** none new (maybe `lang:check`).
- **Tests:** identical keys in the three languages for every module, fallback chain, negotiation
  profile → browser → default, restriction setting.
- **Depends on:** all modules.
- **Risks:** `codeigniter4/translations` has no `ca` folder at all (every system file needs a
  Catalan copy); kpapy's `localeFallbacks` must follow the same chain for content.

## 32 — Tasks tutorial module and AI tooling (`deploy/32-tasks-module.md`)

- **Goal:** the `Tasks` module with the finished code of every tutorial, and tooling for new modules.
- **Files:** `modules/Tasks/…` (projects, tasks, assignees, labels, board view with
  `Assets/js/board.js`, widgets `MyPendingTasks`/`TaskActivityChart`, public page + menu link,
  notification, calendar push/pull, `Tasks/OverdueReminder.php`, setting, attachments with a
  `FileAccessPolicy`, API resources, public form with captcha), `modules/Tasks/CLAUDE.md`;
  `modules/Core/Commands/Generators/Views/module/CLAUDE.md.tpl`, `--standalone` templates;
  `.claude/commands/new-module.md`, `.claude/commands/check-module.md`.
- **Tables:** `task_projects`, `tasks`, `task_assignees`, `task_attachments`, `task_requests`
  (and translations for the translated field).
- **Commands:** `make:module <Name> --standalone`; `/new-module`, `/check-module`.
- **Tests:** module checklist complete, permissions with forged POSTs, migration up/down/up, API
  scopes, widget visibility, reminder task; tests run in CI with the module enabled.
- **Depends on:** 01–31.
- **Risks:** the child-theme override of tutorial 9 cannot live inside the module (contradiction
  32); names (`Tasks` module vs `Tasks/` folder of scheduled tasks).

## 33 — Documentation (`deploy/33-docs.md`)

- **Goal:** every document listed in the specification, matching the code.
- **Files:** `README.md`, `CHANGELOG.md`, `LICENSE`, `docs/{architecture,creating-a-module,installing-modules,permissions,themes,frontend,scheduler,notifications,widgets,api,commands,calendar,files,backup-restore,updates,security,outbound-requests,dependencies}.md`,
  `docs/user-guide.md` + `docs/ca/user-guide.md` + `docs/es/user-guide.md`.
- **Tables:** none.
- **Commands:** none (a docs link checker script optional).
- **Tests:** every command/option/URL mentioned exists (script comparing `docs/commands.md` with
  `php spark list`), links resolve.
- **Depends on:** 01–32.
- **Risks:** drift between docs and code; user guide in three languages is large.

## 34 — Tutorials (`deploy/34-tutorials.md`)

- **Goal:** 14 step-by-step tutorials built on `Tasks`.
- **Files:** `docs/tutorials/README.md`, `docs/tutorials/01-…md` … `14-…md`.
- **Tables:** none.
- **Commands:** none.
- **Tests:** each tutorial ends with its tests (in `modules/Tasks/Tests` or `tests/feature/Tasks`);
  tutorials 1 and 3 followed from a fresh install.
- **Depends on:** 32, 33.
- **Risks:** tutorials 1 and 11 start with `make:module`/`--api` on a module that already exists;
  steps must work in a scratch copy (`Tasks2`).

## 35 — Quality: end-to-end, accessibility and CI (`deploy/35-quality.md`)

- **Goal:** the full quality bar.
- **Files:** `tests/e2e/*.spec.mjs` (Playwright: phone/tablet/desktop × light/dark, overflow,
  axe), `package.json`, `.github/workflows/ci.yml` (all jobs), `.github/dependabot.yml`,
  `.gitleaks.toml`.
- **Tables:** none.
- **Commands:** `npm run e2e`.
- **Tests:** the spec's missing unit/feature/security tests, e2e, accessibility.
- **Depends on:** all.
- **Risks:** CI time (matrix × MariaDB × Playwright); flaky screenshots.

## 36 — Final acceptance and release (`deploy/36-release.md`)

- **Goal:** verify acceptance criteria 1–13 and publish version 1.0.0.
- **Files:** `CHANGELOG.md` (1.0.0), `modules/Core/Config/Version.php`, `docs/progress.md`
  (final report).
- **Tables:** none.
- **Commands:** `security:check --probe` on a production-like setup.
- **Tests:** acceptance 1–13 on SQLite and MariaDB; pitfalls list checked.
- **Depends on:** 00–35.
- **Risks:** merging itself into `main` (the shared *Finish* text still says "the next session
  merges it", contradiction 2); release signing for Updates (contradiction 48).

---

## Contradictions and open questions in the specification

Each item gives where it appears, the issue, and the proposed solution. Items marked
**(decide)** change a public convention or security and need the owner's decision.

1. **Branch names and pushing to `main`** — `deploy/*.md` "Before starting" steps 2 and 4,
   `deploy/next.md`, `deploy/README.md`. Sessions create `session-NN-<slug>` and push `main`, but
   cloud environments assign their own branch (this one is `claude/modest-goldberg-jjs8kp`) and
   often allow pushing only to it. `next.md` finds unfinished sessions by `session-NN-*` branches.
   *Proposal:* identify sessions by the pull request title "Session NN: …" and by
   `docs/progress.md` (record the branch name there); when `main` cannot be pushed, stop and ask the
   owner to merge the pull request (already allowed by the files), and never force-push.
2. **Session 36 finish text** — `deploy/36-release.md`. Its Scope says it merges itself into
   `main`, but the shared *Finish* section says "The next session merges it". *Proposal:* follow
   the Scope (merge after the pull request), and treat the Finish line as boilerplate.
3. **Module discovery with a single `Modules\` namespace** — spec "Directory layout"
   (Autoloading/Discovery), `CLAUDE.md`. CodeIgniter 4.7's `FileLocator::search()` only looks at
   `<namespace root>/Config/Routes.php` (also `Events`, `Registrar`, `Commands/`), so
   `Modules\` → `modules/` finds nothing inside `modules/News/`. Registering every module statically
   in `app/Config/Autoload.php` breaks acceptance 2 ("without touching any core file").
   *Proposal:* keep `Modules\`/`Themes\` for class autoloading and add one PSR-4 entry per module
   (`Modules\News` → `modules/News`) from a **generated static file**
   (`app/Config/ModuleNamespaces.php`) written by `make:module`, `modules:sync` and
   `modules:install`, and loaded by `Autoload.php`; no scanning at runtime. **(decide)**
4. **`modules/Events/Config/Events.php`** — spec "Configuration and settings" ("every module may
   ship `Config/<Name>.php`") and the `Events` module. CodeIgniter includes every discovered
   `Config/Events.php` as an event-listener file, so a config class with that name is included as
   a script (possible "cannot redeclare class"). The same pattern makes `setting('Events.x')`
   resolve to `Config\Events`. *Proposal:* the Events module uses `Config/Calendar.php` and the
   settings namespace `Calendar`/`Events` without a config class of that name; the manifest
   validator refuses module config files named `Events`, `Routes`, `Registrar`, `Filters`,
   `Modules`, `Services`.
5. **`Config\Modules` and `Modules.*` settings** — spec "Package validation" (publisher keys "listed
   in `Config\Modules`"), "Demo data" (`Modules.allowDemoInProduction`). `app/Config/Modules.php`
   is CodeIgniter's discovery configuration. *Proposal:* put package keys and module options in
   `Modules\Core\Config\Packages` (`Config\Packages` override in `app/Config`), and name the setting
   `Packages.allowDemoInProduction` or `Core.allowDemoInProduction`. **(decide)**
6. **`setting()` and manifest defaults** — spec "Configuration and settings". `codeigniter4/settings`
   falls back to the property of a config class with that short name; modules declare defaults in
   the manifest, not in a config class, so `setting('News.perPage')` returns `null` before a value is
   saved. *Proposal:* the Core registers manifest defaults (a `ModuleSettings` handler or a Core
   `settings()` wrapper used everywhere), and a test checks every declared key returns its default.
7. **`Auth.minPasswordLength` vs Shield** — spec "Authentication", session 05. Shield reads
   `Config\Auth::$minimumPasswordLength` (default 8); a new key `minPasswordLength` is ignored by
   Shield's validators. Shield 1.4.1 reads `config('Auth')->minimumPasswordLength` directly (not
   through `setting()`), so even a setting with Shield's name is not applied by itself.
   *Proposal:* keep the setting (name it `Auth.minimumPasswordLength` to match Shield) and copy its
   value into the shared `Config\Auth` instance early in the request (Core event `pre_system` /
   Registrar), with a test that a password shorter than the setting is refused. **(decide:
   setting name)**
8. **API routes "registered by `modules:sync`"** — spec "JSON API" (modules expose resources),
   manifest `$api` (`'handler' => Api\Posts::class`), session 10; versus "Discovery is the only
   loading mechanism", "never require a scan just to make a route reachable" and
   "`Routes.php` never queries the database". kpapy's `kpapy:make` writes
   `Resource::routes(...)` into the module's own `Config/Routes.php` with a *controller method*.
   *Proposal:* routes stay in each module's `Config/Routes.php` (written by `kpapy:make` /
   `make:module --api`, path `api/v1/<path>`); `$api` is metadata validated by `modules:sync` and
   `permissions:check` (routes and manifest must match); disabled modules answer 404 through the
   module filter. The `handler` key names the controller (`Api\Posts::index`).
9. **`kpcrudy:make` output vs Core conventions** — spec "Common module base" (`make:module --crud`
   reuses `kpcrudy:make`), session 09. `kpcrudy:make` writes `\Kpcrudy\Crud::routes(...)` with an
   optional `--filter`, extends `<ns>\Controllers\BaseController`, and instantiates `new Crud()`;
   the Core wants `CrudFactory::routes()` (backend + `can:` + `as`) and `CrudFactory::make()`.
   *Proposal:* `make:module --crud` calls `kpcrudy:make` with `--namespace`, `--extends
   "Modules\Core\Controllers\BackendController"`, `--as <module>-<resource>` and
   `--filter backend`, then rewrites the generated route line to `CrudFactory::routes()` and the
   constructor to `CrudFactory::make()`; `permissions:check` catches anything left.
10. **Order of work** — spec last paragraph ("Core kernel → assets and themes → Auth → RBAC → …
    → installer → tests/CI → docs → Tasks") and `CLAUDE.md` ("following the order in the
    specification") vs the session order (kpcrudy in 09 before notifications; News in 17 before
    Pages/Files; installer in 18; `security:check` in 30). *Proposal:* the session files are the
    order; update the `CLAUDE.md` workflow line to say so.
11. **Bootstrap instructions already applied** — `deploy/00-bootstrap.md` §3 and `deploy/README.md`
    talk about `prompt_new.md`, `claude_suggest.md` and a repository `SIENSIS/ci4app`; this
    repository already has `docs/specification.md` and `CLAUDE.md`. *Proposal:* treat §3 as done;
    no action besides noting it in `docs/progress.md`.
12. **Migrations of third-party packages** — spec "Database schema" ("Shield's and Settings' own
    migrations are run as they ship"). kpapy also ships a migration (`kpapy_login_challenges`, run
    with `php spark migrate -n Kpapy`) needed for the app login. *Proposal:* `modules:sync` runs
    the namespaces `CodeIgniter\Shield`, `CodeIgniter\Settings` and `Kpapy` before the modules,
    and the uninstall/rollback logic never touches them.
13. **Per-user 2FA with Shield** — spec "Authentication" ("Email (Shield's `Email2FA` action)" chosen
    by each user). Shield has one global `Config\Auth::$actions['login']`; `Email2FA` applies to
    every user. kpapy's `EmailProvider::isEnabledFor()` also checks that global action.
    *Proposal:* a Core action `UserTwoFactorAction` set as the `login` action that dispatches to
    Shield's `Email2FA` logic or the TOTP action per user (or none); kpapy providers extended to read
    the user's method.
14. **Remember-me vs absolute session lifetime** — spec "Session handling". With remember-me a user
    is silently logged in again after the absolute lifetime. *Proposal:* the absolute lifetime ends
    the session; remember-me re-creates it but only up to its own (setting) lifetime, and sensitive
    actions still require reauth; or disable remember-me when the absolute lifetime is set.
    **(decide)**
15. **"Email never inline"** — spec "Notifications" (email channel queued/sent by the scheduler) vs
    magic links, password resets, 2FA codes, API email codes and new-device notices, which must be
    sent immediately (Shield sends them inline). *Proposal:* the rule applies to the notification
    email channel only; transactional security emails are sent inline with a short timeout and
    logged.
16. **Shield wildcards are now richer** — spec "RBAC" ("Shield only expands the wildcard in the
    first part"). Shield 1.4.1's `PermissionMatcher` also matches prefix and multi-segment
    wildcards. The `<module>-<resource>.<operation>` naming still works. *Proposal:* keep the
    naming (public convention), fix the explanation in `docs/permissions.md`, and test that
    `news-posts.*` does not match `news-postsx.edit`.
17. **"My …" screens behind `can:`** — spec "Activity" ("My activity for every user, the same CRUD
    with `where`"), "Notifications" (list page with `where(user_id)`), News "my posts", profile,
    dashboard home, "my sessions". Every `backend/` route needs a `can:` filter and CRUD routes get
    `can:<resource>.list`; the full-log resource `activity-log.list` must not be given to everyone.
    *Proposal:* separate resources (`activity-mine`, `core-notifications`, `auth-profile`,
    `dashboard`) whose permissions are granted to every group by `defaultGrants` (or a reserved
    "all authenticated" permission that the `can` filter accepts explicitly). **(decide)**
18. **Permission names outside `<module>-<resource>`** — spec uses `modules.*`, `security.check`,
    `api.docs`, `api.tokens`, `settings.update`, `settings.<module>`, `settings.security`,
    `files.*`, `backup.*`, `updates.*` (Core resources named without the module; single-resource
    modules). `settings.<module>` also clashes with `settings.security`/`settings.update` for
    modules named `Security` or `Update`. *Proposal:* the manifest gets an optional resource
    `'name'` that overrides the generated prefix (only Core and single-resource modules), the
    validator rejects duplicates in the catalogue, and module names `Security`/`Update` are
    reserved. **(decide)**
19. **Building Shield's `AuthGroups` from tables** — spec "RBAC" ("Build Shield's
    `AuthGroups::$permissions` and `$matrix` from the catalogue and that table"). Shield reads them
    with `setting('AuthGroups.matrix')`/`setting('AuthGroups.permissions')`, and `addPermission()`
    throws for unknown names. *Proposal:* after each sync/grant change the Core writes the computed
    arrays with `setting()->set('AuthGroups.…')` (Settings caches them); `group_permissions` stays
    the source of truth and a test checks both agree.
20. **The resolver needs a user, not the session** — spec "RBAC" (`auth()->user()->can()`), used
    by iCal feeds (token, no session), `notifyPermission()`, the scheduler and CLI. *Proposal:*
    `Authorization::for(User $user)->can()` in the Core; `CrudPermissions` uses the current user
    from kpapy's `Context` (API) or the session (web), and feeds/tasks pass the user explicitly.
21. **Default grants vs implied permissions** — spec manifest example: editor gets
    `news-posts.edit` but not `news-posts.show`, while "Implied permissions" says grants are
    completed with `Crud::impliedPermissions()`. *Proposal:* apply implied permissions to
    `defaultGrants` too, and make the validator warn about incomplete sets.
22. **`users_permissions`** — spec "RBAC" names Shield's table `users_permissions`; Shield's is
    `auth_permissions_users`. *Proposal:* use Shield's API (`addPermission()`) and fix the name in
    the docs.
23. **Uploads in kpcrudy/kpapy bypass the Storage service** — spec "CRUDs with kpcrudy"
    ("upload paths from the Storage service"), "Storage" (MIME check, re-encoding, SVG refused,
    private disk, `files` metadata). kpcrudy 1.0 `upload`/`image` fields write to a filesystem path
    (`uploadPath`, default `public/uploads`, the same folder as the public disk) with random names
    and extension checks only; kpapy writes to `writable/uploads/kpapy`. *Proposal:* modules never use
    the libraries' upload field types; they use the file-picker field (`callbackField`) backed by
    the Storage service; set both libraries' `uploadPath` to a non-public, unused folder, and record
    the gap in `docs/library-requests.md`.
24. **Inline `<script>`/`<style>` in library views** — spec "Front-end assets" ("No JavaScript inside
    the HTML (no inline `<script>` code)") and the CI CSP check. kpcrudy's `bootstrap5` assets
    partial prints a nonce'd inline `<script>` and `<style>` in every CRUD; kpapy's docs page has a
    nonce'd `<style>`. They comply with the CSP (nonces) but not with the "no inline code" rule.
    Overriding the templates would copy library code (forbidden). *Proposal:* accept nonce'd blocks
    rendered by the two libraries as the only exception, scope the CI check to project views
    (`app/`, `modules/`, `themes/`), and file a library request for an external-asset option.
25. **`style=""` ban vs real components and content** — spec "Front-end assets" and CSP
    (`style-src 'self' 'nonce-…'`). Bootstrap progress bars and dynamic widths use `style`
    attributes; theme option colours need a place to be printed; rich-text editors and calendar
    libraries may inject `<style>` elements or style attributes; HTMLPurifier-sanitised page bodies
    with `style` attributes are blocked by the CSP. *Proposal:* set widths via JS (CSSOM is allowed)
    or utility classes; serve theme variables as a generated CSS file (or one nonce'd `<style>` in
    the layout, explicitly allowed); pick an editor/calendar that works without `unsafe-inline`
    (test it under the enforced CSP); configure HTMLPurifier to drop `style` and map alignment to
    classes.
26. **Cache busting of ES modules** — spec "Front-end assets" (`asset()` with a hash, import map
    prefixes to folders). Files imported through a folder prefix (`core/ui/confirm.js`) or a relative
    path carry no version, so browsers keep old copies. *Proposal:* the import map lists every
    published JS file with its hashed URL (generated from the asset manifest), or publish into a
    versioned folder; test that the map changes when a file changes.
27. **PWA details** — spec "Minimal PWA". A service worker controls only its folder, so it must be
    served from `/sw.js` (or with `Service-Worker-Allowed`); icons "from the site logo" need PNG
    192/512 generated with GD (an SVG logo is refused by the upload rules). *Proposal:* route
    `/sw.js` and `/manifest.webmanifest` from the Core, generate icons on logo change.
28. **CodeIgniter's `secureheaders` filter** — spec "Security baseline" ("`secureheaders` filter
    plus … `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`"). CI 4.7's
    filter hard-codes `X-Frame-Options: SAMEORIGIN` and `Referrer-Policy: same-origin`.
    *Proposal:* replace it with the Core `SecurityHeaders` filter driven by
    `Config\SecurityHeaders` (do not stack both).
29. **Outbound traffic that is not HTTPS-through-`OutboundClient`** — spec "Outbound requests"
    (email goes through the `OutboundClient`, HTTPS only), "Updates" (`git pull`, `composer
    install`). SMTP is not HTTP, and git/composer use their own clients. *Proposal:* the allow-list
    applies to SMTP hosts through a check in the mail service; `git`/`composer` are console-only
    and documented as exceptions in `docs/outbound-requests.md`.
30. **Route-security test for the API** — spec "RBAC" (every `api/v1/` route has `kpapy` or is
    `kpapy:public`, read-only). kpapy's own login routes use `kpapy:login` and are POST;
    `OPTIONS` preflight routes exist. *Proposal:* the test accepts `kpapy`, `kpapy:optional`,
    `kpapy:hmac`, `kpapy:login` (only for `auth/token*`) and `kpapy:public` (GET only).
31. **Acceptance criterion 2 before session 32** — session 19 checks criterion 2, which needs
    `modules/Events2/CLAUDE.md`, `/check-module` (session 32) and a module that already has "a widget
    and a task" (session 01's `make:module` only lists manifest, routes, language, controller,
    views, assets, migration stub and a test). *Proposal:* session 01/19 add widget and task stubs to
    the templates; session 19 checks criterion 2 without the `CLAUDE.md`/`/check-module` parts and
    session 32 completes it.
32. **Child theme inside the `Tasks` module** — spec "Tutorials" (9: a child theme overriding a
    `Tasks` view) and session 32 ("child-theme override" in the module). Themes live in `themes/`.
    *Proposal:* ship `themes/tasks-demo/` (child of `default`) as part of the tutorial, installed
    with `themes:install` or kept in the repository and documented as tutorial material.
33. **The web server writing into `public/` and the project root** — spec "Installing modules" ("the
    web server never writes code") vs Settings "publish assets", enabling a module from the UI
    (publishes its assets), the web installer (writes `.env`) and restoring `.env` from the UI.
    Published JS is executable code for browsers. *Proposal:* the web user may write only
    `writable/` and `public/assets/modules|core`, `public/themes`, `public/uploads`; the web
    installer shows the `.env` content to copy when the root is not writable; document the risk in
    `docs/security.md`. **(decide)**
34. **New dependencies vs "ask before adding a dependency"** — `CLAUDE.md` workflow vs spec
    "Technical baseline" and `deploy/00-bootstrap.md` §4.4 (TOTP, QR, HTMLPurifier, Flysystem
    installed by the sessions that use them; an MIT editor and calendar). *Proposal:* the libraries
    named in the specification (and an MIT/BSD editor and calendar) are pre-approved; anything else
    needs asking. Add this sentence to `CLAUDE.md`.
35. **Rich text before the editor exists** — News (session 17) has an HTML body sanitised with
    HTMLPurifier, but the editor is vendored in session 20. *Proposal:* News uses a textarea with
    HTMLPurifier in 17; session 20 switches News to `forms/editor.js` and updates its tests.
36. **News group visibility as `multiselect`** — spec "News" ("a `multiselect` or a junction
    table"). A JSON/CSV column cannot be queried portably with the Query Builder on SQLite and
    MariaDB ("visible to any of my groups"). *Proposal:* use a junction table `news_groups` with
    kpcrudy `setRelationNN()`.
37. **Profile fields and birthdays have no table** — spec "Auth" (name, language, time zone, theme
    mode, avatar), Users "Birthdays" provider. Shield's `users` table has none of these, and "each
    module owns its tables". *Proposal:* a `user_profiles` table owned by Auth (one migration),
    with an optional `birth_date` used by the provider.
38. **Groups as data** — spec "Users" (Groups CRUD with kpcrudy, 2FA policy per group), "Groups"
    (reserved names). Shield's groups are configuration (`AuthGroups::$groups`), not a table.
    *Proposal:* a Core `groups` table (name, title, description, reserved, 2FA policy) mirrored
    into `setting('AuthGroups.groups')` like the matrix (item 19).
39. **Upload folders** — spec "Directory layout" (`public/uploads`, `writable/storage`), "Backup"
    (includes `writable/uploads`), kpapy (`writable/uploads/kpapy`), session 00 `.gitignore` (does
    not list `public/uploads`). *Proposal:* the backup includes both disks plus `writable/uploads`
    only if non-empty; add `public/uploads/*` (except `index.html`) to `.gitignore`.
40. **Translated content in kpcrudy** — spec "Languages" (fallback chain), "Demo data" (Catalan
    only), Events (CSV import). kpcrudy 1.0 lists show an **empty cell** when the record has no
    translation in the list locale (no fallback), and translated fields are **not included in CSV
    import**. kpapy does have `localeFallbacks`. *Proposal:* set `listLocale` to the user's locale
    only when it is complete, otherwise `ca`; for Events import, `callbackImport()` writes the
    Catalan translation; record both gaps in `docs/library-requests.md`.
41. **Visibility names and calendar permissions** — News uses `public|private|groups`, Events and
    the calendar API `public|private|restricted`; the `news.notices` calendar declares
    `'permission' => 'news-posts.list'`, which ordinary users (who can read notices on the
    dashboard) do not have. *Proposal:* map `groups` → `restricted` in the News publisher; calendar
    `permission` becomes optional and visibility per event decides; for News leave it empty.
42. **Where ordinary users read private news** — spec "News" ("private news only in the back office
    and the dashboard", "mark as read from the widget and the detail page"). The back-office CRUD
    requires `news-posts.list`. *Proposal:* a read-only "reader" screen (`news-read.view`, granted
    to every group) listing the news the user can see, with the detail page and "mark as read".
    **(decide)**
43. **`about` and `cache:clear` hooks** — spec "Installer and CLI". CodeIgniter 4.7 already has
    `cache:clear`, and command discovery keeps the first command with a name, so a module cannot
    replace it; `about` does not exist in CodeIgniter. *Proposal:* add `about` in Core; for caches,
    a Core `app:cache-clear` (Core caches + CodeIgniter cache), and listen to a `cache.cleared` event
    where possible.
44. **Path placeholders** — spec "Files": personal space `users/{id}/`, picker example
    `users/{user_id}/avatar`. *Proposal:* use `{user_id}` everywhere.
45. **Restoring `.env` and keys** — spec "Backup" (backups contain `.env` and the encryption key;
    "restoring on a new server"). Restoring the old `.env` overwrites the new server's DB
    credentials and base URL. *Proposal:* restore keeps the current `.env` by default and merges
    only the secrets (`encryption.key`, `app.maskKey`, `backup.password`) after confirmation;
    `--with-env` restores it whole (console only).
46. **AES-256-GCM for large archives** — spec "Backup". PHP's OpenSSL GCM is one-shot (whole archive
    in memory). *Proposal:* libsodium `crypto_secretstream_xchacha20poly1305` (streaming,
    authenticated) with Argon2id, or chunked AES-256-GCM with per-chunk tags and a header MAC;
    document the format version in the archive header.
47. **Backup password on the command line** — spec "Backup" (`backup:create … [--password]`). A value
    on the command line ends up in shell history and the process list. *Proposal:* `--password`
    only as a flag that triggers a hidden prompt; non-interactive use reads `backup.password` from
    `.env` or an environment variable.
48. **Release signing for Updates** — spec "Updates" (archive SHA-256 **and** signature, or a signed
    tag) but no session defines how the skeleton's releases are signed or which key ships in
    `Config\Updates`. Without it, every update is refused. *Proposal:* session 28 defines an
    Ed25519 (libsodium/minisign-compatible) signature of the release archive and the public key in
    `Config\Updates`; session 36 documents the release procedure. **(decide: key owner)**
49. **`composer audit` offline** — spec "`security:check`" ("offline cache when there is no
    network"). `composer audit` has no offline mode. *Proposal:* run it in the daily task or CI,
    store the result with its date, and report "last checked on …" (warning when older than 7 days).
50. **The `--probe` targets the site itself** — spec "`security:check`" (probe `/.env`, HTTP→HTTPS
    redirect…) vs `OutboundClient` (HTTPS only, refuses loopback/private IPs). *Proposal:* the probe
    uses a dedicated client limited to the configured base URL host (HTTP allowed only for the
    redirect check), never a user-supplied host other than the site's own `allowedHostnames`.
51. **File permissions of CodeIgniter's own files** — spec "`security:check`" (`writable/` folders
    `0750`, files `0640`). CodeIgniter writes logs with `Config\Logger::$filePermissions = 0644`
    by default; kpcrudy/kpapy key files are `0600`. *Proposal:* set the Logger to `0640`; the check
    accepts stricter modes.
52. **`app.maskKey` fallback** — spec "JSON API" ("never leave it empty: each library would then
    generate its own key file"). Both libraries first fall back to CodeIgniter's encryption key
    (then to their own key files), so ids match but change when the encryption key is rotated.
    Masked ids also depend on the context (table name by default) in both libraries, so the "same
    id in web and API" holds only for the same table. *Proposal:* keep the requirement (key set,
    boot refused in production when empty), fix the explanation in `docs/security.md`, never set a
    custom `maskIds($context)` on one side only.
53. **Captcha implementation** — spec "Authentication" (optional captcha, `hash_equals`, single use)
    names no mechanism, and outbound services are forbidden. *Proposal:* a local image/arith captcha
    rendered with GD (or SVG), answer hashed in the session, invalidated after one check.
54. **`If-Match` for writes** — spec "JSON API" ("`If-Match` for writes"). kpapy supports it and
    can require it (`requireIfMatch`), which makes simple clients fail with `428`. *Proposal:*
    support it (412 on mismatch) but do not require it by default; document it in `docs/api.md`.
55. **Brand-new required libraries** — spec "Required libraries" (`^1.0`). kpcrudy and kpapy 1.0.0
    were published one day before this plan; their behaviour may change in 1.0.x. *Proposal:* pin
    the exact version in `composer.lock`, read each `CHANGELOG.md` before updating, and file gaps in
    `docs/library-requests.md` instead of working around them.
56. **Tasks module vs scheduled tasks naming** — spec: modules keep scheduled tasks in `Tasks/`
    (`TaskInterface`), and the tutorial module is named `Tasks` (`modules/Tasks/Tasks/…`,
    `scheduled_tasks` vs `tasks` tables, "task" in UI texts). *Proposal:* keep the names (they are in
    the spec) but use explicit terms in docs and language keys ("scheduled task" vs "to-do task").
