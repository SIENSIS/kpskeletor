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
| D-09-01 | 09 | Where `app.maskKey` lives and when the application refuses to start | Accepted (proposal) |
| D-09-02 | 09 | CRUD page header, messages as toasts and the confirmation modal | Accepted (proposal) |
| D-09-03 | 09 | `CrudFactory` details and how `permissions:check` inspects CRUDs | Accepted (proposal) |
| D-09-04 | 09 | `make:module --crud`: running `kpcrudy:make` and what is rewritten | Accepted (proposal) |
| D-09-05 | 09 | The sample CRUD lives in the tests, not in a module | Accepted (proposal) |
| D-10-01 | 10 | The API core in a core module `Api` | Accepted (proposal) |
| D-10-02 | 10 | Second factor of the app login: authenticator app, else an emailed code | Accepted (proposal) |
| D-10-03 | 10 | API limits, CORS and login rules | Accepted (proposal) |
| D-10-04 | 10 | No session on the API, its language and `api/v1/me` | Accepted (proposal) |
| D-10-05 | 10 | Personal tokens: scopes, expiry, last IP and the administrators' list | Accepted (proposal) |
| D-11-01 | 11 | The notification type contract | Accepted (proposal) |
| D-11-02 | 11 | The email channel: queue, sending task and the recipient's language | Accepted (proposal) |
| D-11-03 | 11 | Recipients, preferences and mandatory types | Accepted (proposal) |
| D-11-04 | 11 | Notification screens, URLs and the bell | Accepted (proposal) |
| D-11-05 | 11 | How the scheduler decides, locks and records runs; the `schedule` resource | Accepted (proposal) |
| D-11-06 | 11 | The poor man's cron | Accepted (proposal) |
| D-12-01 | 12 | Who writes the activity log | Accepted (proposal) |
| D-12-02 | 12 | CRUD writes logged by a Core subclass of kpcrudy's `Crud` | Accepted (proposal) |
| D-12-03 | 12 | Entry format, action codes and labels, security events | Accepted (proposal) |
| D-12-04 | 12 | Retention setting `Core.activityRetentionDays` | Applied |
| D-12-05 | 12 | Personal data and network fields in the log | Accepted (proposal) |
| D-13-01 | 13 | Private file URL and who may read a private file | Applied (decided) |
| D-13-02 | 13 | Upload settings `Core.uploadTypes`, `Core.uploadMaxSize`, `Core.uploadAllowSvg` | Applied (decided) |
| D-13-03 | 13 | `stored_files` in the Core | Accepted (proposal) |
| D-13-04 | 13 | Headers of served private files | Accepted (proposal) |
| D-13-05 | 13 | Upload checks in detail | Accepted (proposal) |
| D-14-01 | 14 | Which settings belong to Settings › Security, and the `list` type | Applied (decided) |
| D-14-02 | 14 | Names of the new site-wide and CSP settings | Applied (decided) |
| D-14-03 | 14 | Settings permissions, routes and actions | Accepted (proposal) |
| D-14-04 | 14 | What "weakening the baseline" means | Accepted (proposal) |
| D-14-05 | 14 | Test email and clear caches | Accepted (proposal) |
| D-14-06 | 14 | Site-wide settings built now | Accepted (proposal) |
| D-15-01 | 15 | Users permission names, URLs and setting (decide) | Applied (decided) |
| D-15-02 | 15 | Groups as data: names, deletion, new groups and the 2FA policy | Accepted (proposal) |
| D-15-03 | 15 | Adding and editing users; no deletion | Accepted (proposal) |
| D-15-04 | 15 | Who manages whose account | Accepted (proposal) |
| D-15-05 | 15 | Impersonation mechanics and the permission matrix save | Accepted (proposal) |
| D-16-01 | 16 | Dashboard permission names, URLs and the login landing page (decide) | Applied (decided) |
| D-16-02 | 16 | Group layouts: defaults, several groups, what users may change | Accepted (proposal) |
| D-16-03 | 16 | The widget contract, async data and failures | Accepted (proposal) |
| D-16-04 | 16 | The back-office home in the menu and breadcrumbs | Accepted (proposal) |
| D-16-05 | 16 | Dashboard is a core module | Applied (decided) |
| D-16-06 | 16 | Quick links: free URLs and the order chosen by the administrator (decide) | Applied (decided) |
| D-17-01 | 17 | News permission names, URLs, settings and the reader's API permissions (decide) | Applied (decided) |
| D-17-02 | 17 | Sanitising a translated body before kpcrudy reads the form | Accepted (proposal) |
| D-17-03 | 17 | Post statuses, publishing, scheduling and expiry | Accepted (proposal) |
| D-17-04 | 17 | Who reads what: visibility rules and the notice audience | Accepted (proposal) |
| D-17-05 | 17 | The menu link type before the Menus module | Accepted (proposal) |
| D-17-06 | 17 | The cover through a kpcrudy upload handler on the Storage service | Accepted (proposal) |
| D-17-07 | 17 | The API's visibility as a `where()` condition | Applied |
| D-17-08 | 17 | Images inside the rich-text body (decide) | Applied (decided) |
| D-18-01 | 18 | HTTPS and the web installer | Accepted (proposal) |
| D-18-02 | 18 | The site before installation: redirect, base URL and boot checks | Accepted (proposal) |
| D-18-03 | 18 | Passwords and the non-interactive installer | Accepted (proposal) |
| D-18-04 | 18 | Demo data while installing | Accepted (proposal) |
| D-18-05 | 18 | 2FA for the first superadmin without a Core → Auth dependency | Accepted (proposal) |
| D-18-06 | 18 | `app:install` with an existing `.env` | Accepted (proposal) |
| D-18-07 | 18 | The `security:check` summary before session 30 | Accepted (proposal) |
| D-18-08 | 18 | Order of the steps, `.env` permissions and the lock file | Accepted (proposal) |
| D-19-01 | 19 | Demo users' random passwords: shown once, how | Accepted (proposal) |
| D-19-02 | 19 | Routes under `api/` outside `api/v1/` | Accepted (proposal) |
| D-19-03 | 19 | `make:module --api` | Accepted (proposal) |
| D-19-04 | 19 | Shared Core helpers extracted from News | Accepted (proposal) |
| D-19-05 | 19 | HTTP on `localhost` with `php spark serve` | Applied (decided) |
| D-20-01 | 20 | Pages permission names, URLs, the home page setting and the theme's page templates (decide) | Applied (decided) |
| D-20-02 | 20 | Private pages: login for guests, 404 for the wrong group | Accepted (proposal) |
| D-20-03 | 20 | The rich-text editor: Quill 2 under the enforced CSP | Accepted (proposal) |
| D-20-04 | 20 | The catch-all page route, reserved slugs and the home page hook | Accepted (proposal) |
| D-20-05 | 20 | Slugs per language and the language of a page | Accepted (proposal) |
| D-20-06 | 20 | What a revision keeps and what a restore writes back | Accepted (proposal) |
| D-20-07 | 20 | Content helpers extracted from News into the Core | Accepted (proposal) |
| D-21-01 | 21 | Menus permission names, URLs and the Core contracts for menus (decide) | Applied (decided) |
| D-21-02 | 21 | Menu items edited in a tree editor and a form of their own | Accepted (proposal) |
| D-21-03 | 21 | The customisation layer: differences, field-by-field scopes, hidden subtrees | Accepted (proposal) |
| D-21-04 | 21 | Custom links and menu items: addresses and the 403 rule | Accepted (proposal) |
| D-21-05 | 21 | Public menus: visibility, depth, cache and the active item | Accepted (proposal) |
| D-21-06 | 21 | Demo data that depends on another module's demo | Accepted (proposal) |
| D-22-01 | 22 | Files permission names, URLs, settings and space paths (decide) | Decided |
| D-22-02 | 22 | The file manager is a screen of its own over JSON endpoints | Accepted (proposal) |
| D-22-03 | 22 | Virtual folders: `files.folder`, files referencing `stored_files` | Accepted (proposal) |
| D-22-04 | 22 | Who reads a private file, and the folder ACL details (decide) | Decided |
| D-22-05 | 22 | Quotas, trash and thumbnails | Accepted (proposal) |
| D-22-06 | 22 | Upload progress without inline styles | Accepted (proposal) |

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

## D-09-01 — Where `app.maskKey` lives and when the application refuses to start

- **Where:** spec "JSON API" (the same `app.maskKey`), deploy/09 ("refuse to boot in production when it is empty")
- **Problem:** The key must be read by two library configs; "refuse to boot" also stops `spark`, which the installer (session 18) needs to generate the key. kpcrudy/kpapy reject keys under 32 characters only when they first use them.
- **Proposal:** `Config\App::$maskKey` (`.env` `app.maskKey`), read through `Modules\Core\Security\MaskKey` by `Config\Kpcrudy` and `Config\Kpapy` (a minimal kpapy config that session 10 completes). A Core `pre_system` listener refuses **web** requests in production when the key is empty, and in every environment when it is shorter than 32 characters; `spark` is not blocked. The `env` template documents it empty; `phpunit.xml.dist` sets a test-only value; local `.env` files get a generated one (`php -r "echo bin2hex(random_bytes(32));"`).
- **Alternatives:** A) Refuse in `spark` too (the installer could not run). B) Throw only when a CRUD is first used (a misconfigured production would serve pages until then).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 09 as proposed.
- **Applied in:** session 09 (`app/Config/App.php`, `app/Config/Kpcrudy.php`, `app/Config/Kpapy.php`, `modules/Core/Security/MaskKey.php`, `modules/Core/Config/Events.php`, `phpunit.xml.dist`)

## D-09-02 — CRUD page header, messages as toasts and the confirmation modal

- **Where:** spec "CRUDs with kpcrudy" (`showTitle = false`, "`flashKeys` mapped to the layout's toasts", "`kpcrudy:confirm` to use the theme's confirmation modal")
- **Problem:** In section mode kpcrudy only passes `$title` to the layout, which printed a page header only from `$pageHeader`; the layout showed session messages as alerts, not toasts; kpcrudy has an `info` type the layout did not know.
- **Proposal:** `partials/page_top` builds the page header from `$title` + `$breadcrumbs` when no `$pageHeader` is given (`renderCrud()` passes the breadcrumbs); `flashKeys` = success→`success`, danger→`error`, warning→`warning`, info→`info` (new key, also accepted by `BackendController::flash()`); `partials/flash` prints the alerts inside a `core/ui/flash` component that turns them into toasts (alerts stay without JavaScript); `core/ui/confirm.js` cancels `kpcrudy:confirm` and asks with the theme's modal. The library's `crud_container` card is not used: the CRUD markup is already a card.
- **Alternatives:** A) Render CRUDs in fragment mode inside a Core view (loses `page()` layouts of custom operations).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 09 as proposed.
- **Applied in:** session 09 (`themes/default/Views/partials/{page_top,flash}.php`, `modules/Core/Assets/js/ui/{flash,confirm}.js`, `modules/Core/Controllers/BackendController.php`)

## D-09-03 — `CrudFactory` details and how `permissions:check` inspects CRUDs

- **Where:** spec "CRUDs with kpcrudy" (`CrudFactory::make()/routes()`), D-07-05 (CRUD comparison in `permissions:check`)
- **Problem:** CRUDs are configured inside controller actions, so a command cannot read their operations without running them; routes of `backend/*` already get the `backend` chain by pattern.
- **Proposal:** `CrudFactory::routes()` always adds `backend` (CodeIgniter runs an alias once per request, so routes outside `backend/` are covered too), `can:<resource>.list`, extra filters appended, `as` = resource; resource names are validated. `CrudAuditor` (run by `permissions:check`) finds every kpcrudy route, requires the factory's route name and `can:`, runs the action with `CrudFactory::inspect()` and a GET web request and no user; `renderCrud()` stops before rendering; it reports CRUDs not made with the factory, a resource different from the route's, and operations not in the catalogue (`CrudPermissions::undeclared()`). Operations a CRUD adds only for some users are not seen there; `CrudPermissions` still fails loudly at run time outside production.
- **Alternatives:** A) A static `crud()` method per controller describing the CRUD (duplicates the configuration).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 09 as proposed.
- **Applied in:** session 09 (`modules/Core/Crud/`, `modules/Core/Permissions/PermissionAudit.php`)

## D-09-04 — `make:module --crud`: running `kpcrudy:make` and what is rewritten

- **Where:** D-00-09, spec "Common module base"
- **Problem:** `kpcrudy:make` reads its options with `CLI::getOption()`, so `command()` cannot pass them; it needs the table in the database; it writes a non-strict, non-final controller without return types and `image`/`upload` field types (forbidden, D-00-23).
- **Proposal:** `make:module <Name> --crud=<table>` generates the module with a migration for that table, a `crud` resource `<key>` (table without the `<module>_` prefix and underscores; `except` `import`), its admin grant, menu entry (under `main`) and language keys; runs `php spark migrate -n Modules\<Name>` and `php spark kpcrudy:make <table> --namespace … --extends BackendController --controller <Key> --route backend/<module>/<key> --as <module>-<key> --filter backend` as child processes; then rewrites the route to `CrudFactory::routes()`, `new Crud()` → `$this->crud()`, `render()` → `renderCrud()`, adds strict types, `final` and the return type, and replaces upload field types with a comment pointing to the file picker.
- **Alternatives:** A) Call kpcrudy's `CrudGenerator` class directly (not documented as public API).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 09 as proposed.
- **Applied in:** session 09 (`modules/Core/Commands/MakeModule.php`, `modules/Core/Modules/{CrudScaffold,ModuleGenerator}.php`, `modules/Core/Modules/Templates/`)

## D-09-05 — The sample CRUD lives in the tests, not in a module

- **Where:** deploy/09 ("A sample CRUD in a test module")
- **Problem:** A real `modules/<Sample>` would be discovered, synced and shown in every installation.
- **Proposal:** a test-only "Sample" module in `tests/_support/Crud/` (manifest, controller, routes and table installed by the test); the permissions come from its manifest through `CatalogueBuilder`, like a real module's. Screenshots use a module generated with `make:module Library --crud=library_books` that is not committed.
- **Alternatives:** A) A disabled demo module shipped with the skeleton.
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 09 as proposed.
- **Applied in:** session 09 (`tests/_support/Crud/`, `tests/feature/Crud/`)

## D-10-01 — The API core in a core module `Api`

- **Where:** spec "JSON API (kpapy)" (`api.docs`, `api.tokens`, `Api.publicSpec`), `docs/plan.md` session 10 (files in `modules/Core/Api/`), CLAUDE.md ("never hard-code a module in the Core")
- **Problem:** The plan put the API login providers in the Core, but they read the Auth module's 2FA data (the Core must not depend on a module); settings named `Api.*` need a module named `Api` (setting names are `<Module>.<key>`); the administrators' token list needs permissions the specification does not name.
- **Proposal:** a module `Api` (requires Core and Auth) added to the core modules that cannot be disabled (`ModuleToggler::CORE_MODULES`). One resource named `api` (D-00-18): kpcrudy CRUD of every user's tokens (`api.list`, `api.show`, `api.delete`) plus `api.docs` and `api.tokens`; settings `Api.publicSpec`, `Api.corsOrigins`, `Api.appTokenDays`; default grants admin `api.*`, editor `api.tokens`. Names in the specification are unchanged. Modules' API resources stay in their own modules; Core resources (notifications, session 26) in the Core.
- **Alternatives:** A) Everything in the Core with settings `Core.api*` (renames `Api.publicSpec`; Core depends on Auth). B) The admin list as a hand-written screen with `api.manage`.
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 10 as proposed.
- **Applied in:** `modules/Api/`, `modules/Core/Modules/ModuleToggler.php`, `docs/specification.md` (core modules), `docs/api.md`

