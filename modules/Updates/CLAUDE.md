# Updates module

**Purpose:** update notices in the back office (widget, notification, page with the release
notes) and verified updates from the console (`php spark app:update`). Shows, never applies.
Full description: `docs/updates.md`.

## Resources and permissions

- `updates` (single resource, `name => 'updates'`, type page): `updates.view` (page, widget,
  notification) and the extra `updates.check` ("Check now", rate limited). Default grant: admin
  both (D-28-01, open).
- URLs: `backend/updates` (GET), `backend/updates/check` (POST).
- Menu: "Updates" in the `settings` zone (order 87).
- No tables, settings, demo data, assets or API. Outbound hosts: GitHub (API, downloads and the
  hosts downloads redirect to) and gitlab.com.
- Task `CheckUpdates` (daily 05:17), notification `NewVersion` (once per version, to
  `updates.view`), widget `updates.version`, service `updatechecker`.

## Main pieces

- `Config/Updates.php`: repository, token, minisign public keys, method (`auto`/`archive`/`git`),
  limits, protected paths, paths and binaries; overridable from `.env` (`updates.*`).
- `Services/ReleaseSource.php` (GitHub/GitLab releases through `OutboundClient`),
  `VersionComparator.php` (semver precedence), `ReleaseChecker.php` + `UpdateStatus.php`
  (`writable/updates/status.json`), `SignatureVerifier.php` (SHA-256 + minisign Ed25519 through the Core's `Modules\Core\Security\Minisign`, session 35),
  `ReleaseNotes.php` (escaped Markdown subset + HTMLPurifier), `Updater.php` (the update
  procedure), `ProcessRunner.php` (`proc_open` with an argument list).
- Core pieces used: `OutboundClient`, `MaintenanceMode`, `SafeZip`; Backup's `BackupService`.

## Decisions

D-00-48 (Ed25519, owner's key), D-28-01 … D-28-07 (`docs/decisions.md`).

## Open tasks

- Done in session 29: `Config/Events.php` answers the Core's `moduleVersions` event (latest versions from the last check) for the Modules screen. (`docs/installing-modules.md`)
- Session 29: module packages (`modules:install`) may reuse `SignatureVerifier`, `ReleaseSource`
  for modules with `$repository`.
- Done in session 30: `Services/UpdatesSecurityCheck.php` (event `securityChecks`): warning when
  `updates.repository` is set without `updates.publicKeys`; test `Tests/UpdatesSecurityCheckTest.php`.
- Session 36: the release procedure (archive, `.sha256`, `.minisig`, signed tag).

## How to test

`vendor/bin/phpunit modules/Updates` (base `Tests/UpdatesTestCase.php`: a private
`writable/updates` and installation folder, a fake GitHub served through the real
`OutboundClient` with `FakeTransport`, `TestSigner` makes minisign signatures with a key pair per
test, `FakeRunner` records git/composer/spark). By hand: set `updates.repository` (and
`updates.publicKeys`), `php spark updates:check`, `backend/updates`, `php spark app:update --dry-run`.
