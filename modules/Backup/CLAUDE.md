# Backup module

**Purpose:** encrypted backups of the whole application (database dump, Storage disks,
`writable/uploads`, `.env`) and restores, from the console and the back office, with one service.
Full description: `docs/backup-restore.md`.

## Resources and permissions

- `backup` (single resource, `name => 'backup'`; crud − `add`, `edit`, `export`, `import`, trash):
  `backup.list` (list and verify), `backup.show`, `backup.delete`, extras `backup.create`,
  `backup.download` (+ `reauth`), `backup.restore` (+ `reauth`; also "upload a backup file").
- Default grants: admin `backup.list`, `backup.show`, `backup.create`; the rest only superadmin
  (D-27-01, decided).
- URLs: `backend/backups` (kpcrudy list), `create`, `verify/<id>`, `download/<id>`,
  `recover/<id>` (restore; `restore/<id>` is kpcrudy's trash URL), `upload`, `jobs/<job>`; `progress/backup/<job>` (JSON, no session/database,
  reachable in maintenance mode). Masked ids use the context `backups`.
- Menu: "Backups" in the `settings` zone (order 86).
- Settings: `Backup.scheduled`, `scheduledType`, `keepDaily`, `keepWeekly`, `keepMonthly`.
- Table `backups` (index of the files; reconciled with the folder). Tasks `ScheduledBackup`
  (02:41) and `PruneBackups` (04:13). Notification `BackupResult`. Widget `backup.backups`.
  Asset `backup/restore-progress.js`. No demo data, no API, no outbound hosts, no CSP sources.

## Main pieces

- `Services/ArchiveCrypto.php`: file format v1 (Argon2id + secretstream, header as AD, FINAL tag).
- `Services/BackupService.php`: create (temporary folder 0700 under `writable/backups/tmp`, ZIP,
  encrypt, record, log), open (decrypt, `SafeZip`, manifest checksums), verify, areas.
- `Services/DatabaseDumper.php`: JSON-lines dump; restore = the backup's migrations + rows.
- `Services/RestoreService.php`: checks → safety backup → maintenance → database/files → sync.
- `Services/RestoreJob.php` + `JobStore.php`: back-office restore after the response, progress
  files (0600), merged `.env` shown once.
- `Services/Retention.php`, `BackupRepository.php`, `EnvFile.php`, `BackupPassword.php`,
  `ConsolePassword.php`.
- Core pieces used: `Modules\Core\Archive\SafeZip`, `Modules\Core\Maintenance\MaintenanceMode`.
- Configuration: `Config/Backup.php` (paths, Argon2id cost and caps, limits, `copyDisk`,
  `jobsAfterResponse`).

## Decisions

D-27-01 (names/URLs/settings/grants), D-27-02 (maintenance mode, `progress/*`) and D-27-05
(uploads outside the Storage service) decided by the owner as built (Decisions 27); D-27-03,
D-27-04, D-27-06 … D-27-09 accepted; D-00-45, D-00-46, D-00-47, D-00-39.

## Open tasks

- Done in session 28: maintenance mode by hand (Settings › Actions, `app:maintenance`) on top of
  `MaintenanceMode`; `app:update` calls `BackupService::create()` (origin `update`) before updating.
- Done in session 29: `Config/Events.php` answers the Core's `packageBackup` event (full backup, origin `package`) before a module or theme is installed, upgraded or uninstalled (`docs/packages.md`).
- Done in session 30: `Services/BackupSecurityCheck.php` (event `securityChecks`, `Config/Events.php`):
  `secrets.backupPassword` (set when scheduled backups are on) and `installation.backups` (folder
  outside `public/`, backup files not readable by others); test `Tests/BackupSecurityCheckTest.php`.

## How to test

`vendor/bin/phpunit modules/Backup` (base `Tests/BackupTestCase.php`: temporary folders for the
backups, both disks, `uploads` and `.env`, fast Argon2id, restores inside the request, `craft()`
builds hand-made backups). `ArchiveCryptoTest` (format), `BackupServiceTest` (create, verify,
restore, refusals), `BackupScreensTest` (permissions, forged POSTs, re-authentication, the job),
`BackupModuleTest` (manifest, migration, retention, tasks, widget, commands). Core:
`SafeZipTest`, `MaintenanceModeTest`. By hand: `BACKUP_PASSWORD=… php spark backup:create`, then
`backend/backups`.
