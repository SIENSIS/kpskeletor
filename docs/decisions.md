# Decisions

Decisions here override `docs/specification.md` when they disagree (the specification is updated
when a decision is applied). Open questions come from `docs/plan.md` ("Contradictions and open
questions") and from each session's report; they are answered with `deploy/decide.md`.

| ID | Session | Title | Status |
|---|---|---|---|
| D-00-01 | 00 | Branch names and pushing to `main` | Applied |
| D-00-02 | 00 | Session 36 finish text | Applied |
| D-00-03 | 00 | Module discovery with a single `Modules\` namespace | Applied |
| D-00-04 | 00 | `modules/Events/Config/Events.php` | Applied |
| D-00-05 | 00 | `Config\Modules` and `Modules.*` settings | Applied |
| D-00-06 | 00 | `setting()` and manifest defaults | Applied |
| D-00-07 | 00 | `Auth.minPasswordLength` vs Shield | Applied |
| D-00-08 | 00 | API routes "registered by `modules:sync`" | Applied |
| D-00-09 | 00 | `kpcrudy:make` output vs Core conventions | Applied |
| D-00-10 | 00 | Order of work | Applied |
| D-00-11 | 00 | Bootstrap instructions already applied | Applied |
| D-00-12 | 00 | Migrations of third-party packages | Applied |
| D-00-13 | 00 | Per-user 2FA with Shield | Applied |
| D-00-14 | 00 | Remember-me vs absolute session lifetime | Applied |
| D-00-15 | 00 | "Email never inline" | Applied |
| D-00-16 | 00 | Shield wildcards are now richer | Applied |
| D-00-17 | 00 | "My …" screens behind `can:` | Applied |
| D-00-18 | 00 | Permission names outside `<module>-<resource>` | Applied |
| D-00-19 | 00 | Building Shield's `AuthGroups` from tables | Applied |
| D-00-20 | 00 | The resolver needs a user, not the session | Applied |
| D-00-21 | 00 | Default grants vs implied permissions | Applied |
| D-00-22 | 00 | `users_permissions` | Applied |
| D-00-23 | 00 | Uploads in kpcrudy/kpapy bypass the Storage service | Applied |
| D-00-24 | 00 | Inline `<script>`/`<style>` in library views | Applied |
| D-00-25 | 00 | `style=""` ban vs real components and content | Applied |
| D-00-26 | 00 | Cache busting of ES modules | Applied |
| D-00-27 | 00 | PWA details | Applied |
| D-00-28 | 00 | CodeIgniter's `secureheaders` filter | Applied |
| D-00-29 | 00 | Outbound traffic that is not HTTPS-through-`OutboundClient` | Applied |
| D-00-30 | 00 | Route-security test for the API | Applied |
| D-00-31 | 00 | Acceptance criterion 2 before session 32 | Applied |
| D-00-32 | 00 | Child theme inside the `Tasks` module | Applied |
| D-00-33 | 00 | The web server writing into `public/` and the project root | Applied |
| D-00-34 | 00 | New dependencies vs "ask before adding a dependency" | Applied |
| D-00-35 | 00 | Rich text before the editor exists | Applied |
| D-00-36 | 00 | News group visibility as `multiselect` | Applied |
| D-00-37 | 00 | Profile fields and birthdays have no table | Applied |
| D-00-38 | 00 | Groups as data | Applied |
| D-00-39 | 00 | Upload folders | Applied |
| D-00-40 | 00 | Translated content in kpcrudy | Applied |
| D-00-41 | 00 | Visibility names and calendar permissions | Applied |
| D-00-42 | 00 | Where ordinary users read private news | Applied |
| D-00-43 | 00 | `about` and `cache:clear` hooks | Applied |
| D-00-44 | 00 | Path placeholders | Applied |
| D-00-45 | 00 | Restoring `.env` and keys | Applied |
| D-00-46 | 00 | AES-256-GCM for large archives | Applied |
| D-00-47 | 00 | Backup password on the command line | Applied |
| D-00-48 | 00 | Release signing for Updates | Applied |
| D-00-49 | 00 | `composer audit` offline | Applied |
| D-00-50 | 00 | The `--probe` targets the site itself | Applied |
| D-00-51 | 00 | File permissions of CodeIgniter's own files | Applied |
| D-00-52 | 00 | `app.maskKey` fallback | Applied |
| D-00-53 | 00 | Captcha implementation | Applied |
| D-00-54 | 00 | `If-Match` for writes | Applied |
| D-00-55 | 00 | Brand-new required libraries | Applied |
| D-00-56 | 00 | Tasks module vs scheduled tasks naming | Applied |
| D-01-01 | 01 | The `can:` filter before RBAC exists | Accepted (proposal) |
| D-01-02 | 01 | Migration stub of `make:module` | Accepted (proposal) |
| D-01-03 | 01 | Registered modules whose folder disappeared | Accepted (proposal) |
| D-01-04 | 01 | Allowed values in the manifest | Accepted (proposal) |
| D-01-05 | 01 | Core version during development | Accepted (proposal) |
| D-02-01 | 02 | One table for the manifest declarations | Accepted (proposal) |
| D-02-02 | 02 | Manifest defaults through a Settings handler | Accepted (proposal) |
| D-02-03 | 02 | Disabled modules answer 404 through a global filter | Accepted (proposal) |
| D-02-04 | 02 | Default grants applied once: a flag in `modules` | Accepted (proposal) |
| D-02-05 | 02 | Core resource `modules` and its default grants | Accepted (proposal) |
| D-02-06 | 02 | Demo data: confirmations, seeder base class and stub | Accepted (proposal) |
| D-02-07 | 02 | Cache scopes with version keys | Accepted (proposal) |
| D-02-08 | 02 | Activity log entries before the Activity session | Accepted (proposal) |
| D-03-01 | 03 | Theme folders, namespaces and loading | Accepted (proposal) |
| D-03-02 | 03 | Where theme option values are stored | Applied |
| D-03-03 | 03 | Theme assets before `assets:publish` | Accepted (proposal) |
| D-03-04 | 03 | Error pages and the 403 of `can:` | Accepted (proposal) |
| D-03-05 | 03 | Theme preview pages | Applied |
| D-03-06 | 03 | "The assets it loads" in the theme manifest | Accepted (proposal) |
| D-03-07 | 03 | Site name before the Settings session | Accepted (proposal) |
| D-03-08 | 03 | What `BackendController::render()` adds | Accepted (proposal) |
| D-03-09 | 03 | `--name=value` options in the module commands | Accepted (proposal) |
| D-04-01 | 04 | Where the front-end PHP classes live | Accepted (proposal) |
| D-04-02 | 04 | Development serving rule for edited assets | Accepted (proposal) |
| D-04-03 | 04 | Asset manifest format and owners | Accepted (proposal) |
| D-04-04 | 04 | Details of the strict CSP | Accepted (proposal) |
| D-04-05 | 04 | Module CSP sources at runtime | Accepted (proposal) |
| D-04-06 | 04 | Where the light/dark choice is stored before the profile exists | Accepted (proposal) |
| D-04-07 | 04 | Breakpoints and state of the back-office sidebar | Accepted (proposal) |
| D-04-08 | 04 | PWA settings, icons and worker | Accepted (proposal) |
| D-04-09 | 04 | CSRF token for scripts without sessions for every visitor | Accepted (proposal) |
| D-04-10 | 04 | Project views with inline code from CodeIgniter | Accepted (proposal) |
| D-05-01 | 05 | Own login, magic-link and reset controllers | Accepted (proposal) |
| D-05-02 | 05 | Session tracking and remote logout | Accepted (proposal) |
| D-05-03 | 05 | Groups and grants before RBAC | Accepted (proposal) |
| D-05-04 | 05 | Names, units and defaults of the Auth settings | Accepted (proposal) |
| D-05-05 | 05 | App tokens vs personal tokens | Accepted (proposal) |
| D-05-06 | 05 | CSRF and back-office filters before the security session | Accepted (proposal) |
| D-05-07 | 05 | Where a login lands and profile preferences | Accepted (proposal) |
| D-05-08 | 05 | New-device notices | Accepted (proposal) |
| D-06-01 | 06 | Email codes through the Core action | Accepted (proposal) |
| D-06-02 | 06 | 2FA storage, TOTP and recovery codes | Accepted (proposal) |
| D-06-03 | 06 | Remember-me and the 2FA step | Accepted (proposal) |
| D-06-04 | 06 | A password login counts as re-authentication | Applied (decided) |
| D-06-05 | 06 | 2FA group policy and re-authentication settings | Accepted (proposal) |
| D-06-06 | 06 | Re-authentication screen, URLs and non-GET requests | Accepted (proposal) |
| D-07-01 | 07 | The `backend` filter chain | Accepted (proposal) |
| D-07-02 | 07 | Syntax of `can:` with several permissions | Accepted (proposal) |
| D-07-03 | 07 | Unknown and disabled permissions are refused to everybody | Accepted (proposal) |
| D-07-04 | 07 | Grant changes and extra escalation rules before the matrix screen | Accepted (proposal) |
| D-07-05 | 07 | Resolver wiring, the development check and diagnostics | Accepted (proposal) |
| D-08-01 | 08 | Menu keys, zones, rendering and breadcrumbs | Accepted (proposal) |
| D-08-02 | 08 | Security headers on every response | Accepted (proposal) |
| D-08-03 | 08 | Rate-limit service and its settings | Accepted (proposal) |
| D-08-04 | 08 | OutboundClient details and the allow-list sources | Accepted (proposal) |
| D-08-05 | 08 | What `safe_redirect()` accepts | Accepted (proposal) |

## D-00-01 — Branch names and pushing to `main`

- **Where:** `deploy/*.md` "Before starting" steps 2 and 4, `deploy/next.md`, `deploy/README.md`
- **Problem:** Sessions create `session-NN-<slug>` and push `main`, but cloud environments assign their own branch (this one is `claude/modest-goldberg-jjs8kp`) and often allow pushing only to it. `next.md` finds unfinished sessions by `session-NN-*` branches.
- **Proposal:** identify sessions by the pull request title "Session NN: …" and by `docs/progress.md` (record the branch name there); when `main` cannot be pushed, stop and ask the owner to merge the pull request (already allowed by the files), and never force-push.
- **Alternatives:** A) Always create `session-NN-<slug>` even when the environment assigns another branch (may fail to push).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Per títol de PR (recomanada)». Sessions are identified by the pull request title "Session NN: …" and by `docs/progress.md` (which records the real branch name); the environment's assigned branch is accepted; when `main` cannot be pushed the session stops and asks the owner to merge the pull request; never force-push. **Must change:** `deploy/next.md`, `deploy/continue.md` and the shared "Before starting"/"Finish" steps of `deploy/NN-*.md`.
- **Applied in:** deploy/01–36 (Before starting / Finish), deploy/next.md, deploy/continue.md, deploy/review-fixes.md, deploy/README.md, CLAUDE.md

## D-00-02 — Session 36 finish text

- **Where:** `deploy/36-release.md`
- **Problem:** Its Scope says it merges itself into `main`, but the shared *Finish* section says "The next session merges it".
- **Proposal:** follow the Scope (merge after the pull request), and treat the Finish line as boilerplate.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** deploy/36

## D-00-03 — Module discovery with a single `Modules\` namespace (decide)

- **Where:** spec "Directory layout" (Autoloading/Discovery), `CLAUDE.md`
- **Problem:** CodeIgniter 4.7's `FileLocator::search()` only looks at `<namespace root>/Config/Routes.php` (also `Events`, `Registrar`, `Commands/`), so `Modules\` → `modules/` finds nothing inside `modules/News/`. Registering every module statically in `app/Config/Autoload.php` breaks acceptance 2 ("without touching any core file").
- **Proposal:** keep `Modules\`/`Themes\` for class autoloading and add one PSR-4 entry per module (`Modules\News` → `modules/News`) from a **generated static file** (`app/Config/ModuleNamespaces.php`) written by `make:module`, `modules:sync` and `modules:install`, and loaded by `Autoload.php`; no scanning at runtime.
- **Alternatives:** A) Scan `modules/*/Config/Module.php` on every boot (no command needed, disk access per request, against "no scan to make a route reachable"). B) One `autoload.psr-4` entry per module in `composer.json` + `composer dump-autoload` (touches a core file per module).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Fitxer generat (recomanada)». A generated static file `app/Config/ModuleNamespaces.php` (one PSR-4 namespace per module, e.g. `Modules\\News` → `modules/News`) is written by `make:module`, `modules:sync` and `modules:install` and merged by `app/Config/Autoload.php`; no scanning at runtime. **Must change:** specification "Directory layout"/discovery, session 01 scope, `CLAUDE.md` (copying a module by hand requires `php spark modules:sync`).
- **Applied in:** `docs/specification.md`, deploy/01, CLAUDE.md

## D-00-04 — `modules/Events/Config/Events.php`

- **Where:** spec "Configuration and settings" ("every module may ship `Config/<Name>.php`") and the `Events` module
- **Problem:** CodeIgniter includes every discovered `Config/Events.php` as an event-listener file, so a config class with that name is included as a script (possible "cannot redeclare class"). The same pattern makes `setting('Events.x')` resolve to `Config\Events`.
- **Proposal:** the Events module uses `Config/Calendar.php` and the settings namespace `Calendar`/`Events` without a config class of that name; the manifest validator refuses module config files named `Events`, `Routes`, `Registrar`, `Filters`, `Modules`, `Services`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/01, deploy/24

## D-00-05 — `Config\Modules` and `Modules.*` settings (decide)

- **Where:** spec "Package validation" (publisher keys "listed in `Config\Modules`"), "Demo data" (`Modules.allowDemoInProduction`)
- **Problem:** `app/Config/Modules.php` is CodeIgniter's discovery configuration.
- **Proposal:** put package keys and module options in `Modules\Core\Config\Packages` (`Config\Packages` override in `app/Config`), and name the setting `Packages.allowDemoInProduction` or `Core.allowDemoInProduction`.
- **Alternatives:** A) Everything in `Config\\Core` (`Core.allowDemoInProduction`, `Core.publisherKeys`). B) Extend CodeIgniter's `Config\\Modules` as the specification says (conflict risk with framework updates).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Config\\Packages (recomanada)». Publisher keys and module package options live in `Modules\\Core\\Config\\Packages` (overridable by `app/Config/Packages.php` and `.env`); the demo setting is `Packages.allowDemoInProduction`. **Must change:** specification "Package validation" and "Demo data"; sessions 02 and 29.
- **Applied in:** `docs/specification.md`, deploy/02, deploy/29

## D-00-06 — `setting()` and manifest defaults

- **Where:** spec "Configuration and settings"
- **Problem:** `codeigniter4/settings` falls back to the property of a config class with that short name; modules declare defaults in the manifest, not in a config class, so `setting('News.perPage')` returns `null` before a value is saved.
- **Proposal:** the Core registers manifest defaults (a `ModuleSettings` handler or a Core `settings()` wrapper used everywhere), and a test checks every declared key returns its default.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/02

## D-00-07 — `Auth.minPasswordLength` vs Shield (decide)

- **Where:** spec "Authentication", session 05
- **Problem:** Shield reads `Config\Auth::$minimumPasswordLength` (default 8); a new key `minPasswordLength` is ignored by Shield's validators. Shield 1.4.1 reads `config('Auth')->minimumPasswordLength` directly (not through `setting()`), so even a setting with Shield's name is not applied by itself.
- **Proposal:** keep the setting (name it `Auth.minimumPasswordLength` to match Shield) and copy its value into the shared `Config\Auth` instance early in the request (Core event `pre_system` / Registrar), with a test that a password shorter than the setting is refused.
- **Alternatives:** A) Keep `Auth.minPasswordLength` and translate it to Shield's property (two names for one value). B) File configuration only (`app/Config/Auth.php` / `.env`), removed from Settings › Security.
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Nom de Shield + còpia (recomanada)». The setting is named `Auth.minimumPasswordLength` (Shield's name); the Core copies the stored value into the shared `config('Auth')` instance early in every request, and a test checks that a shorter password is refused. **Must change:** specification "Authentication" and Settings › Security; sessions 05 and 14.
- **Applied in:** `docs/specification.md`, deploy/05, deploy/14

## D-00-08 — API routes "registered by `modules:sync`"

- **Where:** spec "JSON API" (modules expose resources), manifest `$api` (`'handler' => Api\Posts::class`), session 10; versus "Discovery is the only loading mechanism", "never require a scan just to make a route reachable" and "`Routes.php` never queries the database"
- **Problem:** kpapy's `kpapy:make` writes `Resource::routes(...)` into the module's own `Config/Routes.php` with a *controller method*.
- **Proposal:** routes stay in each module's `Config/Routes.php` (written by `kpapy:make` / `make:module --api`, path `api/v1/<path>`); `$api` is metadata validated by `modules:sync` and `permissions:check` (routes and manifest must match); disabled modules answer 404 through the module filter. The `handler` key names the controller (`Api\Posts::index`).
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/10

## D-00-09 — `kpcrudy:make` output vs Core conventions

- **Where:** spec "Common module base" (`make:module --crud` reuses `kpcrudy:make`), session 09
- **Problem:** `kpcrudy:make` writes `\Kpcrudy\Crud::routes(...)` with an optional `--filter`, extends `<ns>\Controllers\BaseController`, and instantiates `new Crud()`; the Core wants `CrudFactory::routes()` (backend + `can:` + `as`) and `CrudFactory::make()`.
- **Proposal:** `make:module --crud` calls `kpcrudy:make` with `--namespace`, `--extends "Modules\Core\Controllers\BackendController"`, `--as <module>-<resource>` and `--filter backend`, then rewrites the generated route line to `CrudFactory::routes()` and the constructor to `CrudFactory::make()`; `permissions:check` catches anything left.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/09

## D-00-10 — Order of work

- **Where:** —
- **Problem:** spec last paragraph ("Core kernel → assets and themes → Auth → RBAC → … → installer → tests/CI → docs → Tasks") and `CLAUDE.md` ("following the order in the specification") vs the session order (kpcrudy in 09 before notifications; News in 17 before Pages/Files; installer in 18; `security:check` in 30).
- **Proposal:** the session files are the order; update the `CLAUDE.md` workflow line to say so.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md` (final paragraph), CLAUDE.md

## D-00-11 — Bootstrap instructions already applied

- **Where:** —
- **Problem:** `deploy/00-bootstrap.md` §3 and `deploy/README.md` talk about `prompt_new.md`, `claude_suggest.md` and a repository `SIENSIS/ci4app`; this repository already has `docs/specification.md` and `CLAUDE.md`.
- **Proposal:** treat §3 as done; no action besides noting it in `docs/progress.md`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** docs/progress.md

## D-00-12 — Migrations of third-party packages

- **Where:** spec "Database schema" ("Shield's and Settings' own migrations are run as they ship")
- **Problem:** kpapy also ships a migration (`kpapy_login_challenges`, run with `php spark migrate -n Kpapy`) needed for the app login.
- **Proposal:** `modules:sync` runs the namespaces `CodeIgniter\Shield`, `CodeIgniter\Settings` and `Kpapy` before the modules, and the uninstall/rollback logic never touches them.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`

## D-00-13 — Per-user 2FA with Shield

- **Where:** spec "Authentication" ("Email (Shield's `Email2FA` action)" chosen by each user)
- **Problem:** Shield has one global `Config\Auth::$actions['login']`; `Email2FA` applies to every user. kpapy's `EmailProvider::isEnabledFor()` also checks that global action.
- **Proposal:** a Core action `UserTwoFactorAction` set as the `login` action that dispatches to Shield's `Email2FA` logic or the TOTP action per user (or none); kpapy providers extended to read the user's method.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/06

## D-00-14 — Remember-me vs absolute session lifetime (decide)

- **Where:** spec "Session handling"
- **Problem:** With remember-me a user is silently logged in again after the absolute lifetime.
- **Proposal:** the absolute lifetime ends the session; remember-me re-creates it but only up to its own (setting) lifetime, and sensitive actions still require reauth; or disable remember-me when the absolute lifetime is set.
- **Alternatives:** A) Disable remember-me (strict absolute lifetime). B) A security setting enables remember-me (off by default) and sets its lifetime.
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Recorda'm amb límit (recomanada)». The absolute lifetime ends the session; remember-me may re-create it only up to its own lifetime (a security setting, e.g. 14 days), and sensitive actions still require re-authentication. **Must change:** specification "Session handling" and Settings › Security; sessions 05, 06 and 14 (tests for expiry with and without remember-me).
- **Applied in:** `docs/specification.md`, deploy/05, deploy/06, deploy/14

## D-00-15 — "Email never inline"

- **Where:** —
- **Problem:** spec "Notifications" (email channel queued/sent by the scheduler) vs magic links, password resets, 2FA codes, API email codes and new-device notices, which must be sent immediately (Shield sends them inline).
- **Proposal:** the rule applies to the notification email channel only; transactional security emails are sent inline with a short timeout and logged.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/11

## D-00-16 — Shield wildcards are now richer

- **Where:** spec "RBAC" ("Shield only expands the wildcard in the first part")
- **Problem:** Shield 1.4.1's `PermissionMatcher` also matches prefix and multi-segment wildcards. The `<module>-<resource>.<operation>` naming still works.
- **Proposal:** keep the naming (public convention), fix the explanation in `docs/permissions.md`, and test that `news-posts.*` does not match `news-postsx.edit`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`

## D-00-17 — "My …" screens behind `can:` (decide)

- **Where:** spec "Activity" ("My activity for every user, the same CRUD with `where`"), "Notifications" (list page with `where(user_id)`), News "my posts", profile, dashboard home, "my sessions"
- **Problem:** Every `backend/` route needs a `can:` filter and CRUD routes get `can:<resource>.list`; the full-log resource `activity-log.list` must not be given to everyone.
- **Proposal:** separate resources (`activity-mine`, `core-notifications`, `auth-profile`, `dashboard`) whose permissions are granted to every group by `defaultGrants` (or a reserved "all authenticated" permission that the `can` filter accepts explicitly).
- **Alternatives:** A) A reserved `can:authenticated` permission accepted for any logged-in user (simpler, cannot be removed from a group, an exception in the route test).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Recursos propis (recomanada)». Every personal screen is its own resource (e.g. `activity-mine`, `core-notifications`, `auth-profile`, `dashboard`, `auth-sessions`) granted to every group through `defaultGrants`; the rule "every `backend/` route has `can:`" stays without exceptions. **Must change:** specification "RBAC", "Activity", "Notifications", "Authentication" (profile, sessions), "Dashboard"; sessions 05, 07, 11, 12, 15, 16.
- **Applied in:** `docs/specification.md`, deploy/05, deploy/07, deploy/11, deploy/12, deploy/15, deploy/16, CLAUDE.md

