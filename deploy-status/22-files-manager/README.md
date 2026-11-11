# Session 22 — Files module: file manager and folder ACL

Branch: `claude/upbeat-brown-vq8taw` · Pull request: see `docs/progress.md` · Date: 2026-11-11

## What was built

- **File manager** (`backend/files`, `files.list`): folder tree by space (my files, shared
  public, shared private; other users' spaces and module folders for `files.all`), breadcrumbs,
  **list and grid** views (remembered in the browser), **search** (name, alt text, tags),
  **filters** by type, date range and tag, sort by name/date/size; **upload** by button or **drag
  and drop**, several files, one `<progress>` bar per file; details panel with **preview**
  (images, PDF, text), metadata (name, alt text, tags), **download**, **copy URL** (public URL
  on the public disk, `files/private/{key}` on the private one), **move**, **trash**; the
  **trash** view with **restore** and **delete for good**; folders **create / rename / delete**
  (empty only); the **permissions dialog** (inherit or own entries for groups, users and every
  signed-in user; quota for `files.all`).
- **Folder ACL with inheritance:** rights `view`, `upload`, `edit`, `delete`, `manage` per
  folder; subfolders inherit unless they define their own; subjects group / user / all
  logged-in; **RBAC is the ceiling** (`files.list`, `files.upload`, `files.edit`,
  `files.delete`, `files.acl`), `files.all` reaches everything, superadmins bypass.
- **Spaces:** personal `users/{user_id}` (private disk; owner has every right; shown to the
  groups of `Files.personalSpaceGroups`), shared `shared/…` on each disk, module folders
  `modules/<module>` (hidden, or read-only through a module's `FileAccessPolicy`).
- **Quotas** per folder (chain) and per personal space (`Files.personalQuota`), the trash
  included; **thumbnails** (320 px WebP) generated on upload.
- **Private files** (`files/private/{key}`) now follow the folder ACL (`view`), with a
  `FileAccessPolicy` registry for module folders; the Core's interim `OwnerAccess` rule stays as
  the fallback for files outside the managed folders and while Files is disabled. Still a 404 for
  anything not allowed.

## How it was built

| Piece | Where |
|---|---|
| Manifest: resource `files` (`name` override; crud − show/add/export/import/trash/restore/purge + `upload`, `folders`, `acl`, `all`, `private`), grants, menu, settings, backup paths | `modules/Files/Config/Module.php` |
| Routes `backend/files`, `backend/files/api/*` (`can:all,files.list,<perm>`, `ratelimit:uploads` on upload) | `modules/Files/Config/Routes.php` |
| Private file rule handed to the Core through a registrar (`Config\Storage::$accessRule`) | `modules/Files/Config/Registrar.php`, `Services/FolderAccessRule.php` |
| Spaces and paths, rights | `Services/Spaces.php`, `Services/Rights.php` |
| Folders and ACL rows; ACL evaluation (`rights()`, `managerRights()`, `governs()`, `source()`) | `Services/FolderRepository.php`, `Services/FolderAcl.php` |
| Files listing, filters, tags, module-folder listing | `Services/FileRepository.php` |
| Quotas, thumbnails, trash, uploads | `Services/QuotaService.php`, `ThumbnailService.php`, `Trash.php`, `Uploader.php` |
| Tree and items for the page | `Services/Browser.php` |
| Module policies (interface + registry, event `filesAccessPolicies`) | `Policies/FileAccessPolicy.php`, `Policies/PolicyRegistry.php` |
| Page and JSON endpoints | `Controllers/FileManager.php`, `Controllers/FileApi.php`, `Views/manager.php` |
| Front end (ES module, CSS loaded by the module; no inline code or styles) | `Assets/js/file-manager.js`, `Assets/css/file-manager.css` |
| Tables (one migration each) | `folders`, `folder_permissions`, `files` (→ `stored_files`) |
| Demo data | `Database/Seeds/FilesDemoSeeder.php` |
| Texts ca/es/en (UI, JS, permissions, settings, activity labels) | `Language/*/Files.php` |
| Documentation | `docs/files.md` (new), `docs/storage.md`, `docs/security.md`, `modules/Files/CLAUDE.md` |

Activity log: `file_uploaded`, `file_updated`, `file_moved`, `file_trashed`, `file_restored`,
`file_purged`, `folder_created`, `folder_renamed`, `folder_deleted`; `acl_changed` is a
`security` entry. No new spark command.

## Decisions and deviations

- **D-22-01 (decide, Open):** the eight permission names of the specification on one resource
  `files`; `files.private` = the shared folders of the private disk in the file manager; shared
  space under `shared/`; settings `Files.personalSpaceGroups` (list) and `Files.personalQuota`
  (new, MB); editor grants `files.list/upload/edit/delete/folders/private`; URLs above.
- **D-22-02:** the file manager is a screen of its own over JSON endpoints, not kpcrudy.
- **D-22-03:** virtual folders: `files.folder`; moves and folder renames never touch the disk;
  `files` references `stored_files` (D-13-03).
- **D-22-04 (decide, Open):** the private-file rule order (policy → guests → trash → ACL `view` →
  `OwnerAccess`), `files.all` everywhere except module folders, the owner always keeps every right
  in their space, only `files.all` edits a personal space's ACL, policies only inside `modules/`.
- **D-22-05:** quotas along the chain, trash counts, checked before and after storing;
  thumbnails on the private disk `modules/files/thumbnails/`; no automatic trash purge yet.
- **D-22-06:** upload progress with native `<progress>` and one request per file (no inline
  styles).
- Core tests adjusted (no Core code changed): `PrivateFilesTest` uses an editor as "another user"
  (admins now read personal spaces through `files.all`, as the specification says);
  `PermissionsCommandsTest` (108 permissions) and `ShieldMatrixTest` (`files.*` in admin's grants).

## How to try it

```bash
php spark app:install --env=development --db=sqlite --demo   # or: php spark modules:sync && php spark modules:demo Files
php spark serve
```

- `http://localhost:8080/backend/files` as **editor@example.com** (password printed once by the
  command that loaded the demo): "Els meus fitxers", "Imatges web" (public), "Documents comuns"
  and "Secretaria" › "Circulars" (inherited), not "Actes" (own permissions: admins only).
- As **admin@example.com**: every folder, the permissions dialog ("Permisos") and quotas, other
  users' spaces under "Espais personals".
- A private file's address (`files/private/{key}`, "Copia l'URL") answers 404 to users without
  `view` on its folder and to guests.

## Tests and checks

New tests (`modules/Files/Tests`, 34 tests, 254 assertions):

- `FolderAclTest`: inheritance and override (and inheriting again), empty own ACL hides, the three
  subjects add up, no folder = no rights, `files.all` and superadmins, personal space (owner,
  others, shared subfolder), the group setting, RBAC ceiling, `files.private`, module folders
  hidden or read-only by policy.
- `QuotaTrashTest`: folder quota on subfolders (nothing stored when refused), trash counts until
  purge, purge removes disk file and records, personal quota from the setting, thumbnails made and
  purged, uploads need `upload` and never reach module folders.
- `FileApiTest`: `files.list` needed, the tree follows the ACL (path to a visible subfolder,
  hidden siblings 404), **traversal** (`..`, absolute, hidden, backslash, outside the spaces,
  unknown disk), metadata/move/trash/restore, **forged POSTs** without RBAC permissions (403,
  nothing changed), files of invisible folders 404 on every endpoint, download/preview headers,
  folders (create, duplicates, invalid names, rename moves the files, non-empty and root refused,
  someone else's space 404), permissions editor (403 without `manage`, unknown subject 422, saved
  entries and quota, `security` log entry, inherit again).
- `PrivateFileRuleTest`: registrar replaces the rule, `view` decides (404 otherwise, guests 404),
  trashed files, files outside managed folders keep `OwnerAccess`, module policies (guests
  allowed by policy, `files.all` not overriding it, nothing outside `modules/`).
- `FilesModuleTest`: manifest, permission names and grants, setting defaults, demo data load and
  complete removal, migrations down and up.

Results (full run on this branch):

- `vendor/bin/phpunit`: **OK (942 tests, 5245 assertions)**, 7 min 58 s on SQLite (the first run had
  3 failures in Core counting/rule tests, fixed as described above).
- `vendor/bin/phpstan analyse` (level 6): **No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: **0 of 672 files** to fix.
- `php spark permissions:check`: **Permissions OK: 108 permission(s), 202 route(s) checked.**
- `php spark csp:check`: **No inline scripts, handlers or styles in the project views.**
- `php spark modules:sync`: Files 0.1.0 installed, 7 default grants, assets published.
- Screenshot script: no CSP violation, page error or horizontal scroll.

## Screenshots

Taken with `deploy-status/22-files-manager/screenshots.mjs` (Playwright, device scale factor 2)
against `php spark serve` after a fresh `app:install --demo`.

| File | Caption |
|---|---|
| `screenshots/01-editor-grid.png` | Editor, grid view of "Documents comuns" (private shared folder they may upload to); the tree shows only what the ACL allows (no "Actes") |
| `screenshots/02-details-panel.png` | Details panel of an image: thumbnail, size, type, dimensions, visibility, download, preview, copy URL, metadata form |
| `screenshots/03-upload-progress.png` | Upload through the file input (same code as drag and drop) with its `<progress>` bar; quota "x of 50 MB used" of "Secretaria" |
| `screenshots/04-editor-list-after-upload.png` | List view after the upload |
| `screenshots/05-search.png` | Search by name in the current folder |
| `screenshots/06-permissions-dialog.png` | Admin: permissions of "Secretaria" (own entries for admin and editor, quota 50 MB) |
| `screenshots/07-permissions-override.png` | Admin: "Actes" overrides the inherited permissions (admins only) |
| `screenshots/08-trash.png` | Trash view after moving a file to the trash (restore / delete for good in the details panel) |
| `screenshots/09-phone-tree.png` | Phone width: folder tree opened with the tree button |
| `screenshots/10-phone-list.png` | Phone width: list of a folder |
| `screenshots/11-dark-grid.png` | Dark mode, grid view |

## Known issues

- No automatic purge of the trash (manual "delete for good"); trashed files count for quotas.
- Users are chosen by numeric id in the permissions dialog (no user search yet).
- One upload request per file: `ratelimit:uploads` (100/hour per user by default) also limits the
  number of files.
- Search and filters apply to the current folder, not to its subfolders.
- No `FileAccessPolicy` exists yet (Events, session 24, is the first expected user); module
  folders are therefore hidden in the file manager.
- Public files are served by the web server at their public URL: the folder ACL only decides who
  sees them in the file manager (by design; documented in `docs/files.md`).
- Environment: Composer's stored GitHub token is rejected and `api.github.com` zip downloads are
  refused (known issue 04). This session installed from git sources in a scratch copy whose lock
  file pointed `phpstan/phpstan` at a shallow clone of the same release commit (the project's
  `composer.json`/`composer.lock` are unchanged), then moved `vendor/` into the project. A full
  clone of `phpstan/phpstan` is several GB: clone only the tag (`--depth 1 --branch 2.3.5`).

## What the next session must know

- Session 23 (file picker, upload tokens, avatars) builds on `Modules\Files\Services\Uploader`,
  `FolderAcl`, `QuotaService` and `Spaces`: the avatar folder `users/{user_id}/avatar` is a
  subfolder with its own ACL (`authenticated: view`; the owner keeps every right); folders are
  created with `FolderRepository::ensure()`; the token's folder still goes through the space
  rules. Put `ratelimit:uploads` on the picker's upload routes.
- Modules that store private files for records register a `FileAccessPolicy` for
  `modules/<module>/…` (event `PolicyRegistry::EVENT` in their `Config/Events.php`); see
  `docs/files.md`.
- Tests needing files extend `Modules\Files\Tests\FilesTestCase` (`folder()`, `upload()`,
  `apiJson()`, `statusOf()`); counting tests (`PermissionsCommandsTest`, `ShieldMatrixTest`) list
  every module's permissions.
- D-22-01 and D-22-04 are **Open (decide)**: run `deploy/decide.md` before session 23.
