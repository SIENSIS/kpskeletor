# Progress

Updated by every session (see `deploy/`). Status values: *in progress*, *done* (report written,
pull request open), *merged* (in `main`).

## Status

| Session | Title | Status | Branch | Pull request | Report |
|---|---|---|---|---|---|
| 00 | Bootstrap | merged | `claude/modest-goldberg-jjs8kp` | [SIENSIS/kpskeletor#1](https://github.com/SIENSIS/kpskeletor/pull/1) | [`deploy-status/00-bootstrap/`](../deploy-status/00-bootstrap/README.md) |
| 01 | Core kernel: modules, manifest and sync | merged | `claude/modest-goldberg-jjs8kp` | [SIENSIS/kpskeletor#4](https://github.com/SIENSIS/kpskeletor/pull/4) | [`deploy-status/01-core-modules/`](../deploy-status/01-core-modules/README.md) |
| 02 | Core kernel: catalogue, settings registry, enable/disable and demo data | merged | `claude/clever-lovelace-i36pbh` | [SIENSIS/kpskeletor#5](https://github.com/SIENSIS/kpskeletor/pull/5) | [`deploy-status/02-core-registry/`](../deploy-status/02-core-registry/README.md) |
| 03 | Themes and standard views | done | `claude/tender-ride-z9y4vv` | [SIENSIS/kpskeletor#6](https://github.com/SIENSIS/kpskeletor/pull/6) | [`deploy-status/03-themes/`](../deploy-status/03-themes/README.md) |

## Decisions

- **03:** theme slug ↔ `Themes\<Studly>` namespace registered on demand; theme assets published by `modules:sync`/`make:theme` until `assets:publish`; error pages in the `blank` layout and themed/JSON 403 from `can:`; manifest `$styles`/`$scripts`; `Core.app.name` until site settings; `BackendController::render()` adds layout/breadcrumbs/page header; `--name=value` options (D-03-01, 03, 04, 06–09). D-03-02 (theme option storage) and D-03-05 (theme preview URL) decided by the owner as proposed and applied.

- **02:** one `module_declarations` table for every registry; manifest defaults through a read-only Settings handler; disabled modules answer 404 through the global `moduleenabled` filter; `modules.grants_applied` flag; Core resource `modules`; `DemoSeeder` base class and y/n confirmations; cache scopes with version keys; application log until the activity log exists (D-02-01 … D-02-08).

- **01:** module namespaces generated in `app/Config/ModuleNamespaces.php`; default-deny `can:` filter before RBAC; `make:module` creates an example migration; missing module folders are reported, not deleted; Core version 0.1.0 (D-01-01 … D-01-05).

- **Decisions 00:** the 56 open questions of `docs/plan.md` were answered with `deploy/decide.md` (9 decided by the owner, 47 accepted proposals) and applied to the specification, the session files, `CLAUDE.md` and the code; see `docs/decisions.md`. Bootstrap §3 (specification and `CLAUDE.md`) is done; `prompt_new.md`/`claude_suggest.md` no longer exist.

- **00:** PHPUnit 11.5 with `config.platform.php = 8.2.0` so one lock file installs on PHP
  8.2–8.4.
- **00:** tests on SQLite in memory by default; MariaDB selected with `database.tests.*`
  environment variables (CI job on PHP 8.3 + MariaDB 11.4).
- **00:** Playwright pinned to 1.63.0 in `package.json` (matches the cloud image's Chromium).
- **00:** Shield prerequisites set early (`csrfProtection = 'session'`, `auth`/`setting` helpers)
  so the app boots in development with the debug toolbar.

## Deviations from the specification

- **03:** site name from the language line `Core.app.name` until session 14 (D-03-07); `Themes\` removed from `composer.json` autoload (themes register their namespace on demand, D-03-01); fixed the session 02 gap where `--demo=A,B` was ignored (D-03-09); `.gitignore` `vendor/` anchored so `public/assets/vendor/` is committed.

- **02:** enable/disable and demo-data actions are written to the application log, not the activity log (session 12, D-02-08); `modules:enable` does not publish assets yet (session 04); closure routes of a disabled module are not hidden (modules use controllers, D-02-03).

- **01:** none; session 01 decisions D-01-01 … D-01-05 (accepted proposals) are in `docs/decisions.md`.

- **00:** branch `claude/modest-goldberg-jjs8kp` (assigned by the environment) instead of
  `session-00-bootstrap`. Later sessions: find this session's branch through this table.

## Known issues

- **03:** `CanFilterTest::testGuestIsForbidden` fails when run alone (needs the settings table migrated by an earlier test); passes in the full suite.
- **02:** `CI_ENVIRONMENT` in `.env` wins over a shell variable of the same name (CodeIgniter reads `$_ENV` first).
- Cloud egress proxy refuses `api.github.com` zip downloads; Composer uses git sources
  (`deploy/setup.sh` retries with `--prefer-source`). `phpstan/phpstan` (dist only) needed its
  zip seeded into Composer's cache from a git checkout.
- The 56 open questions from `docs/plan.md` are decided and applied (`docs/decisions.md`).

## Notes for the next session

- Session 04: fold `ThemeAssetPublisher` into `assets:publish`; nonces on `partials/scripts.php`; allow the one nonce'd `<style>` of `partials/head.php`; dark values of theme options, theme switch, remember the sidebar and the cookie notice. Session 08: fill the backend `sidebar` region and the menu locations. Session 09: `setLayout(theme_layout('backend'), 'content')`. Session 14: Settings UI for `Theme.*` and theme options (`docs/themes.md`). See `deploy-status/03-themes/README.md`.
- Session 03+: read registries through `DeclarationRegistry` (disabled modules already hidden), cache with `CoreCache` scopes and invalidate only through `CacheInvalidator`. Session 04: add `assets:publish` to `modules:sync` (step 7) and `ModuleToggler::enable()`. Session 07: grants are in `group_permissions` (applied once per module); write Shield's `AuthGroups` from them. Session 12: replace the `log_message()` calls in `ModuleToggler` and `DemoDataManager` with activity log entries. Session 14: forms from `SettingsRegistry::forModule()`, values cast with `SettingCaster`.
- `bash deploy/setup.sh` starts MariaDB in a new container; `.env` must be recreated from `env`.
- Earlier notes (session 00): read `docs/decisions.md` before starting.
