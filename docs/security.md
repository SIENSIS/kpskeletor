# Security baseline

What is on by default (docs/specification.md, "Security baseline"). Production is the default
environment; never weaken a default to make something work. `php spark security:check`
(session 30) reports the configuration.

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

## Mask key (`app.maskKey`)

kpcrudy and kpapy mask ids in URLs and sign URLs with one shared key, `app.maskKey` in `.env`
(64 random hex characters: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`; the installer
writes it). `Modules\Core\Security\MaskKey` refuses web requests in production when it is
empty, and in every environment when it is shorter than 32 characters (`spark` still runs, so
the key can be generated). Masked ids hide sequential ids; they are never access control.
Changing the key breaks every masked or signed link already sent (D-09-01).

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

Change them in `.env`, e.g. `securityHeaders.frameOptions = SAMEORIGIN`,
`securityHeaders.hsts = false` (a site that cannot serve HTTPS yet), `securityHeaders.hstsPreload
= true` (only after submitting the domain). Arrays (`permissionsPolicy`, `extra`) are changed in
`app/Config/SecurityHeaders.php`; a feature a module needs (`camera=(self)`) goes there. A header
a controller sets on its response is kept (a page that may be framed sets its own
`X-Frame-Options` and CSP `frame-ancestors`).

The Content-Security-Policy is described in `docs/frontend.md` and `Config\ContentSecurityPolicy`
(strict, nonces, module sources from the manifests, `reportOnly` to test a change).

## API

Token authentication only (`Authorization: Bearer`): the API never starts a session nor reads
its cookie and is excluded from CSRF; scopes are intersected with permissions (superadmins too);
app logins need the user's authenticator app or an emailed code (with a link to stop the
sign-in); wrong passwords count in Auth's `LoginThrottle`; CORS closed; plain HTTP refused in
production; the documentation only in the back office (`api.docs`). Details: `docs/api.md`.
Rotating `app.maskKey` changes every masked id of the API too; to revoke every token, delete the
`access_token` rows of `auth_identities` (or per user in Settings › API tokens).

## Rate limits

`Modules\Core\Security\RateLimiter` (CodeIgniter's Throttler, token bucket in the cache) with
named limits whose capacities are Core settings (Settings › Security, docs/settings.md):

| Limit | Setting | Default | Counted per | Used by |
|---|---|---|---|---|
| `backend` | `Core.rateLimitBackend` | 300 / minute | user | the `backend` filter chain |
| `apiToken` | `Core.rateLimitApiToken` | 120 / minute | API token | kpapy (session 10) |
| `apiIp` | `Core.rateLimitApiIp` | 60 / minute | IP | public API endpoints (session 10) |
| `feeds` | `Core.rateLimitFeeds` | 60 / hour | iCal token, IP | News RSS (`news/feed.xml`), iCal feeds (session 25) |
| `uploads` | `Core.rateLimitUploads` | 100 / hour | user | upload routes of the file picker (sessions 22–23; unused until then) |
| `forms` | `Core.rateLimitForms` | 10 / hour | IP | public forms (with captcha) |
| `widgets` | `Core.rateLimitWidgets` | 120 / minute | user | async widget URLs (session 16) |

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
