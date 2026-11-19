# Security baseline

What is on by default (docs/specification.md, "Security baseline"). Production is the default
environment; never weaken a default to make something work. `php spark security:check` and the
Security status page check it (see "Production checklist" below; every command is described in
[`commands.md`](commands.md)).

## Threat model

Who attacks what, and the control that answers each threat. Every control is enforced on the
server; the user interface only hides what the server also refuses.

| Attacker | What they try | Controls (code / docs) |
|---|---|---|
| Anonymous visitor | Guess passwords, find out which accounts exist | Generic messages, an unknown email costs a password hash too; failed logins counted per IP and per account (`Auth.loginAttemptsPerIp`, `Auth.loginAttemptsPerAccount`, `Auth.loginLockMinutes`; `Modules\Auth\Services\LoginThrottle`) → 429; optional captcha (`Auth.captcha`); email links limited (`Auth.emailLinks*`) — [`auth.md`](auth.md) |
| Anonymous visitor | Inject script (XSS), frame the site (clickjacking), forge a POST (CSRF) | Strict CSP with nonces and no `unsafe-*` (`Config\ContentSecurityPolicy`, `php spark csp:check` in CI); output escaped; rich text through `Modules\Core\Security\HtmlSanitizer` (HTMLPurifier); `X-Frame-Options: DENY` + `frame-ancestors 'none'`; the global `csrf` filter on every POST outside `api/*`; cookies `SameSite=Lax` — [`frontend.md`](frontend.md), "Security headers" below |
| Anonymous visitor | Read `.env`, logs, backups or code; run a PHP file from `uploads/` | Document root `public/` only; `writable/` never served; no script execution in `public/uploads` ([`web-server.md`](web-server.md)); the `probe` group of `security:check` requests those paths |
| Anonymous visitor | Open redirect, SSRF through a URL the site fetches | `safe_redirect()` (`Modules\Core\Security\SafeRedirect`); no outbound HTTP on regular requests, `Modules\Core\Http\OutboundClient` with an allow-list and private addresses refused ([`outbound-requests.md`](outbound-requests.md)) |
| Anonymous visitor | Flood feeds, forms, the API or the back office | Named rate limits (`Modules\Core\Security\RateLimiter`, `ratelimit:<limit>` filter) → 429 with `Retry-After`; kpapy limits per token and per IP ("Rate limits" below) |
| Logged-in user | Open screens or records they may not see; forge POSTs to them | Default deny: a `can:` filter on every `backend/` route and record rules in the shared `Modules\Core\Permissions\CrudPermissions` resolver; operations not declared in the manifest are refused; masked ids are not access control; `php spark permissions:check` fails on a route without `can:` — [`permissions.md`](permissions.md) |
| Logged-in user | Give themselves more permissions | Grants change only through `Modules\Core\Permissions\GrantManager`; `EscalationGuard`: nobody grants a permission they lack, changes their own groups or touches a superadmin unless superadmin; the permission matrix, groups and access screens need re-authentication; `SessionTracker::privilegesChanged()` regenerates the session ids — [`permissions.md`](permissions.md), [`auth.md`](auth.md) |
| Logged-in user | Upload a script, an HTML/SVG page or a huge file | Storage service only: allow-lists of extensions and detected MIME types, sizes, random names, SVG and HTML refused by default, images re-encoded, signed upload tokens for the file picker, `uploads` rate limit — "Uploads and private files" below, [`storage.md`](storage.md), [`files.md`](files.md) |
| Stolen password | Log in as the victim | 2FA (email code or authenticator app; required per group); a notice on a login from a new device; "my sessions" with remote logout; a password change ends every other session and revokes app-login tokens — "Authentication" below |
| Stolen session cookie | Act as the victim | `HttpOnly` + `Secure` cookies; absolute lifetime, remember-me cap and idle lock; session id regenerated on login and privilege changes; sensitive actions ask the password (and the 2FA code) again (`reauth` filter); administrators log a user out everywhere (Users › `logout`) |
| Stolen API token | Call the API as the victim | Tokens only over HTTPS in production, scopes intersected with the user's permissions, optional expiry, per-token rate limit, a `token_new_ip` security entry when a token is used from a new IP; revocation by the owner (`backend/profile/api-tokens`) or an administrator (`backend/api/tokens`) — [`api.md`](api.md) |
| Malicious module or theme package | Run code on the server, overwrite files, fill the disk | Validated without running it (`Modules\Core\Packages\PackageValidator`), `Modules\Core\Archive\SafeZip` (zip-slip, links, zip bombs), extension allow-list, `php -l`, optional minisign signature (`packages.requireSignature`), approval by a superadmin with re-authentication, installed only from the console; disabled in production until enabled — "Module and theme packages" below, [`installing-modules.md`](installing-modules.md) |
| Malicious backup file | Restore planted code or data | Header check, authenticated encryption (a tampered file does not open), `SafeZip`, restore superadmin-only with re-authentication, nothing written into public disks — [`backup-restore.md`](backup-restore.md) |
| Compromised dependency or release | Ship vulnerable or tampered code | `composer audit` daily (`php spark security:audit`), SHA-256 of vendored front-end libraries (`public/assets/vendor/vendor.json`), updates verified with an Ed25519 signature or `git verify-tag` before anything changes — [`updates.md`](updates.md), [`dependencies.md`](dependencies.md) |
| Compromised server or web process | Change code, read secrets, hide traces | The web server writes only `writable/` and the published asset/upload folders (never `modules/`, `themes/`, `app/`); `.env` `0640`; backups encrypted with a password that is never stored; the activity log is append-only; "Incident response" below |