## D-10-02 — Second factor of the app login: authenticator app, else an emailed code

- **Where:** spec "JSON API" › "Login for apps", `docs/plan.md` (`twoFactorMethods = ['totp', 'email', 'emailcode']`)
- **Problem:** Users with email 2FA and users without 2FA both receive a code by email; two methods would differ only in the email's wording, and the app would have to handle both.
- **Proposal:** `twoFactorMethods = ['totp', 'email']`. `totp` (`Modules\Api\TwoFactor\TotpProvider`): users whose method is the authenticator app; codes checked by Auth's `TwoFactor::verifyTotp()` (a step is never reused, shared with the web), or a recovery code. `email` (`EmailCodeProvider`): everybody else; the email names device, IP and time and links to `auth/api-login/<challenge>` (no login: the 192-bit challenge id is the proof and it can only stop the sign-in; the POST needs CSRF). Users with 2FA may also answer with a recovery code. Codes valid 10 minutes, 5 wrong codes close the challenge. kpapy's `web` method is not used.
- **Alternatives:** A) A third method `emailcode` for users without 2FA. B) Refuse users without 2FA (`403 two-factor-required`).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 10 as proposed.
- **Applied in:** `app/Config/Kpapy.php`, `modules/Api/TwoFactor/`, `modules/Api/Controllers/LoginDenyController.php`

## D-10-03 — API limits, CORS and login rules

- **Where:** spec "JSON API" › "Cross-cutting", "Security baseline" › rate limits
- **Problem:** kpapy reads its limits and origins from its config class; the skeleton keeps them in settings.
- **Proposal:** `Config\Kpapy`'s constructor reads `Core.rateLimitApiToken` (per token), `Core.rateLimitApiIp` (per IP, routes without a token), `Api.appTokenDays` and `Api.corsOrigins` (full `http(s)://host` origins only; `*` is dropped). The app login keeps kpapy's `rateLimitLogin` (10 per minute per IP and per account) and also counts wrong passwords in Auth's `LoginThrottle` (shared with the web form; `429` while blocked). Users who must change their password get `403 password-reset-required`; personal tokens cannot be refreshed (`403 personal-token`).
- **Alternatives:** A) An extended `ApiFilter` using the Core's `RateLimiter` (the library's alias would have to be overridden).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 10 as proposed.
- **Applied in:** `app/Config/Kpapy.php`, `modules/Api/Controllers/LoginController.php`

## D-10-04 — No session on the API, its language and `api/v1/me`

- **Where:** spec "Security baseline" › API ("the API never reads the session cookie"), "JSON API" (problem+json translated, `me`)
- **Problem:** The global `authsession` filter opened a session (and set its cookie) on API requests; locale negotiation is off in the application, so API errors were always in the default language; `me` is not a table resource.
- **Proposal:** `authsession` excludes `api/*`; a filter `apilocale` (Api module, on `api/*`) negotiates `Accept-Language` among ca/es/en; `GET api/v1/me` is a controller using kpapy's `Responder` (any valid token, no secrets): masked id (context `users`, the same as a future users CRUD), name, email, locale, time zone, colour mode, groups, 2FA method and the token's name, scopes and expiry; avatar URL `null` until session 23. (In development the debug toolbar still starts a session; production never.)
- **Alternatives:** A) `me` as a kpapy `Resource` on `users` limited with `where()` (it would answer a list).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 10 as proposed.
- **Applied in:** `app/Config/Filters.php`, `modules/Api/Filters/ApiLocale.php`, `modules/Api/Controllers/Me.php`

## D-10-05 — Personal tokens: scopes, expiry, last IP and the administrators' list

- **Where:** spec "JSON API" › "Personal tokens" (name, scopes, expiry, last use and IP)
- **Problem:** The specification does not say which scopes or expiries are offered; Shield does not store the address of the last use.
- **Proposal:** scopes `*` or permissions the user holds when creating the token (escalation refused on the server); expiry 7, 30, 90 or 365 days (default 90, never unlimited); creation behind `reauth`, the secret shown once; tokens marked personal (`secret2`, D-05). A table `api_token_ips` (Api module) keeps the last address of each token, written by `Modules\Api\Auth\TokenAuthenticator` only when it changes. Administrators list and revoke every token in a kpcrudy CRUD over `auth_identities` (type `access_token`; no secrets shown).
- **Alternatives:** A) Free-text scopes. B) Store the IP in `auth_identities.extra` (Shield keeps the scopes there).
- **Status:** Accepted (proposal)
- **Decision (2026-11-05):** applied by session 10 as proposed.
- **Applied in:** `modules/Api/Services/PersonalTokens.php`, `modules/Api/Controllers/{PersonalTokens,Tokens}.php`, `modules/Api/Database/Migrations/`

## D-11-01 — The notification type contract

- **Where:** spec "Notifications" (types: key, title, icon, default channels, a `render()` for the bell and an email template)
- **Problem:** The specification does not say how a type is identified, what `render()` returns, nor when the text is produced (sending time or reading time, in which language).
- **Proposal:** an abstract class `Modules\Core\Notifications\NotificationType`: static `title()`, `description()`, `icon()`, `defaultChannels()`, `mandatory()`; `render(): NotificationMessage` (title, body, internal path); `emailSubject()`, `emailView()`. The key is the class name (stored in `notifications.type` and `notification_preferences.type`). The constructor data is stored as JSON and rendered when shown, in the reader's language; types are rebuilt with `fromData()` without their constructor. Only types declared by enabled modules are sent; stored notifications of a vanished type render a generic text. Links are internal paths only.
- **Alternatives:** A) Store the rendered text (one language, stale after edits). B) A string key separate from the class.
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 11 as proposed.
- **Applied in:** `modules/Core/Notifications/`, `docs/notifications.md`

## D-11-02 — The email channel: queue, sending task and the recipient's language

- **Where:** spec "Notifications" (email "queued or sent by the scheduler, never inline"), D-00-15
- **Problem:** Where the queue lives, how failures are retried and in which language the email is written are not specified; the Core does not know where users keep their language.
- **Proposal:** a `mail_queue` table (rendered subject and HTML body, status pending/sent/failed, attempts, last error, available at). The Core scheduled task `SendQueuedEmails` (every minute, without overlapping) sends 50 per run through the `email` service (GuardedEmail: SMTP host in the outbound allow-list), claims each row atomically, retries up to 5 times with growing waits (5, 10, 20, 40 minutes) and deletes sent/failed rows after 30 days. Emails are rendered when queued in the recipient's language: `RecipientLocale` asks a resolver the Auth module registers (profile locale), else the default locale.
- **Alternatives:** A) Render at sending time (needs the type and data again). B) CodeIgniter's queue package (a new dependency).
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 11 as proposed.
- **Applied in:** `modules/Core/Mail/MailQueue.php`, `modules/Core/Tasks/SendQueuedEmails.php`, `modules/Core/Notifications/{Channels,RecipientLocale.php}`, `modules/Auth/Config/Events.php`

## D-11-03 — Recipients, preferences and mandatory types

- **Where:** spec "Notifications" (`notify()`, by group / permission; preferences per type; mandatory types), D-00-20
- **Problem:** Which users are skipped, how preferences are stored and what "mandatory" means for the channels.
- **Proposal:** `notify()` accepts users, ids or a list; each user once per call; banned and deleted users are skipped. `notifyGroup()` reads `auth_groups_users`; `notifyPermission()` checks every user with `Authorization::for($user)` (superadmins included; works without a session). Preferences: one row per user and type with the chosen channels; no row = the type's default channels; users may choose any channel (database, email). Mandatory types always use their default channels; the form shows them disabled and the server ignores them. The 2FA reset (Auth) is mandatory.
- **Alternatives:** A) Preferences limited to the default channels. B) Notify only active (email-verified) users.
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 11 as proposed.
- **Applied in:** `modules/Core/Notifications/Notifier.php`, `modules/Auth/Notifications/TwoFactorReset.php`

## D-11-04 — Notification screens, URLs and the bell

- **Where:** spec "Notifications" › UI, D-00-17
- **Problem:** URLs and operations of the list, the bell and the preferences are not specified.
- **Proposal:** resource `core-notifications` (Core, `list`, `show`, `delete`; granted to every group). `backend/notifications` (kpcrudy, `where('user_id', me)`, mark one / selected / all as read as custom operations checked as `list`); `backend/notifications/bell` (JSON, latest 8 and the unread count); `backend/notifications/open/{id}` (GET: marks the user's own notification as read and redirects to its internal path or detail page; another user's id is "not found"); `POST backend/notifications/read-all`; preferences at `backend/profile/notifications` (GET/POST). All behind `can:core-notifications.list`. The bell is a theme partial (`partials/notifications_bell.php`) with the ES module `core/ui/bell.js`; it works without JavaScript through links and a form.
- **Alternatives:** A) "Open" as a POST (a form per bell item). B) Preferences inside the Auth profile page (would make the Core depend on Auth's view).
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 11 as proposed.
- **Applied in:** `modules/Core/Config/{Module,Routes}.php`, `modules/Core/Controllers/{Notifications,NotificationPreferences}.php`, `themes/default/Views/partials/notifications_bell.php`

## D-11-05 — How the scheduler decides, locks and records runs; the `schedule` resource

- **Where:** spec "Scheduled tasks"; plan risk "locks on shared hosting (file locks vs DB locks)"
- **Problem:** "Due" semantics (missed minutes, two processes), where locks live, where `withoutOverlapping()`/`onlyInEnvironment()` are declared, how much history is kept and the permission names of the screen.
- **Proposal:** `TaskInterface` (`name()`, `schedule(): Schedule`, `run(): TaskResult`); the options are methods of `Schedule` (`daily('03:00')->withoutOverlapping()->onlyInEnvironment('production')`). `scheduled_tasks` keeps each declared class with its `next_run_at`; a task runs when it has passed and the runner first moves it forward in one atomic `UPDATE … WHERE next_run_at = <old>` (two processes never run a slot twice; a missed period runs once; new tasks wait for their first slot). Locks are columns of the same table (`locked_until`, `lock_owner`), so they work on every host and expire. A run blocked by the lock is recorded as `skipped`. `task_runs` keeps the latest 50 runs per task (output up to 60 KB, source cron/manual/web, user of "run now"). Failures are logged and notified (`ScheduledTaskFailed`) to the holders of `schedule.list`. Tasks of disabled modules are marked inactive. Screen: single Core resource `schedule` (D-00-18) with `schedule.list`, `schedule.show`, `schedule.run`; administrators get `schedule.*`.
- **Alternatives:** A) File locks (`flock`, unreliable on some shared/NFS hosts). B) Run every matching minute without `next_run_at` (missed runs are lost; double runs possible).
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 11 as proposed.
- **Applied in:** `modules/Core/Scheduler/`, `modules/Core/Commands/Schedule{Run,List}.php`, `modules/Core/Controllers/Schedule.php`, `docs/scheduler.md`

## D-11-06 — The poor man's cron

- **Where:** spec "Scheduled tasks" (fallback "run after the response is sent"); plan risk (`fastcgi_finish_request()` absent with `php spark serve`/mod_php)
- **Problem:** CodeIgniter triggers `post_system` before sending the response, and not every server can finish a response early.
- **Proposal:** setting `Core.poorMansCron` (bool, off). The after filter `PoorMansCron` is part of the `backend` chain; at most once a minute (cache timestamp; the runner's atomic claims cover races) it registers a shutdown function that closes the session, calls `fastcgi_finish_request()` / `litespeed_finish_request()` when available and runs the due tasks with source `web`. On other servers the browser waits for the tasks (documented).
- **Alternatives:** A) An asynchronous HTTP request to a "tick" URL (an outbound call from a regular request, forbidden). B) Only on the dashboard.
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 11 as proposed.
- **Applied in:** `modules/Core/Filters/PoorMansCron.php`, `app/Config/Filters.php`, `modules/Core/Config/Module.php`

## D-12-01 — Who writes the activity log

- **Where:** spec "Activity log", "Modules" › "Activity"; `docs/plan.md` session 12 (listeners in `modules/Core/Config/Events.php` for Shield, kpapy, reauth, 403s and tokens)
- **Problem:** The plan puts every listener in the Core, but the Core must never hard-code a module (`CLAUDE.md`), and the log must keep working when the Activity module is disabled.
- **Proposal:** The Core owns the table, `ActivityLogger` (service `activity`, helpers `activity_log()` / `security_log()`), `SecretMasker`, `SafeLogger` and the retention task; each module logs its own events where they happen: Auth (Shield `login`/`logout`, failed logins, throttling, 2FA, re-authentication, profile, sessions, groups), Api (kpapy `kpapy.auth.*` in `modules/Api/Config/Events.php`, tokens, new IP), Core (403s from `can:`, rate limits, grants, modules, demo data, scheduler, outbound, preferences). The Activity module only has the two read-only screens and can be disabled.
- **Alternatives:** A) Every listener in the Core's `Config/Events.php` (the Core would know Auth's and Api's events). B) The logger inside the Activity module (nothing logged while it is disabled).
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 12 as proposed.
- **Applied in:** `modules/Core/Activity/`, `modules/Core/Helpers/activity_helper.php`, `modules/{Auth,Api,Core}/…`, `docs/activity.md`

## D-12-02 — CRUD writes logged by a Core subclass of kpcrudy's `Crud`

- **Where:** spec "Activity log" ("kpcrudy `afterWrite()` hooks, custom operations"); session 09 note ("`afterWrite()` for the activity log")
- **Problem:** kpcrudy keeps a single `afterWrite` callback: a Core hook set by `CrudFactory` would be replaced by a module's own `afterWrite()`, and vice versa. Custom operations and exports have no hook.
- **Proposal:** `Modules\Core\Crud\AuditedCrud extends Kpcrudy\Crud` (made by `CrudFactory::make()`, so no module code changes) logs after kpcrudy's own hooks in `callHook()` (insert, update with the changed fields only, delete, restore; bulk actions and imports included), wraps POST custom operations and custom bulk actions (logged unless the callback returns `false`) and logs CSV exports. `skipActivity(…)` leaves out operations that only change the user's own view or log a richer entry themselves (notifications "read", schedule "run"). Module hooks keep working as documented.
- **Alternatives:** A) A Core `afterWrite()` that modules must not override (easy to break). B) A library request for several `afterWrite` callbacks (blocks this session).
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 12 as proposed.
- **Applied in:** `modules/Core/Crud/{AuditedCrud,CrudFactory}.php`, `modules/Core/Controllers/BackendController.php`, `docs/cruds.md`

## D-12-03 — Entry format, action codes and labels, security events

