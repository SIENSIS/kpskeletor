# Session 43 — Devtools: module packages and signing

> Run it by saying: **`executa deploy/43-devtools-packages.md`** (or `run deploy/43-devtools-packages.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (building and signing module packages from the Devtools module; decision D-43-01).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 43
   whose branch is not yet contained in `main` (`git branch -r --no-merged origin/main`):
   - check it is finished: its `docs/progress.md` marks the session "done" and its
     `deploy-status/NN-*/README.md` exists; if not, stop and tell me to run
     `deploy/continue.md NN` first;
   - `git merge --no-ff origin/<its branch> -m "Merge session NN: <title>"`; resolve trivial
     conflicts (e.g. `docs/progress.md`); if a conflict touches code, stop and tell me;
   - run the checks (`composer install`, `vendor/bin/phpunit`, PHPStan, php-cs-fixer); if they fail,
     fix them on `main` in a separate commit or stop and tell me;
   - in `docs/progress.md` change that session's status to **merged**;
   - `git push origin main`, never with `--force`; if pushing to `main` is refused, stop and tell
     me to merge the pull request "Session NN: …" on GitHub, then run this file again.
3. Check that session 42 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-43-devtools-packages`. Record the branch name for session 43 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "43 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "The module contract" (its "Modules screen (back office)" and "Installing and uninstalling modules and themes"), "Security baseline (must be on by default)".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

Build the installable ZIP package of a module (or theme) from the Devtools screen and from the
console, validated exactly as the installer will validate it, with its SHA-256 and, optionally,
its minisign signature — signed from the console or from the local form (D-43-01).

Read D-41-01 … D-41-03, D-42-01 and D-43-01 in `docs/decisions.md` first: they define what
this module may do and the locks it must keep.

## Scope

- **Command** `php spark modules:package <Name> [--theme] [--out=<dir>] [--sign=<secret key file>]`
  (development only, like the rest of Devtools; password asked without echo): reads the version
  from the manifest, refuses local changes not committed if the module is in git (warning only),
  builds `<Name>-<version>.zip` with **one top folder**, leaving out dotfiles, `node_modules`,
  `writable`, editor files and whatever `packages.extensions` would refuse; validates it with the
  Core's `PackageValidator` (the installer's own checks: manifest read without running it, sizes,
  zip-slip, links, extensions) and stops on any error; writes `<file>.sha256` (`sha256sum`
  format) and, with `--sign`, `<file>.minisig`.
