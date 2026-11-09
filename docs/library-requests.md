# Library requests

Add a row here whenever something cannot be done with the public API of `siensis/kpcrudy` or
`siensis/kpapy`: never edit `vendor/` or copy their code, write the temporary solution in the
Core and record it below so it can be added to the library.

Gaps found while reviewing kpcrudy 1.0.0 and kpapy 1.0.0 for `docs/plan.md`:

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | Pluggable storage for `upload`/`image` fields (a storage adapter or upload-handler callback, MIME check, image re-encoding, private disk) | Specification "Storage" and "CRUDs with kpcrudy": uploads must go through the Core Storage service (allow-list + MIME, re-encoding, SVG refused, `files` metadata); kpcrudy writes to `uploadPath` (default `public/uploads`) itself | Do not use kpcrudy upload field types; use the Core file-picker field via `callbackField()` and save through the Storage service | Open |
| kpapy | Pluggable storage for upload fields (same as above) | Same rules apply to files uploaded through the API; kpapy writes to `writable/uploads/kpapy` | No API upload fields in core resources; a custom operation that calls the Storage service when needed | Open |
| kpcrudy | Option to load the CRUD's JavaScript and CSS as external asset files instead of nonce'd inline `<script>`/`<style>` blocks | Specification "Front-end assets": no inline `<script>` code in the HTML | Accept the library's nonce'd blocks as the only exception (CSP still enforced); CI inline-code check scoped to project views | Open |
| kpcrudy | Locale fallback for `setTranslations()` in lists, search, sorting and export (like kpapy's `localeFallbacks`) | Lists show an empty cell when a record has no translation in the list locale; demo data is Catalan only, and the spec requires a fallback chain | Use `listLocale` = `ca` (required language) unless the content is known to be complete in the user's locale | Open |
| kpcrudy | Translated fields in CSV import | Events administration requires CSV import, and event titles are translated | `callbackImport()` writes the Catalan translation row | Open |
| kpcrudy | Responsive list options: priority columns hidden on phones, card view on phones, row actions in a "⋯" menu, collapsible filters panel | Specification "Responsive design" (kpcrudy lists) | Theme CSS on the `bootstrap5` markup and a Core JS component; no template copies | Open |
| kpcrudy | Searchable (AJAX) relation fields for large tables | Events audience "users field (searchable, AJAX)"; a dropdown of every user does not scale | `callbackField()` with a Core `data-component` that queries a permission-checked JSON endpoint | Open |
| kpcrudy | Tree display for self-referencing tables (indentation/order by parent) | Pages "list with tree indentation" | `callbackColumn()` prefixing the depth and a `defaultOrder()` on a materialised path column | Open |

Found in session 09 (kpcrudy integration):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | `kpcrudy:make` options passed in `$params` (it reads `CLI::getOption()` only) | `make:module --crud` runs it from another command; `command()` passes options in `$params` | `make:module` runs `php spark kpcrudy:make …` as a child process | Open |
| kpcrudy | A way to describe a CRUD's operations without running the controller action | `permissions:check` compares every CRUD with the catalogue | `CrudAuditor` runs the action with `CrudFactory::inspect()` and stops at `renderCrud()` | Open |
| kpcrudy | `Crud` accepting the CLI request (it requires `IncomingRequest`) | Building a CRUD from `spark` (`permissions:check`) | `CrudAuditor` injects a GET `IncomingRequest` while inspecting | Open |
| kpcrudy | Upload fields: `uploadPath` set to `writable/kpcrudy-unused-uploads` (session 09) | See the first row above | `make:module --crud` replaces guessed `image`/`upload` field types with a comment | Open |

Found in session 10 (kpapy integration):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpapy | `AuthRoutes::register()` with another login controller class | The skeleton extends `LoginController` (Auth's throttling, password-reset rule, refresh only for app tokens) | The Api module registers the same paths itself pointing to `Modules\Api\Controllers\LoginController` | Open |
| kpapy | Scope check on `me/tokens` (`TokenController`) | Any token, even one limited to `x.list`, can list and revoke its user's other tokens | Accepted for now (it can only remove access, never add it); documented in `docs/api.md` | Open |
| kpapy | Describing custom endpoints (a plain controller such as `me`) in the OpenAPI document | `GET api/v1/me` is missing from the documentation page | Documented in `docs/api.md` | Open |
| kpapy | Marking tokens on creation (`TokenService::create()` options, e.g. `secret2`) and recording the last IP address of a token | Personal tokens must survive a password change; the profile shows the last IP | The Api module updates `secret2` after creating the token; `TokenAuthenticator` writes `api_token_ips` | Open |
| kpapy | Dummy password hash of the API login without null bytes (`LoginController::checkCredentials()` hashed `random_bytes(16)`, which bcrypt refuses with a `ValueError` about 6 % of the time) | `POST api/v1/auth/token` with an unknown email answered 500 instead of 401: an error page, a hint that the account does not exist, and an attempt not counted by `LoginThrottle` (the `ValueError` is not an `ApiException`); `ApiResourceTest::testTheLoginIsLimitedPerIp` failed at random in CI | None needed | Fixed in kpapy 1.0.1 (installed in session 14) |

Found in session 15 (Users module):

| Library | Missing feature | Why it is needed | Workaround | Status |
|---|---|---|---|---|
| kpcrudy | Custom search and filter conditions on the list query: e.g. `searchCallback(fn (BaseBuilder $builder, string $text) => …)` added to the search `OR` group, and custom filters `addFilter($key, $label, $options, fn (BaseBuilder $builder, mixed $value) => …)` (dropdown or text) that act on list, search, export and bulk scope like `filters()`; values bound by the Query Builder, never interpolated | The users list (kpcrudy over Shield's `users`) cannot search by email (`auth_identities.secret`, the foreign key is in the other table, so `setRelation()` does not apply) nor filter by group (`auth_groups_users` stores the group name, so `setRelationNN()` does not apply, and an N-N field would also write memberships past the escalation rules) or by 2FA method (`user_two_factor`). The same need will appear in other CRUDs over Shield or with data in side tables | Search by user name only; filters on the `users` columns (`active`, `status` banned, `last_active` range); the group of a user is shown in the list and edited on the access screen | Open |