## HTTPS and cookies

- `Config\App::$forceGlobalSecureRequests` is true in production (constructor): HTTP requests are
  redirected to HTTPS.
- Cookies are `Secure` in production, `HttpOnly` and `SameSite=Lax` (`Config\Cookie`).
- The installer writes `app.baseURL`; set `app.allowedHostnames` to any other host name the site
  answers on.

## Installer

- `php spark app:install` (recommended) and the optional web installer `/install` share one
  service (`Modules\Core\Install\Installer`). Both refuse to run once `.env` or
  `writable/install.lock` exists (the web installer answers 404) or when the database already
  has tables; the lock file is written last, so a failed installation can be repeated.
- Before installation every web page redirects to `/install` (`installguard` filter) and the
  base URL is taken from the request; nothing else runs without a database. In production the
  page is still redirected to HTTPS; elsewhere it warns when it is served over HTTP.
- Local exception (D-19-05): PHP's development server (`php spark serve`, `php -S`) answering a
  browser on the same computer (client address `127.x`/`::1` **and** host `localhost`,
  `*.localhost`, `127.x` or `[::1]`) is served over HTTP and its cookies are not `Secure`, even in
  production, so a fresh clone opens the web installer at `http://localhost:8080`. A real web
  server (Apache, nginx + PHP-FPM) never qualifies, not even behind a proxy on the same machine
  (`Config\App::isLocalDevServer()`; `tests/feature/Security/LocalDevServerTest.php`).
- The web server should not be able to write the project root (D-00-33): the web installer then
  shows the `.env` content once, for the operator to save by hand (`chmod 640`, owned by the
  deploy user, group of the web server). `.env` written by the installer is `0640`.
- Passwords are never command-line options (shell history): hidden prompts or the
  `INSTALL_DB_PASSWORD` / `INSTALL_ADMIN_PASSWORD` variables. The superadmin password follows
  Shield's password rules; 2FA is offered at the end (console) or from the profile (web).
- Installation and the first superadmin are written to the activity log (`installed`,
  `superadmin_created`).

## Authentication: passwords, 2FA, re-authentication and sessions

The full rules are in [`auth.md`](auth.md); the settings are in **Settings › Security**
(`backend/settings/security`, `settings.security` + re-authentication) and can never go below
the defaults of `app/Config/Auth.php` where a rule says so.

- **Passwords:** Shield's local validators (composition, dictionary, personal information; never
  an online "pwned" service) and a minimum length `Auth.minimumPasswordLength`: **8** by default
  and never less (`min_config`), **12 recommended** — `security:check` warns below 12
  (`accounts.passwordLength`). Passwords are hashed by Shield; resets use single-use email links
  (48 hex characters, stored as SHA-256, `Auth.magicLinkLifetime`).
- **Two-factor authentication:** each user chooses none, an email code or an authenticator app
  (TOTP; the secret is encrypted with `encryption.key`) in `backend/profile/two-factor`.
  **Policy:** the `two_factor` column of each group (Users › Groups) makes 2FA required for its
  members: the `force2fa` filter sends them to the set-up screen and they cannot turn it off
  (`security:check`: `accounts.superadmin2fa` warns about superadmins without 2FA,
  `accounts.group2fa` lists groups where it is optional). **Recovery:** 10 single-use recovery
  codes are shown when 2FA is enabled (regenerable with re-authentication) and accepted at the
  login step instead of a code; a user who lost both is reset by an administrator with the
  `reset2fa` row operation of Users (`users-users.reset2fa` + re-authentication), which deletes
  the method and the recovery codes, logs it and notifies the user (`TwoFactorReset`); they then
  log in with the password and enrol again (at once when their group requires it).
- **Re-authentication ("sudo mode"):** routes with the `reauth` filter (2FA changes, API tokens,
  the permission matrix, access and groups, Settings › Security, backups, packages, the external
  probe…) ask for the password and, with 2FA, a code; the confirmation lasts `Auth.reauthMinutes`
  (15). Only a password login opens the window, not a magic link nor remember-me (D-06-04);
  never possible while impersonating.
- **Sessions:** cookies `HttpOnly`, `SameSite=Lax`, `Secure` in production; every session is a
  `user_sessions` row; absolute lifetime `Auth.absoluteLifetime` (720 minutes), remember-me cap
  `Auth.rememberLifetime` (14 days, 0 = off), idle lock `Auth.idleTimeout` (0 = off); the id is
  regenerated on login, logout, unlock, password change and privilege change
  (`SessionTracker::privilegesChanged()`). Users see and end their sessions in
  `backend/profile/sessions`; a login from a new device sends a notice (`Auth.newDeviceNotice`).
  A password change or reset ends every other session and revokes the tokens of app logins.

