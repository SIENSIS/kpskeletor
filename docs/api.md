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
| Resource names | `$this->crud('tasks-projects')` | `setResource('tasks-projects')` |
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
| `GET me` | `kpapy` | the token's user: masked id, name, email, language, time zone, colour mode, avatar URL, groups, 2FA method, the token's name, scopes and expiry |
| `GET me/tokens`, `DELETE me/tokens/{id}` | `kpapy` | the user's tokens (kpapy's `TokenController`); since kpapy 1.1 only tokens with unrestricted scopes (`*`) or the `tokens.manage` scope (`Config\Kpapy::$tokensScope`) |
| `GET openapi.json` | `kpapy:public` | only with the setting `Api.publicSpec` (404 otherwise) |

Every endpoint above also answers the CORS preflight (`OPTIONS`, `kpapy:public`). Outside
`api/v1`, `auth/api-login/{challenge}` is the "it was not me" page of the sign-in code email
(web form, CSRF, `ratelimit:forms`).

## Core endpoints

Each one is a kpapy resource with the same visibility rules as the web (decisions D-26-01 …
D-26-05). Ids are the back office's masked codes. Read-only unless stated.

| Endpoint | Permission (scope) | |
|---|---|---|
| `GET news[/{id}]` | `news-api.list` / `.show` | posts the user may read (public, private, their groups'), `read` flag; `?filter[type]=notice`, `?q=`, `?lang=` |
| `GET news/notices[?unread=1&limit=10]` | `news-api.list` | the user's notices like the dashboard widget (pinned, priority, date) |
| `POST news/{id}/read` | `news-api.read` | marks a readable post as read (`{"read": true, "marked": bool}`); unreadable → 404 |
| `GET events[/{id}]` | `events-api.list` / `.show` | events the user sees (public, private, `restricted` audience, calendar permissions and policies); recurrence as a rule |
| `GET events/occurrences?start=YYYY-MM-DD&end=YYYY-MM-DD[&calendars=events,news.notices][&category=<slug>]` | `events-api.list` | occurrences in a range (≤ `maxRangeDays`), recurrences expanded, providers' events (birthdays…) included; invalid range → 422 |
| `GET events/calendars` | `events-api.list` | calendars the user may see (`id`, `module`, `label`, `color`, `hidden` = their choice in the calendar view) |
| `GET notifications[/{id}]` | `core-notifications.list` / `.show` | the user's own notifications (bell), title/body/url in the request's language |
| `GET notifications/unread` | `core-notifications.list` | `{"unread": n}` |
| `POST notifications/{id}/read`, `POST notifications/read-all` | `core-notifications.read` | mark one or all as read |
| `GET public/news[/{id}]` | none (`kpapy:public`) | public posts only, with `url` on the public site |
| `GET public/events[/{id}\|/occurrences]` | none (`kpapy:public`) | public events only (no providers); 404 while the public calendar is off |

Module resources of the skeleton besides these: the tutorial module **Tasks** exposes
`api/v1/tasks/tasks` and `api/v1/tasks/projects` (full kpapy resources with the permissions of
their CRUDs, `tasks-tasks.*` and `tasks-projects.*`). `php spark kpapy:routes` lists every route
of the API, and the documentation page (below) describes each one.

Every group holds `news-api.*`, `events-api.*` and `core-notifications.*`; a token narrows them
with its scopes (superadmins included): a token limited to `news-api.list` lists news but gets 403
on `POST news/{id}/read` (acceptance criterion 10). Public endpoints are `GET` only, ask no
permission, ignore any token (they never show more) and are limited per IP
(`Core.rateLimitApiIp`, `429` + `Retry-After`). Marking as read is personal state and, as in the
back office, is not written to the activity log (D-26-03).

```bash
curl -s "https://example.com/api/v1/events/occurrences?start=2026-12-01&end=2027-01-01" -H "Authorization: Bearer $TOKEN"
curl -s -X POST https://example.com/api/v1/notifications/read-all -H "Authorization: Bearer $TOKEN"
curl -s "https://example.com/api/v1/public/news?per_page=5"
```

## Conventions

These come from kpapy (`vendor/siensis/kpapy/README_EN.md`, "Response format", and
`docs/functions_EN.md`, "Listing" and "Operations and HTTP"); the skeleton keeps its defaults
except where noted.

**Envelope.** A list is `{"data": [...], "meta": {...}, "links": {...}}`; a single record is
`{"data": {...}, "links": {"self": "…"}}`. Ids are masked codes, dates are ISO 8601 with a time
zone, decimals are strings, booleans `true`/`false`.

```json
{
  "data": [{"id": "Xk3f9QaLm2", "slug": "closed-on-monday", "type": "notice", "title": "Closed on Monday", "read": false}],
  "meta": {"per_page": 10, "page": 1, "total": 42, "pages": 5},
  "links": {"self": "…?page=1", "first": "…?page=1", "prev": null, "next": "…?page=2", "last": "…?page=5"}
}
```

**Query parameters of a list:**

| Parameter | Example | |
|---|---|---|
| `page`, `per_page` | `?page=2&per_page=50` | 25 per page by default, at most 100 (`Config\Kpapy::$perPage`, `$maxPerPage`) |
| `sort` | `?sort=-published_at,title` | `-` = descending; only sortable fields |
| `q` | `?q=holidays` | search in the resource's search fields |
| `filter[field]` | `?filter[type]=notice` | equality; operators `filter[field][op]`: `eq`, `ne`, `lt`, `lte`, `gt`, `gte`, `in`, `between`, `like`, `null`, `notnull` |
| `fields` | `?fields=title,published_at` | sparse fields |
| `include` | `?include=project` | declared relations only |
| `lang` | `?lang=ca`, `?lang=*` | translated fields: one language (the request's by default) or all |
| `trashed` | `?trashed=with` | resources with a trash only |

An unknown field, operator or invalid value is `400`. A resource with `cursorPagination()` uses
`?cursor=` and `meta.next_cursor` instead of pages.

**Writes.** `POST` (201 + `Location`), `PATCH` (partial) / `PUT`, `DELETE` (204), custom
operations `POST /r/{id}/{key}`; JSON bodies (`Content-Type: application/json`) or
`multipart/form-data`. Only writable fields are read from the body.

**Errors.** `application/problem+json` (RFC 9457) with a stable `code`:

```json
{"type": "about:blank", "title": "Invalid data", "status": 422, "code": "validation", "errors": {"slug": ["…"]}}
```

`bad-request` (400), `unauthorized` (401), `forbidden` (403: no permission or outside the token's
scopes), `not-found` (404, also for records the user may not see and forged ids),
`method-not-allowed` (405), `conflict` / `operation-failed` (409), `precondition-failed` (412),
`payload-too-large` (413), `unsupported-media-type` (415), `validation` (422),
`too-many-requests` (429 + `Retry-After`), `server-error` (500). The skeleton adds
`password-reset-required` (403) on the app login.

**ETags.** Every record has an `ETag`; lists have the hash of the body. `If-None-Match` → `304`;
`PATCH`, `PUT` and `DELETE` with `If-Match` → `412` when the record changed (not required:
`requireIfMatch` keeps kpapy's default `false`).

## A module's resources

`php spark make:module Library --crud=library_books --api` writes all of it for a new module:
`kpapy:make` writes `Controllers/Api/Books.php` (resource `library-books`, the CRUD's) and the route
`api/v1/library/books`, the manifest declares it in `$api` and `Tests/LibraryApiTest.php` checks
the token and its scopes (D-26-06). By hand:

the tutorial module's projects:

```php
// modules/Tasks/Config/Routes.php (written by kpapy:make or make:module --api)
Resource::routes($routes, 'api/v1/tasks/projects', '\Modules\Tasks\Controllers\Api\Projects::index', ['filter' => 'kpapy']);
```

```php
// modules/Tasks/Controllers/Api/Projects.php (shortened)
public function index(): ResponseInterface
{
    return (new Resource())
        ->setTable('task_projects')
        ->setResource('tasks-projects')      // the same name as the CRUD
        ->setTranslations('task_project_translations', 'project_id', 'locale', ['name', 'description'], ContentTranslations::codes(), ContentTranslations::apiOptions())
        ->fields(['id', 'slug', 'name', 'description', 'color', 'ends_on', 'active', 'created_at', 'updated_at'])
        ->listFields(['id', 'slug', 'name', 'color', 'ends_on', 'active'])
        ->writeFields(['slug', 'name', 'description', 'color', 'ends_on', 'active'])
        ->filters(['active'])
        ->searchFields(['name', 'slug'])
        ->unsetTrash()
        ->describe(lang('Tasks.api.projectsSummary'), lang('Tasks.api.projectsDescription'))
        ->render();
}
```

```php
// modules/Tasks/Config/Module.php
public array $api = [
    'projects' => ['path' => 'tasks/projects', 'handler' => '\Modules\Tasks\Controllers\Api\Projects::index'],
];
```

`setResource()` normally names the CRUD's resource so one grant covers both. News is the
exception: its API uses its own resource `news-api` (D-17-01), so every group can read news
through the API without the editors' `news-posts` permissions, limited to what the reader screen
shows (`NewsVisibility` through kpapy's `where(Closure)`).

- `$api` is metadata: `modules:sync` and `permissions:check` refuse an entry without its route
  (`ApiRouteChecker`). Every `api/v1/` route needs kpapy's filter (`kpapy`, `kpapy:optional`,
  `kpapy:hmac`; `kpapy:public` only for GET; `kpapy:login` only on `auth/token*`), checked by
  `RoutesSecurityTest` and `permissions:check`. A disabled module answers 404.
- Every operation the resource can ask about must be declared in the manifest (outside
  production, an undeclared one throws `UndeclaredPermissionException`). `permissions:check`
  also checks it ahead of time (`ApiAuditor`): every kpapy resource route must be declared in a
  `$api`, render a `Kpapy\Resource` (kpapy's describe mode, nothing touches the data) and ask
  only about declared operations; public resources (`kpapy:public`) ask none.
- A public resource (no token) registers only `GET` routes with `['filter' => 'kpapy:public',
  'kpapy' => true]` (the option makes it a resource for the OpenAPI document and the audit),
  `setPermissionResolver(null)` and `setPermission('list'|'show', true)`; copy
  `modules/News/Controllers/Api/PublicNews.php`.
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
- **Logging:** logins, refusals, token creation and revocation are in the activity log; read
  markers are not (D-26-03).

## Documentation page

`Settings › API documentation` (`backend/api/docs`, permission `api.docs`, held by administrators
through `api.*`; grant it to other groups in the permission matrix) is kpapy's page
(`Modules\Api\Controllers\Docs`, only the `api/v1` routes): every endpoint grouped by resource,
with its parameters, fields, filters and errors, generated from the routes, so a new module
resource appears after `php spark modules:sync` without writing anything.

- **Try it** (`docsTryIt`, on in every environment): the panel at the top takes an access token
  (kept only in the tab's memory) or signs in like an app (password, then the 2FA or emailed
  code); each endpoint has a form with its path, query and header parameters, a free field for
  other query parameters and a JSON body for writes. **Send** makes a **real** request (same
  permissions, scopes, rate limits and data as any client) and shows the status, the useful
  headers (`ETag`, `Location`, `Retry-After`) and the body; **Copy as curl** copies the same request.
  The script is an external file (`backend/api/docs/try-it.js`), so the page works under the
  strict CSP.
- **Downloading the OpenAPI document:** the link "OpenAPI document (JSON)" at the top of the page
  (`backend/api/docs/openapi.json`, OpenAPI 3.1, same permission) for Postman, Swagger UI or a
  code generator.
- **Publishing it:** the setting `Api.publicSpec` (Settings › Security, group API; off by default)
  also serves the document without a token at `/api/v1/openapi.json` (`kpapy:public`, limited per
  IP); while it is off that URL answers 404. The page itself is never public. `php spark modules:sync` writes a static copy to
`writable/api/openapi.json` (never served; without `servers`; rewritten only when it changes,
D-26-04); `php spark kpapy:openapi --prefix api/v1 --output <file>` writes one anywhere.

### Example: listing news with a personal token

1. **Profile › My API tokens** (`backend/profile/api-tokens`): name "Intranet app", scope
   `news-api.list`, 30 days; confirm the password (re-authentication) and copy the secret, shown
   once.
2. **Settings › API documentation**: paste the token in the panel at the top.
3. Open **`GET /api/v1/news`**, fill `per_page` = `10` and `sort` = `-published_at`, and in
   "Other query parameters" `filter[type]=notice&lang=ca`. **Send**:

   ```
   200 OK   ETag: "…"
   {
     "data": [
       {"id": "Xk3f9QaLm2", "slug": "closed-on-monday", "type": "notice", "priority": "high", "pinned": true,
        "visibility": "private", "category": "general", "title": "Tancat dilluns", "summary": "…",
        "cover_url": null, "published_at": "2026-11-16T09:00:00+01:00", "expires_at": null, "read": false}
     ],
     "meta": {"per_page": 10, "page": 1, "total": 3, "pages": 1},
     "links": {"self": "…", "first": "…", "prev": null, "next": null, "last": "…"}
   }
   ```

   Only the posts the token's user may read are listed (public, private, their groups').
4. **Copy as curl** gives the same request for a script:

   ```bash
   curl -X GET 'https://example.com/api/v1/news?per_page=10&sort=-published_at&filter[type]=notice&lang=ca' \
     -H 'Accept: application/json' \
     -H 'Authorization: Bearer 3f9c…'
   ```

With this token `GET /api/v1/news/{id}` answers 403 (`news-api.show` is not in its scopes), and
so does `POST /api/v1/news/{id}/read`. Other examples:

```bash
curl -s https://example.com/api/v1/me -H "Authorization: Bearer $TOKEN"
curl -s "https://example.com/api/v1/news/notices?unread=1" -H "Authorization: Bearer $TOKEN"
```

## Tests

Copy `modules/Api/Tests/ApiTestCase.php` (JSON requests without a session, a token helper, a
fresh cache) and `ApiResourceTest.php` (scopes, masked ids, problem+json, ETags, rate limits) for
a module's resource; `tests/_support/Api/SampleNotesApi.php` is the smallest resource. The Core
endpoints' tests: `modules/News/Tests/{NewsApiTest,PublicNewsApiTest}.php`,
`modules/Events/Tests/EventsApiTest.php`, `modules/Core/Tests/{NotificationsApiTest,ApiAuditorTest}.php`.
