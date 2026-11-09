# JSON API

The application exposes a versioned JSON API under `/api/v1`, built with
[kpapy](../vendor/siensis/kpapy/README_EN.md) (specification "JSON API (kpapy)"). Read the
library's own docs first: `vendor/siensis/kpapy/docs/functions_EN.md` (every method and option)
and `docs/howto_EN.md` (recipes). This page is what the skeleton adds.

The API core lives in the **Api** module (`modules/Api/`, a core module that cannot be disabled;
decision D-10-01). Modules add their own resources.

## What the Core shares with the back office

| | Back office (kpcrudy) | API (kpapy) |
|---|---|---|
| Permission resolver | `CrudPermissions` | the same class (`Config\Kpapy::$permissionResolver`) |
| Deny by default | yes | yes |
| Resource names | `CrudFactory::make('news-posts')` | `setResource('news-posts')` |
| Masked ids | `app.maskKey`, context = table | the same key and context |

So a grant in the permission matrix applies to the web and the API, and a record has the same id
in both (`ApiResourceTest::testARecordHasTheSameMaskedIdInTheWebAndTheApi`). Never give a resource
a custom `maskIds($context)` on one side only.

## Authentication

Every request carries a Shield access token: `Authorization: Bearer <token>`. The API never reads
the session cookie, never starts a session (the `authsession` filter skips `api/*`) and is
excluded from CSRF. **Scopes** are permission names (`news-posts.list`), resource wildcards
(`news-posts.*`) or `*`; a token can do the **intersection** of its scopes and its user's
permissions at the time of each request, superadmins included.

### Personal tokens

`Profile › My API tokens` (`backend/profile/api-tokens`, permission `api.tokens`, granted to
admins and editors by default): name, scopes (`*` or permissions the user holds now), expiry (7,
30, 90 or 365 days; never unlimited). Creating one requires re-authentication (`reauth`); the
secret is shown once. The list shows scopes, last use, last IP address and expiry; revoking works
only on the user's own tokens. Personal tokens are marked (`auth_identities.secret2 = 'personal'`),
so a password change keeps them (it revokes the app-login tokens), and they cannot be refreshed.

Administrators see and revoke every user's tokens in `Settings › API tokens`
(`backend/api/tokens`, a kpcrudy CRUD: `api.list`, `api.show`, `api.delete`).

From the console (library command): `php spark kpapy:token ana@example.com --name ERP --scopes
news-posts.list --days 90`.

### App login with a second factor

```http
POST /api/v1/auth/token
Content-Type: application/json

{"email": "ana@example.com", "password": "…", "device_name": "Ana's phone"}
```

```json
{"data": {"challenge": "a31b…", "method": "email", "expires_in": 600, "message": "We have sent a sign-in code to your email."}}
```

```http
POST /api/v1/auth/token/verify
Content-Type: application/json

{"challenge": "a31b…", "code": "123456"}
```

```json
{"data": {"token": "df16…", "token_type": "Bearer", "expires_at": "2026-12-05T17:25:36+00:00", "scopes": ["*"]}}
```

- `method` is `totp` for users of an authenticator app (their code or one of their recovery
  codes) and `email` for everybody else: users whose 2FA method is the emailed code and users
  without 2FA (decision D-10-02). kpapy's `web` method is not used.
- The email names the device, the IP address and the time and links to
  `auth/api-login/<challenge>`, where the user stops the sign-in ("it was not me").
- Codes are single use, valid 10 minutes, and 5 wrong codes close the challenge. TOTP codes are
  checked by the Auth module (a time step is never accepted twice, web and API alike).
- Wrong passwords count in Auth's `LoginThrottle` (per IP and per account, shared with the web
  login form) besides kpapy's `rateLimitLogin`; answers never reveal whether an account exists.
  Users who must change their password get `403 password-reset-required`.
- `POST /api/v1/auth/token/refresh` replaces an app-login token; `DELETE /api/v1/auth/token`
  signs out. App-login tokens last `Api.appTokenDays` (30).

### Endpoints of the Api module

