# Updates

The Updates module **shows, never applies**: the back office tells administrators that a new
version exists (dashboard widget, a notification, the page `backend/updates` with the release
notes); updating is done **from the console** with `php spark app:update`, after verifying the
release. Specification: "Modules" › "Updates"; decisions D-00-48, D-28-01 … D-28-06.

## Configuration (`Modules\Updates\Config\Updates`, `.env`)

| Key (`.env`) | Default | Meaning |
|---|---|---|
| `updates.repository` | empty | Where the application's releases are published: `github:owner/repo`, `gitlab:group/project` (gitlab.com) or `https://gitlab.example.com/group/project`. Empty = no check for the application. |
| `updates.token` | empty | Token for a private repository (GitHub: a fine-grained token with *Contents: read*; GitLab: `read_api`). Only in `.env`. |
| `updates.publicKeys` | empty | The minisign public key(s) trusted for release archives (the base64 line of `minisign.pub`, `RW…`), comma separated. **Empty = every archive update is refused.** |
| `updates.prereleases` | `false` | Offer pre-releases (`1.2.0-rc.1`). |
| `updates.method` | `auto` | `auto` (a git checkout updates with git, anything else with the archive), `archive` or `git`. |
| `updates.gitRemote` | `origin` | Remote fetched by the git method. |
| `updates.maxArchiveBytes`, `updates.downloadTimeout` | 100 MB, 300 s | Limits of the archive download (other outbound requests keep `Config\Outbound`'s). |
| `updates.composerBinary`, `updates.gitBinary`, `updates.phpBinary` | `composer`, `git`, PHP_BINARY | Programs run by `app:update`. |

`protectedPaths` (file only) lists what an archive update never writes nor deletes: `.env`,
`.git/`, `writable/`, `vendor/`, `public/uploads/`, `public/assets/modules/`, `node_modules/`.

Modules may declare `public ?string $repository = 'github:acme/news-module';` in their manifest:
their latest release is compared with the manifest's version and shown on the page and the
widget (installing a module's new version is the job of `modules:install`, session 29).

The release hosts must be in the outbound allow-list. The module declares `api.github.com`,
`github.com`, `objects.githubusercontent.com`, `release-assets.githubusercontent.com` and
`gitlab.com`; add a self-hosted GitLab in Settings › Security or `outbound.hosts`.

## Checking

- The scheduled task *Check for updates* (daily, 05:17), the **Check now** button
  (`updates.check`) and `php spark updates:check` read the releases of the repositories through
  the Core's `OutboundClient` (HTTPS, allow-list, logged). Regular pages never make a request.
- The newest release is the highest **semantic version** among the tags (`v1.2.3` or `1.2.3`);
  drafts, upcoming releases, pre-releases (unless `prereleases`) and other tags are ignored.
- The result is stored in `writable/updates/status.json` (0640): the widget and the page read
  it. Network errors are kept in it, shown on the page and the widget, logged by
  `OutboundClient`, and never break the dashboard.
- Each new version is notified once (notification *New version available*) to the holders of
  `updates.view`.

Permissions: `updates.view` (page, widget, notification) and `updates.check` (check now). Default
grant: administrators get both; superadmins always pass.

## Updating: `php spark app:update [--dry-run] [--yes] [--password]`

`--dry-run` prints the plan and the checks and changes nothing. Without it the command asks for
confirmation (`--yes` skips it) and then:

1. **Checks:** a newer release exists; maintenance mode is not already on; **no local changes**
   (git: `git status` of tracked files; archive: every file written by the previous archive
   update must be unchanged, recorded in `writable/updates/files.json`; before the first archive
   update this cannot be checked and the command says so); a public key and a signed archive
   (archive method); a backup password (`backup.password`, `BACKUP_PASSWORD` or `--password`).
2. **Verified before anything is applied:**
   - *archive:* downloads `<name>.zip`, `<name>.zip.sha256` and `<name>.zip.minisig` from the
     release, checks the SHA-256 **and** the Ed25519 signature (`SignatureVerifier`, minisign
     format, both the file signature and the trusted-comment signature) with the keys of
     `updates.publicKeys`, then extracts it with `SafeZip` (zip-slip and zip-bomb checks) into
     `writable/updates/staging-*`. The archive holds one top-level folder with the application.
   - *git:* `git fetch --tags <remote>` and `git verify-tag <tag>` (the signer's GPG/SSH key must
     be trusted by git on the server).
   A release that does not verify is refused (security log `release_refused`) and nothing changes.
3. **Maintenance mode** on (`MaintenanceMode`, reason `updates.update`, no superadmin bypass).
4. **A full backup** with the Backup module (`BackupService::create()`, origin `update`).
5. **The new code:** archive → the files are copied over the installation except the protected
   paths; replaced and removed files are kept in `writable/updates/previous/<old version>/`;
   git → `git merge --ff-only refs/tags/<tag>`.
6. `composer install --no-dev --optimize-autoloader`, `php spark modules:sync` and
   `php spark app:cache-clear`, each in a new process (they load the new code).
7. Maintenance mode off; activity entry `updated`.

**On failure** after step 3 the command stops, **keeps maintenance mode on** (security entry
`update_failed`) and prints the backup file and the folder with the replaced files. Fix the
cause and run `php spark app:update` again (it refuses while maintenance mode is on: turn it off
first with `php spark app:maintenance off`), or restore: `php spark backup:restore <file>`, copy
the files back from `writable/updates/previous/<version>/` (or `git checkout` the old tag), then
`php spark app:maintenance off`.

`git`, `composer` and `php` are the documented exceptions to "every outbound request goes
through `OutboundClient`" (docs/outbound-requests.md): they run only from the console.

## Signing releases (the owner)

The key pair is generated **on the owner's computer** with
[minisign](https://jedisct1.github.io/minisign/); the secret key never enters the repository, a
server or CI.

```bash
# Once: creates minisign.key (secret, password protected) and minisign.pub
minisign -G -p minisign.pub -s ~/.minisign/kpskeletor.key

# Every release: build the archive (one top-level folder), its checksum and its signature
git archive --format=zip --prefix=kpskeletor-1.2.0/ -o kpskeletor-1.2.0.zip v1.2.0
sha256sum kpskeletor-1.2.0.zip > kpskeletor-1.2.0.zip.sha256
minisign -S -s ~/.minisign/kpskeletor.key -m kpskeletor-1.2.0.zip -t "kpskeletor 1.2.0"
# Upload the three files to the release v1.2.0
```

Publish the second line of `minisign.pub` (it starts with `RW`) in the installations'
`.env` (`updates.publicKeys = RW…`). For git installs, sign the tag too: `git tag -s v1.2.0`.
Session 36 documents the release procedure step by step.

**Key rotation:** generate the new pair, set `updates.publicKeys = RW<old>,RW<new>` everywhere,
sign the next releases with the new key, and remove the old key once every installation has
updated. A lost or leaked secret key: remove its public key from every installation at once
(updates are refused until a release signed with a new key is trusted).

## Maintenance mode by hand

Settings › Actions › *Maintenance mode* (`settings.security` + re-authentication) or
`php spark app:maintenance on|off|status [--message="…"]` turns it on by hand with an optional
plain-text message: visitors and users get the 503 page, the login paths stay open and signed-in
superadmins keep using the site with a banner. A mode turned on by a restore or an update has
no bypass and is not turned off from the screen (`app:maintenance off` from the console).
See docs/security.md, "Maintenance mode".

## Files

`modules/Updates/`: `Config/{Module,Routes,Updates,Services}.php`,
`Services/{VersionComparator,Release,ReleaseSource,ReleaseChecker,UpdateStatus,SignatureVerifier,ReleaseNotes,Updater,CommandRunner,ProcessRunner,UpdateException}.php`,
`Commands/{UpdatesCheck,AppUpdate}.php`, `Tasks/CheckUpdates.php`, `Widgets/SystemVersion.php`,
`Notifications/NewVersion.php`, `Controllers/Updates.php`, `Views/{index,widget}.php`. Tests in
`modules/Updates/Tests` (a fake repository served through the real `OutboundClient`, a test
minisign signer, a fake command runner).