## D-00-18 — Permission names outside `<module>-<resource>` (decide)

- **Where:** spec uses `modules.*`, `security.check`, `api.docs`, `api.tokens`, `settings.update`, `settings.<module>`, `settings.security`, `files.*`, `backup.*`, `updates.*` (Core resources named without the module; single-resource modules)
- **Problem:** `settings.<module>` also clashes with `settings.security`/`settings.update` for modules named `Security` or `Update`.
- **Proposal:** the manifest gets an optional resource `'name'` that overrides the generated prefix (only Core and single-resource modules), the validator rejects duplicates in the catalogue, and module names `Security`/`Update` are reserved.
- **Alternatives:** A) Strict `<module>-<resource>` everywhere (`core-modules.*`, `core-security.check`, `settings-security.*`…; uniform, but every name in the specification changes).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Nom opcional al manifest (recomanada)». A manifest resource may declare `'name'` to override the generated prefix (Core and single-resource modules only); the validator rejects duplicate permissions in the catalogue; `Security` and `Update` are reserved module names. The names used in the specification stay. **Must change:** specification "RBAC" and "Module manifest"; `CLAUDE.md` permissions line; sessions 01, 02, 07.
- **Applied in:** `docs/specification.md`, deploy/01, deploy/02, deploy/07, CLAUDE.md