- **Where:** spec "Activity log" (columns, `security` type, append-only)
- **Problem:** The specification lists columns but not how actions are named and translated, which events are `security`, nor where the summary text comes from (UI texts only in language files).
- **Proposal:** `action` is a code (`insert`, `login_failed`, `token_new_ip`…); its label is `<Module>.activity.<action>`, then `Activity.actions.<action>`, else the code. `summary` is data (a record's title/name/username/email, an email typed at login, a path), never a sentence; `changes` is JSON `{before, after, changes}` masked by key. `type` is `security` for failed logins/codes/re-authentications, throttling, 403s, re-authentication, password/email/2FA/recovery-code changes, grants and user permissions, token creation/revocation/new IP, denied app logins and refused outbound requests; everything else is `activity`. Errors stay in the file log (with `SafeLogger`). Settings changes, refused uploads and refused packages are logged by sessions 14, 13 and 29.
- **Alternatives:** A) Store a translated sentence (frozen in the writer's language). B) Store language keys with parameters (breaks when a module is removed).
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 12 as proposed.
- **Applied in:** `modules/Core/Activity/ActivityLogger.php`, `modules/Activity/Controllers/Activity.php`, language files of Core, Auth, Api and Activity, `docs/activity.md`

## D-12-04 — Retention setting `Core.activityRetentionDays` (decide)

- **Where:** spec "Activity log" ("Retention by setting (e.g. 365 days), purged by a scheduled task")
- **Problem:** The setting has no name; settings names are a public convention.
- **Proposal:** `Core.activityRetentionDays` (int, default 365, 1–3650) in the Core's manifest (the log is the Core's, D-12-01); the Core scheduled task `PurgeActivityLog` runs daily at 03:17 and deletes older entries in chunks of 1000, then writes a `purge` entry. Shown in Settings by session 14.
- **Alternatives:** A) `Activity.retentionDays` in the Activity module (the purge would stop while the module is disabled). B) Separate retention for `security` entries (e.g. 2 years).
- **Status:** Applied
- **Decision (2026-11-06):** «Core.activityRetentionDays (recomanada)». A single retention setting `Core.activityRetentionDays` (int, default 365, 1–3650) owned by the Core; the Core scheduled task `PurgeActivityLog` purges older entries daily, also while the Activity module is disabled. Nothing to change: session 12 built it this way.
- **Applied in:** `modules/Core/Config/Module.php`, `modules/Core/Tasks/PurgeActivityLog.php`, `modules/Core/Language/*/Core.php`, `docs/activity.md`, `docs/specification.md` (session 12; tests in `modules/Core/Tests/ActivityLoggerTest.php`)

## D-12-05 — Personal data and network fields in the log

- **Where:** spec "Activity" (IP and user agent hidden without `activity-log.network`), "My activity"
- **Problem:** A failed login stores what was typed (sometimes a password in the email field); "My activity" would show the IP addresses of the user's own entries.
- **Proposal:** A failed login keeps the typed login name only when it is a valid email address. IP and user agent are hidden with `hideFields()` from every user without `activity-log.network`, on both screens (users see their own entries without them). Each entry stores the IP of the request that caused it (for a failed login: the attacker's).
- **Alternatives:** A) Store the typed text as is. B) Show users their own IP addresses.
- **Status:** Accepted (proposal)
- **Decision (2026-11-06):** applied by session 12 as proposed.
- **Applied in:** `modules/Core/Activity/ActivityLogger.php` (`typedEmail()`), `modules/Activity/Controllers/Activity.php`


## D-13-01 — Private file URL and who may read a private file (decide)

- **Where:** spec "Storage" (private disk "served through a controller that checks permissions"), "Files" (folder ACL and `FileAccessPolicy`, session 22); `docs/plan.md` session 13 (route `files/private/(:segment)`, "temporary permission rule")
- **Problem:** The URL of a private file and the rule used before the folder permissions exist are not specified; URLs are a public convention and the rule is security.
- **Proposal:** `GET files/private/{key}`, where `key` is a random 128-bit identifier (32 hex characters, `stored_files.file_key`, unrelated to the file name); `?download=1` forces a download. Anything not allowed, unknown, on the public disk or missing on disk is a **404** (the existence of a file is not revealed, also to guests). The rule is a class (`Config\Storage::$accessRule`, interface `FileAccessRule`): until session 22 `OwnerAccess` = the user who uploaded the file and superadmins; the Files module replaces it with the folder permissions and its `FileAccessPolicy` registry. A misconfigured rule denies everything.
- **Alternatives:** A) Masked numeric ids (`app.maskKey`): rotating the mask key would break every stored link. B) Login required and 403 for others (reveals that the file exists). C) Administrators read every private file now (before `files.all` exists).
- **Status:** Applied
- **Decision (2026-11-06):** «Clau aleatòria + 404 (recomanada)». Private files are served at `files/private/{key}` with a random 128-bit key; only the uploader and superadmins may read them until session 22 replaces the rule with folder permissions; everybody else, guests included, gets a 404. Nothing to change: session 13 built it this way.
- **Applied in:** `app/Config/Storage.php`, `modules/Core/Storage/{Storage,OwnerAccess,FileAccessRule}.php`, `modules/Core/Controllers/PrivateFiles.php`, `modules/Core/Config/Routes.php`, `docs/storage.md`, `docs/specification.md` ("Storage"), `deploy/22-files-manager.md` (tests `PrivateFilesTest`)

## D-13-02 — Upload settings `Core.uploadTypes`, `Core.uploadMaxSize`, `Core.uploadAllowSvg` (decide)

- **Where:** spec "Storage" ("a setting allows SVG only through a sanitiser", "a setting can turn [re-encoding] off per field"), "Files" ("extension and MIME allow-lists from settings, size limits")
- **Problem:** The settings have no names; settings names are a public convention.
- **Proposal:** Core settings (the Storage service is the Core's and is used without the Files module): `Core.uploadTypes` (string, comma-separated extensions, default `jpg,jpeg,png,gif,webp,pdf,txt,csv,odt,ods,odp,doc,docx,xls,xlsx,ppt,pptx`), `Core.uploadMaxSize` (int, MB, default 10, 1–2048) and `Core.uploadAllowSvg` (bool, default off). A field (`UploadRules`) can only narrow them: an extension must be in both lists and the smaller size wins. Re-encoding is a field option (`UploadRules::$reencode`, default on), not a global setting. Extensions in `Config\Storage::$forbiddenExtensions` (code, HTML, executables, server configuration) are refused whatever the settings say. Shown in Settings › Security by session 14.
- **Alternatives:** A) `Files.*` settings in the Files module (uploads of other modules would have no rules while it is disabled). B) Separate lists per disk or for images.
- **Status:** Applied
- **Decision (2026-11-06):** «Paràmetres del Core (recomanada)». The global upload rules are Core settings `Core.uploadTypes`, `Core.uploadMaxSize` (MB, default 10) and `Core.uploadAllowSvg` (off), which every field can only narrow; re-encoding is a per-field option. Nothing to change in the code: session 13 built it this way.
- **Applied in:** `modules/Core/Config/Module.php`, `modules/Core/Storage/{UploadValidator,UploadRules}.php`, `modules/Core/Language/*/Core.php`, `docs/storage.md`, `docs/specification.md` ("Storage", "Files"), `deploy/14-settings.md` (tests `StorageTest::testTheDefaultRulesComeFromTheSettings`, `UploadRulesTest`)

## D-13-03 — `stored_files` in the Core

- **Where:** spec "Storage" ("the original name is kept in the database"), "Files" (`files` table with file-manager metadata, session 22); `docs/plan.md` session 13 ("`stored_files` minimal unless the Files `files` table is created here")
- **Problem:** The Storage service needs a record per file (original name, MIME type, owner, URL key) before the Files module exists, and must keep working without it.
- **Proposal:** The Core table `stored_files` (`file_key`, `disk`, `path`, `original_name`, `extension`, `mime`, `size`, `checksum` SHA-256, `width`, `height`, `uploaded_by`, `created_at`) written only by `Storage::store()`/`storeContents()` and removed by `Storage::delete()`. Session 22's `files` table adds the file-manager metadata (visibility, alt, tags, trash) with a reference to `stored_files.id` instead of repeating these columns.
- **Alternatives:** A) Create the Files `files` table now in the Core (file-manager columns in the Core). B) No table until session 22 (no original names, no private URLs).
- **Status:** Accepted (proposal)

## D-13-04 — Headers of served private files

- **Where:** spec "Storage" (stored MIME type, `nosniff`, `attachment` for private files that are not images or PDF)
- **Problem:** "Images" includes SVG, which can run scripts when opened; the spec does not say how private files are cached or framed.
- **Proposal:** `Content-Type` = the stored type (the canonical type of the allowed extension, never the client's), `X-Content-Type-Options: nosniff`, `Content-Disposition: inline` only for raster images (JPEG, PNG, GIF, WebP) and PDF, `attachment` for everything else (SVG included) and with `?download=1`; `Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; frame-ancestors 'self'; sandbox` (PDF without `sandbox`: browsers' PDF viewers do not work sandboxed), `X-Frame-Options: SAMEORIGIN` (previews in the file manager), `Cross-Origin-Resource-Policy: same-origin`, `Cache-Control: private, no-transform, no-store, must-revalidate`. The global security headers are added as usual. The public upload folder gets the same `nosniff`/sandbox headers and SVG as attachment from the web server (`public/uploads/.htaccess`, `docs/web-server.md`).
- **Alternatives:** A) SVG inline with the sandbox CSP. B) Private caching with `ETag` (CodeIgniter's `DownloadResponse` cannot answer 304).
- **Status:** Accepted (proposal)

## D-13-05 — Upload checks in detail

- **Where:** spec "Storage" (allow-list and MIME check, SVG through a sanitiser, image re-encoding, no execution), "Security baseline" (uploads)
- **Problem:** The spec lists the rules but not how they are checked, nor which sanitiser to use (adding a dependency needs the owner's approval; only Flysystem is pre-approved here).
- **Proposal:** The MIME type is detected from the contents with `finfo` and must belong to the extension (`Config\Mimes` + `Config\Storage::$extraMimes`); HTML/XML/SVG/PHP contents under another extension and `<?php` in the first 64 KB are refused; images must decode with `getimagesize`, match their extension and stay under 40 megapixels, then are re-encoded with GD (EXIF orientation applied first; animated GIFs keep their first frame); SVG is cleaned by the Core's own allow-list `SvgSanitizer` (DOM, no DOCTYPE/entities, no scripts, handlers, styles, `foreignObject`, links or external references). Paths are normalised by `PathNormalizer` (no `..`, `.`, absolute, backslashes, control characters or hidden segments) before Flysystem's own check; stored names are random (`<32 hex>.<ext>`). Public disk: folders `0755`, files `0644` (the web server reads them); private disk: `0750`/`0640`. Refused uploads are logged as `security` entries `Core`/`upload_refused` with the reason, disk and folder.
- **Alternatives:** A) `enshrined/svg-sanitize` (a new dependency). B) Trust the browser's MIME type (forgeable).
- **Status:** Accepted (proposal)

## D-14-01 — Which settings belong to Settings › Security, and the `list` type (decide)

- **Where:** specification "Configuration and settings" (Settings › Security lists the settings it shows; definitions have a `group`), "Module manifest" (`$settings`)
- **Problem:** The Settings UI must know which declared settings need `settings.security` + re-authentication instead of `settings.<module>`, without the Settings module hard-coding other modules; and several security settings are lists (outbound hosts, CORS origins, CSP origins) that a JSON textarea makes error-prone. Both are manifest conventions.
- **Proposal:** A setting whose `group` is `security` or `security.<sub>` is shown only in Settings › Security (one card per module, a heading per `<sub>`); every other setting is on its module's tab. A new setting type `list` (list of strings, edited one per line, cast by `SettingCaster`). `Core.outboundHosts` and `Api.corsOrigins` change from `json` to `list` (same stored array). Validation rules that refuse weakening values are ordinary rule names (`csp_sources`, `outbound_hosts`, `min_config[…]`…) registered by a module's registrar in `Config\Validation::$ruleSets`.
- **Alternatives:** A) A new boolean manifest key `'security' => true` (group keeps only meaning a heading). B) A fixed list of setting names in the Settings module (hard-codes other modules). C) No `list` type: JSON textareas.
- **Status:** Applied
- **Decision (2026-11-06):** «Grup «security» (recomanada)». A setting whose `group` is `security` or `security.<sub>` is shown only in Settings › Security; other settings stay on their module's tab. The new setting type `list` (one entry per line) is kept, and `Core.outboundHosts` and `Api.corsOrigins` use it. Nothing to change in the code: session 14 built it this way.
- **Applied in:** `docs/specification.md` ("Module manifest", "Configuration and settings"), `CLAUDE.md` ("Settings"), `docs/settings.md`; code of session 14 (`modules/Core/Settings/SettingCaster.php`, `modules/Core/Modules/ManifestValidator.php`, `modules/Settings/Services/SettingsEditor.php`; tests `SettingsFormTest`, `SettingsScreensTest`)

## D-14-02 — Names of the new site-wide and CSP settings (decide)

- **Where:** specification "Configuration and settings" (site name, time zone; "extra CSP origins per directive")
- **Problem:** The specification does not name them; settings names are a public convention.
- **Proposal:** `Core.siteName` (string, empty = the language line `Core.app.name`, read with `site_name()`), `Core.timezone` (string, default `UTC`, the display time zone of `ui_date()`; dates stay stored in UTC), and one `list` setting per directive: `Core.cspScriptSrc`, `Core.cspStyleSrc` (no wildcards), `Core.cspImgSrc`, `Core.cspFontSrc`, `Core.cspConnectSrc`, `Core.cspFrameSrc`, `Core.cspMediaSrc`, merged by the `cspmodules` filter.
- **Alternatives:** A) One JSON setting `Core.cspOrigins` (directive => list; needs a custom form). B) `Core.appName` / `Core.siteTimezone`. C) Change `Config\App::$appTimezone` instead (stored dates would shift).
- **Status:** Applied
- **Decision (2026-11-06):** «Noms proposats (recomanada)». The settings are named `Core.siteName` (empty = the language line `Core.app.name`), `Core.timezone` (the display time zone; dates stay stored in UTC) and one `list` setting per CSP directive: `Core.cspScriptSrc`, `Core.cspStyleSrc` (no wildcards), `Core.cspImgSrc`, `Core.cspFontSrc`, `Core.cspConnectSrc`, `Core.cspFrameSrc`, `Core.cspMediaSrc`. Nothing to change in the code: session 14 built it this way.
- **Applied in:** `docs/specification.md` ("Configuration and settings", "Security baseline" › "Configuring headers and CSP"), `docs/settings.md`; code of session 14 (`modules/Core/Config/Module.php`, `modules/Core/Filters/ContentSecurity.php`, `modules/Core/Helpers/ui_helper.php`; tests `SettingsScreensTest::testACspOriginAddedInTheUiAppearsInTheHeader`, `testAModuleTabIsSavedCastAndLogged`)

## D-14-03 — Settings permissions, routes and actions

- **Where:** specification "Configuration and settings" (`settings.update` opens the UI, `settings.<module>` changes a tab, `settings.security` + re-authentication), "Modules" › "Settings" (actions), D-00-33
- **Problem:** The specification does not say what a user with `settings.update` but without `settings.<module>` sees, which permission the theme tab and the actions need, nor how `settings.update`/`settings.security` are generated.
- **Proposal:** The Settings module has one resource `name => 'settings'` of type `crud` with every CRUD operation excluded and the extras `update` and `security` (exactly `settings.update`, `settings.security`). `settings.update` opens every tab (read-only without the tab's permission: fields disabled, POST refused by the `can:` filter's 403 and `forbidden` entry); the theme tab saves with `settings.core`. URLs `backend/settings` (first tab), `backend/settings/<module>` (lower case; 404 for a module without non-security settings), `backend/settings/theme`, `backend/settings/security` (`can:settings.security` + `reauth`, GET and POST), `backend/settings/actions`. Test email and clear caches: `settings.update`; publish assets: `settings.security` + `reauth` (it writes JavaScript into `public/`). Default grant: `admin` → `settings.*`.
- **Alternatives:** A) Hide tabs without their permission. B) `settings.update` also for publishing assets.
- **Status:** Accepted (proposal)

## D-14-04 — What "weakening the baseline" means

