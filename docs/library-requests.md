# Library requests

Add a row here whenever something cannot be done with the public API of `siensis/kpcrudy` or
`siensis/kpapy`: never edit `vendor/` or copy their code, write the temporary solution in the
Core and record it below so it can be added to the library.

**kpcrudy 1.1.0 and kpapy 1.1.0** (released on 2026-11-09, installed in the `composer.lock` of
session 15) implement every request below that was open. The workarounds in the Core and the
modules still work and are replaced in a later session; new code uses the 1.1 features directly.

Gaps found while reviewing kpcrudy 1.0.0 and kpapy 1.0.0 for `docs/plan.md`:

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | Pluggable storage for `upload`/`image` fields (a storage adapter or upload-handler callback, MIME check, image re-encoding, private disk) | Specification "Storage" and "CRUDs with kpcrudy": uploads must go through the Core Storage service (allow-list + MIME, re-encoding, SVG refused, `files` metadata); kpcrudy writes to `uploadPath` (default `public/uploads`) itself | Do not use kpcrudy upload field types; use the Core file-picker field via `callbackField()` and save through the Storage service | Done in kpcrudy 1.1.0: `UploadHandler` (`Config::$uploadHandler`, field option `handler`) |
| kpapy | Pluggable storage for upload fields (same as above) | Same rules apply to files uploaded through the API; kpapy writes to `writable/uploads/kpapy` | No API upload fields in core resources; a custom operation that calls the Storage service when needed | Done in kpapy 1.1.0: `UploadHandler` (+ `UploadReader`, `UploadValidator`; `Config::$uploadHandler`, field option `handler`) |
| kpcrudy | Option to load the CRUD's JavaScript and CSS as external asset files instead of nonce'd inline `<script>`/`<style>` blocks | Specification "Front-end assets": no inline `<script>` code in the HTML | Accept the library's nonce'd blocks as the only exception (CSP still enforced); CI inline-code check scoped to project views | Done in kpcrudy 1.1.0: `$assetsMode = 'external'` + `kpcrudy:publish assets` |
| kpcrudy | Locale fallback for `setTranslations()` in lists, search, sorting and export (like kpapy's `localeFallbacks`) | Lists show an empty cell when a record has no translation in the list locale; demo data is Catalan only, and the spec requires a fallback chain | Use `listLocale` = `ca` (required language) unless the content is known to be complete in the user's locale | Done in kpcrudy 1.1.0: `setTranslations(..., ['fallbacks' => ...])` |
| kpcrudy | Translated fields in CSV import | Events administration requires CSV import, and event titles are translated | `callbackImport()` writes the Catalan translation row | Done in kpcrudy 1.1.0: one column per locale (`title:ca`), `exportTranslations()` |
| kpcrudy | Responsive list options: priority columns hidden on phones, card view on phones, row actions in a "⋯" menu, collapsible filters panel | Specification "Responsive design" (kpcrudy lists) | Theme CSS on the `bootstrap5` markup and a Core JS component; no template copies | Done in kpcrudy 1.1.0: `columnPriority()`, `rowActionsMenu()`, `mobileCards()` |
| kpcrudy | Searchable (AJAX) relation fields for large tables | Events audience "users field (searchable, AJAX)"; a dropdown of every user does not scale | `callbackField()` with a Core `data-component` that queries a permission-checked JSON endpoint | Done in kpcrudy 1.1.0: `setRelation(..., ['ajax' => true])` |
| kpcrudy | Tree display for self-referencing tables (indentation/order by parent) | Pages "list with tree indentation" | `callbackColumn()` prefixing the depth and a `defaultOrder()` on a materialised path column | Done in kpcrudy 1.1.0: `tree()` |

Found in session 09 (kpcrudy integration):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | `kpcrudy:make` options passed in `$params` (it reads `CLI::getOption()` only) | `make:module --crud` runs it from another command; `command()` passes options in `$params` | `make:module` runs `php spark kpcrudy:make …` as a child process | Done in kpcrudy 1.1.0: every command reads `command()` parameters |
| kpcrudy | A way to describe a CRUD's operations without running the controller action | `permissions:check` compares every CRUD with the catalogue | `CrudAuditor` runs the action with `CrudFactory::inspect()` and stops at `renderCrud()` | Done in kpcrudy 1.1.0: `Crud::capture()`, `Crud::captureController()`, `describe()` |
| kpcrudy | `Crud` accepting the CLI request (it requires `IncomingRequest`) | Building a CRUD from `spark` (`permissions:check`) | `CrudAuditor` injects a GET `IncomingRequest` while inspecting | Done in kpcrudy 1.1.0: empty GET request from the CLI, `setRequest()` |
| kpcrudy | Upload fields: `uploadPath` set to `writable/kpcrudy-unused-uploads` (session 09) | See the first row above | `make:module --crud` replaces guessed `image`/`upload` field types with a comment | Done in kpcrudy 1.1.0: `UploadHandler` (see the first row) |

Found in session 10 (kpapy integration):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpapy | `AuthRoutes::register()` with another login controller class | The skeleton extends `LoginController` (Auth's throttling, password-reset rule, refresh only for app tokens) | The Api module registers the same paths itself pointing to `Modules\Api\Controllers\LoginController` | Done in kpapy 1.1.0: `$loginController` parameter / `Config::$loginController` |
| kpapy | Scope check on `me/tokens` (`TokenController`) | Any token, even one limited to `x.list`, can list and revoke its user's other tokens | Accepted for now (it can only remove access, never add it); documented in `docs/api.md` | Done in kpapy 1.1.0: `tokens.manage` scope (`$tokensScope`, `$tokensScopeRequired`) |
| kpapy | Describing custom endpoints (a plain controller such as `me`) in the OpenAPI document | `GET api/v1/me` is missing from the documentation page | Documented in `docs/api.md` | Done in kpapy 1.1.0: route option `'openapi'`, `Generator::addPath()` |
| kpapy | Marking tokens on creation (`TokenService::create()` options, e.g. `secret2`) and recording the last IP address of a token | Personal tokens must survive a password change; the profile shows the last IP | The Api module updates `secret2` after creating the token; `TokenAuthenticator` writes `api_token_ips` | Done in kpapy 1.1.0: `TokenService::create(..., ['kind' => ...])` (`secret2`), event `kpapy.token.used`, `$trackTokenIps` |
| kpapy | Dummy password hash of the API login without null bytes (`LoginController::checkCredentials()` hashed `random_bytes(16)`, which bcrypt refuses with a `ValueError` about 6 % of the time) | `POST api/v1/auth/token` with an unknown email answered 500 instead of 401: an error page, a hint that the account does not exist, and an attempt not counted by `LoginThrottle` (the `ValueError` is not an `ApiException`); `ApiResourceTest::testTheLoginIsLimitedPerIp` failed at random in CI | None needed | Fixed in kpapy 1.0.1 (installed in session 14) |

Found in session 15 (Users module):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | Custom search and filter conditions on the list query: e.g. `searchCallback(fn (BaseBuilder $builder, string $text) => …)` added to the search `OR` group, and custom filters `addFilter($key, $label, $options, fn (BaseBuilder $builder, mixed $value) => …)` (dropdown or text) that act on list, search, export and bulk scope like `filters()`; values bound by the Query Builder, never interpolated | The users list (kpcrudy over Shield's `users`) cannot search by email (`auth_identities.secret`, the foreign key is in the other table, so `setRelation()` does not apply) nor filter by group (`auth_groups_users` stores the group name, so `setRelationNN()` does not apply, and an N-N field would also write memberships past the escalation rules) or by 2FA method (`user_two_factor`). The same need will appear in other CRUDs over Shield or with data in side tables | Search by user name only; filters on the `users` columns (`active`, `status` banned, `last_active` range); the group of a user is shown in the list and edited on the access screen | Done in kpcrudy 1.1.0: `searchCallback()`, `addFilter()` |

Found in session 17 (News module):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | Time zone of `datetime` fields: show and edit them in the site's (or the user's) zone and store UTC | Forms showed and saved UTC while lists show `Core.timezone`: a post scheduled at 09:00 in Barcelona went out at 10:00 or 11:00 (every CRUD with dates) | None (all dates were edited in UTC) | Done in kpcrudy 1.2.0 (`Config::$timezone`, `$storageTimezone`, `setTimezone()`, field option `'timezone' => false`); `CrudFactory::make()` sets the site's time zone (`Core.timezone`) |
| kpcrudy | `before*` hooks that can change translated values (e.g. `beforeInsert(fn (array $data, array $translations) => …)` returning both, or a `setPostedTranslations()`) | The News body is translated and must be sanitised with HTMLPurifier before it is stored; `postedTranslations()` is read-only (D-17-02) | News and Pages clean `kp_tr[*][body]` in the request before the CRUD reads it; the reader and public pages clean it again on output (tests: `PostsCrudTest`, `PagesCrudTest`) | Open — on hold (owner, 2026-11-12): the workaround is safe and tested |
| kpapy, kpcrudy | A scope condition that is a closure over the query builder (`where(fn (BaseBuilder $builder) => …)`), applied to list, details, operations and counts | Visibility rules are an `OR` of conditions with subqueries ("public, private, or one of my groups"), not one column and one value (D-17-07) | kpapy: none needed any more. kpcrudy: no back-office CRUD needs it yet (the News reader is a controller of its own); without it, a CRUD would need a hand-written subquery (`where('<table>.id IN', RawSql(…))`) kept in sync with the visibility rule, with a test per operation | kpapy: done in 1.2.0 (`where(Closure)`), used by `modules/News/Controllers/Api/Posts.php`. kpcrudy: **done in 1.3.0** (2026-11-12, same contract as kpapy: list, search, filters, counts, export, details, edit, delete, bulk actions, trash, custom operations; a saved record must stay in the scope). Installed in session 24 (`composer.json` `^1.3`), used by the Events administration |

Found in session 20 (Pages module):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | HTML attributes for a translated field without changing its type (e.g. a `setTranslations()` option `'attrs' => [field => [...]]`) | The body of News and Pages is a translated field turned into the rich-text editor (`data-component`); `fieldType('body', 'textarea', …)` takes it out of the language tabs and adds a main-table field | `fieldType('body', 'translation', ['attrs' => …])` (kpcrudy's internal type of translated fields, undocumented) (the most fragile workaround: `PostsCrudTest` and `PagesCrudTest` check every language's body carries the editor) | **Done in kpcrudy 1.3.0** (2026-11-12): `setTranslations(..., ['attrs' => [field => array|fn (string $locale, string $field): array]])`, editor-agnostic, documented with examples (a bundled WYSIWYG field type was not wanted: the Core has its own editor and sanitiser). After installing 1.3, News and Pages switch from `fieldType('body', 'translation', …)` to the option, and `RichTextEditor`'s docblock follows |
| kpcrudy | Tree lists with translated labels and a virtual path column checked under search and filters | `tree('parent_id', ['label' => 'title'])` works with a translated title; documenting it would make it a contract | Used as is (`modules/Pages/Controllers/Pages.php`) | Open (documentation) — on hold (owner, 2026-11-12) |

