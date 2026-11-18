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
| `updates.publicKeys` | the owner's key shipped in `Config\Updates` (empty until the owner provides it) | The minisign public key(s) trusted for release archives (the base64 line of `minisign.pub`, `RW…`), comma separated; `.env` replaces the shipped list. **Empty = every archive update is refused.** |
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
     release, checks the SHA-256 **and** the Ed25519 signature (`SignatureVerifier` on top of the Core's
     `Modules\Core\Security\Minisign`, minisign format, both the file signature and the trusted-comment signature) with the keys of
     `updates.publicKeys`, then extracts it with `SafeZip` (zip-slip and zip-bomb checks) into
     `writable/updates/staging-*`. The archive holds one top-level folder with the application.
   - *git:* `git fetch --tags <remote>` and `git verify-tag <tag>` (the signer's GPG/SSH key must
     be trusted by git on the server).
   A release that does not verify is refused (security log `release_refused`) and nothing changes.
3. **Maintenance mode** on (`MaintenanceMode`, reason `updates.update`, no bypass for anybody).
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

### Rolling back

An update can be undone with what `app:update` left behind:

1. `php spark app:maintenance on` (if it is not on already).
2. **Code:** archive method → copy the files back from `writable/updates/previous/<old version>/`
   (the replaced and removed files) and delete the files the release added; git method →
   `git checkout v<old version>` (or `git reset --hard` to the old tag on the branch).
   Then `composer install --no-dev --optimize-autoloader`.