## D-00-19 — Building Shield's `AuthGroups` from tables

- **Where:** spec "RBAC" ("Build Shield's `AuthGroups::$permissions` and `$matrix` from the catalogue and that table")
- **Problem:** Shield reads them with `setting('AuthGroups.matrix')`/`setting('AuthGroups.permissions')`, and `addPermission()` throws for unknown names.
- **Proposal:** after each sync/grant change the Core writes the computed arrays with `setting()->set('AuthGroups.…')` (Settings caches them); `group_permissions` stays the source of truth and a test checks both agree.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/07

## D-00-20 — The resolver needs a user, not the session

- **Where:** —
- **Problem:** spec "RBAC" (`auth()->user()->can()`), used by iCal feeds (token, no session), `notifyPermission()`, the scheduler and CLI.
- **Proposal:** `Authorization::for(User $user)->can()` in the Core; `CrudPermissions` uses the current user from kpapy's `Context` (API) or the session (web), and feeds/tasks pass the user explicitly.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`

## D-00-21 — Default grants vs implied permissions

- **Where:** —
- **Problem:** spec manifest example: editor gets `news-posts.edit` but not `news-posts.show`, while "Implied permissions" says grants are completed with `Crud::impliedPermissions()`.
- **Proposal:** apply implied permissions to `defaultGrants` too, and make the validator warn about incomplete sets.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/07

## D-00-22 — `users_permissions`

- **Where:** —
- **Problem:** spec "RBAC" names Shield's table `users_permissions`; Shield's is `auth_permissions_users`.
- **Proposal:** use Shield's API (`addPermission()`) and fix the name in the docs.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/07

## D-00-23 — Uploads in kpcrudy/kpapy bypass the Storage service

- **Where:** spec "CRUDs with kpcrudy" ("upload paths from the Storage service"), "Storage" (MIME check, re-encoding, SVG refused, private disk, `files` metadata)
- **Problem:** kpcrudy 1.0 `upload`/`image` fields write to a filesystem path (`uploadPath`, default `public/uploads`, the same folder as the public disk) with random names and extension checks only; kpapy writes to `writable/uploads/kpapy`.
- **Proposal:** modules never use the libraries' upload field types; they use the file-picker field (`callbackField`) backed by the Storage service; set both libraries' `uploadPath` to a non-public, unused folder, and record the gap in `docs/library-requests.md`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/09, deploy/10

## D-00-24 — Inline `<script>`/`<style>` in library views

- **Where:** spec "Front-end assets" ("No JavaScript inside the HTML (no inline `<script>` code)") and the CI CSP check
- **Problem:** kpcrudy's `bootstrap5` assets partial prints a nonce'd inline `<script>` and `<style>` in every CRUD; kpapy's docs page has a nonce'd `<style>`. They comply with the CSP (nonces) but not with the "no inline code" rule. Overriding the templates would copy library code (forbidden).
- **Proposal:** accept nonce'd blocks rendered by the two libraries as the only exception, scope the CI check to project views (`app/`, `modules/`, `themes/`), and file a library request for an external-asset option.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/04

## D-00-25 — `style=""` ban vs real components and content

- **Where:** spec "Front-end assets" and CSP (`style-src 'self' 'nonce-…'`)
- **Problem:** Bootstrap progress bars and dynamic widths use `style` attributes; theme option colours need a place to be printed; rich-text editors and calendar libraries may inject `<style>` elements or style attributes; HTMLPurifier-sanitised page bodies with `style` attributes are blocked by the CSP.
- **Proposal:** set widths via JS (CSSOM is allowed) or utility classes; serve theme variables as a generated CSS file (or one nonce'd `<style>` in the layout, explicitly allowed); pick an editor/calendar that works without `unsafe-inline` (test it under the enforced CSP); configure HTMLPurifier to drop `style` and map alignment to classes.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/20

## D-00-26 — Cache busting of ES modules

- **Where:** spec "Front-end assets" (`asset()` with a hash, import map prefixes to folders)
- **Problem:** Files imported through a folder prefix (`core/ui/confirm.js`) or a relative path carry no version, so browsers keep old copies.
- **Proposal:** the import map lists every published JS file with its hashed URL (generated from the asset manifest), or publish into a versioned folder; test that the map changes when a file changes.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/04

## D-00-27 — PWA details

- **Where:** spec "Minimal PWA"
- **Problem:** A service worker controls only its folder, so it must be served from `/sw.js` (or with `Service-Worker-Allowed`); icons "from the site logo" need PNG 192/512 generated with GD (an SVG logo is refused by the upload rules).
- **Proposal:** route `/sw.js` and `/manifest.webmanifest` from the Core, generate icons on logo change.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/04

## D-00-28 — CodeIgniter's `secureheaders` filter

- **Where:** spec "Security baseline" ("`secureheaders` filter plus … `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`")
- **Problem:** CI 4.7's filter hard-codes `X-Frame-Options: SAMEORIGIN` and `Referrer-Policy: same-origin`.
- **Proposal:** replace it with the Core `SecurityHeaders` filter driven by `Config\SecurityHeaders` (do not stack both).
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/08

## D-00-29 — Outbound traffic that is not HTTPS-through-`OutboundClient`

- **Where:** spec "Outbound requests" (email goes through the `OutboundClient`, HTTPS only), "Updates" (`git pull`, `composer install`)
- **Problem:** SMTP is not HTTP, and git/composer use their own clients.
- **Proposal:** the allow-list applies to SMTP hosts through a check in the mail service; `git`/`composer` are console-only and documented as exceptions in `docs/outbound-requests.md`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/08

## D-00-30 — Route-security test for the API

- **Where:** spec "RBAC" (every `api/v1/` route has `kpapy` or is `kpapy:public`, read-only)
- **Problem:** kpapy's own login routes use `kpapy:login` and are POST; `OPTIONS` preflight routes exist.
- **Proposal:** the test accepts `kpapy`, `kpapy:optional`, `kpapy:hmac`, `kpapy:login` (only for `auth/token*`) and `kpapy:public` (GET only).
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/07

## D-00-31 — Acceptance criterion 2 before session 32

- **Where:** —
- **Problem:** session 19 checks criterion 2, which needs `modules/Events2/CLAUDE.md`, `/check-module` (session 32) and a module that already has "a widget and a task" (session 01's `make:module` only lists manifest, routes, language, controller, views, assets, migration stub and a test).
- **Proposal:** session 01/19 add widget and task stubs to the templates; session 19 checks criterion 2 without the `CLAUDE.md`/`/check-module` parts and session 32 completes it.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/01, deploy/19, deploy/32

## D-00-32 — Child theme inside the `Tasks` module

- **Where:** spec "Tutorials" (9: a child theme overriding a `Tasks` view) and session 32 ("child-theme override" in the module)
- **Problem:** Themes live in `themes/`.
- **Proposal:** ship `themes/tasks-demo/` (child of `default`) as part of the tutorial, installed with `themes:install` or kept in the repository and documented as tutorial material.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/32

## D-00-33 — The web server writing into `public/` and the project root (decide)

- **Where:** spec "Installing modules" ("the web server never writes code") vs Settings "publish assets", enabling a module from the UI (publishes its assets), the web installer (writes `.env`) and restoring `.env` from the UI
- **Problem:** Published JS is executable code for browsers.
- **Proposal:** the web user may write only `writable/` and `public/assets/modules|core`, `public/themes`, `public/uploads`; the web installer shows the `.env` content to copy when the root is not writable; document the risk in `docs/security.md`.
- **Alternatives:** A) Console only: publishing assets, enabling modules, installing and restoring `.env` only with `php spark`; the web shows status and the command. B) As the specification says, without folder limits.
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Carpetes limitades (recomanada)». The web server user may write only `writable/`, `public/assets/modules`, `public/assets/core`, `public/themes` and `public/uploads`; when the project root is not writable the web installer shows the `.env` content to copy; the risk is documented in `docs/security.md` and checked by `security:check`. **Must change:** specification "Installing modules", "Installer", "Backup" (restore), "security:check"; sessions 18, 27, 29, 30.
- **Applied in:** `docs/specification.md`, deploy/18, deploy/27, deploy/29, deploy/30

## D-00-34 — New dependencies vs "ask before adding a dependency"

- **Where:** —
- **Problem:** `CLAUDE.md` workflow vs spec "Technical baseline" and `deploy/00-bootstrap.md` §4.4 (TOTP, QR, HTMLPurifier, Flysystem installed by the sessions that use them; an MIT editor and calendar).
- **Proposal:** the libraries named in the specification (and an MIT/BSD editor and calendar) are pre-approved; anything else needs asking. Add this sentence to `CLAUDE.md`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** CLAUDE.md

## D-00-35 — Rich text before the editor exists

- **Where:** —
- **Problem:** News (session 17) has an HTML body sanitised with HTMLPurifier, but the editor is vendored in session 20.
- **Proposal:** News uses a textarea with HTMLPurifier in 17; session 20 switches News to `forms/editor.js` and updates its tests.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** deploy/17, deploy/20

## D-00-36 — News group visibility as `multiselect`

- **Where:** spec "News" ("a `multiselect` or a junction table")
- **Problem:** A JSON/CSV column cannot be queried portably with the Query Builder on SQLite and MariaDB ("visible to any of my groups").
- **Proposal:** use a junction table `news_groups` with kpcrudy `setRelationNN()`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/17

## D-00-37 — Profile fields and birthdays have no table

- **Where:** spec "Auth" (name, language, time zone, theme mode, avatar), Users "Birthdays" provider
- **Problem:** Shield's `users` table has none of these, and "each module owns its tables".
- **Proposal:** a `user_profiles` table owned by Auth (one migration), with an optional `birth_date` used by the provider.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/05, deploy/25

## D-00-38 — Groups as data

