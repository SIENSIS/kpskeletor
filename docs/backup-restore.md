# Backups and restores

The Backup module (`modules/Backup`) makes encrypted backups of the whole application and restores
them, from the console and from the back office, with the same service (specification "Backup";
decisions D-00-45 … D-00-47, D-27-01 … D-27-09).

## What a backup contains

A backup is one file in `writable/backups` (outside `public/`, never served, `0640`), named
`backup-<YYYYmmdd>-<HHMMSS>-<kind>-<8 hex>.kpbackup` (kind `full`, `db`, `files`, `safety` or
`upload`). Inside the encryption it is a ZIP with:

| Entry | Contents | Types |
|---|---|---|
| `manifest.json` | format, Core and module versions, date, type, origin, demo data flag, driver, contents, rows per table, the paths modules declare in `$backup`, and the SHA-256 of every other entry | all |
| `database/<table>.jsonl` | every table with the configured prefix (prefix removed): a line with the columns, then one JSON array per row (non-UTF-8 strings as `{"b64": …}`), read with the Query Builder in chunks | `full`, `db` |
| `files/<disk>/…` | every Storage disk of `Config\Storage` (`public` = `public/uploads`, `private` = `writable/storage`) | `full`, `files` |
| `files/writable-uploads/…` | `writable/uploads`, only when it is not empty | `full`, `files` |
| `env/.env` | the `.env` with its secrets (`encryption.key`, `app.maskKey`, `backup.password`, mail…) | all |

The top-level `index.html`, `.htaccess` and `web.config` of a storage folder belong to the
installation: they are neither backed up nor replaced. There is no `mysqldump` output: the dump is
portable between SQLite and MariaDB and between table prefixes (D-27-03).

## Encryption and passwords

Because a backup holds every secret, **a password is mandatory** (at least 12 characters) and it
is never stored. The file format (`Modules\Backup\Services\ArchiveCrypto`, version 1):

- key: Argon2id (`sodium_crypto_pwhash`) with a random 16-byte salt; the cost (opslimit,
  memlimit) is stored in the header and capped when reading (`Config\Backup::$maxOpsLimit`,
  `$maxMemLimit`) so a crafted file cannot exhaust the server;
- data: libsodium `crypto_secretstream_xchacha20poly1305` in 1 MiB chunks, each authenticated,
  with the whole header as additional data and a FINAL tag on the last chunk;
- a wrong password fails on the first chunk; a changed byte, a changed header, a truncated file or
  bytes after the end are refused; nothing is restored before the whole file is authenticated and
  every checksum of the manifest matches.

Scheduled and non-interactive backups read the password from `backup.password` in `.env` or the
`BACKUP_PASSWORD` environment variable. On the command line `--password` takes **no value**: it
asks with a hidden prompt (a value would stay in the shell history and the process list).

Keep the password in a password manager. Without it the backup cannot be restored; with the file
and the password anyone gets every secret of the site.

## Console

```bash
php spark backup:create [--only=db|files] [--password]
php spark backup:list
php spark backup:verify <file> [--password]
php spark backup:restore <file> [--only=db|files] [--with-env] [--password] [--yes]
php spark backup:prune
```

`backup:restore` asks to type the file name (or `--yes`), restores, and then, when the backup's
secrets differ from the current `.env`, asks before merging them; `--with-env` replaces the whole
`.env` with the backup's (only from the console). The console is the recommended way for large
sites.

## Back office

`Settings › Backups` (`backend/backups`):

| Action | Permission | Notes |
|---|---|---|
| list, detail | `backup.list`, `backup.show` | the folder is reconciled with the index: files copied by hand appear as "Found in the folder" |
| create now | `backup.create` | type and password (typed twice) |
| verify | `backup.list` | decrypts in a private temporary folder and checks every checksum |
| download | `backup.download` + re-authentication | security log entry |
| delete | `backup.delete` | the file too |
| upload a backup file | `backup.restore` + re-authentication | only files with a backup header; stored with a generated name (D-27-05) |
| restore | `backup.restore` + re-authentication | password, parts, the file name typed as confirmation |

