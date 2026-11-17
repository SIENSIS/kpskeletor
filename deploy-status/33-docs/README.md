# Session 33 — Documentation

Branch: `claude/vibrant-hypatia-t0pedc` · Pull request: [SIENSIS/kpskeletor#41](https://github.com/SIENSIS/kpskeletor/pull/41) · Date: 2026-11-17

## What was built

Every document the specification lists ("Documentation (English)"), checked against the code, and
a test that keeps them in step with it.

- **New:** `CHANGELOG.md` (Keep a Changelog, an `Unreleased` section; 1.0.0 is session 36),
  `docs/architecture.md` (modules and manifest keys, discovery, `modules:sync` steps, Core
  services, request lifecycle in the real filter order, caching scopes), `docs/dependencies.md`
  (Composer constraints, lock policy, `composer audit` / `security:audit`, Dependabot, vendored
  front-end libraries and `vendor.json`, adding or updating one), `docs/user-guide.md` and its
  Catalan and Spanish versions `docs/ca/user-guide.md`, `docs/es/user-guide.md` (every label quoted
  from that language's files).
- **Renamed to the specification's names (D-33-01):** `docs/rbac.md` → `docs/permissions.md`,
  `docs/calendar-api.md` → `docs/calendar.md`, `docs/packages.md` → `docs/installing-modules.md`
  (stubs at the old paths; every reference updated).
- **Completed or corrected:** `README.md` (requirements, every document linked),
  `docs/creating-a-module.md` (News step by step: every manifest key, resources, routes, menus,
  settings, widgets, tasks, notifications, calendars, assets, tests; the checklist; Claude Code and
  the cloud environment; a module in its own repository; ZIP packaging and minisign signing),
  `docs/permissions.md` (resources, the `'name'` override, wildcards as Shield matches them,
  implied permissions, personal screens, own records, hidden fields, the matrix),
  `docs/installing-modules.md` (three ways, signatures, cron line, upgrading, what happens to the
  data), `docs/themes.md` (layouts, regions, menu locations, overrides, child themes, PWA),
  `docs/frontend.md` (tutorial "A new view with JavaScript" on the Tasks board),
  `docs/widgets.md` (tutorial "A new widget" on `tasks.pending` and `tasks.activity`),
  `docs/scheduler.md`, `docs/notifications.md`, `docs/api.md` (conventions, the real `api/v1`
  routes, the documentation page and the worked example with a personal token and `curl`),
  `docs/calendar.md`, `docs/files.md`, `docs/backup-restore.md` (keys, disaster recovery
  checklist), `docs/updates.md` (release process, rolling back), `docs/security.md` (threat model,
  authentication, `app.maskKey` fallbacks, headers and CSP with examples, writable folders,
  production checklist with every check id, incident response), `docs/outbound-requests.md`,
  `docs/commands.md` (every command checked against its class; needs and exit codes; examples),
  `docs/web-server.md` (permissions recipe).

## How it was built

- `tests/unit/Docs/DocumentationTest.php` (8 tests): every listed document exists; every
  `docs/….md` named in code spans exists (or is a library document under `vendor/siensis/*/`);
  every relative link and `#anchor` (GitHub slugs) resolves in `README.md`, `CHANGELOG.md`,
  `CLAUDE.md`, `docs/**`, `.claude/commands/*.md` and `modules/*/{CLAUDE,README}.md`;
  `docs/commands.md` lists exactly the commands registered by `App\`/`Modules\` classes and only
  options they declare; every `php spark <command> --option` written in the docs exists; every
  `backend/…` and `api/v1/…` URL named in the docs is a route (or a prefix of routes; placeholders
  `<id>`, `{id}`, `(:num)`, `…`, `[…]`). The historical records (`decisions`, `plan`, `progress`,
  `specification`) are checked for links only.
- Code fixes found while documenting (each with a test):
  - **D-33-03:** the manifest validator accepted camel-case `extra` operations (`markRead`) whose
    permission was then refused to everyone; now only `^[a-z][a-z0-9]*$`
    (`ManifestValidatorTest`).
  - Security header overrides in `.env` use CodeIgniter's lower-case prefix
    (`securityheaders.frameOptions`); the config comment, the `hsts` check's fix text (ca/es/en)
    and the docs said `securityHeaders.…`, which is ignored (`SecurityDefaultsTest`).
  - The web server recipe now gives `writable/` the modes `installation.writableModes` expects
    (`0750`/`0640`, owned by the PHP user).
  - `deploy-status/31-i18n/README.md:88` no longer carries an example password that
    `secrets.trackedFiles` reports (known issue of sessions 31–32).
  - Comments: `NotificationType` example (`news-read.view`), `Config\Filters`, `Config\Packages`
    (`COPYING`, `.gitkeep`), `themes/tasks-demo/Theme.php` (Settings › Theme),
    `modules/Tasks/CLAUDE.md` (`backend/api/docs`).

## Decisions and deviations

- D-33-01 (accepted): documents renamed to the specification's names; stubs at the old paths.
- D-33-02 (accepted): the JavaScript and widget tutorials describe the Tasks code as built and say
  where the specification differs (bare specifiers instead of relative imports — `CLAUDE.md` wins;
  no `confirm`/`toast` imports, JSON block, `data-i18n` block or `head` section on the board; texts
  and the stylesheet through `data-options`; the activity chart drawn on the server by
  `core/ui/widget`, not refreshed; no JS unit tests). Session 34 may extend Tasks where a tutorial
  needs it; a `head` section in the layouts would need the owner.
- D-33-03 (accepted): extra operation names in lower case only.

## How to try it

```bash
vendor/bin/phpunit tests/unit/Docs/DocumentationTest.php --testdox
php spark list --simple      # compare with docs/commands.md
```

Read `README.md` → "Documentation" for the index.

## Tests and checks

```
Documentation (Tests\Unit\Docs\Documentation)
 ✔ Every listed document exists
 ✔ Every code mention of a document points at an existing file
 ✔ Relative links resolve
 ✔ The commands reference lists exactly the skeletons commands
 ✔ The commands reference gives only real options
 ✔ Every spark command written in the docs exists
 ✔ Every back office and api url in the docs is a route
 ✔ The route matcher of this test works
OK (8 tests, 87 assertions)
```

Full suite (`vendor/bin/phpunit`): `OK (1288 tests, 17228 assertions)` in 11:23; after the last
code fixes, the affected tests again (`--filter 'ManifestValidator|SecurityDefaults|Documentation|LanguageKeys|SecurityCheck|ModuleGenerator|CrudScaffold|Packages|ModulesScreen|CalendarApi|SecurityHeaders'`):
`OK (128 tests, 10109 assertions)`.

PHPStan (level 6): `[OK] No errors`. php-cs-fixer: `Found 0 of 994 files that can be fixed`.
`php spark csp:check`: no inline scripts, handlers or styles.

## Screenshots

This session adds no screens (documents and a test), so there are no screenshots; the console
output of the checks is above.

## Known issues

- Found while documenting, not fixed (outside this session's scope; recorded for later sessions):
  - `CoreCache` says `CacheInvalidator` is the only invalidator, but `ModuleState` and
    `DeclarationRegistry` call `CoreCache::invalidate()` directly (no `modules.cacheInvalidate`
    event from the latter); the `assets` cache scope is invalidated but never written.
  - The menu cache key is the user's groups plus their individual permissions (not only the group
    set `CLAUDE.md` mentions); correct, but the wording differs.
  - `defaultGrants` are applied once per module: permissions added by an upgrade are not granted
    (documented in `installing-modules.md`).
  - A module's `$outboundHosts` are not shown before installing or enabling it (only in the
    `outbound.modules` check).
  - No console command resets 2FA or ends every session at once (incident response documents the
    manual steps).
  - The Catalan and Spanish language files mix forms of address (tu/vós; tú/usted/vosotros) in
    Auth, Tasks and Menus; the user guides quote the labels as they are (session 35 could
    harmonise them).
  - No task empties the Files trash automatically (known since session 22).
  - `docs/tutorials/` does not exist yet (session 34); `modules/Tasks` points at it.
- Composer still needs the known issue 04 workaround (phpstan's zip seeded into Composer's cache
  from a shallow clone of tag 2.3.5, then `env -u COMPOSER_AUTH composer install --prefer-source`;
  this time it needed the owner's approval in the session).

## Notes for the next session

- Session 34 (tutorials): the frontend and widget tutorials already walk through the Tasks board
  and widgets; see D-33-02 for the gaps between the specification's tutorial text and the Tasks
  code. Link the new tutorials from `README.md` and `docs/creating-a-module.md`;
  `DocumentationTest` checks `docs/tutorials/*.md` links, commands and URLs as soon as they exist.
- Every new command needs a row in `docs/commands.md` (first code span = the command name), or
  `DocumentationTest` fails.