- **Where:** spec "Users" (Groups CRUD with kpcrudy, 2FA policy per group), "Groups" (reserved names)
- **Problem:** Shield's groups are configuration (`AuthGroups::$groups`), not a table.
- **Proposal:** a Core `groups` table (name, title, description, reserved, 2FA policy) mirrored into `setting('AuthGroups.groups')` like the matrix (item 19).
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/15

## D-00-39 — Upload folders

- **Where:** —
- **Problem:** spec "Directory layout" (`public/uploads`, `writable/storage`), "Backup" (includes `writable/uploads`), kpapy (`writable/uploads/kpapy`), session 00 `.gitignore` (does not list `public/uploads`).
- **Proposal:** the backup includes both disks plus `writable/uploads` only if non-empty; add `public/uploads/*` (except `index.html`) to `.gitignore`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/27, .gitignore, public/uploads/index.html, tests/unit/SecurityDefaultsTest.php

## D-00-40 — Translated content in kpcrudy

- **Where:** spec "Languages" (fallback chain), "Demo data" (Catalan only), Events (CSV import)
- **Problem:** kpcrudy 1.0 lists show an **empty cell** when the record has no translation in the list locale (no fallback), and translated fields are **not included in CSV import**. kpapy does have `localeFallbacks`.
- **Proposal:** set `listLocale` to the user's locale only when it is complete, otherwise `ca`; for Events import, `callbackImport()` writes the Catalan translation; record both gaps in `docs/library-requests.md`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/24

## D-00-41 — Visibility names and calendar permissions

- **Where:** —
- **Problem:** News uses `public|private|groups`, Events and the calendar API `public|private|restricted`; the `news.notices` calendar declares `'permission' => 'news-posts.list'`, which ordinary users (who can read notices on the dashboard) do not have.
- **Proposal:** map `groups` → `restricted` in the News publisher; calendar `permission` becomes optional and visibility per event decides; for News leave it empty.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/25

## D-00-42 — Where ordinary users read private news (decide)

- **Where:** spec "News" ("private news only in the back office and the dashboard", "mark as read from the widget and the detail page")
- **Problem:** The back-office CRUD requires `news-posts.list`.
- **Proposal:** a read-only "reader" screen (`news-read.view`, granted to every group) listing the news the user can see, with the detail page and "mark as read".
- **Alternatives:** A) The dashboard widget opens the full post in a modal (no list or history). B) Private posts on the public site for logged-in users with access (contradicts "back office only").
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Pantalla de lectura (recomanada)». A read-only reader screen (resource `news-read`, permission `news-read.view` granted to every group) lists the posts the user may see, with the detail page and "mark as read". **Must change:** specification "News"; session 17.
- **Applied in:** `docs/specification.md`, deploy/17

## D-00-43 — `about` and `cache:clear` hooks

- **Where:** spec "Installer and CLI"
- **Problem:** CodeIgniter 4.7 already has `cache:clear`, and command discovery keeps the first command with a name, so a module cannot replace it; `about` does not exist in CodeIgniter.
- **Proposal:** add `about` in Core; for caches, a Core `app:cache-clear` (Core caches + CodeIgniter cache), and listen to a `cache.cleared` event where possible.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/18

## D-00-44 — Path placeholders

- **Where:** —
- **Problem:** spec "Files": personal space `users/{id}/`, picker example `users/{user_id}/avatar`.
- **Proposal:** use `{user_id}` everywhere.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/22

## D-00-45 — Restoring `.env` and keys

- **Where:** spec "Backup" (backups contain `.env` and the encryption key; "restoring on a new server")
- **Problem:** Restoring the old `.env` overwrites the new server's DB credentials and base URL.
- **Proposal:** restore keeps the current `.env` by default and merges only the secrets (`encryption.key`, `app.maskKey`, `backup.password`) after confirmation; `--with-env` restores it whole (console only).
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/27

## D-00-46 — AES-256-GCM for large archives

- **Where:** spec "Backup"
- **Problem:** PHP's OpenSSL GCM is one-shot (whole archive in memory).
- **Proposal:** libsodium `crypto_secretstream_xchacha20poly1305` (streaming, authenticated) with Argon2id, or chunked AES-256-GCM with per-chunk tags and a header MAC; document the format version in the archive header.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/27

## D-00-47 — Backup password on the command line

- **Where:** spec "Backup" (`backup:create … [--password]`)
- **Problem:** A value on the command line ends up in shell history and the process list.
- **Proposal:** `--password` only as a flag that triggers a hidden prompt; non-interactive use reads `backup.password` from `.env` or an environment variable.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/27

## D-00-48 — Release signing for Updates (decide)

- **Where:** spec "Updates" (archive SHA-256 **and** signature, or a signed tag) but no session defines how the skeleton's releases are signed or which key ships in `Config\Updates`
- **Problem:** Without it, every update is refused.
- **Proposal:** session 28 defines an Ed25519 (libsodium/minisign-compatible) signature of the release archive and the public key in `Config\Updates`; session 36 documents the release procedure.
- **Alternatives:** A) Ed25519 with the private key as a GitHub Actions secret (automatic; whoever controls the repository can sign). B) Signed git tags (GPG/SSH; only for `git pull` installs, not ZIPs).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Ed25519, clau teva (recomanada)». Session 28 implements Ed25519 signatures of the release archive (libsodium, minisign-compatible); the owner generates the key pair on their own computer and the private key never leaves it; the public key ships in `Config\\Updates`; session 36 documents the signing procedure for every release. **Must change:** specification "Updates"; sessions 28 and 36.
- **Applied in:** `docs/specification.md`, deploy/28, deploy/36

## D-00-49 — `composer audit` offline

- **Where:** spec "`security:check`" ("offline cache when there is no network")
- **Problem:** `composer audit` has no offline mode.
- **Proposal:** run it in the daily task or CI, store the result with its date, and report "last checked on …" (warning when older than 7 days).
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/30

## D-00-50 — The `--probe` targets the site itself

- **Where:** —
- **Problem:** spec "`security:check`" (probe `/.env`, HTTP→HTTPS redirect…) vs `OutboundClient` (HTTPS only, refuses loopback/private IPs).
- **Proposal:** the probe uses a dedicated client limited to the configured base URL host (HTTP allowed only for the redirect check), never a user-supplied host other than the site's own `allowedHostnames`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/30

## D-00-51 — File permissions of CodeIgniter's own files

- **Where:** spec "`security:check`" (`writable/` folders `0750`, files `0640`)
- **Problem:** CodeIgniter writes logs with `Config\Logger::$filePermissions = 0644` by default; kpcrudy/kpapy key files are `0600`.
- **Proposal:** set the Logger to `0640`; the check accepts stricter modes.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/30, app/Config/Logger.php, tests/unit/SecurityDefaultsTest.php

## D-00-52 — `app.maskKey` fallback

- **Where:** spec "JSON API" ("never leave it empty: each library would then generate its own key file")
- **Problem:** Both libraries first fall back to CodeIgniter's encryption key (then to their own key files), so ids match but change when the encryption key is rotated. Masked ids also depend on the context (table name by default) in both libraries, so the "same id in web and API" holds only for the same table.
- **Proposal:** keep the requirement (key set, boot refused in production when empty), fix the explanation in `docs/security.md`, never set a custom `maskIds($context)` on one side only.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/10

## D-00-53 — Captcha implementation