Default grants: administrators get `backup.list`, `backup.show` and `backup.create`; download,
delete and restore stay with the superadmin (D-27-01).

A restore from the back office first checks the password (key derivation and the first chunk),
then runs as a job after the response is sent (FastCGI/LiteSpeed; inside the request otherwise).
The page shows its progress from `progress/backup/<job>` (status, step and percentage only; the job
id is 128 random bits) while every other page answers 503 (maintenance mode). At the end the page
reloads with the result. **The back office never writes `.env`:** when the secrets differ it shows
the merged content once, to copy into `.env` by hand (D-27-06). Until then, data encrypted with the
backup's key (authenticator secrets…) and masked ids do not match.

## Restore steps

1. decrypt, authenticate, check the checksums (nothing touched);
2. compatibility: a newer Core or module version, a module that is not installed, or a migration
   the installed code does not have is refused;
3. a **safety backup** of the current site (`full`, same password, kind `safety`);
4. **maintenance mode** on (`writable/maintenance.json`; only `progress/backup/*` stays reachable;
   `schedule:run` skips), activity log paused;
5. database: drop the tables, run the backup's own migrations in order, load the rows; files:
   replace each storage folder's contents (server code and server configuration are never written
   into a public disk);
6. `modules:sync` (migrations added since the backup, permissions, declarations, assets);
7. maintenance mode off; the restore is logged.

If a step after 4 fails, maintenance mode **stays on** and the error names the safety backup:
restore it from the console (`php spark backup:restore <safety file> --password`).

Users stay signed in when their account exists in the restored database; their session list
starts again.

## Scheduled backups and retention

| Setting | Default | Meaning |
|---|---|---|
| `Backup.scheduled` | off | a backup every night (02:41, task `ScheduledBackup`), encrypted with `backup.password` |
| `Backup.scheduledType` | `full` | `full` or `db` |
| `Backup.keepDaily` / `keepWeekly` / `keepMonthly` | 7 / 4 / 6 | the newest backup of each of the last N days, ISO weeks and months is kept; the others are deleted every night (`PruneBackups`, 04:13) or with `backup:prune`; 0 in all three keeps everything; files younger than an hour are never deleted |

The holders of `backup.list` get a notification (`BackupResult`) with the result of each scheduled
backup. `Config\Backup::$copyDisk` copies every new backup to another, non-public Storage disk.

The dashboard widget **Backups** (`backup.backups`, `backup.list`) shows the last backup, its
result and size, and the next scheduled run.

## Moving a site to a new server

1. Install the same (or a newer) version on the new server (`php spark app:install`).
2. Copy the backup file into `writable/backups` (or upload it in the back office).
3. `php spark backup:restore <file> --password` and accept merging the secrets (the new server's
   database credentials and base URL stay).

## Activity log

`Backup.activity.*`: `create`, `create_failed`, `verify`, `upload`, `restore`, `prune`; security
entries `verify_failed`, `download`, `upload_refused`, `restore_refused`, `restore_failed`.

## Files

`modules/Backup/Services/`: `ArchiveCrypto` (format), `BackupService` (create, open, verify),
`DatabaseDumper` (dump and restore), `RestoreService`, `RestoreJob` + `JobStore` (back office),
`Retention`, `BackupRepository` (files and the `backups` table), `EnvFile`, `BackupPassword`,
`ConsolePassword`. Core: `Modules\Core\Archive\SafeZip` (zip-slip and zip-bomb checks, shared with
module packages) and `Modules\Core\Maintenance\MaintenanceMode`.

## Backups before module changes

Installing, upgrading or uninstalling a module or theme (`modules:install`, `modules:uninstall`,
`themes:*`, staged operations) asks for a full backup through the Core event `packageBackup`;
this module answers it (origin `package`, password from `backup.password`/`BACKUP_PASSWORD` or
`--password`). See `docs/installing-modules.md`.