## Mask key (`app.maskKey`)

kpcrudy and kpapy mask ids in URLs and sign URLs with one shared key, `app.maskKey` in `.env`
(64 random hex characters: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`; the installer
writes it). `app/Config/Kpcrudy.php` and `app/Config/Kpapy.php` both read it through
`Modules\Core\Security\MaskKey::value()`, so the same id has the same code in a CRUD and in the
API.

**Fallbacks and why it must be set.** When the key is empty each library falls back on its own:
first `encryption.key`, then a random key file it creates (`writable/kpcrudy/mask.key`,
`writable/kpapy/mask.key`). Those keys differ between the two libraries and between servers, and
change when `encryption.key` is rotated or the key file is lost (a new server, a restore without
`writable/`): every masked id and signed URL already handed out stops working, and a CRUD and the
API disagree about the same record. The Core's own signatures (upload tokens of the file picker,
signed avatar URLs; `Modules\Core\Security\SigningKey`) are derived from `encryption.key`, or from
`app.maskKey` when there is none, or from a random per-process key when neither exists (nothing
can then be forged, but signatures only live for one request).

So `MaskKey::assertBootable()` (on `pre_system`, `modules/Core/Config/Events.php`) refuses web
requests of an installed site in production when the key is empty, and in every environment when
it is shorter than 32 characters (`spark` still runs, so the key can be generated);
`security:check` reports `secrets.maskKey` (set, and the same for kpcrudy and kpapy: never set
`kpcrudy.maskKey` or `kpapy.maskKey` in `.env`). Masked ids hide sequential ids; they are never
access control. Changing the key breaks every masked or signed link already sent (D-09-01).

## Security headers

The Core `securityheaders` filter (`Modules\Core\Filters\SecurityHeaders`) is a **required
"after" filter**, so it runs on every response, also for routes that do not exist; error
responses rendered by the exception handler get the same headers
(`Modules\Core\Http\SecureExceptionHandler`). It replaces CodeIgniter's `secureheaders` filter,
which hard-codes other values; never use both (decision D-00-28).

| Header | Default (`Config\SecurityHeaders`) | Why |
|---|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains`, production only (`hsts = null`) | browsers use HTTPS only; never less than a year when sent |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | no paths or queries leak to other sites |
| `X-Content-Type-Options` | `nosniff` | no MIME sniffing (uploads) |
| `X-Frame-Options` | `DENY` | no framing in old browsers (CSP `frame-ancestors 'none'` for the rest) |
| `Cross-Origin-Opener-Policy` | `same-origin` | isolates the window from pages it opens |
| `Permissions-Policy` | camera, microphone, geolocation, payment, USB… `()` | features disabled for everybody |

Change them in `.env` with the lower-case class name as prefix (CodeIgniter matches
`securityheaders.<property>` exactly), e.g. `securityheaders.frameOptions = SAMEORIGIN`,
`securityheaders.hsts = false` (a site that cannot serve HTTPS yet), `securityheaders.hstsPreload
= true` (only after submitting the domain). Arrays (`permissionsPolicy`, `extra`) are changed in
`app/Config/SecurityHeaders.php`; a feature a module needs (`camera=(self)`) goes there. A header
a controller sets on its response is kept (a page that may be framed sets its own
`X-Frame-Options` and CSP `frame-ancestors`).

The Content-Security-Policy is described in `docs/frontend.md` and `Config\ContentSecurityPolicy`
(strict, nonces, module sources from the manifests, `reportOnly` to test a change).

## Configuring headers and CSP

The policy every page gets is built from four places, in this order; the Security status page
(`backend/security`) shows the **effective** result (`Modules\Core\Security\Check\EffectivePolicy`).

1. **`app/Config/ContentSecurityPolicy.php`** (enabled by `Config\App::$CSPEnabled = true`): the
   strict default
   `default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'nonce-…'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`,
   plus `script-src-attr 'none'` and `style-src-attr 'none'` (no `on*=""` handlers, no `style=""`).
   Scalars can be changed from `.env`:

   ```ini
   # Test a change without blocking anything (security:check reports it as an error):
   contentsecuritypolicy.reportOnly = true
   contentsecuritypolicy.reportURI = https://csp-reports.example.com/report
   ```

2. **A module's manifest** (`$csp`, applied by the global `cspmodules` filter
   `Modules\Core\Filters\ContentSecurity` only while the module is enabled). Directives:
   `default-src`, `script-src`, `style-src`, `img-src`, `font-src`, `connect-src`, `media-src`,
   `object-src`, `frame-src`, `child-src`, `worker-src`, `manifest-src`, `form-action`,
   `frame-ancestors`. Sources: `https://host[:port][/path]` (optionally `https://*.host`),
   `'self'`, `'none'` or `blob:`; `modules:sync` refuses `'unsafe-inline'`, `'unsafe-eval'`,
   `'unsafe-hashes'`, `*`, `http:` and `data:` (`ManifestValidator`). Sources added to
   `script-src`/`style-src` are also added to `script-src-elem`/`style-src-elem`.

   ```php
   // modules/Maps/Config/Module.php
   public array $csp = [
       'img-src'     => ['https://tile.openstreetmap.org'],
       'connect-src' => ['https://nominatim.openstreetmap.org'],
   ];
   ```