- **Where:** specification "Configuration and settings" ("values that would weaken the baseline (e.g. `unsafe-inline`) are refused"), "Security baseline" (CSP origins "validated as origins (no wildcards for scripts)")
- **Problem:** Only `unsafe-inline` is named.
- **Proposal:** Bounds in each setting's `rules` (e.g. password ≥ 8, remember-me ≤ 365 days, rate limits ≤ 10000) plus: CSP origins only `https://host[:port]`, `https://*.host` or `wss://host` (no keywords, `*`, schemes alone, `http:`, `data:` or paths; scripts/styles without wildcards); outbound hosts only host names or `*.domain` (no IPs, local names, schemes or ports); CORS origins `https://host[:port]` (`http://` only for localhost); upload types never a forbidden extension; the minimum password length never below `Config\Auth` (`min_config`); 2FA policy only for existing groups. Refusals are `security_settings_refused` security entries; the CSP filter re-checks stored origins. The UI stores only overrides (a value equal to the file configuration or manifest default is forgotten).
- **Alternatives:** A) Refuse any value weaker than the file default (would forbid raising a rate limit).
- **Status:** Accepted (proposal)

## D-14-05 — Test email and clear caches

- **Where:** specification "Modules" › "Settings" (actions), D-00-15 (email never inline except security emails)
- **Problem:** A test email sent inline would block the request and contradict D-00-15; "clear caches" could also wipe the rate limiter's counters.
- **Proposal:** The test email goes to the logged-in user through `mail_queue` (sent by `SendQueuedEmails` within a minute; the screen shows the queue length). "Clear caches" invalidates the Core's cache scopes and the theme registry only. Both are logged.
- **Alternatives:** A) Send the test email inline with a short timeout. B) `cache()->clean()`.
- **Status:** Accepted (proposal)

## D-14-06 — Site-wide settings built now

- **Where:** specification "Configuration and settings" (site name, logo, default/available locales, time zone, maintenance mode, idle lock, captcha, 2FA policy, mail transport, themes)
- **Problem:** Several of them belong to later sessions or already exist elsewhere.
- **Proposal:** Built now: site name, time zone (display), themes (theme tab), and in Settings › Security the existing idle lock, captcha and 2FA policy. Later: default/available locales (session 31), maintenance mode (session 28, used by `app:update`); the mail transport stays in `.env` (it holds secrets; "send a test email" checks it); the logo is the theme option `logo`. The user's own time zone (profile) is applied by session 31.
- **Alternatives:** A) A maintenance-mode setting now without the filter.
- **Status:** Accepted (proposal)

## D-15-01 — Users permission names, URLs and setting (decide)

- **Where:** specification "Modules" › "Users" (users CRUD, row operations, groups CRUD, matrix, impersonation time limit "setting, e.g. 30 minutes"), "RBAC" (names `<module>-<resource>.<operation>`; D-00-18 allows a `'name'` override only for Core resources and single-resource modules)
- **Problem:** The specification names no permission, URL or setting for the Users module, and all three are public conventions. The module has three resources, so the convention gives `users-users.*`, which reads oddly.
- **Proposal:** Permissions by the convention: `users-users.{list,show,add,edit,ban,forcereset,reset2fa,logout,impersonate,access}`, `users-groups.{list,show,add,edit,delete}`, `users-matrix.{view,edit}` (operation keys lower case without separators, as kpcrudy custom operations require). URLs `backend/users`, `backend/users/<op>/<masked id>`, `backend/users/access/<id>`, `backend/users/groups`, `backend/users/permissions`, and `POST auth/impersonation/stop`. Setting `Users.impersonationMinutes` (int, 30, 1–120, group `security.impersonation`). Built this way; renaming later means changing the manifest, the routes and the tests (no data migration while unreleased).
- **Alternatives:** A) `'name'` overrides for a core module with several resources: `users.*`, `groups.*`, `permissions.*` (shorter; needs an exception to D-00-18 for core modules). B) Resources `accounts`/`groups`/`matrix` → `users-accounts.*` (avoids the repetition).
- **Status:** Applied
- **Decision (2026-11-09):** «Convenció actual (recomanada)». The Users module keeps the generated names `users-users.{list,show,add,edit,ban,forcereset,reset2fa,logout,impersonate,access}`, `users-groups.{list,show,add,edit,delete}` and `users-matrix.{view,edit}` (no exception to D-00-18), the URLs `backend/users`, `backend/users/<op>/<masked id>`, `backend/users/access/<id>`, `backend/users/groups`, `backend/users/permissions` and `POST auth/impersonation/stop`, and the setting `Users.impersonationMinutes`. Nothing to change in the code: session 15 built it this way.
- **Applied in:** `docs/specification.md` ("Modules" › "Users"), `docs/users.md`; code of session 15 (`modules/Users/Config/Module.php`, `modules/Users/Config/Routes.php`, `modules/Auth/Config/Routes.php`)

## D-15-02 — Groups as data: names, deletion, new groups and the 2FA policy

- **Where:** specification "Authentication" › "Groups" (Core `groups` table mirrored into `setting('AuthGroups.groups')`), "Modules" › "Users" (Groups CRUD, 2FA policy per group, "a new group receives the personal-screen grants every group has"); D-00-38, D-06-05
- **Problem:** The specification does not say whether names change, what deleting a group does to its members, how the "grants every group has" are found, nor where the 2FA policy setting of session 06 goes.
- **Proposal:** Core migration `groups` (name unique, title, description, reserved, `two_factor`); `GroupRegistry` creates the four reserved groups on `modules:sync` (titles from `Config\AuthGroups`) and mirrors every change into `AuthGroups.groups` with the policy as each group's `twoFactor` key, which `AuthPolicy::twoFactorPolicy()` reads. Names are set once (`[a-z][a-z0-9_-]{1,49}`, never reserved); reserved groups and groups with members cannot be deleted; deleting a group deletes its grants. A new group gets the grants all four reserved groups share (the personal screens). The `Auth.twoFactorPolicy` setting, its rule and its Settings › Security field are removed (stored values are not migrated: nothing is released).
- **Alternatives:** A) Renaming custom groups (rewrites memberships and grants). B) Deleting a group with members removes their membership. C) Keep `Auth.twoFactorPolicy` written by the Groups screen.
- **Status:** Accepted (proposal)

## D-15-03 — Adding and editing users; no deletion

- **Where:** specification "Modules" › "Users" ("Shield identities handled through hooks… password changes go through Shield")
- **Problem:** The email and password are not columns of `users`; letting administrators set passwords hands them other people's credentials; the specification lists no deletion.
- **Proposal:** kpcrudy over `users` (`deleted_at` rows outside the CRUD) with virtual inputs for the email and an initial password, validated in `beforeInsert` (Shield's local password rules, unique email) and saved through Shield in `afterInsert` (identity, default group, forced password change at the first login). Edit: user name, email (through Shield, logged as a security entry) and "active" (not one's own). Administrators never set another user's password (they force a reset). No deletion: accounts are banned.
- **Alternatives:** A) Delete through Shield's soft delete (orphaned content in later modules). B) An admin-set password on edit.
- **Status:** Accepted (proposal)

## D-15-04 — Who manages whose account

- **Where:** specification "RBAC" › "Escalation rules", D-07-04
- **Problem:** The rules cover grants and groups, but editing an account (email), forcing a reset or resetting the 2FA of a stronger account lets an administrator take it over.
- **Proposal:** `EscalationGuard::canManageUser()`: superadmins manage every account; anybody else only accounts that are not superadmins and hold nothing they do not hold (groups' grants and individual permissions). Every row operation but edit is refused on one's own account; the groups and permissions screen too. The buttons are hidden and the server refuses the same.
- **Alternatives:** A) Only the "superadmin" rule (an admin could take over an account with permissions they lack).
- **Status:** Accepted (proposal)

## D-15-05 — Impersonation mechanics and the permission matrix save

- **Where:** specification "Modules" › "Users" (impersonation), "Authentication" (session id regeneration on impersonation), "RBAC" › "Permission matrix screen"
- **Problem:** Where the impersonation lives (Users requires Auth, and Auth's filters must refuse sensitive actions), what happens to the session, the expiry and the pending request, and how the matrix treats columns, unknown names and grants it does not show.
- **Proposal:** Impersonation is an Auth service (session state only, no table): superadmins only, never a superadmin, a banned/inactive user, oneself or twice; started by the Users operation after a re-authentication; the PHP session keeps the impersonator's tracked row; ids regenerated and the re-authentication window forgotten at start and end; the `authsession` filter adds the banner to the top-bar region and ends an expired impersonation without running the pending request; re-authentication (hence every `reauth` route), the password and the email change are refused meanwhile; logout ends both; every step is a security entry. Matrix: columns of the actor's own groups and `superadmin` are read-only; each editable column posts an empty marker and only posted columns change; only atomic names and resource wildcards are accepted (anything else refuses the whole save); grants the screen does not show are kept; every group is checked before any write and all are saved in one transaction.
- **Alternatives:** A) An `impersonations` table (not needed: the activity log keeps the history). B) Replaying the pending request as the impersonator after expiry.
- **Status:** Accepted (proposal)

## D-16-01 — Dashboard permission names, URLs and the login landing page (decide)

- **Where:** specification "Modules" › "Dashboard" ("the dashboard home is the resource `dashboard`, granted to every group"; per-group configuration "from the UI"), "Dashboard widgets"; D-00-17, D-00-18; plan session 16 (routes `backend/`, `backend/dashboard/widgets/(:segment)/data`, `backend/dashboard/groups`); D-05-08 (login on the profile "until session 16")
- **Problem:** The specification names the resource but not its operations, the permission of the group configuration, the URLs of the user layout, nor where a login lands now; all are public conventions. The Dashboard module is not a core module, so it can be disabled.
- **Proposal:** One resource `dashboard` with the `'name'` override (single-resource module): `dashboard.view` (home, own layout, async data; granted to every group) and `dashboard.configure` (group layouts; `admin` gets `dashboard.*`). URLs: `backend` (route `dashboard`), `backend/dashboard/customize`, `POST backend/dashboard/layout`, `POST backend/dashboard/layout/reset`, `backend/dashboard/widgets/<id>/data`, `backend/dashboard/groups[/<group>]` (route `dashboard-groups`; menu "Dashboard widgets" in the settings zone). Widget ids `<module>.<name>` (`core.scheduled-tasks`, `core.quick-links`, `users.summary`). A login, the start and the end of an impersonation land on `backend`; `backend` always exists: the Dashboard is a core module (D-16-05). Built this way; renaming means changing the manifest, routes, tests and `Config\Auth::$redirects`.
- **Alternatives:** A) A separate resource for the configuration: `dashboard-groups.{view,edit}` (needs two resources, so no `'name'` override: `dashboard-dashboard.view`). B) Keep the login on `backend/profile`.
- **Status:** Applied
- **Decision (2026-11-09):** «Com està (recomanada)». The Dashboard keeps `dashboard.view` (every group) and `dashboard.configure` (admin), the URLs `backend` (route `dashboard`), `backend/dashboard/customize`, `POST backend/dashboard/layout`, `POST backend/dashboard/layout/reset`, `backend/dashboard/widgets/<id>/data` and `backend/dashboard/groups[/<group>]` (route `dashboard-groups`), widget ids `<module>.<name>`, and a login or an impersonation lands on `backend`. Nothing to change in the code: session 16 built it this way.
- **Applied in:** `docs/specification.md` ("Modules" › "Dashboard", "Dashboard widgets"), `docs/widgets.md`; code of session 16 (`modules/Dashboard/Config/Module.php`, `modules/Dashboard/Config/Routes.php`, `app/Config/Auth.php`)

## D-16-02 — Group layouts: defaults, several groups, what users may change

- **Where:** specification "Modules" › "Dashboard" (per group: available widgets, default order and size, options; per user: reorder, resize, hide, restore)
- **Problem:** The specification does not say what a group without configuration shows, which group wins when a user is in several, nor what users may change.
- **Proposal:** Table `dashboard_group_widgets` (`group_name`, `widget`, `enabled`, `position`, `size`, `options` JSON); a widget without a row is **enabled** with its defaults (manifest order, first size, default options), so new modules' widgets appear for groups holding their permission and the shipped dashboard is useful without configuration; the permission check is never skipped. A widget is available when one of the user's groups enables it; position, size and options come from the first such group in `GroupRegistry` order (reserved first). Users change only order, size (within `sizes()`) and visibility, stored as one JSON row per user (`dashboard_user_layouts`); options stay per group. Saving a group layout is logged (`group_layout_saved`); a user's layout is a personal preference and is not.
- **Alternatives:** A) Disabled by default (an empty dashboard until an administrator configures each group). B) Options per user.
- **Status:** Accepted (proposal)

## D-16-03 — The widget contract, async data and failures

- **Where:** specification "Dashboard widgets" (`id()`, `title()`, `permission()`, `sizes()`, `settings()`, `render()` or `data()`; "slow widgets load asynchronously and are cached per user for a few minutes"; isolation), "Security baseline" (rate limit of async widget URLs)
- **Problem:** The specification lists the methods but not how async widgets are marked, what the data URL returns, how options are typed, nor where the framework lives.
- **Proposal:** Framework in the Core (`Modules\Core\Widgets`: `WidgetInterface` + `icon()`, `AsyncWidgetInterface` with `data()`, `renderData()`, `component()`, `cacheSeconds()`, `refreshSeconds()`; base classes `Widget`/`AsyncWidget`; `WidgetRegistry`, `WidgetRenderer`, `WidgetOptions`, `WidgetSizes`); the Dashboard module stores and shows the layouts. Sizes `small|medium|large|full`; permission null = only `dashboard.view`. Option types `int`, `bool`, `string`, `select`, `menu`. The data URL answers `{widget, data, html}` (`no-store`), 403 for unknown, server-side or unavailable widgets (security log `widget_forbidden`), 500 with a generic message on failure; the cache (scope `widgets`) is keyed by user, language and options and read only after the checks. Failures are written to the application log, never shown. `ManifestValidator` refuses `$widgets` classes that do not implement `WidgetInterface`; the `make:module` widget stub implements it.
- **Alternatives:** A) A `async(): bool` flag on one interface. B) Rendering async widgets' HTML only in the browser.
- **Status:** Accepted (proposal)

## D-16-04 — The back-office home in the menu and breadcrumbs

- **Where:** D-08-01 (active entry by route, then exact path, then longest path prefix)
- **Problem:** The home's path `backend` is a prefix of every back-office path, so the "Dashboard" entry was marked active on every screen.
- **Proposal:** Path prefixes count only for entries whose path has at least two segments; a one-segment entry (the home) is active only on itself or by route name.
- **Alternatives:** A) A per-entry `exact` key in the manifest menu.
- **Status:** Accepted (proposal)

## D-16-05 — Dashboard is a core module

- **Where:** specification "Module lifecycle" (core modules `Core`, `Auth`, `Api`, `Users`, `Settings` cannot be disabled), "Modules" › "Dashboard"; D-16-01
- **Problem:** The Dashboard module could be disabled, and then `backend`, where a login and an impersonation land, answered 404.
- **Proposal:** Add `Dashboard` to `ModuleToggler::CORE_MODULES`: it cannot be disabled, so `backend` always exists. Disabling it would give nothing a user cannot already get by hiding every widget (an empty home).
- **Alternatives:** A) Keep it optional and send the login to the profile when it is disabled.
- **Status:** Applied (decided)
- **Decision (2026-11-09):** «Fes que el dashboard no es pugui desactivar, ja que en ell pots eliminar tots els widgets i que et quedi com una pantalla buida, que seria el mateix efecte que desactivar-lo». The Dashboard is a core module that cannot be disabled.
- **Applied in:** `modules/Core/Modules/ModuleToggler.php`, `modules/Dashboard/Config/Module.php`, `modules/Dashboard/CLAUDE.md`, `docs/specification.md` ("Module lifecycle"), `docs/commands.md`, `docs/widgets.md`; tests `ModuleToggleTest::testCoreModulesCannotBeDisabled`, `DashboardModuleTest::testItIsACoreModule`