- **Screen** `backend/devtools/packages` (`devtools.view`; building and signing need
  `devtools.write` + `reauth`): the modules and themes with their version, a "Build package"
  button, the validation result, download links for the three files (served by a controller from
  `writable/devtools/packages/`, never from `public/`), and the minisign command for signing on
  another machine. Optionally "raise the version" (patch/minor/major in the manifest and a new
  section in the module's `CHANGELOG.md`) before building.
- **Signing (minisign-compatible, Ed25519, prehashed BLAKE2b, trusted comment
  "`<Name> <version>`"):** implemented with libsodium: parse the minisign secret key file,
  decrypt it with the password (scrypt, `sodium_crypto_pwhash_scryptsalsa208sha256`; also accept
  unencrypted keys), sign, write the `.minisig`, then **verify it with the Core's
  `Modules\Core\Security\Minisign`** against the public key derived from the secret key and show
  the public key line (`RW…`) to put in `packages.publicKeys`. A wrong password is a clear error.
- **Signing from the local form (D-43-01):** the form takes the secret key (a textarea with the
  file's content, or a file upload read in memory) and its password (password field, with session
  38's show/hide button). Mandatory safeguards, each with a test:
  - the key and the password are never written anywhere: not to disk (no temporary file), not to
    the session (no `withInput()`, no flash data), not to logs or the activity log (it records
    only "signed <file> with key id <id>"), and they are wiped from memory after use
    (`sodium_memzero`);
  - **the debug toolbar never stores them**: CodeIgniter's toolbar saves the request variables
    (`Config\Toolbar::$collectVarData`) in `writable/debugbar/`; the signing request must not be
    collected (disable the toolbar for that route or filter the fields) — a test posts a key and
    checks that no file under `writable/` contains it;
  - the development error page must not show them if signing fails (catch every exception in the
    controller and answer with a generic error);
  - `autocomplete="off"` on both fields, `Cache-Control: no-store` on the response, the Devtools
    locks (development, loopback, `devtools.write`, `reauth`, rate limit).
- Themes: the same for a theme folder (`themes:install` package format).

## Tests

- The package of the `Tasks` module (and of a generated module) validates with `PackageValidator`
  and installs with `modules:install` in a test checkout; a package with a forbidden file is not
  built.
- Signing: a minisign secret key made in the test (encrypted and unencrypted) signs a package that
  `Minisign` verifies; interoperability with the format (compare with a signature made by the
  `minisign` CLI if available, otherwise with known test vectors); wrong password refused.
- The safeguards above (no trace of the key or the password under `writable/`, in the session, in
  logs or in the activity log; toolbar; error page); all Devtools locks still apply.
- Screenshots of the packages screen and the signing form.

## Docs

`docs/devtools.md` (building and signing packages), `docs/installing-modules.md` (publishing a
package: where to put the `.minisig`, the public key for `packages.publicKeys`),
`docs/commands.md` (`modules:package`), tutorial 13 (the package step can use the new command),
`CHANGELOG.md` `[Unreleased]`.

## Done when

- A module can be packaged, validated and signed from the console and from the local screen, the
  package installs on another site that trusts the key, and the key never leaves memory.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-43-01`, `D-43-02`…, with where, problem, proposal and alternatives; format in
  `deploy/decide.md`):
  - if it changes a public convention (permission names, manifest keys, URLs, settings names) or
    security, mark it **(decide)**, leave it **Open** and, if possible, build this session so the
    decision can still be changed (or stop if you cannot continue without it);
  - otherwise apply the most secure and simplest option consistent with the specification and
    record it as **Accepted (proposal)**.
- Texts in `ca`, `es` and `en` from the start; no inline JavaScript; tests for every rule you add.
- The skeleton is released (1.0.0): note every change under `[Unreleased]` in `CHANGELOG.md`
  (Added, Changed, Fixed); do not raise `Version::CORE` (the owner does it when releasing,
  `docs/updates.md`).

## Finish

1. Run the checks and show the results: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`,
   `vendor/bin/php-cs-fixer fix --dry-run --diff`, `php spark modules:sync`,
   `php spark permissions:check`, `php spark csp:check`, `php spark security:check` in development. Fix everything that fails.
2. **Status report** in `deploy-status/43-devtools-packages/` (see `deploy-status/README.md` for the format):
   - `README.md`: what was built, **how** (design, main classes and files, tables, routes,
     permissions, commands), decisions and deviations, how to try it (commands and URLs with the
     demo data), test results (counts and the checks' output summary), known issues and what the
     next session must know;
   - `screenshots/`: screenshots of what this session added that has a screen (back office,
     public site, phone width and dark mode when relevant), taken with Playwright against
     `php spark serve` with demo data, at device scale factor 2, PNG, named
     `01-<what>.png`, `02-…`, each one listed in the README with a caption. If the session has no
     screen, include the console output in the README instead; if screenshots are impossible in
     this environment, say why in the README.
3. Update `docs/progress.md`: status of session 43 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/43-devtools-packages/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 43: Devtools: module packages and signing" with a summary, the checks' output and a link to the status report.
   No session follows this one: merge this branch into `main` yourself with the procedure of
   "Before starting" (checks, `docs/progress.md` → merged, push, never `--force`; if `main`
   cannot be pushed, stop and ask me to merge the pull request on GitHub).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "the planned sessions are complete".