3. **Data:** `php spark backup:restore <file> --password` with the backup made in step 4 of the
   update (origin `update`, listed by `php spark backup:list`). Needed whenever the new version
   ran migrations: the database must go back to the state the old code expects. Restore after
   putting the old code back (the restore checks the backup's versions against the code).
4. `php spark modules:sync`, `php spark app:cache-clear`, `php spark app:maintenance off`.

There is no automatic rollback command: the update is designed so that every step before
applying the code refuses and changes nothing, and the backup is always made first.

`git`, `composer` and `php` are the documented exceptions to "every outbound request goes
through `OutboundClient`" ([`outbound-requests.md`](outbound-requests.md)): they run only from the console.

## Release process (the owner)

Every release of the skeleton follows these steps, from a clean checkout of `main` on the owner's
own computer (never in CI: the signing key does not leave that computer).

1. **Version:** semantic versioning. Raise `Modules\Core\Config\Version::CORE`; when the major
   version changes, raise the bundled modules' `'Core' => '^<major>.0'` too. Move the changes
   from `[Unreleased]` to a new section of `CHANGELOG.md` (Keep a Changelog: Added, Changed,
   Fixed, Security, with anything that needs action: new `.env` keys, PHP version). Merge to
   `main` with every check green (CI, `php spark security:check` on a production-like copy).
2. **Tag:** `git tag -s v1.2.0 -m "kpskeletor 1.2.0"` (a signed tag, needed by git installs) and
   `git push origin v1.2.0`. Only tags that are a semantic version (`v1.2.3` or `1.2.3`,
   optionally `-rc.1`) are offered; pre-release tags only reach installations with
   `updates.prereleases`.
3. **Archive, checksum and signature:** build them from the tag as in "Signing releases" below.
4. **Release notes:** create the GitHub/GitLab release for the tag; its description (Markdown) is
   what the page `backend/updates` shows, as safe HTML (`ReleaseNotes`: escaped, headings, lists,
   bold, code and https links only, then HTMLPurifier). Paste the version's `CHANGELOG.md`
   section. Upload the three files (`.zip`, `.zip.sha256`, `.zip.minisig`). The archive update
   uses the first `.zip` asset that has both companions; a release without them is refused by the
   archive method.
5. **Check** from a test installation that trusts the key: `php spark updates:check`, then
   `php spark app:update --dry-run` (the plan says the signature verifies).

## Signing releases (the owner)

Releases are signed with **Ed25519** in the [minisign](https://jedisct1.github.io/minisign/)
format (`Modules\Core\Security\Minisign` verifies it; D-00-48, D-35-03). The key pair is
generated **on the owner's computer**; the secret key never enters the repository, a server, a
backup of the site or CI, and is protected by a password.

```bash
# Once: creates the secret key (password protected) and minisign.pub
minisign -G -p minisign.pub -s ~/.minisign/kpskeletor.key
# Keep an offline copy of ~/.minisign/kpskeletor.key and its password (e.g. an encrypted USB key).

# Every release (example: 1.2.0), from the signed tag
VERSION=1.2.0
git archive --format=zip --prefix=kpskeletor-$VERSION/ -o kpskeletor-$VERSION.zip v$VERSION
sha256sum kpskeletor-$VERSION.zip > kpskeletor-$VERSION.zip.sha256
minisign -S -s ~/.minisign/kpskeletor.key -m kpskeletor-$VERSION.zip -t "kpskeletor $VERSION"

# Check before uploading: the checksum and the signature with the public key
sha256sum -c kpskeletor-$VERSION.zip.sha256
minisign -V -p minisign.pub -m kpskeletor-$VERSION.zip
# Upload kpskeletor-$VERSION.zip, .zip.sha256 and .zip.minisig to the release v$VERSION
```

The archive has one top-level folder (`--prefix`); `git archive` leaves out what is not tracked
(`.env`, `vendor/`, `writable/` contents), and `app:update` never writes the protected paths.
The checksum file is `<sha256>  <file name>` (the format of `sha256sum`); `app:update` checks
both the SHA-256 and the signature (the file signature and its trusted comment) before anything
changes.

**Where the public key lives.** The public key is the second line of `minisign.pub` (it starts
with `RW`). It ships with the skeleton in `Modules\Updates\Config\Updates::$publicKeys`, so a new
installation trusts the owner's releases without extra steps; an installation can replace the
list with `updates.publicKeys = RW…` in `.env` (for example to trust a fork's key). The secret key
is never needed on a server.

**Key rotation** (planned, e.g. every few years or when the owner changes):

1. Generate the new pair as above.
2. Release a version **signed with the old key** whose `Config\Updates::$publicKeys` lists both
   keys (`'RW<old>,RW<new>'`), and tell administrators who set `updates.publicKeys` in `.env` to
   add the new key there too.
3. Sign the following releases with the new key.
4. Once every installation runs a version with both keys, release one that lists only the new key.

**A lost or leaked secret key:** publish a security notice at once, remove its public key from
`Config\Updates::$publicKeys` in a release signed with the new key, and tell administrators to
set `updates.publicKeys = RW<new>` in `.env` and to install that release by hand (`git pull` or the
archive, after checking its checksum and signature with `minisign -V`): an installation that still
trusts the leaked key only, refuses releases signed with the new one, which is the intended
behaviour. Never reuse a leaked key.

## Maintenance mode by hand

Settings › Actions › *Maintenance mode* (`maintenance.manage` + re-authentication) or
`php spark app:maintenance on|off|status [--message="…"]` turns it on by hand with an optional
plain-text message: visitors and users get the 503 page, the login paths stay open and signed-in
holders of `maintenance.manage` (superadmins always; no default grant) keep using the site with a
banner. A mode turned on by a restore or an update has
no bypass and is not turned off from the screen (`app:maintenance off` from the console).
See [`security.md`](security.md), "Maintenance mode".

## Files

`modules/Updates/`: `Config/{Module,Routes,Updates,Services}.php`,
`Services/{VersionComparator,Release,ReleaseSource,ReleaseChecker,UpdateStatus,SignatureVerifier,ReleaseNotes,Updater,CommandRunner,ProcessRunner,UpdateException}.php`,
`Commands/{UpdatesCheck,AppUpdate}.php`, `Tasks/CheckUpdates.php`, `Widgets/SystemVersion.php`,
`Notifications/NewVersion.php`, `Controllers/Updates.php`, `Views/{index,widget}.php`. Tests in
`modules/Updates/Tests` (a fake repository served through the real `OutboundClient`, a test
minisign signer, a fake command runner).