## D-16-06 — Quick links: free URLs and the order chosen by the administrator (decide)

- **Where:** specification "Modules" › "Dashboard" (*Quick links*: "configurable shortcuts per group"); `modules/Core/Widgets/QuickLinks.php`, `WidgetOptions`
- **Problem:** The quick links could only be entries of the back-office menu, shown in alphabetical order; the owner wants links to external sites and the order set by the administrator.
- **Proposal:** A new option type `links`: one ordered list (at most `Core.widgetMaxLinks` items: a Core setting, default 20, 1–100, added at the owner's request) whose items are either a back-office menu entry (filtered by each user's permissions, as before) or a free link (one label, up to 100 characters, and an address). Addresses: `https://` URLs (no credentials) or internal paths starting with `/`; `http:`, `javascript:`, `data:`, `mailto:` and the rest are refused. Free links are shown to every user of the group (an internal path still answers 403 when the user lacks its permission). External links open in a new tab with `rel="noopener noreferrer"` and an "external" icon. The editor in Dashboard › Group layouts is a single ordered list with up/down/remove/add buttons and drag and drop (a position field without JavaScript). Stored lists of menu keys keep working.
- **Alternatives:** A) Also `http://`. B) Also `mailto:`/`tel:`. C) An optional permission per free link. D) One label per language. E) Two separate lists (menu entries, free links).
- **Status:** Applied
- **Decision (2026-11-09):** «https + camins interns», «Tots els del grup», «Un sol text», «Llista única ordenable» (the four recommended options). Free links accept `https://` URLs and internal paths only, are shown to every user of the group, have one untranslated label, and are edited with menu entries in one list whose order the administrator sets.
- **Applied in:** `modules/Core/Config/Module.php` (setting `Core.widgetMaxLinks`, group `dashboard`), `modules/Core/Widgets/WidgetOptions.php` (type `links`, `linkUrl()`, `maxLinks()`), `modules/Core/Widgets/QuickLinks.php`, `modules/Core/Views/widgets/quick_links.php`, `modules/Dashboard/Views/links_editor.php`, `modules/Dashboard/Assets/js/links-editor.js`, `modules/Dashboard/Views/groups.php`, language files (Core, Dashboard), `docs/specification.md`, `docs/widgets.md`; tests `GroupLayoutTest::testFreeLinksAndTheOrderChosenByTheAdministrator`, `GroupLayoutTest::testInvalidValuesAreRefused`, `WidgetsTest::testFreeLinkAddresses`, `WidgetsTest::testStoredMenuKeysStillWorkAsLinks`, `WidgetsTest::testTheMostLinksComeFromTheSetting`, `GroupLayoutTest::testTheLimitOfLinksIsASetting`; `SettingsScreensTest` posts the new field

## D-17-01 — News permission names, URLs, settings and the reader's API permissions (decide)

- **Where:** specification "Modules" › "News" (resources `posts` with extras `publish`, `author`, `readers`, and `categories`; "my posts"; public routes; "two settings"), D-00-42 (reader screen `news-read`, `type: page`, `news-read.view` for every group), plan session 17 (routes), "JSON API" (`setResource()` = the CRUD's resource)
- **Problem:** The specification does not name the "my posts" resource, the reader's URLs, the two settings nor the permissions of the `news` API resource. The API lists and shows posts (kpapy operations `list` and `show`), but the reader resource is a page with only `view`: kpapy checks token scopes against `<resource>.<operation>`, so a token limited to `news-read.view` could not list anything, and `news-posts.list` (authors only) would hide the API from ordinary users.
- **Proposal (built):**
  - Resources: `news-posts` (crud − `import`, extras `publish`, `author`, `readers`), `news-categories` (crud − `import`/trash), `news-mine` ("My posts", crud − `import`/`export`/trash, extra `publish`), `news-read` (page: `view`, plus **`list` and `show` for the API**). Grants: admin everything; editor `news-posts.{list,show,add,edit,publish}`, `news-categories.{list,show}`, `news-mine.*`, `news-read.*`; user and superadmin `news-read.*`.
  - Back office: `backend/news` (reader, route `news-read`), `backend/news/read/{masked id}`, `POST backend/news/read/{masked id}/mark`, `backend/news/posts` (+ operations `publish`, `unpublish`, `readers`), `backend/news/mine`, `backend/news/categories`. Public: `news`, `news/{slug}`, `news/category/{slug}`, `news/feed.xml`. API: `api/v1/news` (resource name `news-read`).
  - Settings: `News.perPage` (public list, 10) and `News.feedItems` (RSS, 20), group "Public site".
- **Alternatives:** A) The API uses `news-read.view` for both operations through a resource-level resolver (no new names; a token needs the scope `news-read.*` or `*`, a token scoped `news-read.view` gets 403). B) The API on `news-posts` (authors only; ordinary users get nothing). C) A separate API resource `news-api.{list,show}`. D) "My posts" without its own resource (a filter of `news-posts`; editors without `news-posts.list` lose it).
- **Status:** Applied
- **Decision (2026-11-09):** «Recurs API separat». The News API gets its own resource `news-api` with the operations `list` and `show` (granted to every group, like the reader screen), instead of extra `list`/`show` operations on `news-read`; `news-read` keeps only `view`. The other names, URLs and settings stay as built (`news-posts`, `news-mine`, `news-categories`, `news-read.view`; `backend/news…`, `news…`, `api/v1/news`; `News.perPage`, `News.feedItems`). **Must change:** `modules/News/Config/Module.php` (resources, grants), `modules/News/Controllers/Api/Posts.php` (`setResource('news-api')`), tests (`NewsApiTest` scopes, `NewsModuleTest`), counting tests of earlier sessions, `docs/news.md`, `modules/News/CLAUDE.md`, specification "News"
- **Applied in:** `modules/News/Config/Module.php` (resource `api`, grants), `modules/News/Controllers/Api/Posts.php` (`setResource('news-api')`), `modules/News/Language/{ca,es,en}/News.php`, `docs/specification.md` ("News", manifest example), `docs/news.md`, `modules/News/CLAUDE.md`; tests `NewsApiTest::testScopesNarrowTheToken` (`news-read.view` alone is refused), `NewsModuleTest::testManifestIsValid`, `ShieldMatrixTest`. Existing development databases: `php spark modules:sync --prune` removes `news-read.list`/`news-read.show`; the new grants apply on a fresh install (grants are applied once per module).

## D-17-02 — Sanitising a translated body before kpcrudy reads the form

- **Where:** specification "News" ("HTML sanitised with HTMLPurifier in a `before*` hook"), "Translations" (`setTranslations()`), D-00-35
- **Problem:** The body is a translated field (`news_translations`); kpcrudy's `before*` hooks receive only the main table's data, and `postedTranslations()` is read-only, so a hook cannot change the stored body.
- **Proposal:** The controller cleans `kp_tr[<locale>][body]` with `BodySanitizer` (HTMLPurifier: paragraphs, headings, lists, emphasis, links `http`/`https`/`mailto` with `rel="noopener noreferrer"`, images, quotes, code, tables; no scripts, handlers, styles, iframes or forms) **before** kpcrudy reads the form (`IncomingRequest::setGlobal('post', …)`); the reader screen, the public page and the API (`transform()`) clean it again on output; the CSV import stays disabled on `news-posts` and `news-mine` (a test enforces it). Library request: let `before*` hooks change translated values.
- **Alternatives:** A) Body not translated (one body in the main table). B) Clean only on output.
- **Status:** Accepted (proposal)

## D-17-03 — Post statuses, publishing, scheduling and expiry

- **Where:** specification "News" (status draft / published / scheduled, `published_at`, `expires_at`, "a task that expires notices", "publishing a private notice sends a notification")
- **Problem:** The specification does not say how a post becomes scheduled, what expiry changes, nor whether the form can publish.
- **Proposal:** Statuses `draft`, `scheduled`, `published` and `expired`. The form never sets the status (new posts are drafts); the operation `publish` (and its bulk action, permission `publish`) makes a post `published`, or `scheduled` when `published_at` is in the future; `unpublish` (same permission) turns it back into a draft. Readers only see posts whose status is `published`/`scheduled`, whose date has passed and whose `expires_at` has not, so nothing waits for the task. The task `ExpireNotices` (every 5 minutes) turns due scheduled posts into `published` and notices past `expires_at` into `expired` (unpinned), and writes one activity entry when it changes something. A `private`/`groups` notice notifies its audience once (`notified_at`), when published or released.
- **Alternatives:** A) Status editable in the form, guarded by a record rule. B) Expired notices deleted.
- **Status:** Accepted (proposal)

## D-17-04 — Who reads what: visibility rules and the notice audience

- **Where:** specification "News" (visibility public/private/groups; notices in the widget "of the users who can see them"; "private news only in the back office and the dashboard"), D-00-36, D-00-42
- **Problem:** The specification does not say whether superadmins read every group's posts, what the public site shows to a signed-in user, nor who is notified.
- **Proposal:** One rule (`NewsVisibility`) for the reader screen, the widgets, the public site, the feed and the API: guests and the public site see only `public`; signed-in users see `public`, `private` and `groups` posts linked to one of their groups (superadmins too: authors see everything in `news-posts`). The public site and the feed show only public posts, whoever is signed in; anything else is a 404. Audience: `private` → every user with `news-read.view` (`notifyPermission()`), `groups` → the members of those groups (`notifyGroup()`).
- **Alternatives:** A) Superadmins read every post on the reader screen. B) Signed-in users see private posts on the public site.
- **Status:** Accepted (proposal)

## D-17-05 — The menu link type before the Menus module

- **Where:** specification "Menus" (public menu items: "the link types offered by modules (`$menuLinks`: a news category…)"), "News" ("the menu link type news category")
- **Problem:** The Menus module and its link-type contract arrive in session 21; there is no Core interface yet.
- **Proposal:** `Modules\News\Menu\CategoryLink` with `key()` (`news.category`), `label()`, `options()` (slug → name of the active categories) and `url($value)` (null when the category is gone), declared in `$menuLinks`; session 21 turns these methods into a Core interface (or adapts the class).
- **Alternatives:** A) Add a Core interface now (a Core change for one module).
- **Status:** Accepted (proposal)

## D-17-06 — The cover through a kpcrudy upload handler on the Storage service

- **Where:** session 17 scope ("cover through the storage service… a simple upload field and leave a TODO"), `docs/cruds.md` ("never use kpcrudy's `image`/`upload` field types"), `docs/library-requests.md` (kpcrudy 1.1 `UploadHandler`)
- **Problem:** The file picker arrives in session 23; kpcrudy upload fields used to write files outside the Storage service, but kpcrudy 1.1 delegates storage to an upload handler.
- **Proposal:** The cover is kpcrudy's `image` field with a module handler (`Modules\News\Storage\CoverUploads`): every file goes through `service('storage')->store()` (public disk, folder `modules/news`, JPG/PNG/WebP, at most 5 MB, re-encoded), the column keeps the file key, refusals become field errors; a TODO points at session 23 (file picker). `docs/cruds.md` now names this exception.
- **Alternatives:** A) No cover until session 23. B) A custom upload screen.
- **Status:** Accepted (proposal)

## D-17-07 — The API's visibility as a `where()` condition

- **Where:** "JSON API (kpapy)", `CLAUDE.md` ("No raw SQL with interpolated values"; Query Builder only)
- **Problem:** kpapy's `where()` takes a column and a value (equality or an operator in the key); the visibility rule is an `OR` of conditions with a group subquery, which cannot be written that way, and the Query Builder prefixes table aliases inconsistently in subqueries.
- **Proposal:** `NewsVisibility::condition()` returns `[<news>.id, RawSql('(SELECT v.id FROM news v WHERE v.id = <news>.id AND <readable>)')]`: a correlated subquery compiled by the Query Builder (every value escaped by it; only table names, with the connection prefix, are written by hand; the alias carries the prefix so it is never prefixed twice). List, details, search and filters are all limited; an unreadable post is a 404. Library request: a `where()` that takes a closure over the builder — done in kpapy `main` (`where(Closure)`, for 1.2.0); once released, the resource uses `where(fn ($builder) => NewsVisibility::apply($builder, $user))` and the `RawSql` goes away.
- **Alternatives:** A) A per-record `setPermission('show')` rule (403 instead of 404; lists still need a query). B) A database view.
- **Status:** Applied
- **Applied in:** kpapy 1.2.0 (`where(Closure)`, requested and added after the session 17 review; `composer.json` requires `^1.2`): `modules/News/Controllers/Api/Posts.php` uses `where(fn (BaseBuilder $builder) => NewsVisibility::apply($builder, $user))`; the `RawSql` subquery (`NewsVisibility::condition()`) was removed. Tests: `NewsApiTest`.

## D-17-08 — Images inside the rich-text body (decide)

- **Where:** specification "News" (body sanitised with HTMLPurifier), "Files" (file picker), "Front-end assets" (vendored rich-text editor); `deploy/20-pages.md` (editor, News switches to it), `deploy/23-files-picker.md` (picker, News cover); `modules/News/Services/BodySanitizer.php` (allows `img[src|alt|width|height]` with any `http`/`https` source)
- **Problem:** Session 20 brings a WYSIWYG editor and session 23 the file picker, but no session says that authors can **insert images inside the text** (only the cover is planned). Authors who do not know HTML cannot add an image to a post today, and the sanitiser accepts images from any `https` site (external images leak the readers' IP addresses to that site and may disappear).
- **Proposal:** In session 23 (file picker), add an "Insert image" button to the editor of session 20 that opens the file picker (upload or choose; images only: JPG, PNG, WebP, GIF; the Storage service's rules, re-encoding and limits; public disk, folder `modules/<module>` of the content, e.g. `modules/news`), and inserts `<img src="/uploads/…" alt="…">` with an alt text field. HTMLPurifier then keeps only images of the site itself (`URI.Host`/a custom URI filter: relative `/uploads/…` URLs or the site's own host); external images are removed. Pages (session 20) use the same component. Paste or drop of images in the editor goes through the same upload.
- **Alternatives:** A) Allow external `https` images as today (no upload; authors paste URLs). B) Images only as attachments/gallery under the text, never inside it. C) Do it already in session 20 with a simple upload dialog, before the file picker exists.
- **Status:** Applied
- **Decision (2026-11-09):** «Botó a la sessió 23 (recomanada)». As proposed: session 23 adds an "Insert image" button to the editor of session 20 that opens the file picker (upload or choose; JPG, PNG, WebP, GIF; the Storage service's rules and re-encoding; an alt text), for News and Pages, pasted or dropped images go through the same upload, and HTMLPurifier then keeps only images of the site itself. **Must change:** `deploy/23-files-picker.md` (scope and tests), specification "Files" (file picker) and "News", `modules/News/Services/BodySanitizer.php` in session 23
- **Applied in:** `deploy/23-files-picker.md` (scope and tests), `docs/specification.md` ("Files" › file picker, "News"), `modules/News/CLAUDE.md` (open tasks); the code changes are session 23's.

## D-18-01 — HTTPS and the web installer

- **Where:** spec "Installer and CLI" ("It warns when the page is not served over HTTPS"), "Security baseline" (`forceGlobalSecureRequests` in production)
- **Problem:** Without `.env` the environment is production, so every HTTP request (the installer included) is redirected to HTTPS; the warning could never show there, and a server without TLS cannot reach the web installer.
- **Proposal:** Keep the production default: the web installer is redirected to HTTPS like every page; the warning is shown when the page is served over HTTP (an environment set by the server, e.g. `CI_ENVIRONMENT=development`, or a proxy without the HTTPS header). A server without TLS installs from the console (`php spark app:install`), as the warning says.
- **Alternatives:** A) Do not force HTTPS while unconfigured (passwords and the database credentials could travel in clear).
- **Status:** Accepted (proposal)
- **Applied in:** `app/Config/App.php`, `modules/Core/Views/install/form.php`, `docs/security.md` ("Installer"); test `WebInstallerTest::testFormIsShownBeforeInstallation`