- **Where:** —
- **Problem:** spec "Authentication" (optional captcha, `hash_equals`, single use) names no mechanism, and outbound services are forbidden.
- **Proposal:** a local image/arith captcha rendered with GD (or SVG), answer hashed in the session, invalidated after one check.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`

## D-00-54 — `If-Match` for writes

- **Where:** spec "JSON API" ("`If-Match` for writes")
- **Problem:** kpapy supports it and can require it (`requireIfMatch`), which makes simple clients fail with `428`.
- **Proposal:** support it (412 on mismatch) but do not require it by default; document it in `docs/api.md`.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/10

## D-00-55 — Brand-new required libraries

- **Where:** spec "Required libraries" (`^1.0`)
- **Problem:** kpcrudy and kpapy 1.0.0 were published one day before this plan; their behaviour may change in 1.0.x.
- **Proposal:** pin the exact version in `composer.lock`, read each `CHANGELOG.md` before updating, and file gaps in `docs/library-requests.md` instead of working around them.
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** no change needed (composer.lock already pins 1.0.0; rule kept in CLAUDE.md)

## D-00-56 — Tasks module vs scheduled tasks naming

- **Where:** —
- **Problem:** spec: modules keep scheduled tasks in `Tasks/` (`TaskInterface`), and the tutorial module is named `Tasks` (`modules/Tasks/Tasks/…`, `scheduled_tasks` vs `tasks` tables, "task" in UI texts).
- **Proposal:** keep the names (they are in the spec) but use explicit terms in docs and language keys ("scheduled task" vs "to-do task").
- **Alternatives:** —
- **Status:** Applied (accepted proposal)
- **Decision (2026-11-04):** «accepta la resta»: the proposal above is accepted.
- **Applied in:** `docs/specification.md`, deploy/11, deploy/32

## D-01-01 — The `can:` filter before RBAC exists

- **Where:** deploy/01 scope ("Permissions … later sessions"), `make:module` routes, `CLAUDE.md` ("every `backend/` route has a `can:` filter")
- **Problem:** Generated module routes must declare `can:`, but the alias does not exist until the RBAC session; an unknown filter alias makes CodeIgniter fail on the route.
- **Proposal:** Register a `can` filter now in the Core registrar (`Modules\Core\Filters\Can`) that denies by default (guests and missing permissions get 403) and checks Shield's `can()`; the RBAC session replaces the check with the central resolver.
- **Alternatives:** A) Generate routes without a filter until session 07 (breaks the rule). B) Generate routes with Shield's `permission` filter (a different convention to migrate later).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 01 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 01 (`modules/Core`)

## D-01-02 — Migration stub of `make:module`

- **Where:** specification "Common module base" ("migration stub")
- **Problem:** A stub migration that does nothing teaches nothing; an empty folder does not test the per-module migrations.
- **Proposal:** `make:module` generates a real example migration (`<name>_items`: id, name, author and timestamp columns, soft delete) with `down()`, to be renamed or deleted before the module's first release.
- **Alternatives:** A) An empty `Database/Migrations/` folder. B) A migration with empty `up()`/`down()`.
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 01 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 01 (`modules/Core`)

## D-01-03 — Registered modules whose folder disappeared

- **Where:** specification "Module lifecycle"
- **Problem:** The specification does not say what `modules:sync` does with a registry row whose module folder no longer exists.
- **Proposal:** `modules:sync` and `modules:list` report it as "folder missing" and keep the row; removing tables and rows is `modules:uninstall`'s job.
- **Alternatives:** A) Delete the registry row on sync (loses the installed version; tables stay orphaned).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 01 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 01 (`modules/Core`)

## D-01-04 — Allowed values in the manifest

- **Where:** specification "The module contract"
- **Problem:** The specification lists the manifest keys but not every allowed value (setting types, CSP directives and sources, path formats).
- **Proposal:** Setting types: string, text, int, float, bool, select, email, url, color, json; CSP: only https hosts, `'self'`, `'none'` and `blob:` (never `unsafe-inline`, `unsafe-eval`, `*`, `http:` or `data:`); API paths relative without slashes at the ends; backup paths relative without `..`. Later sessions may add values, never remove them.
- **Alternatives:** A) Accept any value and let each consumer fail later (unclear errors).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 01 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 01 (`modules/Core`)

## D-01-05 — Core version during development

- **Where:** specification "Versioning"
- **Problem:** The Core is not released yet; generated modules require the Core with a caret constraint.
- **Proposal:** The Core starts at `0.1.0`; `make:module` writes `'Core' => '^<major>.<minor>'` from the current Core version. The release session sets `1.0.0`.
- **Alternatives:** A) Start at `1.0.0` now (claims a stability the code does not have yet).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 01 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 01 (`modules/Core`)

## D-02-01 — One table for the manifest declarations

- **Where:** specification "Module lifecycle" step 6, deploy/02 ("Registries for widgets, tasks, notification types, menu link types, calendars, API resources"), plan session 02 (one class per registry)
- **Problem:** Regular requests must not load the manifests, so the declarations have to be stored somewhere; the plan lists one registry class per kind but no tables.
- **Proposal:** One `module_declarations` table (module, kind, key, JSON definition) written by `modules:sync` and read through `Modules\Core\Registry\DeclarationRegistry` (`widgets()`, `tasks()`, `notificationTypes()`, `menuLinkTypes()`, `calendars()`, `calendarProviders()`, `apiResources()`) and `SettingsRegistry`. Readers hide disabled modules, except settings, whose defaults stay readable. Map keys are qualified with the module (`News.perPage`, `News.notices`). Later sessions may add typed wrappers.
- **Alternatives:** A) One table per registry (six tables with the same shape). B) A generated PHP file like `ModuleNamespaces.php` (no database, but one more generated file to keep in sync per installation).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-02 — Manifest defaults through a Settings handler

- **Where:** specification "Configuration and settings", D-00-06
- **Problem:** `setting('News.perPage')` must return the manifest default before a value is saved, without a wrapper that every caller must remember.
- **Proposal:** A read-only codeigniter4/settings handler (`Modules\Core\Settings\ManifestDefaultsHandler`, alias `manifest`) placed after the database handler in `app/Config/Settings.php`. A real config class property with the same name (e.g. `Config\Auth::$minimumPasswordLength`) keeps priority, so file configuration and `.env` still set those defaults (D-00-07). `SettingCaster` and `SettingsRegistry::value()` give typed values; a test checks every declared key.
- **Alternatives:** A) A Core `settings()` wrapper used everywhere instead of `setting()` (easy to forget; third-party code would still see `null`).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-03 — Disabled modules answer 404 through a global filter

- **Where:** specification "Module lifecycle" ("a disabled module's routes answer 404"), D-00-08
- **Problem:** Routes stay in each module's `Config/Routes.php`, which never queries the database, so disabled modules' routes are still registered.
- **Proposal:** A global `before` filter `moduleenabled` (`Modules\Core\Filters\ModuleEnabled`, in `app/Config/Filters.php`) maps the matched controller (`Modules\<Name>\…`) to its module and throws a 404 when the module is disabled (registry state cached in the "modules" scope). Closure routes cannot be mapped to a module: modules must use controllers (already the convention).
- **Alternatives:** A) A `module:<Name>` route filter declared on every route (one more rule for every module to remember).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-04 — Default grants applied once: a flag in `modules`

- **Where:** specification "Module lifecycle" step 5, plan session 02 ("`default_grants_applied` or a flag in `modules`")
- **Problem:** Default grants must be applied only on a module's first install, also for installations whose registry existed before this session.
- **Proposal:** A new migration adds `modules.grants_applied`; `modules:sync` applies the expanded `defaultGrants` of every module whose flag is 0 and sets it. Grants removed by the owner are never re-added. Shield's computed `AuthGroups` arrays are written by the RBAC session (07).
- **Alternatives:** A) A separate `default_grants_applied` table.
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-05 — Core resource `modules` and its default grants

- **Where:** specification "Modules screen" (permissions `modules.list`, `modules.toggle`, `modules.sync`, `modules.upload`, `modules.uninstall`), "Demo data" (`modules.demo`)
- **Problem:** The Core must generate these permissions from its manifest; the specification only says the last two (and `modules.demo`) are superadmin only by default.
- **Proposal:** The Core manifest declares a `crud` resource `modules` with `'name' => 'modules'`, every crud operation but `list` excluded, and the extras `toggle`, `sync`, `upload`, `uninstall`, `demo`. Default grants: `admin` gets `modules.list`, `modules.toggle`, `modules.sync`; superadmins bypass the checks (session 07).
- **Alternatives:** A) A `page` resource (it would also generate `modules.view`, not in the specification).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-06 — Demo data: confirmations, seeder base class and stub

- **Where:** specification "Demo data"
- **Problem:** The specification says a confirmation is always required and that seeders write through `DemoData`, but not how a seeder receives it nor how the console confirms.
- **Proposal:** Demo seeders extend `Modules\Core\Demo\DemoSeeder` and implement `load(DemoData $demo)`; running one with `db:seed` is refused (it would not be tracked). `modules:demo` and `modules:sync --demo` always ask y/n (no flag skips it; the non-interactive installer comes in session 18). Loading runs in a transaction; removal goes newest first, keeps and reports rows that other data depends on, and deletes recorded files only inside `writable/`. `make:module` generates a `<Name>DemoSeeder` stub.
- **Alternatives:** A) Plain CodeIgniter seeders that call `DemoData` themselves (nothing guarantees they are tracked).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-07 — Cache scopes with version keys

- **Where:** deploy/02 ("Caches (permissions, menus, settings, widgets, assets) with one invalidation service")
- **Problem:** CodeIgniter's cache has no tags, and the file handler cannot delete by prefix.
- **Proposal:** `Modules\Core\Cache\CoreCache` stores each scope's version in the cache and puts it in every key; `CacheInvalidator` (the only invalidation service) bumps the versions and then fires `modules.cacheInvalidate`. A sixth scope, `modules`, holds the enabled state and the declarations.
- **Alternatives:** A) `cache()->clean()` on every change (drops unrelated caches such as rate limits). B) `deleteMatching()` (not supported by every handler).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-02-08 — Activity log entries before the Activity session

- **Where:** specification "Modules screen" and "Demo data" ("written to the activity log")
- **Problem:** The activity log arrives in session 12.
- **Proposal:** Enable/disable and demo-data actions are written to the application log (`log_message('notice')`) for now; session 12 replaces those calls with activity log entries (noted in `docs/progress.md`).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 02 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 02 (`modules/Core`)

## D-03-01 — Theme folders, namespaces and loading

- **Where:** specification "Themes" (`themes/<name>/`, manifest `Themes\<Name>\Theme`), "Directory layout" (`Themes\` → `themes/` in PSR-4, D-00-03)
- **Problem:** Theme folders are lower-case (`themes/default`, `themes/tasks-demo` in D-00-32) but PSR-4 maps `Themes\Default` to `themes/Default` and a hyphen cannot be part of a namespace; CodeIgniter's view locator needs the theme's namespace to find `Themes\Default\Views\…`.
- **Proposal:** a theme slug is lower-case letters, digits and hyphens; its namespace is the StudlyCase slug (`tasks-demo` → `Themes\TasksDemo`). `ThemeRegistry` registers that namespace with the autoloader when it loads the theme (only the active chain per request, no scan of `themes/`), so hand-copied themes work without a sync and the generated `ModuleNamespaces.php` stays modules-only. The `Themes\` entry is removed from `composer.json` (Composer warned that the lower-case folders do not comply); `app/Config/Autoload.php` keeps it.
- **Alternatives:** A) StudlyCase folders (`themes/Default`, against the specification). B) Theme namespaces in the generated namespaces file (one more step after copying a theme).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 03 (`modules/Core/Theme/ThemeRegistry.php`, `composer.json`)

## D-03-02 — Where theme option values are stored (decide)

- **Where:** specification "Themes" ("options … declared like settings and edited from the Settings UI"), "Configuration and settings"
- **Problem:** Options belong to a theme (a child redeclares the parent's defaults, two themes may be active at once for the back office and the public site), and the specification does not name the settings. A settings name is a public convention.
- **Proposal:** store them with codeigniter4/settings as `Theme.<option>` with the context `theme:<slug>` (`service('settings')->set('Theme.primaryColor', '#6f42c1', 'theme:corporate')`); the default is the merged manifest default. Built behind `ThemeOptions` (one closure reads the stored value), so the storage can change without touching the views.
- **Alternatives:** A) One settings class per theme, `Theme<Studly>.<option>` without context (`ThemeCorporate.primaryColor`). B) One JSON setting `Theme.options` per theme context.
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Theme.opció + context (recomanada)». Theme option values are stored with codeigniter4/settings as `Theme.<option>` with the context `theme:<slug>`; the default is the merged manifest default.
- **Applied in:** `docs/specification.md` ("Themes"); code built by session 03 (`modules/Core/Theme/ThemeOptions.php`, `docs/themes.md`, `ThemeOptionsTest`)

## D-03-03 — Theme assets before `assets:publish`

- **Where:** specification "Themes" ("theme assets are published like module assets"), deploy/04 (`assets:publish`)
- **Problem:** The default theme's CSS must be reachable from `public/themes/<slug>/` now; the asset pipeline (hashes, asset manifest, `--watch`) arrives in session 04.
- **Proposal:** `ThemeAssetPublisher` mirrors `themes/<slug>/Assets/` to `public/themes/<slug>/` (only allowed extensions: no PHP or HTML; changed files copied, stale files removed), run by `modules:sync` (step 7) and `make:theme`. URLs carry a `?v=` content hash. Session 04 folds it into `assets:publish`.
- **Alternatives:** A) A `themes:publish` command (one more public command to retire later).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 as proposed.
- **Applied in:** session 03 (`modules/Core/Theme/ThemeAssetPublisher.php`, `ModuleSynchronizer`)

## D-03-04 — Error pages and the 403 of `can:`

- **Where:** specification "Common module base" (403/404/500 pages), "Security baseline"
- **Problem:** The specification does not say which layout the error pages use; the `can:` filter answered 403 with plain text; CodeIgniter's 404 and production 500 views have inline `<style>` blocks (against the CSP rules).
- **Proposal:** standard views `errors/403|404|500` in the theme's `blank` layout (no menus, which need a user and permissions), rendered by `ErrorPage`, with a plain fallback page when the theme itself fails; `app/Views/errors/html/error_404.php` and `production.php` use it (the exception message is shown only outside production); the `can:` filter answers with the themed 403, or JSON (`{"error": …}`) when the client asks for `application/json`.
- **Alternatives:** A) The back-office or public layout (needs the menu and the user, which may be what failed).
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 as proposed.
- **Applied in:** session 03 (`modules/Core/Theme/ErrorPage.php`, `modules/Core/Filters/Can.php`, `app/Views/errors/html/`)

## D-03-05 — Theme preview pages (decide)

- **Where:** specification "Themes" (theme authors), deploy/03 (screenshots of the standard views)
- **Problem:** The standard views and the layouts have no screen yet that shows them all (menus, users and CRUDs come later), and theme authors need one to check an override. A new URL is a public convention.
- **Proposal:** `GET /theme-preview/<layout>` (`backend`, `public`, `auth`, `blank`, `print`, `errors-403|404|500`) with sample content and `?theme=<slug>`, answering 404 unless `CI_ENVIRONMENT = development` **and** `theme.preview = true` (off by default; it reads no data). Easy to remove: one controller, two routes, one config property.
- **Alternatives:** A) No preview: screenshots only once real screens exist. B) A back-office page `backend/themes/preview` behind a `can:` permission (needs RBAC, session 07).
- **Status:** Applied (decided)
- **Decision (2026-11-04):** «Només desenvolupament (recomanada)». Keep `GET /theme-preview/<layout>` (and `?theme=<slug>`), answering 404 unless `CI_ENVIRONMENT = development` and `theme.preview = true` (off by default); it reads no data.
- **Applied in:** `docs/specification.md` ("Themes"); code built by session 03 (`ErrorPagesTest`, `modules/Core/Controllers/ThemePreview.php`, `modules/Core/Config/Routes.php`, `Config\Theme::$preview`)

## D-03-06 — "The assets it loads" in the theme manifest

- **Where:** specification "Themes" (manifest: name, version, author, parent, layouts, regions, menu locations, the assets it loads, options)
- **Problem:** The specification names the content of the manifest but not the property names.
- **Proposal:** `$name`, `$version`, `$author`, `$parent`, `$layouts`, `$regions` (layout → regions), `$menuLocations` (location → label key), `$styles` and `$scripts` (paths under `Assets/`, or `vendor:<path>` under `public/assets/vendor/`; loaded after the parent's) and `$options` (setting definitions plus `css` for colour options). A child declares only what it adds or changes.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 as proposed.
- **Applied in:** session 03 (`modules/Core/Theme/ThemeManifest.php`, `docs/themes.md`)

## D-03-07 — Site name before the Settings session

- **Where:** specification "Configuration and settings" (site name is a site-wide setting, session 14)
- **Problem:** The layouts print the site name in the title, the brand and the footer.
- **Proposal:** the language line `Core.app.name` ("KP Skeletor") until session 14 adds the site settings; the logo is the theme option `logo`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 as proposed (deviation until session 14).
- **Applied in:** session 03 (`themes/default/Views/partials/`)

## D-03-08 — What `BackendController::render()` adds

- **Where:** specification "Common module base" (`BackendController`: layout, breadcrumbs…)
- **Problem:** Every back-office view needs the layout, breadcrumbs and a page header.
- **Proposal:** `render()` resolves the view through the theme and adds `layout` (`theme_layout('backend')`), `breadcrumbs` ("Back office" + `breadcrumb()` calls) and, when the data has a `title`, `pageHeader` (title, subtitle, icon, breadcrumbs, actions) that the layout prints; anything given in the data wins. `flash()` stores a message for the layout. `FrontendController` adds `meta()` (SEO) and `region()`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 as proposed.
- **Applied in:** session 03 (`modules/Core/Controllers/`)

## D-03-09 — `--name=value` options in the module commands

- **Where:** `docs/commands.md` (`modules:sync --demo[=A,B]`, `make:theme --parent=default`)
- **Problem:** CodeIgniter's CLI parser keeps `--demo=News` as an option named `demo=News`, so the documented `--name=value` form of session 02 was ignored.
- **Proposal:** the shared option reader (`ModulesSync::option()`) also accepts `--name=value`; both forms are documented.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 03 (fix of a session 02 gap).
- **Applied in:** session 03 (`modules/Core/Commands/ModulesSync.php`)

## D-04-01 — Where the front-end PHP classes live

- **Where:** plan (`modules/Core/Assets/AssetPublisher.php`, `AssetManifest.php`, `ImportMap.php`)
- **Problem:** `modules/Core/Assets/` is the Core's asset *source* folder (published to `public/assets/core/`); mixing PHP classes into it blurs the rule "Assets/ holds only publishable files".
- **Proposal:** the classes live in `modules/Core/Frontend/` (`Modules\Core\Frontend\AssetPublisher`, `AssetManifest`, `ImportMap`, `ThemeMode`, `Pwa`, `CspViewScanner`); `Assets/` only holds JS/CSS/images. `ThemeAssetPublisher` (session 03) is folded into `AssetPublisher` and removed.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`modules/Core/Frontend/`, `docs/frontend.md`)

## D-04-02 — Development serving rule for edited assets

- **Where:** spec "Front-end assets" ("the asset helper can serve a module's file directly when it is newer than the published copy")
- **Problem:** Sources under `modules/` are not web-reachable; serving them through PHP would add a route that reads code folders.
- **Proposal:** in development only (`assets.devRefresh`, default on), the first asset lookup of a request republishes everything when a source file or folder is newer than the manifest (the web user may write `public/assets/*` and `public/themes`, D-00-33). Production never checks the sources.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`AssetManifest::create()`, `AssetPublisher::isStale()`)

## D-04-03 — Asset manifest format and owners

- **Where:** spec "Front-end assets" (`asset('events::js/calendar.js')`, manifest with hashes)
- **Problem:** Owners, theme files and vendored files need a key scheme.
- **Proposal:** `public/assets/manifest.json` (git-ignored) maps `<owner>::<path>` to `{url, hash}` (SHA-256, 12 hex): owners `core`, `<module>` (kebab case of the module name) and `theme:<slug>`; `asset()` also accepts `theme::` (active chain) and `vendor::` (hash from `vendor.json`). `core`, `theme` and `vendor` are reserved module owners. The import map is built from the manifest (bare specifiers `core/…`, `<module>/…`, `theme/…`; relative imports are refused by a test).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`modules/Core/Frontend/`, `modules/Core/Helpers/asset_helper.php`)

## D-04-04 — Details of the strict CSP

- **Where:** spec "Security baseline" (CSP list), deploy/04 (report-only)
- **Problem:** The spec lists the main directives; CodeIgniter also sends `*-elem`/`*-attr` directives and needs values for fonts, workers and the manifest; a report endpoint would be an unauthenticated write.
- **Proposal:** `CSPEnabled = true`; `default-src`, `script-src`, `style-src`, `connect-src`, `font-src`, `manifest-src`, `worker-src`, `child-src`, `base-uri`, `form-action` = `'self'`; `img-src 'self' data:` (Bootstrap's CSS icons); `object-src`, `frame-ancestors`, `script-src-attr`, `style-src-attr` = `'none'`; nonces added automatically. Report-only and `reportURI` only through `.env` (`contentsecuritypolicy.*`); no built-in report endpoint (log flooding).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`app/Config/ContentSecurityPolicy.php`, `app/Config/App.php`)

## D-04-05 — Module CSP sources at runtime

- **Where:** spec "Configuring headers and CSP" (modules add sources in their manifest)
- **Problem:** `$csp` was validated since session 01 but not applied, and requests must not load manifests.
- **Proposal:** `modules:sync` stores `$csp` as declarations of kind `csp`; a global before filter `cspmodules` adds the sources of enabled modules to the policy (`script-src`/`style-src` also to their `-elem` directive, `'none'` and forbidden sources skipped).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`DeclarationRegistry::cspSources()`, `modules/Core/Filters/ContentSecurity.php`, `app/Config/Filters.php`)

## D-04-06 — Where the light/dark choice is stored before the profile exists

- **Where:** spec "Light and dark mode" (logged-in users in their profile, visitors in a cookie)
- **Problem:** There is no user profile until the Auth/Users sessions.
- **Proposal:** everyone's choice goes to the `kp_theme` cookie for now; `ThemeMode` already takes a user-preference reader that the Users session plugs in. Theme options `defaultMode` (auto), `forceMode` (empty), `modeSwitch` (on), `primaryColorDark` and `logoDark` (empty = same as light); option definitions accept `'mode' => 'dark'` and empty colour defaults.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`modules/Core/Frontend/ThemeMode.php`, `themes/default/Theme.php`)

## D-04-07 — Breakpoints and state of the back-office sidebar

- **Where:** spec "Responsive design" (offcanvas on phones, icon-only on tablets, remembers its state)
- **Problem:** Session 03 switched to the offcanvas below 992 px and had no icon-only state.
- **Proposal:** offcanvas below 768 px, icon-only bar 768–1199 px, full from 1200 px; a top-bar toggle switches icon-only/full and is remembered in the `kp_sidebar` cookie, printed by the server as a body class (no flash). Menu labels go in `<span class="nav-text">` (contract for session 08).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`themes/default/`, `core/ui/sidebar.js`)

## D-04-08 — PWA settings, icons and worker

- **Where:** spec "Minimal PWA", D-00-27
- **Problem:** Where icons are written, the setting names and the start URL.
- **Proposal:** settings `Core.pwa` (bool, off by default) and `Core.pwaStart` (`public`/`backend`); icons generated with GD from the public theme's local raster logo (never SVG or remote) into `writable/pwa/`, file name hashed on logo and primary colour, served by `/pwa/icon-<192\|512>.png`; `/sw.js` caches nothing; all routes 404 while off. Declaring Core settings also generates `settings.core` (automatic, like any module with settings).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`modules/Core/Frontend/Pwa.php`, `modules/Core/Controllers/Pwa.php`, `modules/Core/Config/Module.php`)

## D-04-09 — CSRF token for scripts without sessions for every visitor

- **Where:** spec "Front-end assets" (`http.js` with the CSRF header and token refresh)
- **Problem:** With session-based CSRF, printing the token on every page would start a session (cookie) for every anonymous visitor.
- **Proposal:** the head prints `csrf-header`/`csrf-token` meta tags only when a session is already active (back office, forms); `http.js` sends the header on unsafe methods and takes a refreshed token from the response header when present.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`themes/default/Views/partials/head.php`, `core/http.js`)

## D-04-10 — Project views with inline code from CodeIgniter

- **Where:** deploy/04 (CI CSP check of `app/`)
- **Problem:** `app/Views/welcome_message.php`, `errors/html/error_400.php` and `error_exception.php` (CodeIgniter's) have inline styles, scripts and handlers.
- **Proposal:** the welcome page is replaced by a themed home page (`app/Views/home.php`, until the Pages module), `error_400` loses its `<style>`, the development debug page gets nonces and is the only listed exception of `csp:check`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-04):** applied by session 04 as proposed.
- **Applied in:** session 04 (`app/Views/`, `modules/Core/Frontend/CspViewScanner.php`)

## D-05-01 — Own login, magic-link and reset controllers

- **Where:** deploy/05, spec "Authentication (Shield)"
- **Problem:** Shield's `LoginController`/`MagicLinkController` reveal whether an email exists ("Unable to find a user"), store magic-link tokens in clear and have no password-reset screen (Shield uses the magic link for that).
- **Proposal:** the Auth module has its own controllers on Shield's authenticator API: generic messages, a dummy hash for unknown emails, captcha, per-IP/per-account limits; magic-link and reset tokens are Shield identities (`magic-link`, `reset-password`) whose secret is the SHA-256 of the token, single use; a reset sets a new password on a form (not a login).
- **Alternatives:** A) Shield's controllers with overridden views (keep the enumeration and clear tokens).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-02 — Session tracking and remote logout

- **Where:** spec "Session handling", plan risk "session store that can be enumerated"
- **Problem:** "My sessions", remote logout and the absolute lifetime need to enumerate sessions; the file session handler cannot, and Shield's remember-me re-creates a remotely ended session.
- **Proposal:** table `user_sessions` (one row per login, the PHP session holds a random token, only its hash is stored, with the remember-me selector); the global `authsession` filter checks it on every request: ended row → logout; past `Auth.absoluteLifetime` → the PHP session ends without touching remember-me, which may re-create it (new row `remembered`); remote logout also deletes that session's remember-me token; remember-me tokens older than `Auth.rememberLifetime` (from their creation; Shield extends them on use) are deleted before Shield reads them. Logging out keeps Shield's behaviour (all the user's remember-me tokens are deleted).
- **Alternatives:** A) Database session handler and deleting session rows (ties the app to one handler).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-03 — Groups and grants before RBAC

- **Where:** spec "Groups", deploy/05 and 07
- **Problem:** `can:` uses Shield's matrix, which session 07 computes; until then nobody could open the profile.
- **Proposal:** `Config\AuthGroups` lists the four groups and, as an interim bootstrap, the `auth-profile.*`/`auth-sessions.*` permissions for every group (the same as the manifest's `defaultGrants`); session 07 replaces them with the computed `setting('AuthGroups.…')`. Superadmins bypass `can:` (`Can` filter and `BackendController::can()`); `GroupRules` holds the reserved-group rules (only a superadmin assigns or removes `superadmin`, never the last one, nobody changes their own groups, the four groups cannot be renamed or deleted) and flags the user's sessions for id regeneration.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-04 — Names, units and defaults of the Auth settings

- **Where:** spec "Session handling", "Rate limits", Settings › Security (session 14)
- **Problem:** The specification names only `Auth.minimumPasswordLength`.
- **Proposal:** Auth module settings (defaults in `Config\Auth`, equal to the manifest, a test checks it): `minimumPasswordLength` (8), `absoluteLifetime` (minutes, 720), `idleTimeout` (minutes, 0 = off), `rememberLifetime` (days, 14, 0 = off), `captcha` (off), `newDeviceNotice` (on), `loginAttemptsPerIp` (10), `loginAttemptsPerAccount` (5), `loginLockMinutes` (15), `emailLinksPerIp` (10/hour), `emailLinksPerAccount` (3/hour, extra requests silent). `AuthPolicy` raises weak values to a floor (8 characters, 5 minutes). Counters in the cache.
- **Alternatives:** A) Seconds everywhere (harder to edit).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-05 — App tokens vs personal tokens

- **Where:** spec "changing the password … revokes the tokens obtained by app logins; personal API tokens are listed"
- **Problem:** Shield access tokens have no origin; `extra` holds their scopes.
- **Proposal:** a personal token (created in the back office, later session) has `auth_identities.secret2 = 'personal'`; every other access token counts as an app-login token and is revoked by a password change or reset. Event `passwordChanged` (user, sessions ended).
- **Alternatives:** A) A naming convention (fragile).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-06 — CSRF and back-office filters before the security session

- **Where:** deploy/05, deploy/07 (`backend` chain), spec "Security baseline"
- **Problem:** Login forms need CSRF, which was not enabled; `backend/*` needs login and forced reset before session 07 builds its chain.
- **Proposal:** global `csrf` (except `api/*`) and `authsession`; `session` (Shield) and `forcereset` (Auth: only `backend/profile/password` is reachable while a reset is forced) on `backend`/`backend/*` in `Config\Filters::$filters`. Logout is a POST (GET `logout` shows a confirmation).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-07 — Where a login lands and profile preferences

- **Where:** deploy/05
- **Problem:** There is no dashboard yet; the profile stores language and colour mode but the theme reads the `kp_theme` cookie (D-04-06) and `supportedLocales` was `['en']`.
- **Proposal:** login redirect `backend/profile` until session 16; the profile's colour mode is copied into `kp_theme` on login and on save; the profile's language is applied to back-office requests by `authsession`; `App::$supportedLocales = ['en', 'ca', 'es']` (default locale left to session 31).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-05-08 — New-device notices

- **Where:** spec "an email notice when the account signs in from a new device"
- **Problem:** Every first login of a new account would send a notice.
- **Proposal:** random 32-byte cookie `kp_device` (1 year, HttpOnly, Lax), SHA-256 per user in `known_devices`; no notice for the first device of an account; setting `Auth.newDeviceNotice`.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 05 as proposed.
- **Applied in:** session 05 (`modules/Auth/`, `app/Config/`)

## D-06-01 — Email codes through the Core action

- **Where:** spec "Authentication" ("Email (Shield's `Email2FA` logic)"), decision D-00-13
- **Problem:** Shield's `Email2FA` class asks the user to type their email again, stores the code in clear, sends it with `emailer()` directly (not through `SecurityMailer`) and has no expiry; it also uses its own identity type, which a per-user dispatcher cannot share.
- **Proposal:** `UserTwoFactorAction` (Shield's `login` action, `ConditionalActionInterface`: applies only to users with 2FA) keeps Shield's logic (a 6-digit code in a pending code identity, `checkAction()` to complete the login) but sends the code at once through `SecurityMailer`, stores only its SHA-256 hash (`secret2`), expires it after 10 minutes, offers "send a new code" within the email limits and counts wrong codes as failed logins. The identity secret is a random nonce that `checkAction()` compares once a code (email, TOTP or recovery) has been verified. Its routes keep Shield's names (`auth-action-show|handle|verify`) at `auth/a/*`, rendered in the theme's auth layout, plus `auth/a/cancel`.
- **Alternatives:** use `Email2FA` unchanged for the email method (code in clear, no `SecurityMailer`).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 06 as proposed.
- **Applied in:** session 06 (`modules/Auth/Authentication/`, `app/Config/Auth.php`)

## D-06-02 — 2FA storage, TOTP and recovery codes

- **Where:** spec "Authentication" (TOTP secret encrypted, recovery codes hashed, ±1 step, no reuse)
- **Problem:** Shield has no place for a user's method, the TOTP secret or recovery codes.
- **Proposal:** two Auth tables: `user_two_factor` (user, method `email`/`totp`, TOTP secret encrypted with CI4 Encryption and base64-encoded, last accepted time step) and `user_recovery_codes` (SHA-256 of 10 codes of 10 characters from a 31-letter alphabet, ≈50 bits each, `used_at`). TOTP with `spomky-labs/otphp` (6 digits, 30 s, SHA-1: what every app reads), 160-bit secrets, steps −1…+1 accepted and only a step later than the last accepted one (conditional update, so two simultaneous requests cannot both pass). QR with `chillerlan/php-qrcode` as an SVG `data:` image (allowed by `img-src data:`; always on white). The app method is unavailable while `encryption.key` is empty (never stored in clear). Enabling a method creates new recovery codes (shown once); turning 2FA off or an admin reset deletes them.
- **Alternatives:** store the method in `user_profiles` (released migration: needs a new column migration anyway); bcrypt for recovery codes (slower per check, no gain at 50 random bits).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 06 as proposed.
- **Applied in:** session 06 (`modules/Auth/`, `composer.json`)

## D-06-03 — Remember-me and the 2FA step

- **Where:** spec "Session handling" (remember-me) and "Two-factor authentication"
- **Problem:** Shield issues the remember-me token inside `attempt()`, before the login action completes: a cookie obtained with the password alone would log in once the pending 2FA identity disappears (for example after an admin reset). On the other hand a valid remember-me token re-creates a session without asking for a code (Shield's behaviour: the device is trusted).
- **Proposal:** `Config\Auth::$authenticators['session']` is `Modules\Auth\Authentication\SessionAuthenticator` (a subclass of Shield's `Session`): for a user with 2FA the login form does not ask Shield to remember them and the token is issued only after a correct code. Remember-me logins keep skipping the code (device trust, limited by `Auth.rememberLifetime`), and sensitive actions still require re-authentication with password and code.
- **Alternatives:** disable remember-me for users with 2FA; ask the code on every remember-me login.
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 06 as proposed.
- **Applied in:** session 06 (`modules/Auth/`, `app/Config/Auth.php`)

## D-06-04 — A password login counts as re-authentication (decide)

- **Where:** spec "Re-authentication" (the confirmation lasts 15 minutes; a session re-created by remember-me still requires it)
- **Problem:** Right after typing the password (and the 2FA code) at the login, a sensitive action (for example the forced 2FA set-up of a required group) would ask for them again.
- **Proposal:** a password login, with its 2FA step, opens the re-authentication window for `Auth.reauthMinutes`; a magic-link login, a remember-me session and an unlocked idle lock do not. Changing it is one line in `LoginListener::login()`.
- **Alternatives:** A) no login counts: every sensitive action asks again, also just after logging in (stricter, one more prompt). B) magic links count too (weaker: the email account alone would give sudo mode).
- **Status:** Applied (decided)
- **Decision (2026-11-05):** «A) Sí, compta (recomanada)». A password login, with its 2FA step, opens the re-authentication window for `Auth.reauthMinutes`; magic-link logins, sessions re-created by remember-me and unlocking the idle lock do not.
- **Applied in:** `modules/Auth/Services/LoginListener.php` (built by session 06; tests `ReauthTest::testPasswordLoginOpensTheWindowForFifteenMinutes`, `TwoFactorLoginTest::testEmailCodeLogin`, `TwoFactorLoginTest::testMagicLinkLoginAlsoAsksForTheCode`, `ReauthTest::testSessionRecreatedByRememberMeStillRequiresIt`), `docs/specification.md`

## D-06-05 — 2FA group policy and re-authentication settings

- **Where:** spec "Policy per group (setting)", "Groups" (a `groups` table with the 2FA policy, session 15), "Re-authentication" (15 minutes, setting)
- **Problem:** The `groups` table arrives in session 15, but the policy is needed now.
- **Proposal:** settings `Auth.twoFactorPolicy` (json: group name → `required`|`optional`, missing groups optional; default `{}`) and `Auth.reauthMinutes` (int, 1–240, default 15), defaults in `Config\Auth` like the other Auth settings; session 15 moves the policy to the `groups` table (or makes the column write this setting). The `force2fa` filter on `backend/*` (after `forcereset`) sends a user of a required group without 2FA to `backend/profile/two-factor` (only the 2FA and password screens stay reachable; JSON gets 403); such a user can change the method but not turn 2FA off.
- **Alternatives:** wait for session 15 (acceptance criterion 5 needs the policy now).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 06 as proposed.
- **Applied in:** session 06 (`modules/Auth/`, `app/Config/Auth.php`, `app/Config/Filters.php`)

## D-06-06 — Re-authentication screen, URLs and non-GET requests

- **Where:** spec "Re-authentication" (`reauth` filter, `$this->requireReauth()`)
- **Problem:** Where the confirmation is asked and what happens to the request that triggered it.
- **Proposal:** `auth/reauth` (auth layout, checks the session itself) asks for the password and, with 2FA, the method's code (TOTP, or an emailed code requested with a button) or a recovery code; failures count as failed logins and past the limit end the session. The window is stored in the PHP session (so a re-created session starts without it) and forgotten at login/logout. The filter sends a GET to the screen and back afterwards; a POST/PUT/DELETE is never replayed (the user confirms and repeats it; they return to the Referer when it is a page of this site); JSON gets 401 with the URL. `BackendController::requireReauth()` runs the filter registered under the alias `reauth` (no module name in the Core) and denies when it is missing. The profile's 2FA changes (`backend/profile/two-factor/*`) are the first routes using it; impersonation (session 15) will refuse it.
- **Alternatives:** a modal with an AJAX confirmation (needs JS for a security path); replaying the POST (stores request bodies in the session).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 06 as proposed.
- **Applied in:** session 06 (`modules/Auth/`, `modules/Core/Controllers/BackendController.php`)

## D-07-01 — The `backend` filter chain

- **Where:** spec "RBAC" (`backend` chain: authentication, forced reset, forced 2FA, disabled-module check, maintenance, activity log, idle lock, throttling), example `['filter' => ['backend', 'can:…']]`
- **Problem:** Half of the chain exists (Shield `session`, `forcereset`, `force2fa`), part runs globally already (`moduleenabled`, `authsession` with the idle lock) and the rest does not exist yet (throttling 08, activity 12, maintenance 28). A chain that each route must name can be forgotten.
- **Proposal:** `backend` is a filter alias in `app/Config/Filters.php` listing the classes in order (`SessionAuth`, `ForcePasswordReset`, `ForceTwoFactor`) and is applied by pattern to `backend` and `backend/*` (`$filters`), replacing the three separate entries; routes only add `can:` (and `reauth`). The global filters keep covering the disabled-module check and the idle lock; later sessions append their classes to the alias. `RoutesSecurityTest` checks the pattern.
- **Alternatives:** A) Each route group names `backend` (as in the specification's example; easy to forget). B) A Core filter class that runs a configurable list of aliases (more indirection for the same result).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 07 as proposed (not a public convention change nor a security weakening).
- **Applied in:** session 07 (`app/Config/Filters.php`, `docs/rbac.md`)

## D-07-02 — Syntax of `can:` with several permissions

- **Where:** spec "RBAC" ("a `can` filter (accepts several permissions, any/all)")
- **Problem:** CodeIgniter filter arguments are a comma-separated list; the specification does not say how "all" is written.
- **Proposal:** `can:a.x,b.y` passes with **any** of them; `can:all,a.x,b.y` requires **all** of them (`all` has no dot, so it cannot be a permission). `can:` or `can:all` alone deny. Helpers `can()` (any) and `can_all()`.
- **Alternatives:** A) A second alias `canall:`. B) Several `can:` filters on the route (CodeIgniter runs each one: "all" by repetition, verbose).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 07 as proposed (fills a gap; no existing name changes).
- **Applied in:** session 07 (`modules/Core/Filters/Can.php`, `modules/Core/Helpers/can_helper.php`, `docs/specification.md`)

## D-07-03 — Unknown and disabled permissions are refused to everybody

- **Where:** spec "RBAC" (superadmins bypass the resolver; disabled modules hidden), "Module lifecycle"
- **Problem:** A superadmin bypass that answers yes to any string hides typos (`can:news-post.list` would work for the developer testing as superadmin and fail for everybody else), and grants of a disabled module would still match through Shield.
- **Proposal:** `Authorization` refuses names that are not in the catalogue of the **enabled** modules to everybody, superadmins included; superadmins hold every other permission. Shield's `AuthGroups.permissions`/`matrix` only contain the enabled catalogue and the grants (atomic or wildcard) that give some of it; grants are kept and come back when the module is enabled. `RoutesSecurityTest` and `permissions:check` also fail when a `can:` names a permission outside the catalogue.
- **Alternatives:** A) Superadmins pass any name (the literal reading; typos go unnoticed).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 07 as proposed (stricter than the specification, not weaker).
- **Applied in:** session 07 (`modules/Core/Permissions/Authorization.php`, `ShieldMatrix.php`, `RouteSecurityAuditor.php`, `docs/specification.md`)

## D-07-04 — Grant changes and extra escalation rules before the matrix screen

- **Where:** spec "RBAC" (implied permissions on save, atomic saves, escalation rules), session 15 (matrix screen)
- **Problem:** The rules must exist and be tested in this session, but the screens come in session 15. The specification lists four escalation rules; group membership and wildcards leave gaps (an admin could add a user to a group holding more than the admin; a wildcard could hide permissions the granter lacks).
- **Proposal:** `GrantManager` is the only way to change grants: names validated (atomic catalogue names, or Shield wildcards matching some of them; users get atomic names only), implied permissions added, group saves in one transaction, Shield's matrix rewritten, event `privilegesChanged` (user id) for every affected user, which Auth turns into `SessionTracker::privilegesChanged()`. `EscalationGuard` (Core) holds the rules and `Auth\GroupRules` delegates to it. Besides the four rules: a wildcard needs every permission it matches; adding someone to a group needs every permission of the group; only superadmins touch a superadmin's groups or permissions and the `superadmin` group's grants; nobody but a superadmin changes the grants of a group they belong to. Changes go to the application log until session 12.
- **Alternatives:** A) Only the four rules of the specification (leaves the gaps above).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 07 as proposed (stricter, not weaker).
- **Applied in:** session 07 (`modules/Core/Permissions/GrantManager.php`, `EscalationGuard.php`, `modules/Auth/Services/GroupRules.php`, `modules/Auth/Config/Events.php`)

## D-07-05 — Resolver wiring, the development check and diagnostics

- **Where:** spec "RBAC" (`CrudPermissions`, "in development it fails loudly", `permissions:check` "for every CRUD route")
- **Problem:** `Config\Kpcrudy` and `Config\Kpapy` are created by sessions 09 and 10; no CRUD exists yet, so `permissions:check` cannot instantiate CRUDs; kpcrudy/kpapy instantiate the resolver with `new $class()`.
- **Proposal:** `CrudPermissions` is final, has a no-argument constructor and `forUser($user)` for explicit users; sessions 09/10 set it as `$permissionResolver` with `$denyByDefault = true`. The catalogue comparison runs in every environment but production (so tests catch it), once per resource and process. `permissions:check` covers manifests, catalogue, grants, matrix and routes now; session 09 adds the CRUD comparison through `CrudPermissions::undeclared()` when `CrudFactory` exists. `permissions:check` diagnostics are English developer output (like exception messages); its summary lines are translated.
- **Alternatives:** A) Create the kpcrudy/kpapy config files now (out of scope of this session).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 07 as proposed.
- **Applied in:** session 07 (`modules/Core/Permissions/CrudPermissions.php`, `PermissionAudit.php`)

## D-08-01 — Menu keys, zones, rendering and breadcrumbs

- **Where:** spec "Menus", "Modules › Menus" (stable keys), deploy/08
- **Problem:** The specification names keys, zones and parents but not how `parent` refers to another entry, which zones the theme prints, what happens to entries whose parent or route is missing, nor how breadcrumbs from the tree combine with the ones controllers add.
- **Proposal:** entry key `<Module>.<resource>` or the route name (`<key>@<zone>` for the same resource in a second zone); `parent` = a resource of the same module or any key; `modules:sync` stores normalised entries in `module_declarations` (kind `menu`). Zones: `sidebar` and `settings` (under a heading) in the sidebar, `user` in the user menu (Auth declares profile and sessions there); other zones are kept for later themes/sessions. Entries without an existing route are hidden; an entry whose parent is missing goes to the root; cycles are dropped. Cache key: groups + individual permissions; `GrantManager` invalidates `menus`. Breadcrumbs come from the menu only when the controller adds none.
- **Alternatives:** A) Mix menu and controller breadcrumbs (duplicates). B) Hide orphans (a disabled parent module would hide another module's entries).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 08 as proposed.
- **Applied in:** session 08 (`modules/Core/Menus/`, `themes/default/Views/partials/`, `docs/menus.md`)

## D-08-02 — Security headers on every response

- **Where:** spec "Security baseline" (headers), D-00-28
- **Problem:** Global "after" filters do not run for unknown routes and exception pages; the specification does not say whether a controller may change a header.
- **Proposal:** `securityheaders` is a **required** after filter (runs for every route, existing or not) and `Config\Exceptions::handler()` wraps CodeIgniter's handler to add the same headers to error responses. `Cross-Origin-Opener-Policy: same-origin` added; HSTS `null` = production only, max-age never below one year; a header already set by a controller is kept; CodeIgniter's `secureheaders` alias removed. `forceGlobalSecureRequests` true in production (constructor), like `Cookie::$secure`.
- **Alternatives:** A) Global after filter only (404/500 pages without headers).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 08 as proposed.
- **Applied in:** session 08 (`app/Config/SecurityHeaders.php`, `modules/Core/Filters/SecurityHeaders.php`, `modules/Core/Http/SecureExceptionHandler.php`, `docs/security.md`)

## D-08-03 — Rate-limit service and its settings

- **Where:** spec "Security baseline › Rate limits" ("values in settings"), "RBAC" (throttling in the `backend` chain), D-05-04
- **Problem:** The settings are not named and Auth already limits logins, links, 2FA and re-authentication with its own settings.
- **Proposal:** `RateLimiter` (Throttler) with named limits and Core settings `rateLimitBackend` (300/min per user), `rateLimitApiToken` (120/min), `rateLimitApiIp` (60/min), `rateLimitFeeds` (60/h per token), `rateLimitUploads` (100/h per user), `rateLimitForms` (10/h per IP), `rateLimitWidgets` (120/min per user); `ratelimit:<limit>` filter (per user, or IP for guests) whose class closes the `backend` chain; 429 + `Retry-After` (themed `errors/429` or JSON). Auth keeps `LoginThrottle` and its `Auth.*` settings for the authentication forms; API login (`auth/token`) reuses them in session 10.
- **Alternatives:** A) Move Auth's counters to the Core service now (rewrites tested Auth code without need).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 08 as proposed.
- **Applied in:** session 08 (`modules/Core/Security/RateLimiter.php`, `modules/Core/Filters/RateLimit.php`, `modules/Core/Config/Module.php`)

## D-08-04 — OutboundClient details and the allow-list sources

- **Where:** spec "Outbound requests", D-00-29, plan session 08 (DNS rebinding)
- **Problem:** The format of allow-list entries, the allowed ports, proxies, the setting name and how the mail service is hooked are not specified.
- **Proposal:** entries are host names or `*.example.com` (subdomains only); sources `Config\Outbound::$allowedHosts` + `.env` `outbound.hosts` + enabled modules' `$outboundHosts` (`module_declarations` kind `outbound`) + setting `Core.outboundHosts` (JSON list); port 443 only (configurable); every resolved address must be public, connection pinned with `CURLOPT_RESOLVE`; redirects followed by hand and re-checked; direct connections unless `outbound.proxy` is set; logging to the application log until session 12 (no query strings). Mail: `app/Config/Services::email()` returns `GuardedEmail`, which refuses SMTP hosts outside the list.
- **Alternatives:** A) CodeIgniter's CURLRequest (no address pinning, follows redirects itself).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 08 as proposed.
- **Applied in:** session 08 (`app/Config/Outbound.php`, `modules/Core/Http/`, `modules/Core/Mail/GuardedEmail.php`, `docs/outbound-requests.md`)

## D-08-05 — What `safe_redirect()` accepts

- **Where:** spec "Data handling" ("redirects only to internal paths")
- **Problem:** Absolute URLs of the site itself are common in `return` values.
- **Proposal:** `safe_redirect($url, $fallback)` follows paths and http(s) URLs whose host is `app.baseURL`'s or in `app.allowedHostnames`; refuses `//`, backslashes, other schemes, user information and control characters; an external fallback falls back to `/`.
- **Alternatives:** A) Paths only (breaks absolute self links).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 08 as proposed.
- **Applied in:** session 08 (`modules/Core/Security/SafeRedirect.php`, `modules/Core/Helpers/redirect_helper.php`)