3. **Settings › Security › Content-Security-Policy** (`backend/settings/security`, settings
   `Core.cspScriptSrc`, `Core.cspStyleSrc`, `Core.cspImgSrc`, `Core.cspFontSrc`,
   `Core.cspConnectSrc`, `Core.cspFrameSrc`, `Core.cspMediaSrc`; one origin per line): extra
   origins for the whole site, e.g. an embedded video player in `frame-src`
   (`https://www.youtube-nocookie.com`). Only `https://` origins (and `wss://` outside scripts and
   styles); no keywords, schemes or paths, and no wildcards for scripts and styles
   (`Modules\Core\Settings\SecurityRules`, checked again by the filter).
4. **A controller** may widen the policy of one response through CodeIgniter's CSP service
   (`service('csp')->addFrameAncestor('https://intranet.example.com')`) and set its own
   `X-Frame-Options`; the `securityheaders` filter keeps a header the response already has.

Never add `'unsafe-inline'` or `'unsafe-eval'`: move the code to an ES module in
`modules/<Name>/Assets/js` and data to a `<script type="application/json">` block, and check the
views with `php spark csp:check` ([`frontend.md`](frontend.md)).

The other headers come from `app/Config/SecurityHeaders.php` (`Config\SecurityHeaders`, table
above). Examples:

```ini
# .env (prefix: the class name in lower case)
# only if the site frames its own pages:
securityheaders.frameOptions = SAMEORIGIN
# only after submitting the domain to the HSTS preload list:
securityheaders.hstsPreload = true
securityheaders.referrerPolicy = no-referrer
```

```php
// app/Config/SecurityHeaders.php: a module that needs the camera on this site only
public array $permissionsPolicy = [
    // …
    'camera' => '(self)',
];

// An extra header on every response
public array $extra = ['Cross-Origin-Resource-Policy' => 'same-origin'];
```

`security:check` reports `configuration.csp` (enabled, enforced, no `unsafe-*` nor `*`, `http:`,
`https:`, `data:` for scripts, styles, objects and the default), `configuration.hsts` and
`configuration.headers`; `--probe` checks that the web server really sends them
(`probe.headers`).

## API

Token authentication only (`Authorization: Bearer`): the API never starts a session nor reads
its cookie and is excluded from CSRF; scopes are intersected with permissions (superadmins too);
app logins need the user's authenticator app or an emailed code (with a link to stop the
sign-in); wrong passwords count in Auth's `LoginThrottle`; CORS closed; plain HTTP refused in
production; the documentation only in the back office (`api.docs`). Details: `docs/api.md`.
Rotating `app.maskKey` changes every masked id of the API too; to revoke every token, delete the
`access_token` rows of `auth_identities` (or one by one in Settings › API tokens,
`backend/api/tokens`, `api.delete`; the owner in `backend/profile/api-tokens`).

## Rate limits