## D-18-02 — The site before installation: redirect, base URL and boot checks

- **Where:** spec "Installer and CLI" (web installer "available only while the application has not been configured"); D-09-01 (production refuses web requests without `app.maskKey`)
- **Problem:** Before installation there is no `.env`: the base URL is `http://localhost:8080/`, `app.maskKey` is empty (production refuses every web request), the database is not configured and global filters, settings and the debug toolbar's Shield collector query it.
- **Proposal:** While neither `.env` nor `writable/install.lock` exists: a required before filter `installguard` (after `forcehttps`) redirects every page but `install` to it; `Config\App` takes the base URL from the request (well-formed host only); the `pre_system` mask-key check waits until the application is configured; `moduleenabled`, `cspmodules` and `authsession` skip `install`; the debug toolbar drops Shield's collector. The web installer is a standalone page (no theme or settings) with the tracked Bootstrap copy. Once configured, nothing of this applies and `install` answers 404 (also when the configured database has tables). `service('installstate')` counts the testing environment as configured.
- **Alternatives:** A) A static HTML page outside CodeIgniter. B) No redirect: only `/install` works and other pages fail with database errors.
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Filters/InstallGuard.php`, `modules/Core/Install/InstallState.php`, `modules/Core/Config/{Events,Services,Routes}.php`, `app/Config/{App,Filters}.php`; tests `WebInstallerTest`

## D-18-03 — Passwords and the non-interactive installer

- **Where:** spec "Installer and CLI" ("non-interactive with `--options`"), D-00-47 (passwords on the command line)
- **Problem:** Options would put the database and superadmin passwords in the shell history and the process list.
- **Proposal:** Every value is an option except the passwords: asked without echo, or read from `INSTALL_DB_PASSWORD` and `INSTALL_ADMIN_PASSWORD`. `--no-interaction` (or input that is not a terminal) never asks; a missing required value stops before anything is written. The superadmin password must pass Shield's password validators (length, composition, nothing personal, dictionary). Values with a line break or `${` are refused (DotEnv would change them); the others are written single-quoted and escaped.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Commands/AppInstall.php`, `modules/Core/Install/{InstallOptions,EnvWriter}.php`; tests `AppCommandsTest`, `EnvWriterTest`, `InstallerTest::testCommonPasswordsAreRefusedByShieldsRules`

## D-18-04 — Demo data while installing

- **Where:** spec "Demo data" ("Interactive install … `--demo` / `--demo=News,Events`", "refuse in production unless `Packages.allowDemoInProduction`"), session 17 note (News after Auth)
- **Problem:** The installer writes production by default, where demo data is refused; and `--demo=News` alone would give News posts without the Auth demo users who write them.
- **Proposal:** The installer validates demo data against the environment it installs (development, or production with `packages.allowDemoInProduction`); the interactive question is only asked then. `--demo` loads every module with demo data, `--demo=A,B` those modules plus the modules they require that have demo data, always in dependency order (`--demo=News` loads Auth, then News). The web form has one "load demo data" checkbox (every module).
- **Alternatives:** A) Load only the listed modules.
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Install/Installer.php`; tests `InstallerTest::testDemoDataIsRefusedInProduction`, `testDemoOfAModuleLoadsTheModulesItNeedsFirst`

## D-18-05 — 2FA for the first superadmin without a Core → Auth dependency

- **Where:** spec "Installer and CLI" ("creates the first superadmin (and offers to set up 2FA)"), `CLAUDE.md` ("Never hard-code a module in the Core")
- **Problem:** The installer lives in the Core, 2FA in Auth.
- **Proposal:** The installer fires `install.superadmin` with the user and an `InstallPrompt` (the console; none on the web). Auth listens (`InstallTwoFactor`): `--2fa=email|totp|none` or a question; `totp` shows the `otpauth://` URI and the secret and stores it only after a valid code (three tries); the recovery codes are shown once. The web installer's last page tells the superadmin to set up 2FA from the profile.
- **Alternatives:** A) The Core calls Auth's `TwoFactor` directly. B) No 2FA offer (only the group policy).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Install/{Installer,InstallPrompt,CliPrompt}.php`, `modules/Auth/Services/InstallTwoFactor.php`, `modules/Auth/Config/Events.php`; test `InstallerTest::testTheSuperadminIsOfferedTwoFactor`

## D-18-06 — `app:install` with an existing `.env`

- **Where:** spec "Installer and CLI" (writes `.env`), plan risk (session 00 creates a local `.env`)
- **Problem:** Developers already have a `.env`; overwriting it would lose their database settings and keys.
- **Proposal:** Without a lock file and with a `.env`, the console asks to keep it (`--keep-env` without asking): its database settings are used, only a missing or empty `encryption.key` / `app.maskKey` is added, and the rest runs as usual. With `--no-interaction` and no `--keep-env` it stops. The web installer never runs with a `.env`.
- **Alternatives:** A) Always refuse when `.env` exists.
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Commands/AppInstall.php`, `modules/Core/Install/EnvWriter.php::complete()`; tests `AppCommandsTest::testAnExistingEnvIsNotOverwrittenWithoutKeepEnv`, `EnvWriterTest::testCompleteAddsOnlyMissingOrEmptyKeys`

## D-18-07 — The `security:check` summary before session 30

- **Where:** spec "Installer and CLI" ("at the end, runs `security:check` and prints its summary"); `deploy/18-installer.md` (out of scope: `security:check`)
- **Problem:** `security:check` arrives in session 30.
- **Proposal:** Until then the installer prints a reminder to run it; session 30 replaces the reminder with the call and its summary (noted in `docs/progress.md`).
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Commands/AppInstall.php` (`Install.cli.securityCheck`)

## D-18-08 — Order of the steps, `.env` permissions and the lock file

- **Where:** spec "Installer and CLI"
- **Problem:** If `.env` were written first, a failed migration would leave the site "configured" (the web installer gone) and half installed.
- **Proposal:** The installer validates, applies the new values to the running process (`RuntimeConfig`: environment variables, database, encryption key, mask key), checks the database is reachable and empty, runs `modules:sync`, creates the superadmin, loads demo data, logs `installed`/`superadmin_created`, and only then writes `.env` (atomically, `0640`) and `writable/install.lock` (JSON: date, Core version, environment; `0640`). A failure before the end leaves no `.env` and no lock: empty the database and run it again.
- **Alternatives:** —
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Install/{Installer,RuntimeConfig,InstallState,EnvWriter}.php`; tests `InstallerTest`

## D-19-01 — Demo users' random passwords: shown once, how

- **Where:** spec "Modules" › "Demo data" › "Safety" ("demo users get random passwords shown once (never fixed passwords)"); known issue of session 18
- **Problem:** The Auth demo users shared the fixed password `Demo-kpskeletor-2026`; the spec does not say how "shown once" works for the console, the installer and the future Modules screen.
- **Proposal:** Each demo user gets its own random password (`DemoData::randomPassword()`: 4 groups of 5 characters without look-alikes, about 99 bits). Seeders register it with `$demo->secret($label, $value)`; `DemoDataManager::takeSecrets()` returns them once, in memory only (never stored in clear, never in the activity log). `modules:demo`, `modules:sync --demo` and `app:install --demo` print them once; the web installer's last page shows them (as it shows the `.env`); the Modules screen (session 29) shows them once after loading.
- **Alternatives:** A) One random password for every demo user (simpler to type, but one leak opens every demo account). B) Send them by email (no mail server in a fresh development install).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Demo/{DemoData,DemoDataManager}.php`, `modules/Auth/Database/Seeds/AuthDemoSeeder.php`, `modules/Core/Commands/{ModulesDemo,ModulesSync,AppInstall}.php`, `modules/Core/Views/install/done.php`; tests `InstallerTest::testDemoUsersGetRandomPasswordsShownOnce`, `DemoDataTest::testRandomPasswordsAreLongAndDifferent`

## D-19-02 — Routes under `api/` outside `api/v1/`

- **Where:** spec "RBAC" › "Enforcement on routes" (every `api/v1/` route has kpapy's filter); `app/Config/Filters.php` (no `csrf`/`authsession` on `api/*`)
- **Problem:** The route audit only looked at `api/v1/`; a module route such as `api/internal/x` or `api/v2/x` would have neither CSRF, session hardening nor an auth filter, and pass `permissions:check`.
- **Proposal:** `RouteSecurityAuditor` refuses any route under `api/` that is not under `api/v1/` ("API routes must live under api/v1/"). A future API version is a decision of its own.
- **Alternatives:** A) Require kpapy on any `api/*` route (keeps the door open to `api/v2/` without a review).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Permissions/RouteSecurityAuditor.php`, `docs/rbac.md`; test `RoutesSecurityTest::testApiRoutesNeedKpapy`

## D-19-03 — `make:module --api`

- **Where:** spec "Modules" (`make:module` "With `--api`, it also generates the kpapy resource"), "API", `CLAUDE.md` ("`php spark make:module <Name> [--crud=<table>] [--api]`")
- **Problem:** `make:module` only implements `--crud`; `--api` was never built (no session file names it explicitly). It is not part of acceptance criterion 2.
- **Proposal:** Build it in session 26 (Core API endpoints, where the API catalogue checks are completed): with `--crud`, run `kpapy:make` for the same table, route it under `api/v1/<module>` with the `kpapy` filter, `setResource()` = the CRUD's resource, add the `$api` entry and an API scope test. Until then the manifest template shows the `$api` format and `docs/api.md` the manual steps.
- **Alternatives:** A) Build it now (new feature, out of this session's scope). B) Remove `--api` from `CLAUDE.md` and the spec (changes a documented command; owner's call).
- **Status:** Accepted (proposal)
- **Applied in:** `docs/plan.md` (session 26 and 32 notes)

## D-19-04 — Shared Core helpers extracted from News

- **Where:** `deploy/19-mvp-review.md` ("Refactor duplication that later modules would copy")
- **Problem:** Pages and Events need the slug generator and the HTML sanitiser of News; nine controllers had their own "current user" method; four test base classes repeated helpers.
- **Proposal:** `Modules\Core\Text\Slugger` (scope and fallback word added, `rules()`), `Modules\Core\Security\HtmlSanitizer` (formerly `Modules\News\Services\BodySanitizer`, same allow-list), `BackendController::currentUser()` / `currentUserOrNobody()`, and `AuthTestCase::loginWith()` / `assertNotFound()` / `assertCrudRefused()`. No public convention changes (manifest keys, permission names, URLs). The translation fallback, the group audience and the publish workflow are extracted by session 20 when Pages shows what they share (recorded in `docs/plan.md`).
- **Alternatives:** A) Extract everything now (guessing the needs of Pages and Events).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/{Text/Slugger,Security/HtmlSanitizer,Controllers/BackendController}.php`, `modules/Auth/Tests/AuthTestCase.php`, News, Users, Auth, Api, Dashboard controllers

## D-19-05 — HTTP on `localhost` with `php spark serve`

- **Where:** spec "Security baseline" (`forceGlobalSecureRequests` and `Secure` cookies in production), "Installer and CLI" (web installer); D-18-01
- **Problem:** A fresh clone has no `.env`, so the environment is production: `php spark serve` and `http://localhost` redirect to `https://localhost`, which has no certificate, and the web installer cannot be opened on the developer's own computer (D-18-01 only offered the console installer or `CI_ENVIRONMENT=development` for that).
- **Proposal:** Production keeps forcing HTTPS and `Secure` cookies except when PHP's built-in development server (`PHP_SAPI === 'cli-server'`: `php spark serve`, `php -S`) answers a client on a loopback address (`127.x`, `::1`) for a loopback host (`localhost`, `*.localhost`, `127.x`, `[::1]`): nothing crosses the network there. Checking the Host header alone is not enough (an nginx with the default `proxy_pass http://127.0.0.1:8080` sends `Host: 127.0.0.1` from a loopback address for every visitor), hence the server type. The installer still warns that the page is not served over HTTPS.
- **Alternatives:** A) Exempt `localhost` hosts on any server (unsafe behind a local proxy). B) Keep D-18-01 as it was (console installer or `CI_ENVIRONMENT=development` only).
- **Status:** Applied (decided)
- **Decision (2026-11-11):** requested by the owner while testing a fresh clone locally ("si s'arrenca amb localhost no requereixi https"); applied with the narrow rule above.
- **Applied in:** `app/Config/App.php` (`isLocalDevServer()`, constructor), `app/Config/Cookie.php`, `docs/security.md` ("Installer"), `README.md`; tests `tests/feature/Security/LocalDevServerTest.php`

## D-20-01 — Pages permission names, URLs, the home page setting and the theme's page templates (decide)

- **Where:** specification "Modules" › "Pages" (resource names and settings are not given; "the home page chosen in settings"; "template (from the theme's page templates)"), "Themes" (the theme manifest lists no page templates), D-00-18 (single-resource modules may name their resource)
- **Problem:** The specification names no permission, URL or setting of the Pages module, and a theme has no way to declare page templates. The settings forms only offer fixed choices (`select` options are static), so the home page cannot be a list of pages without a Core change.
- **Proposal (built):**
  - One resource named `pages` (single-resource module): `pages.{list,show,add,edit,delete,export,trash,restore,purge}` + `pages.publish` (no `import`). The custom operations reuse CRUD permissions: preview and revisions `show`, restore a revision `edit`, duplicate `add`. Grants: admin `pages.*`; editor `pages.{list,show,add,edit,publish}`.
  - URLs: back office `backend/pages` (kpcrudy; `preview/{id}`, `revisions/{id}`, `POST restorerevision/{id}`, `POST duplicate/{id}`, `POST publish|unpublish/{id}`); public `/{slug}` and nested `/{slug}/{slug}…` at the root of the site; `sitemap.xml`.
  - Setting `Pages.homePage` (group "Public site"): the **path** of the home page (e.g. `inici`, `qui-som/equip`), empty = the theme's home.
  - Theme manifest key `public array $pageTemplates = [key => label key]` (inherited and extended by child themes; `ThemeManager::pageTemplates()`); a template is the view `Modules\Pages\Views\templates\<key>`, overridden by themes in `Views/overrides/Pages/templates/<key>.php`. The default theme offers `default`, `wide` and `sidebar`.
