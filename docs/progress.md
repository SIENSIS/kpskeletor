# Progress

Updated by every session (see `deploy/`). Status values: *in progress*, *done* (report written,
pull request open), *merged* (in `main`).

## Status

| Session | Title | Status | Branch | Pull request | Report |
|---|---|---|---|---|---|
| 00 | Bootstrap | merged | `claude/modest-goldberg-jjs8kp` | [SIENSIS/kpskeletor#1](https://github.com/SIENSIS/kpskeletor/pull/1) | [`deploy-status/00-bootstrap/`](../deploy-status/00-bootstrap/README.md) |
| 01 | Core kernel: modules, manifest and sync | merged | `claude/modest-goldberg-jjs8kp` | [SIENSIS/kpskeletor#4](https://github.com/SIENSIS/kpskeletor/pull/4) | [`deploy-status/01-core-modules/`](../deploy-status/01-core-modules/README.md) |

## Decisions

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

- **01:** none; session 01 decisions D-01-01 … D-01-05 (accepted proposals) are in `docs/decisions.md`.

- **00:** branch `claude/modest-goldberg-jjs8kp` (assigned by the environment) instead of
  `session-00-bootstrap`. Later sessions: find this session's branch through this table.

## Known issues

- Cloud egress proxy refuses `api.github.com` zip downloads; Composer uses git sources
  (`deploy/setup.sh` retries with `--prefer-source`). `phpstan/phpstan` (dist only) needed its
  zip seeded into Composer's cache from a git checkout.
- The 56 open questions from `docs/plan.md` are decided and applied (`docs/decisions.md`).

## Notes for the next session

- Session 02: extend `ModuleSynchronizer::sync()` with steps 4–7 (catalogue, grants, settings,
  widgets/tasks, demo data) after the registry upsert, and keep `ModulesSyncTest`'s
  "second sync changes nothing" assertion true. Use `ModuleManifest::permissionPrefix()`.
- `bash deploy/setup.sh` starts MariaDB in a new container; `.env` must be recreated from `env`.
- Earlier notes (session 00): read `docs/decisions.md` before starting.
