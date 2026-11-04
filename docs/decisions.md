# Decisions

Decisions here override `docs/specification.md` when they disagree (the specification is updated
when a decision is applied). Open questions come from `docs/plan.md` ("Contradictions and open
questions") and from each session's report; they are answered with `deploy/decide.md`.

| ID | Session | Title | Status |
|---|---|---|---|
| D-00-01 | 00 | Branch names and pushing to `main` | Decided |
| D-00-02 | 00 | Session 36 finish text | Accepted (proposal) |
| D-00-03 | 00 | Module discovery with a single `Modules\` namespace | Decided |
| D-00-04 | 00 | `modules/Events/Config/Events.php` | Accepted (proposal) |
| D-00-05 | 00 | `Config\Modules` and `Modules.*` settings | Decided |
| D-00-06 | 00 | `setting()` and manifest defaults | Accepted (proposal) |
| D-00-07 | 00 | `Auth.minPasswordLength` vs Shield | Decided |
| D-00-08 | 00 | API routes "registered by `modules:sync`" | Accepted (proposal) |
| D-00-09 | 00 | `kpcrudy:make` output vs Core conventions | Accepted (proposal) |
| D-00-10 | 00 | Order of work | Accepted (proposal) |
| D-00-11 | 00 | Bootstrap instructions already applied | Accepted (proposal) |
| D-00-12 | 00 | Migrations of third-party packages | Accepted (proposal) |
| D-00-13 | 00 | Per-user 2FA with Shield | Accepted (proposal) |
| D-00-14 | 00 | Remember-me vs absolute session lifetime | Decided |
| D-00-15 | 00 | "Email never inline" | Accepted (proposal) |
| D-00-16 | 00 | Shield wildcards are now richer | Accepted (proposal) |
| D-00-17 | 00 | "My …" screens behind `can:` | Decided |
| D-00-18 | 00 | Permission names outside `<module>-<resource>` | Decided |
| D-00-19 | 00 | Building Shield's `AuthGroups` from tables | Accepted (proposal) |
| D-00-20 | 00 | The resolver needs a user, not the session | Accepted (proposal) |
| D-00-21 | 00 | Default grants vs implied permissions | Accepted (proposal) |
| D-00-22 | 00 | `users_permissions` | Accepted (proposal) |
| D-00-23 | 00 | Uploads in kpcrudy/kpapy bypass the Storage service | Accepted (proposal) |
| D-00-24 | 00 | Inline `<script>`/`<style>` in library views | Accepted (proposal) |
| D-00-25 | 00 | `style=""` ban vs real components and content | Accepted (proposal) |
| D-00-26 | 00 | Cache busting of ES modules | Accepted (proposal) |
| D-00-27 | 00 | PWA details | Accepted (proposal) |
| D-00-28 | 00 | CodeIgniter's `secureheaders` filter | Accepted (proposal) |
| D-00-29 | 00 | Outbound traffic that is not HTTPS-through-`OutboundClient` | Accepted (proposal) |
| D-00-30 | 00 | Route-security test for the API | Accepted (proposal) |
| D-00-31 | 00 | Acceptance criterion 2 before session 32 | Accepted (proposal) |
| D-00-32 | 00 | Child theme inside the `Tasks` module | Accepted (proposal) |
| D-00-33 | 00 | The web server writing into `public/` and the project root | Decided |
| D-00-34 | 00 | New dependencies vs "ask before adding a dependency" | Accepted (proposal) |
| D-00-35 | 00 | Rich text before the editor exists | Accepted (proposal) |
| D-00-36 | 00 | News group visibility as `multiselect` | Accepted (proposal) |
| D-00-37 | 00 | Profile fields and birthdays have no table | Accepted (proposal) |
| D-00-38 | 00 | Groups as data | Accepted (proposal) |
| D-00-39 | 00 | Upload folders | Accepted (proposal) |
| D-00-40 | 00 | Translated content in kpcrudy | Accepted (proposal) |
| D-00-41 | 00 | Visibility names and calendar permissions | Accepted (proposal) |
| D-00-42 | 00 | Where ordinary users read private news | Decided |
| D-00-43 | 00 | `about` and `cache:clear` hooks | Accepted (proposal) |
| D-00-44 | 00 | Path placeholders | Accepted (proposal) |
| D-00-45 | 00 | Restoring `.env` and keys | Accepted (proposal) |
| D-00-46 | 00 | AES-256-GCM for large archives | Accepted (proposal) |
| D-00-47 | 00 | Backup password on the command line | Accepted (proposal) |
| D-00-48 | 00 | Release signing for Updates | Decided |
| D-00-49 | 00 | `composer audit` offline | Accepted (proposal) |
| D-00-50 | 00 | The `--probe` targets the site itself | Accepted (proposal) |
| D-00-51 | 00 | File permissions of CodeIgniter's own files | Accepted (proposal) |
| D-00-52 | 00 | `app.maskKey` fallback | Accepted (proposal) |
| D-00-53 | 00 | Captcha implementation | Accepted (proposal) |
| D-00-54 | 00 | `If-Match` for writes | Accepted (proposal) |
| D-00-55 | 00 | Brand-new required libraries | Accepted (proposal) |
| D-00-56 | 00 | Tasks module vs scheduled tasks naming | Accepted (proposal) |

## D-00-01 — Branch names and pushing to `main`

- **Where:** `deploy/*.md` "Before starting" steps 2 and 4, `deploy/next.md`, `deploy/README.md`
- **Problem:** Sessions create `session-NN-<slug>` and push `main`, but cloud environments assign their own branch (this one is `claude/modest-goldberg-jjs8kp`) and often allow pushing only to it. `next.md` finds unfinished sessions by `session-NN-*` branches.
- **Proposal:** identify sessions by the pull request title "Session NN: …" and by `docs/progress.md` (record the branch name there); when `main` cannot be pushed, stop and ask the owner to merge the pull request (already allowed by the files), and never force-push.
- **Alternatives:** A) Always create `session-NN-<slug>` even when the environment assigns another branch (may fail to push).
- **Status:** Decided
- **Decision (2026-11-04):** «Per títol de PR (recomanada)». Sessions are identified by the pull request title "Session NN: …" and by `docs/progress.md` (which records the real branch name); the environment's assigned branch is accepted; when `main` cannot be pushed the session stops and asks the owner to merge the pull request; never force-push. **Must change:** `deploy/next.md`, `deploy/continue.md` and the shared "Before starting"/"Finish" steps of `deploy/NN-*.md`.
- **Applied in:** —

## D-00-02 — Session 36 finish text

- **Where:** `deploy/36-release.md`
- **Problem:** Its Scope says it merges itself into `main`, but the shared *Finish* section says "The next session merges it".
- **Proposal:** follow the Scope (merge after the pull request), and treat the Finish line as boilerplate.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-03 — Module discovery with a single `Modules\` namespace (decide)

- **Where:** spec "Directory layout" (Autoloading/Discovery), `CLAUDE.md`
- **Problem:** CodeIgniter 4.7's `FileLocator::search()` only looks at `<namespace root>/Config/Routes.php` (also `Events`, `Registrar`, `Commands/`), so `Modules\` → `modules/` finds nothing inside `modules/News/`. Registering every module statically in `app/Config/Autoload.php` breaks acceptance 2 ("without touching any core file").
- **Proposal:** keep `Modules\`/`Themes\` for class autoloading and add one PSR-4 entry per module (`Modules\News` → `modules/News`) from a **generated static file** (`app/Config/ModuleNamespaces.php`) written by `make:module`, `modules:sync` and `modules:install`, and loaded by `Autoload.php`; no scanning at runtime.
- **Alternatives:** A) Scan `modules/*/Config/Module.php` on every boot (no command needed, disk access per request, against "no scan to make a route reachable"). B) One `autoload.psr-4` entry per module in `composer.json` + `composer dump-autoload` (touches a core file per module).
- **Status:** Decided
- **Decision (2026-11-04):** «Fitxer generat (recomanada)». A generated static file `app/Config/ModuleNamespaces.php` (one PSR-4 namespace per module, e.g. `Modules\\News` → `modules/News`) is written by `make:module`, `modules:sync` and `modules:install` and merged by `app/Config/Autoload.php`; no scanning at runtime. **Must change:** specification "Directory layout"/discovery, session 01 scope, `CLAUDE.md` (copying a module by hand requires `php spark modules:sync`).
- **Applied in:** —

## D-00-04 — `modules/Events/Config/Events.php`

- **Where:** spec "Configuration and settings" ("every module may ship `Config/<Name>.php`") and the `Events` module
- **Problem:** CodeIgniter includes every discovered `Config/Events.php` as an event-listener file, so a config class with that name is included as a script (possible "cannot redeclare class"). The same pattern makes `setting('Events.x')` resolve to `Config\Events`.
- **Proposal:** the Events module uses `Config/Calendar.php` and the settings namespace `Calendar`/`Events` without a config class of that name; the manifest validator refuses module config files named `Events`, `Routes`, `Registrar`, `Filters`, `Modules`, `Services`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-05 — `Config\Modules` and `Modules.*` settings (decide)

- **Where:** spec "Package validation" (publisher keys "listed in `Config\Modules`"), "Demo data" (`Modules.allowDemoInProduction`)
- **Problem:** `app/Config/Modules.php` is CodeIgniter's discovery configuration.
- **Proposal:** put package keys and module options in `Modules\Core\Config\Packages` (`Config\Packages` override in `app/Config`), and name the setting `Packages.allowDemoInProduction` or `Core.allowDemoInProduction`.
- **Alternatives:** A) Everything in `Config\\Core` (`Core.allowDemoInProduction`, `Core.publisherKeys`). B) Extend CodeIgniter's `Config\\Modules` as the specification says (conflict risk with framework updates).
- **Status:** Decided
- **Decision (2026-11-04):** «Config\\Packages (recomanada)». Publisher keys and module package options live in `Modules\\Core\\Config\\Packages` (overridable by `app/Config/Packages.php` and `.env`); the demo setting is `Packages.allowDemoInProduction`. **Must change:** specification "Package validation" and "Demo data"; sessions 02 and 29.
- **Applied in:** —

## D-00-06 — `setting()` and manifest defaults

- **Where:** spec "Configuration and settings"
- **Problem:** `codeigniter4/settings` falls back to the property of a config class with that short name; modules declare defaults in the manifest, not in a config class, so `setting('News.perPage')` returns `null` before a value is saved.
- **Proposal:** the Core registers manifest defaults (a `ModuleSettings` handler or a Core `settings()` wrapper used everywhere), and a test checks every declared key returns its default.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-07 — `Auth.minPasswordLength` vs Shield (decide)

- **Where:** spec "Authentication", session 05
- **Problem:** Shield reads `Config\Auth::$minimumPasswordLength` (default 8); a new key `minPasswordLength` is ignored by Shield's validators. Shield 1.4.1 reads `config('Auth')->minimumPasswordLength` directly (not through `setting()`), so even a setting with Shield's name is not applied by itself.
- **Proposal:** keep the setting (name it `Auth.minimumPasswordLength` to match Shield) and copy its value into the shared `Config\Auth` instance early in the request (Core event `pre_system` / Registrar), with a test that a password shorter than the setting is refused.
- **Alternatives:** A) Keep `Auth.minPasswordLength` and translate it to Shield's property (two names for one value). B) File configuration only (`app/Config/Auth.php` / `.env`), removed from Settings › Security.
- **Status:** Decided
- **Decision (2026-11-04):** «Nom de Shield + còpia (recomanada)». The setting is named `Auth.minimumPasswordLength` (Shield's name); the Core copies the stored value into the shared `config('Auth')` instance early in every request, and a test checks that a shorter password is refused. **Must change:** specification "Authentication" and Settings › Security; sessions 05 and 14.
- **Applied in:** —

## D-00-08 — API routes "registered by `modules:sync`"

- **Where:** spec "JSON API" (modules expose resources), manifest `$api` (`'handler' => Api\Posts::class`), session 10; versus "Discovery is the only loading mechanism", "never require a scan just to make a route reachable" and "`Routes.php` never queries the database"
- **Problem:** kpapy's `kpapy:make` writes `Resource::routes(...)` into the module's own `Config/Routes.php` with a *controller method*.
- **Proposal:** routes stay in each module's `Config/Routes.php` (written by `kpapy:make` / `make:module --api`, path `api/v1/<path>`); `$api` is metadata validated by `modules:sync` and `permissions:check` (routes and manifest must match); disabled modules answer 404 through the module filter. The `handler` key names the controller (`Api\Posts::index`).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-09 — `kpcrudy:make` output vs Core conventions

- **Where:** spec "Common module base" (`make:module --crud` reuses `kpcrudy:make`), session 09
- **Problem:** `kpcrudy:make` writes `\Kpcrudy\Crud::routes(...)` with an optional `--filter`, extends `<ns>\Controllers\BaseController`, and instantiates `new Crud()`; the Core wants `CrudFactory::routes()` (backend + `can:` + `as`) and `CrudFactory::make()`.
- **Proposal:** `make:module --crud` calls `kpcrudy:make` with `--namespace`, `--extends "Modules\Core\Controllers\BackendController"`, `--as <module>-<resource>` and `--filter backend`, then rewrites the generated route line to `CrudFactory::routes()` and the constructor to `CrudFactory::make()`; `permissions:check` catches anything left.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-10 — Order of work

- **Where:** —
- **Problem:** spec last paragraph ("Core kernel → assets and themes → Auth → RBAC → … → installer → tests/CI → docs → Tasks") and `CLAUDE.md` ("following the order in the specification") vs the session order (kpcrudy in 09 before notifications; News in 17 before Pages/Files; installer in 18; `security:check` in 30).
- **Proposal:** the session files are the order; update the `CLAUDE.md` workflow line to say so.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-11 — Bootstrap instructions already applied

- **Where:** —
- **Problem:** `deploy/00-bootstrap.md` §3 and `deploy/README.md` talk about `prompt_new.md`, `claude_suggest.md` and a repository `SIENSIS/ci4app`; this repository already has `docs/specification.md` and `CLAUDE.md`.
- **Proposal:** treat §3 as done; no action besides noting it in `docs/progress.md`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-12 — Migrations of third-party packages

- **Where:** spec "Database schema" ("Shield's and Settings' own migrations are run as they ship")
- **Problem:** kpapy also ships a migration (`kpapy_login_challenges`, run with `php spark migrate -n Kpapy`) needed for the app login.
- **Proposal:** `modules:sync` runs the namespaces `CodeIgniter\Shield`, `CodeIgniter\Settings` and `Kpapy` before the modules, and the uninstall/rollback logic never touches them.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-13 — Per-user 2FA with Shield

- **Where:** spec "Authentication" ("Email (Shield's `Email2FA` action)" chosen by each user)
- **Problem:** Shield has one global `Config\Auth::$actions['login']`; `Email2FA` applies to every user. kpapy's `EmailProvider::isEnabledFor()` also checks that global action.
- **Proposal:** a Core action `UserTwoFactorAction` set as the `login` action that dispatches to Shield's `Email2FA` logic or the TOTP action per user (or none); kpapy providers extended to read the user's method.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-14 — Remember-me vs absolute session lifetime (decide)

- **Where:** spec "Session handling"
- **Problem:** With remember-me a user is silently logged in again after the absolute lifetime.
- **Proposal:** the absolute lifetime ends the session; remember-me re-creates it but only up to its own (setting) lifetime, and sensitive actions still require reauth; or disable remember-me when the absolute lifetime is set.
- **Alternatives:** A) Disable remember-me (strict absolute lifetime). B) A security setting enables remember-me (off by default) and sets its lifetime.
- **Status:** Decided
- **Decision (2026-11-04):** «Recorda'm amb límit (recomanada)». The absolute lifetime ends the session; remember-me may re-create it only up to its own lifetime (a security setting, e.g. 14 days), and sensitive actions still require re-authentication. **Must change:** specification "Session handling" and Settings › Security; sessions 05, 06 and 14 (tests for expiry with and without remember-me).
- **Applied in:** —

## D-00-15 — "Email never inline"

- **Where:** —
- **Problem:** spec "Notifications" (email channel queued/sent by the scheduler) vs magic links, password resets, 2FA codes, API email codes and new-device notices, which must be sent immediately (Shield sends them inline).
- **Proposal:** the rule applies to the notification email channel only; transactional security emails are sent inline with a short timeout and logged.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-16 — Shield wildcards are now richer

- **Where:** spec "RBAC" ("Shield only expands the wildcard in the first part")
- **Problem:** Shield 1.4.1's `PermissionMatcher` also matches prefix and multi-segment wildcards. The `<module>-<resource>.<operation>` naming still works.
- **Proposal:** keep the naming (public convention), fix the explanation in `docs/permissions.md`, and test that `news-posts.*` does not match `news-postsx.edit`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-17 — "My …" screens behind `can:` (decide)

- **Where:** spec "Activity" ("My activity for every user, the same CRUD with `where`"), "Notifications" (list page with `where(user_id)`), News "my posts", profile, dashboard home, "my sessions"
- **Problem:** Every `backend/` route needs a `can:` filter and CRUD routes get `can:<resource>.list`; the full-log resource `activity-log.list` must not be given to everyone.
- **Proposal:** separate resources (`activity-mine`, `core-notifications`, `auth-profile`, `dashboard`) whose permissions are granted to every group by `defaultGrants` (or a reserved "all authenticated" permission that the `can` filter accepts explicitly).
- **Alternatives:** A) A reserved `can:authenticated` permission accepted for any logged-in user (simpler, cannot be removed from a group, an exception in the route test).
- **Status:** Decided
- **Decision (2026-11-04):** «Recursos propis (recomanada)». Every personal screen is its own resource (e.g. `activity-mine`, `core-notifications`, `auth-profile`, `dashboard`, `auth-sessions`) granted to every group through `defaultGrants`; the rule "every `backend/` route has `can:`" stays without exceptions. **Must change:** specification "RBAC", "Activity", "Notifications", "Authentication" (profile, sessions), "Dashboard"; sessions 05, 07, 11, 12, 15, 16.
- **Applied in:** —

## D-00-18 — Permission names outside `<module>-<resource>` (decide)

- **Where:** spec uses `modules.*`, `security.check`, `api.docs`, `api.tokens`, `settings.update`, `settings.<module>`, `settings.security`, `files.*`, `backup.*`, `updates.*` (Core resources named without the module; single-resource modules)
- **Problem:** `settings.<module>` also clashes with `settings.security`/`settings.update` for modules named `Security` or `Update`.
- **Proposal:** the manifest gets an optional resource `'name'` that overrides the generated prefix (only Core and single-resource modules), the validator rejects duplicates in the catalogue, and module names `Security`/`Update` are reserved.
- **Alternatives:** A) Strict `<module>-<resource>` everywhere (`core-modules.*`, `core-security.check`, `settings-security.*`…; uniform, but every name in the specification changes).
- **Status:** Decided
- **Decision (2026-11-04):** «Nom opcional al manifest (recomanada)». A manifest resource may declare `'name'` to override the generated prefix (Core and single-resource modules only); the validator rejects duplicate permissions in the catalogue; `Security` and `Update` are reserved module names. The names used in the specification stay. **Must change:** specification "RBAC" and "Module manifest"; `CLAUDE.md` permissions line; sessions 01, 02, 07.
- **Applied in:** —

## D-00-19 — Building Shield's `AuthGroups` from tables

- **Where:** spec "RBAC" ("Build Shield's `AuthGroups::$permissions` and `$matrix` from the catalogue and that table")
- **Problem:** Shield reads them with `setting('AuthGroups.matrix')`/`setting('AuthGroups.permissions')`, and `addPermission()` throws for unknown names.
- **Proposal:** after each sync/grant change the Core writes the computed arrays with `setting()->set('AuthGroups.…')` (Settings caches them); `group_permissions` stays the source of truth and a test checks both agree.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-20 — The resolver needs a user, not the session

- **Where:** —
- **Problem:** spec "RBAC" (`auth()->user()->can()`), used by iCal feeds (token, no session), `notifyPermission()`, the scheduler and CLI.
- **Proposal:** `Authorization::for(User $user)->can()` in the Core; `CrudPermissions` uses the current user from kpapy's `Context` (API) or the session (web), and feeds/tasks pass the user explicitly.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-21 — Default grants vs implied permissions

- **Where:** —
- **Problem:** spec manifest example: editor gets `news-posts.edit` but not `news-posts.show`, while "Implied permissions" says grants are completed with `Crud::impliedPermissions()`.
- **Proposal:** apply implied permissions to `defaultGrants` too, and make the validator warn about incomplete sets.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-22 — `users_permissions`

- **Where:** —
- **Problem:** spec "RBAC" names Shield's table `users_permissions`; Shield's is `auth_permissions_users`.
- **Proposal:** use Shield's API (`addPermission()`) and fix the name in the docs.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-23 — Uploads in kpcrudy/kpapy bypass the Storage service

- **Where:** spec "CRUDs with kpcrudy" ("upload paths from the Storage service"), "Storage" (MIME check, re-encoding, SVG refused, private disk, `files` metadata)
- **Problem:** kpcrudy 1.0 `upload`/`image` fields write to a filesystem path (`uploadPath`, default `public/uploads`, the same folder as the public disk) with random names and extension checks only; kpapy writes to `writable/uploads/kpapy`.
- **Proposal:** modules never use the libraries' upload field types; they use the file-picker field (`callbackField`) backed by the Storage service; set both libraries' `uploadPath` to a non-public, unused folder, and record the gap in `docs/library-requests.md`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-24 — Inline `<script>`/`<style>` in library views

- **Where:** spec "Front-end assets" ("No JavaScript inside the HTML (no inline `<script>` code)") and the CI CSP check
- **Problem:** kpcrudy's `bootstrap5` assets partial prints a nonce'd inline `<script>` and `<style>` in every CRUD; kpapy's docs page has a nonce'd `<style>`. They comply with the CSP (nonces) but not with the "no inline code" rule. Overriding the templates would copy library code (forbidden).
- **Proposal:** accept nonce'd blocks rendered by the two libraries as the only exception, scope the CI check to project views (`app/`, `modules/`, `themes/`), and file a library request for an external-asset option.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-25 — `style=""` ban vs real components and content

- **Where:** spec "Front-end assets" and CSP (`style-src 'self' 'nonce-…'`)
- **Problem:** Bootstrap progress bars and dynamic widths use `style` attributes; theme option colours need a place to be printed; rich-text editors and calendar libraries may inject `<style>` elements or style attributes; HTMLPurifier-sanitised page bodies with `style` attributes are blocked by the CSP.
- **Proposal:** set widths via JS (CSSOM is allowed) or utility classes; serve theme variables as a generated CSS file (or one nonce'd `<style>` in the layout, explicitly allowed); pick an editor/calendar that works without `unsafe-inline` (test it under the enforced CSP); configure HTMLPurifier to drop `style` and map alignment to classes.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-26 — Cache busting of ES modules

- **Where:** spec "Front-end assets" (`asset()` with a hash, import map prefixes to folders)
- **Problem:** Files imported through a folder prefix (`core/ui/confirm.js`) or a relative path carry no version, so browsers keep old copies.
- **Proposal:** the import map lists every published JS file with its hashed URL (generated from the asset manifest), or publish into a versioned folder; test that the map changes when a file changes.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-27 — PWA details

- **Where:** spec "Minimal PWA"
- **Problem:** A service worker controls only its folder, so it must be served from `/sw.js` (or with `Service-Worker-Allowed`); icons "from the site logo" need PNG 192/512 generated with GD (an SVG logo is refused by the upload rules).
- **Proposal:** route `/sw.js` and `/manifest.webmanifest` from the Core, generate icons on logo change.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-28 — CodeIgniter's `secureheaders` filter

- **Where:** spec "Security baseline" ("`secureheaders` filter plus … `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`")
- **Problem:** CI 4.7's filter hard-codes `X-Frame-Options: SAMEORIGIN` and `Referrer-Policy: same-origin`.
- **Proposal:** replace it with the Core `SecurityHeaders` filter driven by `Config\SecurityHeaders` (do not stack both).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-29 — Outbound traffic that is not HTTPS-through-`OutboundClient`

- **Where:** spec "Outbound requests" (email goes through the `OutboundClient`, HTTPS only), "Updates" (`git pull`, `composer install`)
- **Problem:** SMTP is not HTTP, and git/composer use their own clients.
- **Proposal:** the allow-list applies to SMTP hosts through a check in the mail service; `git`/`composer` are console-only and documented as exceptions in `docs/outbound-requests.md`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-30 — Route-security test for the API

- **Where:** spec "RBAC" (every `api/v1/` route has `kpapy` or is `kpapy:public`, read-only)
- **Problem:** kpapy's own login routes use `kpapy:login` and are POST; `OPTIONS` preflight routes exist.
- **Proposal:** the test accepts `kpapy`, `kpapy:optional`, `kpapy:hmac`, `kpapy:login` (only for `auth/token*`) and `kpapy:public` (GET only).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-31 — Acceptance criterion 2 before session 32

- **Where:** —
- **Problem:** session 19 checks criterion 2, which needs `modules/Events2/CLAUDE.md`, `/check-module` (session 32) and a module that already has "a widget and a task" (session 01's `make:module` only lists manifest, routes, language, controller, views, assets, migration stub and a test).
- **Proposal:** session 01/19 add widget and task stubs to the templates; session 19 checks criterion 2 without the `CLAUDE.md`/`/check-module` parts and session 32 completes it.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-32 — Child theme inside the `Tasks` module

- **Where:** spec "Tutorials" (9: a child theme overriding a `Tasks` view) and session 32 ("child-theme override" in the module)
- **Problem:** Themes live in `themes/`.
- **Proposal:** ship `themes/tasks-demo/` (child of `default`) as part of the tutorial, installed with `themes:install` or kept in the repository and documented as tutorial material.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-33 — The web server writing into `public/` and the project root (decide)

- **Where:** spec "Installing modules" ("the web server never writes code") vs Settings "publish assets", enabling a module from the UI (publishes its assets), the web installer (writes `.env`) and restoring `.env` from the UI
- **Problem:** Published JS is executable code for browsers.
- **Proposal:** the web user may write only `writable/` and `public/assets/modules|core`, `public/themes`, `public/uploads`; the web installer shows the `.env` content to copy when the root is not writable; document the risk in `docs/security.md`.
- **Alternatives:** A) Console only: publishing assets, enabling modules, installing and restoring `.env` only with `php spark`; the web shows status and the command. B) As the specification says, without folder limits.
- **Status:** Decided
- **Decision (2026-11-04):** «Carpetes limitades (recomanada)». The web server user may write only `writable/`, `public/assets/modules`, `public/assets/core`, `public/themes` and `public/uploads`; when the project root is not writable the web installer shows the `.env` content to copy; the risk is documented in `docs/security.md` and checked by `security:check`. **Must change:** specification "Installing modules", "Installer", "Backup" (restore), "security:check"; sessions 18, 27, 29, 30.
- **Applied in:** —

## D-00-34 — New dependencies vs "ask before adding a dependency"

- **Where:** —
- **Problem:** `CLAUDE.md` workflow vs spec "Technical baseline" and `deploy/00-bootstrap.md` §4.4 (TOTP, QR, HTMLPurifier, Flysystem installed by the sessions that use them; an MIT editor and calendar).
- **Proposal:** the libraries named in the specification (and an MIT/BSD editor and calendar) are pre-approved; anything else needs asking. Add this sentence to `CLAUDE.md`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-35 — Rich text before the editor exists

- **Where:** —
- **Problem:** News (session 17) has an HTML body sanitised with HTMLPurifier, but the editor is vendored in session 20.
- **Proposal:** News uses a textarea with HTMLPurifier in 17; session 20 switches News to `forms/editor.js` and updates its tests.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-36 — News group visibility as `multiselect`

- **Where:** spec "News" ("a `multiselect` or a junction table")
- **Problem:** A JSON/CSV column cannot be queried portably with the Query Builder on SQLite and MariaDB ("visible to any of my groups").
- **Proposal:** use a junction table `news_groups` with kpcrudy `setRelationNN()`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-37 — Profile fields and birthdays have no table

- **Where:** spec "Auth" (name, language, time zone, theme mode, avatar), Users "Birthdays" provider
- **Problem:** Shield's `users` table has none of these, and "each module owns its tables".
- **Proposal:** a `user_profiles` table owned by Auth (one migration), with an optional `birth_date` used by the provider.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-38 — Groups as data

- **Where:** spec "Users" (Groups CRUD with kpcrudy, 2FA policy per group), "Groups" (reserved names)
- **Problem:** Shield's groups are configuration (`AuthGroups::$groups`), not a table.
- **Proposal:** a Core `groups` table (name, title, description, reserved, 2FA policy) mirrored into `setting('AuthGroups.groups')` like the matrix (item 19).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-39 — Upload folders

- **Where:** —
- **Problem:** spec "Directory layout" (`public/uploads`, `writable/storage`), "Backup" (includes `writable/uploads`), kpapy (`writable/uploads/kpapy`), session 00 `.gitignore` (does not list `public/uploads`).
- **Proposal:** the backup includes both disks plus `writable/uploads` only if non-empty; add `public/uploads/*` (except `index.html`) to `.gitignore`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-40 — Translated content in kpcrudy

- **Where:** spec "Languages" (fallback chain), "Demo data" (Catalan only), Events (CSV import)
- **Problem:** kpcrudy 1.0 lists show an **empty cell** when the record has no translation in the list locale (no fallback), and translated fields are **not included in CSV import**. kpapy does have `localeFallbacks`.
- **Proposal:** set `listLocale` to the user's locale only when it is complete, otherwise `ca`; for Events import, `callbackImport()` writes the Catalan translation; record both gaps in `docs/library-requests.md`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-41 — Visibility names and calendar permissions

- **Where:** —
- **Problem:** News uses `public|private|groups`, Events and the calendar API `public|private|restricted`; the `news.notices` calendar declares `'permission' => 'news-posts.list'`, which ordinary users (who can read notices on the dashboard) do not have.
- **Proposal:** map `groups` → `restricted` in the News publisher; calendar `permission` becomes optional and visibility per event decides; for News leave it empty.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-42 — Where ordinary users read private news (decide)

- **Where:** spec "News" ("private news only in the back office and the dashboard", "mark as read from the widget and the detail page")
- **Problem:** The back-office CRUD requires `news-posts.list`.
- **Proposal:** a read-only "reader" screen (`news-read.view`, granted to every group) listing the news the user can see, with the detail page and "mark as read".
- **Alternatives:** A) The dashboard widget opens the full post in a modal (no list or history). B) Private posts on the public site for logged-in users with access (contradicts "back office only").
- **Status:** Decided
- **Decision (2026-11-04):** «Pantalla de lectura (recomanada)». A read-only reader screen (resource `news-read`, permission `news-read.view` granted to every group) lists the posts the user may see, with the detail page and "mark as read". **Must change:** specification "News"; session 17.
- **Applied in:** —

## D-00-43 — `about` and `cache:clear` hooks

- **Where:** spec "Installer and CLI"
- **Problem:** CodeIgniter 4.7 already has `cache:clear`, and command discovery keeps the first command with a name, so a module cannot replace it; `about` does not exist in CodeIgniter.
- **Proposal:** add `about` in Core; for caches, a Core `app:cache-clear` (Core caches + CodeIgniter cache), and listen to a `cache.cleared` event where possible.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-44 — Path placeholders

- **Where:** —
- **Problem:** spec "Files": personal space `users/{id}/`, picker example `users/{user_id}/avatar`.
- **Proposal:** use `{user_id}` everywhere.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-45 — Restoring `.env` and keys

- **Where:** spec "Backup" (backups contain `.env` and the encryption key; "restoring on a new server")
- **Problem:** Restoring the old `.env` overwrites the new server's DB credentials and base URL.
- **Proposal:** restore keeps the current `.env` by default and merges only the secrets (`encryption.key`, `app.maskKey`, `backup.password`) after confirmation; `--with-env` restores it whole (console only).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-46 — AES-256-GCM for large archives

- **Where:** spec "Backup"
- **Problem:** PHP's OpenSSL GCM is one-shot (whole archive in memory).
- **Proposal:** libsodium `crypto_secretstream_xchacha20poly1305` (streaming, authenticated) with Argon2id, or chunked AES-256-GCM with per-chunk tags and a header MAC; document the format version in the archive header.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-47 — Backup password on the command line

- **Where:** spec "Backup" (`backup:create … [--password]`)
- **Problem:** A value on the command line ends up in shell history and the process list.
- **Proposal:** `--password` only as a flag that triggers a hidden prompt; non-interactive use reads `backup.password` from `.env` or an environment variable.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-48 — Release signing for Updates (decide)

- **Where:** spec "Updates" (archive SHA-256 **and** signature, or a signed tag) but no session defines how the skeleton's releases are signed or which key ships in `Config\Updates`
- **Problem:** Without it, every update is refused.
- **Proposal:** session 28 defines an Ed25519 (libsodium/minisign-compatible) signature of the release archive and the public key in `Config\Updates`; session 36 documents the release procedure.
- **Alternatives:** A) Ed25519 with the private key as a GitHub Actions secret (automatic; whoever controls the repository can sign). B) Signed git tags (GPG/SSH; only for `git pull` installs, not ZIPs).
- **Status:** Decided
- **Decision (2026-11-04):** «Ed25519, clau teva (recomanada)». Session 28 implements Ed25519 signatures of the release archive (libsodium, minisign-compatible); the owner generates the key pair on their own computer and the private key never leaves it; the public key ships in `Config\\Updates`; session 36 documents the signing procedure for every release. **Must change:** specification "Updates"; sessions 28 and 36.
- **Applied in:** —

## D-00-49 — `composer audit` offline

- **Where:** spec "`security:check`" ("offline cache when there is no network")
- **Problem:** `composer audit` has no offline mode.
- **Proposal:** run it in the daily task or CI, store the result with its date, and report "last checked on …" (warning when older than 7 days).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-50 — The `--probe` targets the site itself

- **Where:** —
- **Problem:** spec "`security:check`" (probe `/.env`, HTTP→HTTPS redirect…) vs `OutboundClient` (HTTPS only, refuses loopback/private IPs).
- **Proposal:** the probe uses a dedicated client limited to the configured base URL host (HTTP allowed only for the redirect check), never a user-supplied host other than the site's own `allowedHostnames`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-51 — File permissions of CodeIgniter's own files

- **Where:** spec "`security:check`" (`writable/` folders `0750`, files `0640`)
- **Problem:** CodeIgniter writes logs with `Config\Logger::$filePermissions = 0644` by default; kpcrudy/kpapy key files are `0600`.
- **Proposal:** set the Logger to `0640`; the check accepts stricter modes.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-52 — `app.maskKey` fallback

- **Where:** spec "JSON API" ("never leave it empty: each library would then generate its own key file")
- **Problem:** Both libraries first fall back to CodeIgniter's encryption key (then to their own key files), so ids match but change when the encryption key is rotated. Masked ids also depend on the context (table name by default) in both libraries, so the "same id in web and API" holds only for the same table.
- **Proposal:** keep the requirement (key set, boot refused in production when empty), fix the explanation in `docs/security.md`, never set a custom `maskIds($context)` on one side only.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-53 — Captcha implementation

- **Where:** —
- **Problem:** spec "Authentication" (optional captcha, `hash_equals`, single use) names no mechanism, and outbound services are forbidden.
- **Proposal:** a local image/arith captcha rendered with GD (or SVG), answer hashed in the session, invalidated after one check.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-54 — `If-Match` for writes

- **Where:** spec "JSON API" ("`If-Match` for writes")
- **Problem:** kpapy supports it and can require it (`requireIfMatch`), which makes simple clients fail with `428`.
- **Proposal:** support it (412 on mismatch) but do not require it by default; document it in `docs/api.md`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-55 — Brand-new required libraries

- **Where:** spec "Required libraries" (`^1.0`)
- **Problem:** kpcrudy and kpapy 1.0.0 were published one day before this plan; their behaviour may change in 1.0.x.
- **Proposal:** pin the exact version in `composer.lock`, read each `CHANGELOG.md` before updating, and file gaps in `docs/library-requests.md` instead of working around them.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —

## D-00-56 — Tasks module vs scheduled tasks naming

- **Where:** —
- **Problem:** spec: modules keep scheduled tasks in `Tasks/` (`TaskInterface`), and the tutorial module is named `Tasks` (`modules/Tasks/Tasks/…`, `scheduled_tasks` vs `tasks` tables, "task" in UI texts).
- **Proposal:** keep the names (they are in the spec) but use explicit terms in docs and language keys ("scheduled task" vs "to-do task").
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** —