| Endpoint | Filter | |
|---|---|---|
| `POST auth/token`, `POST auth/token/verify` | `kpapy:login` | app login |
| `POST auth/token/refresh`, `DELETE auth/token` | `kpapy` | refresh, sign out |
| `GET me` | `kpapy` | the token's user: masked id, name, email, language, time zone, colour mode, groups, 2FA method, the token's name, scopes and expiry |
| `GET me/tokens`, `DELETE me/tokens/{id}` | `kpapy` | the user's tokens (kpapy's `TokenController`); since kpapy 1.1 only tokens with unrestricted scopes (`*`) or the `tokens.manage` scope (`Config\Kpapy::$tokensScope`) |
| `GET openapi.json` | `kpapy:public` | only with the setting `Api.publicSpec` |

## A module's resources

```php
// modules/News/Config/Routes.php (written by kpapy:make or make:module --api)
\Kpapy\Resource::routes($routes, 'api/v1/news', '\Modules\News\Controllers\Api\Posts::index', ['filter' => 'kpapy']);
```

```php
// modules/News/Controllers/Api/Posts.php
public function index(): ResponseInterface
{
    return (new Resource())
        ->setTable('news_posts')
        ->setResource('news-posts')          // the same name as the CRUD
        ->fields(['id', 'title', 'summary', 'published_at'])
        ->readOnly()
        ->render();
}
```

```php
// modules/News/Config/Module.php
public array $api = [
    'news' => ['path' => 'news', 'handler' => '\Modules\News\Controllers\Api\Posts::index'],
];
```

- `$api` is metadata: `modules:sync` and `permissions:check` refuse an entry without its route
  (`ApiRouteChecker`). Every `api/v1/` route needs kpapy's filter (`kpapy`, `kpapy:optional`,
  `kpapy:hmac`; `kpapy:public` only for GET; `kpapy:login` only on `auth/token*`), checked by
  `RoutesSecurityTest` and `permissions:check`. A disabled module answers 404.
- Every operation the resource can ask about must be declared in the manifest (outside
  production, an undeclared one throws `UndeclaredPermissionException`).
- No kpapy upload fields: `uploadPath` points at an unused folder (library request); files go
  through the Storage service.

## Cross-cutting behaviour

- **Errors:** `application/problem+json` with a stable `code`, titles in the request's language
  (`Accept-Language`: ca, es, en; the `apilocale` filter), no details outside development.
- **Rate limits:** `Core.rateLimitApiToken` per token and minute, `Core.rateLimitApiIp` per IP on
  routes without a token, `429` with `Retry-After`.
- **CORS:** closed; `Api.corsOrigins` lists full origins (`https://app.example.com`, never `*`).
- **ETags:** `If-None-Match` → `304`; `If-Match` on writes → `412` when the record changed (not
  required by default).
- **HTTPS:** plain HTTP is refused in production (`requireHttps`); behind a proxy configure
  `Config\App::$proxyIPs`.
- **Logging:** logins, refusals, token creation and revocation go to the application log until
  the activity log (session 12).

## Documentation page

`Settings › API documentation` (`backend/api/docs`, permission `api.docs`) is kpapy's page with
every endpoint and **Try it**: paste a token or sign in like an app, send real requests (same
permissions, scopes and rate limits), copy them as `curl`. `backend/api/docs/openapi.json` is the
OpenAPI 3.1 document. Nothing is public unless `Api.publicSpec` is on, which also serves it at
`/api/v1/openapi.json`. A static copy: `php spark kpapy:openapi --prefix api/v1 --output
writable/openapi.json`.

### Example: listing with a personal token

```bash
curl -s https://example.com/api/v1/me -H "Authorization: Bearer $TOKEN"
curl -s "https://example.com/api/v1/news?sort=-published_at&per_page=10" -H "Authorization: Bearer $TOKEN"   # session 17+
```

## Tests

Copy `modules/Api/Tests/ApiTestCase.php` (JSON requests without a session, a token helper, a
fresh cache) and `ApiResourceTest.php` (scopes, masked ids, problem+json, ETags, rate limits) for
a module's resource; `tests/_support/Api/SampleNotesApi.php` is the smallest resource.