- **Alternatives:** A) `pages-pages.*` (the generated prefix, no `name`). B) Extra permissions `pages.revisions` (view and restore revisions) and `pages.preview`. C) `Pages.homePage` as a page id chosen from a list, which needs a Core extension for dynamic `select` options in manifests (e.g. `'options' => [Class::class, 'method']`). D) Page templates as files only (any `Views/pages/*.php` of the theme), without a manifest key and without translated labels.
- **Status:** Applied
- **Decision (2026-11-11):** «Inici triat d'una llista». Everything stays as built (permissions `pages.*` with `pages.publish`, operations on `show`/`edit`/`add`; `backend/pages`; pages at the site root and `sitemap.xml`; the theme key `$pageTemplates`), except the home page setting: `Pages.homePage` is chosen from a list of the pages and stores the page **id**, so renaming a slug does not break the home page. This needs a Core extension: dynamic options for `select` settings declared in manifests (a new manifest setting key). **Must change:** the Core settings registry/definition and the Settings form generator and validation (dynamic options, the value must be one of them), `docs/settings.md`, `modules/Pages/Config/Module.php` (setting type and options provider), `modules/Pages/Services/HomePageResolver.php`, Pages tests and docs, the specification ("Configuration and settings", "Pages").
- **Applied in:** `modules/Core/Settings/{SettingOptionsProvider,SettingDefinition}.php` (manifest setting key `optionsProvider`), `modules/Core/Modules/ManifestValidator.php`, `modules/Settings/Forms/Field.php` and `Views/fields/select.php` (choices shown as they are, validated with `in_list`), `modules/Pages/Settings/HomePageOptions.php`, `modules/Pages/Config/Module.php` (`Pages.homePage` is a `select`), `modules/Pages/Services/HomePageResolver.php` (reads the id), `modules/Pages/Language/*/Pages.php`, `docs/specification.md` ("Configuration and settings", "Themes", "Pages"), `docs/settings.md`, `docs/pages.md`, `modules/Pages/CLAUDE.md`; tests `HomePageSettingTest`, `PublicPagesTest::testTheHomePageFromTheSettings` (renaming the slug keeps the home page), `ManifestValidatorTest` (`optionsProvider`). As built and kept: `modules/Pages/Config/{Module,Routes}.php`, `modules/Core/Theme/{ThemeManifest,ThemeManager,ThemeValidator}.php`, `themes/default/Theme.php`, `docs/themes.md`. Existing development databases: a stored path in `Pages.homePage` no longer matches (the theme's home is shown) until a page is chosen again.

## D-20-02 — Private pages: login for guests, 404 for the wrong group

- **Where:** specification "Pages" ("Private pages require login and the right group", "`noindex` for non-public pages")
- **Problem:** The specification does not say what a guest or a signed-in user without the group sees.
- **Proposal:** A guest who opens a `private` or `groups` page is sent to the login and comes back after it (Shield's `beforeLoginUrl`); a signed-in user who may not read it gets the theme's 404, like a page that does not exist (its existence is not revealed beyond the login prompt). Non-public pages are sent with `<meta name="robots" content="noindex, nofollow">` and `Cache-Control: private, no-store`, and never appear in the sitemap; superadmins follow the same rule (authors use the back office's preview, `noindex` and `X-Robots-Tag`). Drafts, future scheduled pages and trashed pages (and their children) are a 404 for everybody.
- **Alternatives:** A) 404 for guests too (no login prompt; the specification asks for login). B) 403 for the wrong group (reveals that the page exists).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Pages/Controllers/PublicPages.php`, `modules/Pages/Services/{PageVisibility,PageRenderer}.php`; tests `PublicPagesTest::testPrivatePagesRequireLoginAndTheRightGroup`, `testDraftsTrashedAndFuturePagesAreNotFound`

## D-20-03 — The rich-text editor: Quill 2 under the enforced CSP

- **Where:** specification "Front-end assets" (vendored MIT editor that works without `unsafe-inline`), "Pages" (HTMLPurifier drops `style`, alignment mapped to classes), contradiction 25 of `docs/plan.md`, D-00-35 (News switches to the editor)
- **Problem:** Most editors write `style` attributes (blocked by the CSP) or are GPL (TinyMCE 7); kpcrudy has no rich-text field and puts translated fields in tabs with its own template.
- **Proposal:** Quill 2.0.3 (BSD-3-Clause, pre-approved as "MIT/BSD"), vendored in `public/assets/vendor/quill/` with `vendor.json`, loaded on demand by `core/forms/editor.js` (a `data-component` on the textarea; the textarea stays the form value and the fallback without JavaScript). Quill only uses the CSSOM (allowed); alignment is registered as the classes `text-center`/`text-right`/`text-justify` instead of `ql-align-*`. The Core's `HtmlSanitizer` maps any alignment (`style`, `align`, `ql-align-*`) to those classes, allows no other class and drops `style`. Toolbar: headings 2–4, bold, italic, underline, strike, lists, quote, code block, alignment, link, clear; UI texts from `Core.js.editor.*`; colours from Bootstrap variables (light and dark). kpcrudy: `fieldType('body', 'translation', ['attrs' => RichTextEditor::attrs()])` (the type of translated fields; another type would take the field out of the tabs; library request). Images inside the text: session 23 (D-17-08).
- **Alternatives:** A) Trix (MIT; injects a `<style>` element, needs a nonce meta). B) A home-made `contenteditable` editor (`execCommand` is deprecated and writes `style`). C) Tiptap (MIT; needs a bundler).
- **Status:** Accepted (proposal)
- **Applied in:** `public/assets/vendor/quill/`, `public/assets/vendor/vendor.json`, `modules/Core/Assets/js/forms/editor.js`, `modules/Core/Assets/css/editor.css`, `modules/Core/Frontend/RichTextEditor.php`, `modules/Core/Security/{HtmlSanitizer,AlignmentToClass}.php`, `modules/Core/Language/*/Core.php` (`js.editor`), `themes/default/Assets/css/theme.css`, `modules/News/Controllers/Posts.php`; tests `HtmlSanitizerTest`, `PostsCrudTest::testTheBodyUsesTheRichTextEditorAndKeepsAlignmentAsClasses`, `PagesCrudTest::testTheBodyIsAnEditorInEveryLanguage`

## D-20-04 — The catch-all page route, reserved slugs and the home page hook

- **Where:** specification "Pages" (public routes `/{slug}` and nested slugs; the home page chosen in settings), `docs/plan.md` session 20 risks (a catch-all route shadows routes of modules loaded later), `CLAUDE.md` ("Never hard-code a module in the Core")
- **Problem:** Module routes are discovered in namespace order, so a catch-all of Pages could hide the routes of later modules (Settings, Users…); a page slug could hide a route (`news`); `/` belongs to `app/Config/Routes.php` and discovered routes cannot replace it.
- **Proposal:** `Config\Routing::$prioritize = true` and the page route `(:pagepath)` with `priority => 1` (matched after every other route); the placeholder only accepts valid slugs and never `backend/…` or `api/…` (those keep the router's 404 and their filters). A root page cannot take a reserved slug: the first static segment of every route plus `api`, `assets`, `backend`, `themes`, `uploads`, `vendor`, `writable` (a typed slug is refused, a generated one gets a suffix). `/`: `App\Controllers\Home` triggers the Core event `homePage` with a `Modules\Core\Frontend\HomePage`; Pages' listener (only while the module is enabled) answers with the page of `Pages.homePage` when the visitor may read it, otherwise the theme's home is shown; the page's own path redirects to `/`, its canonical URL is `/`.
- **Alternatives:** A) Pages under a prefix (`/p/{slug}`; the specification asks for `/{slug}`). B) A list of reserved slugs only (forgets routes added later). C) The home page decided in the Core's `Home` controller (hard-codes a module).
- **Status:** Accepted (proposal)
- **Applied in:** `app/Config/Routing.php`, `app/Controllers/Home.php`, `modules/Core/Frontend/HomePage.php`, `modules/Pages/Config/{Routes,Events}.php`, `modules/Pages/Services/{PageSlugs,HomePageResolver}.php`; tests `PublicPagesTest::testOtherRoutesWinOverPages`, `testTheHomePageFromTheSettings`, `PagesCrudTest::testSlugsAreUniquePerParentAndLanguage`, `PagesModuleTest::testTheHomePageEventIsIgnoredWhileTheModuleIsDisabled`

## D-20-05 — Slugs per language and the language of a page

- **Where:** specification "Pages" (slug unique per parent and language; title, slug… translated with `setTranslations()`), "Languages" (Catalan default; guests see `en` until session 31)
- **Problem:** With a slug per language, the specification does not say which language a URL shows, which URLs go into the sitemap, nor how slugs are made unique (kpcrudy's hooks cannot change translated values, D-17-02).
- **Proposal:** Before kpcrudy reads the form, empty slugs are filled from each language's title (unique among the siblings in that language); a typed slug that clashes with a sibling in the same language (trashed pages included, they can be restored) is refused by `beforeInsert`/`beforeUpdate`. A path resolves segment by segment with the slugs of any language (current language first, then Catalan); the texts follow the visitor's language with the Catalan fallback, and the canonical URL is the path in that language (session 31 may make the path's language select the content language). The sitemap lists each distinct path of each public, published, indexable page (a language with its own canonical URL is left out) and `/`.
- **Alternatives:** A) One slug per page (not translated; the specification translates it). B) A language prefix in URLs (`/es/…`; language negotiation is session 31's).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Pages/Services/{PageSlugs,PageResolver,PageTree,SitemapBuilder}.php`, `modules/Pages/Controllers/Pages.php` (`prepareForm()`, `beforeSave()`); tests `PagesCrudTest::testSlugsAreUniquePerParentAndLanguage`, `PublicPagesTest::testPublishedPublicPagesAtTheirNestedSlugs`, `testTheSitemapListsOnlyPublicIndexablePages`

## D-20-06 — What a revision keeps and what a restore writes back

- **Where:** specification "Pages" ("every save keeps a revision (who, when, diff); restore a revision as a custom operation")
- **Problem:** The specification does not say what a revision contains, how the diff is shown nor what a restore changes (restoring the status would publish without `pages.publish`; restoring the parent could break the tree or clash with a sibling's slug).
- **Proposal:** `page_revisions` keeps a JSON snapshot (the page's columns, its texts in every language and its groups), the user, the date, the action (`create`, `save`, `restore`, `duplicate`) and the list of changed fields; a save that changes nothing adds no revision. The revisions page lists them and shows the selected revision's changes field by field (before / after, rich text without tags). A restore (POST, `pages.edit`, a revision of that page only) writes back the texts, groups (still existing), template, visibility, `noindex` and featured image (if the file still exists), sanitises the body again, suffixes a slug taken meanwhile, and records a new revision; the parent, the order, the status and the publication date are kept. Revisions are deleted with the page (purge).
- **Alternatives:** A) Restore everything (could publish a draft or move the page). B) Line-by-line text diff (heavier; the field view is enough for pages).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Pages/Services/Revisions.php`, `modules/Pages/Views/revisions.php`, `modules/Pages/Database/Migrations/2026-11-11-100004_CreatePageRevisions.php`; tests `PagesCrudTest::testEverySaveKeepsARevisionAndARevisionCanBeRestored`, `PagesModuleTest::testRevisionsSkipUnchangedSaves`

## D-20-07 — Content helpers extracted from News into the Core

- **Where:** D-19-04, `docs/plan.md` session 20 ("extract to the Core what Pages shares with News")
- **Problem:** Pages needs News' translation lookup, audience rule, publish workflow and upload handler.
- **Proposal:** `Modules\Core\Content\ContentTranslations` (languages, Catalan fallback, `texts()`, `pick()`), `Modules\Core\Content\Audience` (public / private / groups with a junction table, `groupIds()`), `Modules\Core\Content\PublishWorkflow` (`statusFor()`, `applyPublished()`, `releaseScheduled()`) and `Modules\Core\Storage\CrudUploadHandler` (kpcrudy upload handler on the Storage service, `fieldOptions()`). News keeps its public names (`NewsVisibility`, `NewsRepository::FALLBACK_LOCALE`, `Posts::LOCALES`, `CoverUploads`) on top of them. No public convention changes.
- **Alternatives:** A) Copy the code into Pages (two copies of a security rule).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Content/`, `modules/Core/Storage/CrudUploadHandler.php`, `modules/News/Services/{NewsVisibility,NewsRepository,Publisher}.php`, `modules/News/Storage/CoverUploads.php`, `modules/News/Controllers/Posts.php`

## D-21-01 — Menus permission names, URLs and the Core contracts for menus (decide)

- **Where:** specification "Modules" › "Menus" (`menus-public.*` list/add/edit/delete, `menus-backend.*` view/customise/links/reset), "Menus", `docs/plan.md` session 21 (routes `backend/menus/public`, `backend/menus/backend`, helper `menu('primary')`), D-17-05 (link type contract)
- **Problem:** Permission names, URLs, manifest keys and the Core extension points are public conventions. The specification names the permissions and the helper but not the URLs of the items editor, the link type contract, nor how a module (not the Core) plugs into the generated back-office menu and the theme's menu locations.
- **Proposal:** Resources `public` (crud − show, export, import, trash, restore, purge) and `backend` (page + extras `customise`, `links`, `reset`): exactly `menus-public.{list,add,edit,delete}` and `menus-backend.{view,customise,links,reset}`; admin gets both, editor `menus-public.{list,edit}`. URLs: `backend/menus/public` (kpcrudy CRUD of menus), `backend/menus/items/<menu>[/new|/edit/<item>|/save|/order|/delete/<item>]` (`menus-public.edit`), `backend/menus/backend[/order|/entry|/group/new|/link/new|/custom/<id>|/custom/save|/custom/<id>/delete|/reset|/orphans]`, scope `?group=<name>`. Core contracts: `Modules\Core\Menus\MenuLinkType` (`key()`, `label()`, `options()`, `url()`; `ManifestValidator` checks `$menuLinks` against it; Pages' `PageLink` and News' `CategoryLink` implement it); event `backendMenu` with `BackendMenuLayer` (definitions may add `kind` `group`/`link`, `href`, `target`, `permissions`, `anyPermissions`, `groups`, `labels`, `hidden`), applied by `MenuBuilder` before the permission filter; event `publicMenu` with `PublicMenu` and the helper `menu('<location>')` returning `PublicMenuItem`s, rendered by the theme partial `partials/public_menu` in the `primary` and `footer` locations; `DemoSeeder::AFTER` (D-21-06).
- **Alternatives:** A) Items as a second kpcrudy CRUD (`backend/menus/public/items`, see D-21-02). B) `menus-backend.edit` instead of `customise` (the specification says `customise`). C) The Core reads the Menus tables directly (hard-codes a module in the Core).
- **Status:** Applied (decided)
- **Decision (2026-11-11):** «Tal com està» — the permission names, URLs and Core contracts stay as built and proposed (the recommended option).
- **Specification updated:** built by session 21 as proposed (no code change); `docs/specification.md` ("Modules" › "Menus": the link type interface, the events, the partial, `DemoSeeder::AFTER`, the URLs)
- **Applied in:** `modules/Menus/Config/{Module,Routes,Events}.php`, `modules/Core/Menus/{MenuLinkType,BackendMenuLayer,PublicMenuItem,MenuBuilder,MenuEntry}.php`, `modules/Core/Frontend/PublicMenu.php`, `modules/Core/Helpers/menu_helper.php`, `modules/Core/Modules/ManifestValidator.php`, `themes/default/Views/partials/public_menu.php`, `themes/default/Views/layouts/public.php`; tests `MenusModuleTest::testPermissionNamesAndGrants`, `testTheLinkTypesOfTheOtherModules`, `BackendMenuTest::testEveryRouteNeedsItsPermissionAndForgedPostsAreRefused`, `PublicMenusTest::testEveryRouteNeedsItsPermissionAndForgedPostsAreRefused`

## D-21-02 — Menu items edited in a tree editor and a form of their own

- **Where:** `CLAUDE.md` (every back-office CRUD screen with kpcrudy), specification "Modules" › "Menus" (tree editor: drag and drop, keyboard)
- **Problem:** Items belong to one menu, nest, change their target field with their type (URL, route or one of a module's choices) and are ordered by dragging: kpcrudy has a tree list but no drag-and-drop ordering nor fields that depend on the type.
- **Proposal:** The menus themselves are a kpcrudy CRUD (name, location; an "Items" operation). The items are edited in a screen of the module: a tree editor (`menus/tree-editor`: drag and drop with pointer events, arrow keys on the handle, up/down/indent/outdent buttons that also work without JavaScript, one move per request) and an item form (labels per language, type and target, parent, icon, window, CSS class, visibility, groups). Writes are logged with `activity_log()`. The same tree editor serves the back-office customisation.
- **Alternatives:** A) A second kpcrudy CRUD with a `where(menu_id)` and order fields (no tree editing).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Menus/Controllers/{PublicMenus,MenuItems}.php`, `modules/Menus/Views/{items,items_tree,item_form,tree_buttons,target_fields}.php`, `modules/Menus/Assets/js/{tree-editor,link-form}.js`

## D-21-03 — The customisation layer: differences, field-by-field scopes, hidden subtrees

- **Where:** specification "Modules" › "Menus" (stable keys, permissions always win, new entries in their default place, orphans, cycles impossible, reset, per group "the most specific one wins")
- **Problem:** The specification does not say what is stored when the tree is saved, how two scopes combine, which group wins for a user in several groups, nor what hiding a parent does.
- **Proposal:** One row per entry key and scope (`group_name` null = everyone) with nullable `parent` (`''` = root), `zone`, `position`, `labels`, `icon`, `hidden`; the editor stores only differences from its base (the generated menu for everyone; the menu of everyone for a group), so untouched entries follow their module. Scopes merge field by field: everyone, then the user's groups from the last to the first in the order of Users › Groups (reserved groups first), so the group listed first wins. Only place, label, icon, order and hidden change — never the route or the permission, which `MenuBuilder` checks after the layer. Hiding hides the entry with its children (a group scope may show it again). A relocation under the entry's own descendants is refused by the editor and ignored if stored. Customisations of unknown keys are kept, inactive, listed as orphans and deleted only on request; reset per entry, per scope or everything (custom groups and links are kept). Custom groups and links are entries `custom.<id>`, created for everyone (their place can be customised per group).
- **Alternatives:** A) Store the whole tree per scope (new modules would need to be placed by hand). B) Whole-row precedence (a group would have to repeat everyone's changes).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Menus/Services/{Customisations,CustomisationApplier,BackendLayout,TreeMoves}.php`, `modules/Menus/Controllers/{BackendMenu,CustomLinks}.php`; tests in `modules/Menus/Tests/BackendMenuTest.php`

## D-21-04 — Custom links and menu items: addresses and the 403 rule

- **Where:** specification "Modules" › "Menus" (only `https://`, `http://`, internal paths and routes; never `javascript:`, `data:` or `//`; "a link to an internal route takes that route's `can:` permission by default; the editor cannot make a link visible to users who would get a 403")
- **Problem:** How to validate and how to enforce the 403 rule when a path or route changes after the link is saved.
- **Proposal:** `UrlValidator`: `https://`/`http://` with a host and no credentials, or a path starting with one `/`; anything with spaces, control characters or backslashes is refused (browsers strip them, so `java\tscript:` would run). An internal path or a route name must be answered by a GET route (routes with parameters are refused). The `can:` filters of that route are read when the menu is built (not stored), so the link (back office) or item (public menus) is shown only to users who pass them; a custom link may add one permission from the catalogue and groups. Stored values are validated again when the menu is built: an invalid or gone target is never shown. External links opening in a new tab get `rel="noopener noreferrer"`. Public paths answered by the Pages catch-all are accepted (the page itself decides who reads it).
- **Alternatives:** A) Store the route's permission when saving (stale after a route change). B) Also allow `mailto:`/`tel:` (not in the specification).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Menus/Services/{UrlValidator,RouteAccess,LinkTarget}.php`, `modules/Core/Menus/MenuBuilder.php` (`linkVisible()`); tests `UrlValidatorTest`, `BackendMenuTest::testCustomLinksFollowTheRouteThePermissionAndTheGroups`, `testCustomLinkAddressesAreValidated`, `PublicMenusTest::testItemAddressesAreValidated`

## D-21-05 — Public menus: visibility, depth, cache and the active item

- **Where:** specification "Modules" › "Menus" (visibility everyone/guests/logged-in/groups; `menu('primary')` cached per menu, language and group set, marks the active item)
- **Problem:** Page link types depend on the pages' publication, which changes with time (scheduled pages); the nesting depth and the active rule are not specified.
- **Proposal:** One menu per theme location (`menus.location` unique, empty = not placed). Items nest up to three levels (the default theme shows the third level indented in the dropdown). The tree for a visitor is cached in the `menus` scope per menu, language and group set for five minutes (invalidated on every change of the module's data); labels fall back to Catalan. An item whose target is gone is hidden, or kept as a heading when it has visible children. The active item is the one whose URL is the current URL, else the longest item URL that is a parent path of it; its ancestors are marked open.
- **Alternatives:** A) Cache for a day and invalidate from Pages (the Pages module would need to know about Menus). B) Unlimited depth.
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Menus/Services/{MenuRenderer,MenuRepository}.php`, `themes/default/Views/partials/public_menu.php`; tests `PublicMenusTest`

## D-21-06 — Demo data that depends on another module's demo

- **Where:** `CLAUDE.md` (demo data only in `<Name>DemoSeeder`), `app:install --demo` (loads every module's demo in name order)
- **Problem:** The Menus demo links to the Pages and News demo data, but `Menus` comes before `Pages` in name order.
- **Proposal:** `DemoSeeder::AFTER` (a constant, default `[]`) names the modules whose demo data a seeder uses; `DemoDataManager::modulesWithDemo()` orders the modules so those come first (name order otherwise; a cycle keeps the name order). The Menus seeder also falls back to addresses when the pages do not exist.
- **Alternatives:** A) The Menus seeder loads the Pages demo itself (the installer would not list it). B) Only addresses in the demo menus.
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Core/Demo/{DemoSeeder,DemoDataManager}.php`, `modules/Menus/Database/Seeds/MenusDemoSeeder.php`; test `MenusModuleTest::testDemoDataLoadsAfterPagesAndIsRemovedCompletely`

## D-22-01 — Files permission names, URLs, settings and space paths (decide)

- **Where:** specification "Modules" › "Files" (permissions `files.list`, `files.upload`, `files.edit`, `files.delete`, `files.folders`, `files.acl`, `files.all`, `files.private`; setting `Files.personalSpaceGroups`; spaces "personal", "modules", "shared"; quotas per user); `docs/plan.md` session 22 (routes `backend/files`, `backend/files/api/*`)
- **Problem:** The specification names the permissions but does not say what `files.private` allows, how the shared space is laid out, how the personal quota is set, the setting's type or the default grants.
- **Proposal:** One resource named `files` (D-00-18) of type `crud` without `show`, `add`, `export`, `import`, `trash`, `restore`, `purge` plus the extras `upload`, `folders`, `acl`, `all`, `private`: exactly the eight names of the specification. `files.private` = using the **shared folders of the private disk** in the file manager (public shared folders and the personal space do not need it; serving a private file by URL never needs it). Spaces by path prefix: `users/{user_id}` (private disk), `modules/<module>`, and **`shared/…`** on each disk (two roots, created on first use with an empty ACL: only `files.all` until an administrator shares them); other paths are refused. Settings: `Files.personalSpaceGroups` (`list` of group names, default `admin`, `editor`) and **`Files.personalQuota`** (int, MB, default 100, 0 = none), group `files` of the Files tab. Grants: `admin` `files.*`; `editor` `files.list`, `files.upload`, `files.edit`, `files.delete`, `files.folders`, `files.private` (not `files.acl`, `files.all`). URLs: `backend/files` (page) and `backend/files/api/{tree,list,trash,upload,file/{id}[/download|preview|thumbnail|update|move|delete|restore|purge],folder[/rename|/delete],acl}`.
- **Alternatives:** A) `files.private` = also needed to read private files in the manager (personal spaces included): a user of `Files.personalSpaceGroups` without it could not open their own space. B) Shared folders anywhere at the disk root (no `shared/` prefix): more freedom, but module and personal paths could be shadowed. C) The personal quota per group instead of one setting. D) `editor` without `files.folders`.
- **Status:** Decided
- **Decision (2026-11-12):** «Com està fet (recomanada)». As proposed and built: the eight names of the specification on the resource `files`; `files.private` = the shared folders of the private disk in the file manager; shared space under `shared/` on each disk; settings `Files.personalSpaceGroups` and `Files.personalQuota` (MB, default 100); editor grants `files.list`, `files.upload`, `files.edit`, `files.delete`, `files.folders`, `files.private`; URLs `backend/files` and `backend/files/api/*`. **Must change:** specification "Files" (what `files.private` means, the `shared/` space, `Files.personalQuota`).
- **Applied in:** `modules/Files/Config/{Module,Routes}.php`, `modules/Files/Services/{Spaces,FolderAcl,QuotaService}.php`, `modules/Files/Language/*/Files.php`, `docs/files.md`; tests `FilesModuleTest`, `FolderAclTest`

## D-22-02 — The file manager is a screen of its own over JSON endpoints

- **Where:** `CLAUDE.md` (every back-office CRUD screen with kpcrudy), specification "Files" (browse folders, drag and drop with progress, list and grid views, preview, move, trash, folder permissions)
- **Problem:** A file manager is a folder tree with uploads, previews and per-folder rights, not a table CRUD; kpcrudy has no tree browsing, drag-and-drop uploads with progress or ACL editing.
- **Proposal:** `backend/files` renders a shell (`Views/manager.php`); the ES module `files/file-manager` talks to JSON endpoints (`Controllers/FileApi.php`) that check the RBAC permission (route filters) and the folder ACL (every request). No kpcrudy CRUD in this module; the manifest resource is still `crud`-typed so the catalogue has `files.list`/`edit`/`delete` with the specification's names. Writes are logged with `activity_log()` (ACL changes with `security_log()`).
- **Alternatives:** A) kpcrudy CRUDs for `files` and `folders` (flat lists filtered by folder; no tree, no uploads with progress).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Files/Controllers/{FileManager,FileApi}.php`, `modules/Files/Views/manager.php`, `modules/Files/Assets/js/file-manager.js`, `modules/Files/Assets/css/file-manager.css`

## D-22-03 — Virtual folders: `files.folder`, files referencing `stored_files`

- **Where:** specification "Files" ("the folder tree is virtual (paths)", `files` metadata), D-13-03 (`stored_files` written only by the Storage service; `files` references it)
- **Problem:** Moving a file or renaming a folder would have to move files on disk and rewrite `stored_files.path`, which only the Storage service writes.
- **Proposal:** `files` has `stored_file_id` (unique, cascade), `disk`, `folder` (virtual), `name` (display/download name, keeps the stored extension), `visibility`, `alt`, `tags`, `thumbnail`, `deleted_at`/`deleted_by`. Uploads are stored physically under the folder's path, but moving a file or renaming a folder only changes `files.folder` (and `folders.path`). Folders are rows of `folders`; there is no implicit folder. Files that other modules store (no `files` row) are not listed, except read-only in a module folder whose policy says `readonly`.
- **Alternatives:** A) Physical moves through a new Core `Storage::move()` (Core change; renaming a folder moves every file).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Files/Database/Migrations/*`, `modules/Files/Services/{FileRepository,FolderRepository}.php`

## D-22-04 — Who reads a private file, and the folder ACL details (decide)

- **Where:** specification "Files" ("Serving private files", "RBAC is the ceiling, the ACL decides where", predefined spaces, `FileAccessPolicy`), D-13-01 (`Config\Storage::$accessRule`)
- **Problem:** The specification does not say what happens to private files outside the managed folders (stored by other modules), to trashed files, who edits a personal space's ACL, how a module registers its policy or what `files.all` means for module folders.
- **Proposal:** The Files module sets `Config\Storage::$accessRule` to `FolderAccessRule` through its registrar (no Core change; the rule behaves like `OwnerAccess` while Files is disabled). Order: a module policy for the file's folder decides (superadmins always pass; guests possible: the record's visibility); guests otherwise get nothing; a trashed file only for users with `delete` in its folder; a folder governed by the ACL → its `view` right; anything else → `OwnerAccess` (uploader and superadmins). Always a 404 when refused. ACL: an own ACL with no entries hides the folder; entries add up; `files.all` and superadmins have every right everywhere **except module folders** (only `view`, and only when the policy says `readonly`); the owner of a personal space always has every right in it; only `files.all` edits the ACL of a personal space (its owner cannot share it in this session). Policies register through the event `filesAccessPolicies` (`PolicyRegistry`), only for folders inside `modules/` and only for enabled modules; the longest folder wins.
- **Alternatives:** A) Files outside managed folders are denied to everyone but superadmins (breaks private uploads of modules without a policy). B) `files.all` overrides module policies. C) Owners may share their personal space with other users.
- **Status:** Decided
- **Decision (2026-11-12):** «Només superadmin + registre (recomanada)». The rule order stays as proposed, but `files.all` no longer reaches other users' personal spaces: it gives every right in the shared folders only. Only superadmins enter another user's personal space (and set its quota and its subfolders' permissions); every time a superadmin reads (downloads, previews, opens through `files/private/{key}`) a file of someone else's personal space, a `security` entry is written to the activity log. For anything else administrators use impersonation (already re-authenticated and logged). Reason (owner): private files belong to their owner; in case of need an administrator can impersonate the user; superadmins keep access to ease the management of the platform. **Must change:** `FolderAcl`, `Browser` ("Personal spaces" node for superadmins), `FolderAccessRule` and the file manager's download/preview (security log), `PrivateFilesTest` (an admin is "another user" again), Files tests, `docs/files.md`, specification "Files" (personal space: "nobody else except superadmins").
- **Applied in:** `modules/Files/Config/Registrar.php`, `modules/Files/Services/{FolderAccessRule,FolderAcl}.php`, `modules/Files/Policies/{FileAccessPolicy,PolicyRegistry}.php`, `docs/files.md`, `docs/storage.md`; tests `PrivateFileRuleTest`, `FolderAclTest`

## D-22-05 — Quotas, trash and thumbnails

- **Where:** specification "Files" (quotas per user and per shared folder, trash with restore, thumbnails generated on upload)
- **Problem:** Whether trashed files count, which quota applies to nested folders, where thumbnails live and how they are served.
- **Proposal:** Every quota of the folder's chain must hold (the nearest is shown); the trash counts until files are deleted for good; the quota is checked before storing and again with the stored (re-encoded) size. Only `files.all` sets a folder quota (in the permissions dialog). No automatic purge of the trash in this session. Thumbnails: 320 px WebP (JPEG without WebP support), private disk `modules/files/thumbnails/<key>.webp`, served by the file manager after the `view` right, deleted with the file. Only empty folders (trash included) can be deleted.
- **Alternatives:** A) The trash does not count. B) A scheduled task purging old trash (could come with session 27 or 35).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Files/Services/{QuotaService,ThumbnailService,Trash,Uploader}.php`; tests `QuotaTrashTest`

## D-22-06 — Upload progress without inline styles

- **Where:** `docs/plan.md` session 22 risks ("upload progress bars without `style=""`", contradiction 25), strict CSP (`CLAUDE.md`)
- **Problem:** Bootstrap progress bars set their width with an inline style, which the CSP forbids.
- **Proposal:** Each file is uploaded in its own request (`XMLHttpRequest`, `upload.onprogress`) and shown with a native `<progress>` element (its `value` attribute is not a style), styled from `Assets/css/file-manager.css` (`accent-color`). The CSRF header and its regenerated token are handled like `core/http.js`. One request per file also means each file counts against `ratelimit:uploads`.
- **Alternatives:** A) CSS classes per 5 % step (`.w-pct-35`). B) A single request for every file (one progress bar).
- **Status:** Accepted (proposal)
- **Applied in:** `modules/Files/Assets/js/file-manager.js`, `modules/Files/Assets/css/file-manager.css`