`Modules\Core\Security\RateLimiter` (CodeIgniter's Throttler, token bucket in the cache) with
named limits whose capacities are Core settings (Settings › Security, docs/settings.md):

| Limit | Setting | Default | Counted per | Used by |
|---|---|---|---|---|
| `backend` | `Core.rateLimitBackend` | 300 / minute | user | the `backend` filter chain (every `backend/*` request) |
| `apiToken` | `Core.rateLimitApiToken` | 120 / minute | API token | kpapy's own limiter (`Config\Kpapy::$rateLimit`, read from the setting by `app/Config/Kpapy.php`) |
| `apiIp` | `Core.rateLimitApiIp` | 60 / minute | IP | kpapy routes without a token (`Config\Kpapy::$rateLimitPublic`) |
| `feeds` | `Core.rateLimitFeeds` | 60 / hour | iCal token, IP | News RSS (`news/feed.xml`), the public calendar feed and `.ics` exports, iCal feeds (Events) |
| `uploads` | `Core.rateLimitUploads` | 100 / hour | user | `files/picker/upload`, the file manager's uploads, package uploads (`modules.upload`) and backup uploads |
| `forms` | `Core.rateLimitForms` | 10 / hour | user, or IP for guests | sensitive POST actions: modules sync, demo data, package approval and uninstall, backups (create, verify, restore), Security status "Check again" and probe, denying an API app login (`auth/api-login/*`) |
| `widgets` | `Core.rateLimitWidgets` | 120 / minute | user | async widget URLs (`backend/dashboard/widgets/<id>/data`) |

Login (per IP and per account), password reset, magic links, 2FA codes, recovery codes and
re-authentication keep their own counters (`Auth.loginAttempts*`, `Auth.emailLinks*`; Auth's
`LoginThrottle`).

- On a route: `['filter' => 'ratelimit:feeds']` (per user, or per IP for guests).
- In code: `$wait = (new RateLimiter())->attempt('feeds', $tokenHash);` then
  `RateLimit::tooManyRequests($request, $wait)`.
- Past the limit the answer is **429** with `Retry-After`: the themed `errors/429` page, or
  `{"error": …}` for clients that accept JSON.
- Subjects are hashed before they reach the cache. A module's own limit:
  `attemptCustom('news-votes', $subject, 5, MINUTE)`.

## Activity and security log

Security events (failed logins and codes, throttling, 403s, re-authentication, token changes, a
token used from a new IP, refused outbound requests, refused uploads…) are `security` entries of the append-only
activity log; secrets are masked by key and the file log escapes line breaks (`SafeLogger`).
Log a new security rule's refusals with `security_log()`. See `docs/activity.md`.

## Uploads and private files

Files are written only through the Storage service: allow-listed extensions and MIME types
detected from the contents, size limits, random names, SVG and HTML refused by default, images
re-encoded, private files served only through `files/private/{key}` with `nosniff`, a sandboxing
CSP and `attachment` unless image or PDF. Nothing is executed in `public/uploads` and `writable/`
is never served. See `docs/storage.md` and `docs/web-server.md` (Apache and Nginx snippets).

Module fields upload through the file picker (`files/picker/upload`): only with a **signed upload
token** issued with the field (user, folder, disk, types, size, expiry; HMAC with a key derived
from `encryption.key`/`app.maskKey`, `Modules\Core\Security\SigningKey`); a tampered or foreign
token is a 403 and a `security` entry `upload_token_refused`. The saved value is checked again
(`PickerField::accepts()`): a key that did not come through the field is refused. Avatars are
served at `files/avatar/{key}` only to signed-in users or with a signed, expiring URL. Rich text
keeps only images of the site itself (docs/files.md, "File picker").

## Web server and writable folders

The document root is `public/`; Apache (`public/.htaccess`, `public/uploads/.htaccess`) and Nginx
configurations, the HTTPS redirect and the file permissions are in
[`web-server.md`](web-server.md). In short:

- Only `public/index.php` runs PHP; `public/uploads` never executes scripts (`installation.uploadsHtaccess`
  and `probe.uploads` check it); `.env`, `writable/`, `modules/`, `app/`, `vendor/` and `.git` are
  never served (`installation.public`, `probe.hidden`).
- The code belongs to a deploy user. The web server (PHP-FPM, Apache) may write only the paths of
  `Config\ProductionCheck::$writablePaths`:

  | Path | Why the web server writes it |
  |---|---|
  | `writable/` | cache, sessions, logs, the private Storage disk, packages, backups, `security/`, `maintenance.json` |
  | `public/uploads` | Storage's public disk |
  | `public/assets/core`, `public/assets/modules`, `public/themes` | published assets: "Sync" in the Modules screen, Settings › Actions › "Publish assets" and the development refresh (`assets.devRefresh`) |

  and must not write `$readOnlyPaths` (the project root, `.env`, `app`, `modules`, `themes`,
  `vendor`, `public`, `public/index.php`, `public/.htaccess`); `installation.webWritable`
  checks it (set `productioncheck.webUser = www-data` in `.env` for the console).
- **The risk of published JavaScript:** the files under `public/assets/` are served from the
  site's own origin, which the CSP trusts (`script-src 'self'`). Anyone who can write them — the
  web process after a compromise, or a user with a shell — runs JavaScript in every
  administrator's browser, CSP or not. Keep the published folders writable only by the deploy user
  when the site does not need to publish from the web (publish from the console with
  `php spark assets:publish` or `modules:sync`, and leave "Sync"/"Publish assets" to the console),
  never edit `public/assets/modules/…` by hand (`assets:publish --check` in CI detects changed
  copies), and treat any unexpected change there as an incident. The vendored libraries of
  `public/assets/vendor` are checked against `vendor.json` (`dependencies.vendor`).

## Redirects

Redirect targets that come from the request (`return`, `next`…) go through `safe_redirect()`:

```php
return safe_redirect($this->request->getGet('return'), 'backend');
```

Only paths (`/backend/news?page=2`, `backend/news`) and http(s) URLs of the site's own hosts
(`app.baseURL`, `app.allowedHostnames`) are followed; anything else (other hosts, `//host`,
backslashes, `javascript:`, `data:`, `https://site@evil`, control characters) goes to the fallback
(`Modules\Core\Security\SafeRedirect`).

## Outbound requests

See `docs/outbound-requests.md`: `OutboundClient` (HTTPS, allow-list, private addresses refused
after DNS and redirects) and the SMTP host check.

## Maintenance mode

`Modules\Core\Maintenance\MaintenanceMode` (D-27-02) keeps its state in
`writable/maintenance.json`. While it is on, the global `maintenance` filter (the first one)
answers 503 with `Retry-After` and the theme's maintenance page drawn without the database, except the paths given when it
was turned on (a restore allows only `progress/backup/*`); `schedule:run` and the poor man's cron
do nothing. Paths under `progress/*` return the JSON progress of long jobs and skip the filters
that read the database or the session (`moduleenabled`, `cspmodules`, `authsession`,
`csrftoken`); they must never return secrets. Two exist: `progress/backup/<job>` (a restore) and
`progress/install/<job>` (the web installer, session 39: only for an existing job of the last
hour, `WebInstaller::PROGRESS_LIMIT` requests per minute and IP, answers status, step, label,
percentage and log lines; the installer guard lets it through before installation). The job id
(128 random bits) is the capability; `security:check --probe` checks every `progress/*` route for
secret-looking keys. Restores and `app:update` turn it on and off
(docs/backup-restore.md, docs/updates.md) without any bypass.

The 503 page is the theme's `errors/maintenance` view in the `blank` layout, like the other error
pages (a theme overrides it in `themes/<slug>/Views/errors/maintenance.php`). It is drawn by
`Modules\Core\Maintenance\MaintenancePage` with a settings service without handlers, so nothing
reads the database (only the file configuration: `Config\Theme`'s public theme, its default
options); if the theme fails, a plain HTML page is returned (D-28-07).

**By hand** (D-28-02): Settings › Actions › *Maintenance mode* (`maintenance.manage` +
re-authentication) or `php spark app:maintenance on|off|status [--message="…"]`. The state file
then also holds a plain-text message (at most 500 characters, shown escaped on the 503 page and
in the JSON answer), the login paths (`login`, `login/*`, `logout`, `auth/*`) and the `bypass`
flag: a request that carries a session cookie of a signed-in user with **`maintenance.manage`**
passes, with a banner in the back office. The permission is a Core resource with no default grant
(superadmins always pass; administrators do not get it through `settings.*`; assign it in the
permission matrix). Nobody else passes; turning it on and off is
written to the security log. The screen never turns off a mode turned on by a restore or an
update.

## Archives

ZIP files from outside (backups, module packages) are read only through
`Modules\Core\Archive\SafeZip`: entry names are checked on their stored bytes (relative, `/` only,
no `..`/`.`/empty segments, no control characters or drive letters, under the allowed prefixes),
symbolic links are refused, and the number of entries, the total uncompressed size and the
compression ratio of each entry are limited before anything is extracted; sizes are enforced again
while copying, and each file is written by the class itself with the given permissions. A restore
never writes server code or server configuration into a public disk.

## Module and theme packages

Modules and themes are code: the web server never writes `modules/`, `themes/` nor `app/Config/`.
An uploaded package (`modules.upload`, superadmin only by default, with a recent
re-authentication) is validated without running it (`ManifestReader`), syntax-checked with
`php -l` in a private folder, stored with a random name in `writable/packages/` (0640, never
served) and installed only from the console (`php spark modules:install --pending`) after a
superadmin approved it; its SHA-256 is checked again at approval and at install time. Packages go
through `SafeZip`, an extension allow-list (no `phtml`, `phar`, `.htaccess`…), size, file-count
and ratio limits, and optionally a minisign signature by a trusted publisher key
(`packages.requireSignature`). Every refusal is a security log entry (`package_refused`). In
production a module copied by hand or installed without `--enable` stays disabled until an
administrator enables it (D-29-02). See `docs/installing-modules.md`.

## Backups

Backups contain `.env` and every secret, so they are always encrypted with a password of at least
12 characters that is never stored (Argon2id + libsodium secretstream), created `0640` in
`writable/backups` (never served) and downloaded or restored only after re-authentication;
restore is superadmin-only by default. See `docs/backup-restore.md`.

## Production checklist

`php spark security:check` (console) and **Settings › Security status** (`backend/security`,
permission `security.check`: superadmin and admin by default) run the same read-only checks, each
with its status (ok, info, warning, error), what it means and how to fix it. The command exits
with code 1 when a check is in error (7 for an unknown `--only` group or `--only=probe` without
`--probe`), so a deployment pipeline can stop on it; `--only=<group>` runs one group (its results
are not stored) and `--json` prints JSON. Before going live:

1. `php spark app:install` (it ends with this summary), then fix every **error**.
2. `chmod 640 .env`; `find writable -type d -exec chmod 750 {} +` and
   `find writable -type f -exec chmod 640 {} +` (stricter modes are accepted).
3. Give the code to a deploy user and let the web server write only `writable/`,
   `public/assets/modules`, `public/assets/core`, `public/themes` and `public/uploads`; tell the
   console which user that is with `productioncheck.webUser = www-data` in `.env` (the page,
   which runs as the web user, checks it directly).
4. `php spark security:audit` (or let the daily task run it).
5. `php spark security:check --probe=https://<your site>/` from the server.

| Group | Checks (error unless noted) |
|---|---|
| `configuration` | `CI_ENVIRONMENT=production`; no `CI_DEBUG` nor `display_errors`; `forceGlobalSecureRequests`; cookies `Secure`, `HttpOnly`, `SameSite` Lax/Strict; an `https://` base URL (a local or example host is a warning); CSP enabled, enforced (not report-only) and without `unsafe-*` (nor `*`, `http:`, `https:`, `data:` for scripts, styles, objects and the default); HSTS sent (warning); the other security headers (warning); CORS of the API never `*` (Api module) |
| `secrets` | `encryption.key` set (32 bytes or more); `app.maskKey` set and the same for kpcrudy and kpapy; `.env` not readable by other users (no `.env`: warning); no secret in the files git tracks (private keys, GitHub/GitLab/AWS/Slack/Stripe tokens, `.env` secrets with a value; placeholders and lines marked `security-check: ignore` are skipped; not a git checkout: info); `backup.password` set when `Backup.scheduled` is on (Backup module) |
| `installation` | `writable/install.lock` present; no demo data loaded; `writable/` folders `0750` and files `0640` or stricter (warning); `writable/maintenance.json` not readable by others; what the web server user may write (unknown from the console without `productioncheck.webUser`: warning); `public/` holds no `.env`, `writable`, `.git` nor script other than `index.php`; backup files not readable by others and their folder outside `public/` (Backup module, D-27-05); `public/uploads/.htaccess` present (warning); unsigned packages accepted — `packages.requireSignature` off or no `packages.publicKeys` (warning) — and `packages.autoEnable` on (info) |
| `accounts` | superadmins without 2FA (warning); groups where 2FA is optional (info); `Auth.minimumPasswordLength` below 12 (warning); personal API tokens without expiry (info) |
| `dependencies` | the last `composer audit` (advisories: error; never run, failed or older than 7 days: warning); the SHA-256 checksums of `public/assets/vendor/vendor.json`; `updates.repository` without `updates.publicKeys` (warning; none configured: info; Updates module) |
| `outbound` | the allow-list and the hosts each module adds (info) |
| `probe` | with `--probe=<url>` or "Run external probe" (re-authentication): the security headers on `/`; `/.env`, `writable/` (logs, packages, backups), `modules/`, `app/`, `vendor/`, `composer.json`, `.git/config` not served; a harmless PHP file written to `public/uploads` is not executed (and is removed); `?return=https://…invalid/` is not followed on `/`, `login` and `backend`; the login answers 429 after at most `productioncheck.probeLoginAttempts` wrong passwords for a made-up `example.invalid` address (they count towards the server's own login limit for a while; `productioncheck.probeLoginPath = ""` skips it); plain HTTP redirects to HTTPS (or is not answered); the `progress/*` routes answer no secret-looking keys (D-27-02). The probe uses its own client: requests only to the base URL host and `app.allowedHostnames`, HTTPS (HTTP only for the redirect check), no redirects followed, never through the OutboundClient nor to third parties. |

Check ids, by group (stable: the JSON output, the stored results and the notification use them;
a check that throws is reported as `check.<class>` in its first group):

| Group | Ids |
|---|---|
| `configuration` | `configuration.environment`, `configuration.debug`, `configuration.https`, `configuration.cookies`, `configuration.hostnames`, `configuration.csp`, `configuration.hsts`, `configuration.headers`, `configuration.cors` (Api) |
| `secrets` | `secrets.encryptionKey`, `secrets.maskKey`, `secrets.envFile`, `secrets.trackedFiles`, `secrets.backupPassword` (Backup) |
| `installation` | `installation.lock`, `installation.demo`, `installation.writableModes`, `installation.maintenanceFile`, `installation.webWritable`, `installation.public`, `installation.backups` (Backup), `installation.uploadsHtaccess`, `installation.packages`, `installation.autoEnable` |
| `accounts` | `accounts.superadmin2fa`, `accounts.group2fa`, `accounts.passwordLength` (Auth), `accounts.personalTokens` (Api) |
| `dependencies` | `dependencies.audit`, `dependencies.vendor`, `dependencies.updates` (Updates) |
| `outbound` | `outbound.allowList`, `outbound.modules` |
| `probe` | `probe.target`, `probe.headers`, `probe.hidden`, `probe.uploads`, `probe.redirect`, `probe.login`, `probe.https`, `probe.progress` |

Example (a development checkout without `.env`):

```console
$ php spark security:check --only=secrets

Secrets
  [error]   Encryption key: encryption.key is empty: 2FA secrets and other encrypted values cannot be protected.
            Fix: Run php spark key:generate (it writes encryption.key to .env) and keep a copy in a safe place.
  [error]   Mask key (app.maskKey): app.maskKey is empty: set it in .env (64 random hex characters) before running in production.
            Fix: Set app.maskKey in .env to 64 random hex characters: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;".
  [warning] .env file: There is no .env file: the configuration must come from the server's environment variables.
            Fix: Run php spark app:install, or make sure the secrets are set as environment variables.
  [ok]      Secrets in tracked files: No secret found in the files git tracks.
  [ok]      Password of scheduled backups: Scheduled backups are off: no password needed.

1 ok, 0 information, 1 warnings, 2 errors.
```

- The page shows the results of the last run (the daily task "Security checks and composer audit"
  at 04:43, `security:check` or "Check again"; the first visit runs them), the last probe and the
  **effective** Content-Security-Policy and security headers (configuration + modules' `$csp` +
  Settings › Security origins). The dashboard widget "Security status" (`core.security-status`,
  `security.check`) shows the summary from the stored results. When a check turns red the
  superadmins get the notification "Security check failed" once (until it recovers).
- On PHP's development server answering this computer (D-19-05) HTTPS and `Secure` cookies are
  shown as information: run the command on the real server.
- Results are kept in `writable/security/` (`status.json`, `probe.json`, `composer-audit.json`,
  0640). Options: `Config\ProductionCheck` (`productioncheck.*` in `.env`: `webUser`, `composer`,
  `auditMaxAgeDays`, `maxScannedFiles`, `probeTimeout`, `probeLoginPath`, `probeLoginAttempts`,
  `writablePaths`, `readOnlyPaths`, `path`).
- A module adds its own checks from the Core event `securityChecks`: a class implementing
  `Modules\Core\Security\Check\SecurityCheck` (`groups()`, `run(SecurityReport $report)`, results
  with stable ids `<group>.<what>` and texts in its language files), added in its
  `Config/Events.php` when the module is enabled (see `modules/Backup/Config/Events.php`).


## Incident response

When an account, a token, the server or a backup may be compromised. Work from the console as the
deploy user; every step below is written to the activity log.

1. **Contain.** Turn maintenance mode on (`php spark app:maintenance on --message="Back soon"`;
   holders of `maintenance.manage` keep access) or, for one account, ban it (Users, row
   operation `ban`, `users-users.ban`) and force a password change (`forcereset`).
2. **Read the activity log** (`backend/activity`, `activity-log.list`; filter by type
   `security`, user, module, action and dates; CSV export with `activity-log.export`; IP addresses and user agents need `activity-log.network`;
   [`activity.md`](activity.md)). Look for `login_failed`/`throttled` bursts, `token_new_ip`,
   grant and group changes, `outbound_refused`, `package_refused`, `upload_token_refused` and
   logins from unknown IP addresses or user agents. The log is append-only: an attacker with a
   back-office account cannot remove entries. Check also `writable/logs/` and the Security status
   page (`backend/security`, "Check again").
3. **Revoke sessions.**
   - One user: Users › row operation `logout` ("log out everywhere", `users-users.logout`) ends
     every session and remember-me token; the user can do it in `backend/profile/sessions`
     ("Log out all other sessions"). A password change or reset does the same and revokes the
     tokens of app logins.
   - Everybody: delete the session files and the remember-me tokens, e.g.
     `rm -f writable/session/ci_session*` and `DELETE FROM auth_remember_tokens;` in the database
     client (with a table prefix, `<prefix>auth_remember_tokens`). The tracked `user_sessions`
     rows end on the next request.
4. **Revoke API tokens.** One by one in Settings › API tokens (`backend/api/tokens`,
   `api.delete`) or by their owner (`backend/profile/api-tokens`); all of them with
   `DELETE FROM auth_identities WHERE type = 'access_token';`. Clients log in again (app logins
   need the 2FA code or the emailed code).
5. **Rotate the encryption key** (if `.env` or a backup with its password leaked). `encryption.key`
   encrypts the TOTP secrets of authenticator apps and derives the Core's signatures (upload
   tokens, signed avatar URLs). Copy the old value to `encryption.previousKeys` in `.env` (comma
   separated, so the TOTP secrets still decrypt), then `php spark key:generate --force`. Because
   the old key is exposed, reset the 2FA of the users with an authenticator app (Users ›
   `reset2fa`) so they enrol a new secret, and then remove `encryption.previousKeys`. Open upload
   forms and signed avatar URLs simply stop working and are issued again.
6. **Rotate `app.maskKey`**: write a new value
   (`php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`) to `app.maskKey` in `.env`, then
   `php spark app:cache-clear`. Every masked id and signed URL changes: bookmarks and links already
   sent stop working (D-09-01), in the back office and in the API. Rotate at the same time the
   other secrets of `.env` (database and SMTP passwords, `backup.password`, tokens of
   `updates.repository`) at their source.
7. **Restore a backup** when data or code was changed: pick one from before the incident
   (`php spark backup:list`), check it (`php spark backup:verify <file> --password`) and restore
   it (`php spark backup:restore <file> --password`; it makes a safety backup first and runs
   maintenance mode and `modules:sync`). The current `.env` is kept: when the backup's secrets
   differ the command asks to merge them — **answer no** after rotating keys, and never use
   `--with-env` then. For the code, deploy a verified release again (`php spark app:update`, or
   check out a signed tag) instead of trusting files on a compromised server; compare `modules/`,
   `themes/` and `app/` with the repository (`git status`) and published assets with
   `php spark assets:publish --check`. See [`backup-restore.md`](backup-restore.md).
8. **Verify and reopen.** `php spark security:audit`, `php spark security:check --probe=https://<your site>/`,
   `php spark permissions:check`, then `php spark app:maintenance off`. Tell the affected users
   (password reset, new 2FA enrolment) and write down what happened.
