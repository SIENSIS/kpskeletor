# Files module

**Purpose:** the file manager over the Core's Storage service, with folder permissions (ACL)
inherited by subfolders, predefined spaces (personal, shared, modules), quotas, trash,
thumbnails, and the rule that serves private files (`files/private/{key}`). Full description:
`docs/files.md`. It is also the backend of the Core's file picker (`Services/PickerBackend.php`,
session 23): folders on demand, quotas, `files` rows and browsing through the folder ACL.

## Resources and permissions

- `files` (single resource, `'name' => 'files'`, crud − show/add/export/import/trash/restore/
  purge + extras): `files.list`, `upload`, `edit`, `delete`, `folders`, `acl`, `all`, `private`.
  RBAC is the ceiling; the folder ACL decides where (`Services/FolderAcl.php`). `files.all` covers
  shared folders, not other users' personal spaces (superadmins only, logged).
- Grants: admin `files.*`; editor `files.list`, `upload`, `edit`, `delete`, `folders`, `private`.
- Menu: "Files" (sidebar, 30). Settings: `Files.personalSpaceGroups` (list, default
  admin/editor), `Files.personalQuota` (MB, default 100).
- Tables: `folders`, `folder_permissions`, `files` (references `stored_files`), one migration
  each. Registrar: `Config\Storage::$accessRule` = `Services/FolderAccessRule.php`.
- Routes: `backend/files` and `backend/files/api/*` (JSON; table in `docs/files.md`); uploads with
  `ratelimit:uploads`.

## Main pieces

- `Services/Spaces.php` (paths of the spaces), `Rights.php`, `FolderRepository.php` (folders and
  ACL rows), `FolderAcl.php` (`rights()` = ACL, `managerRights()` = ACL capped by RBAC and the
  space rules), `FileRepository.php` (listing, filters, tags), `QuotaService.php`,
  `ThumbnailService.php`, `Trash.php`, `Uploader.php`, `Browser.php` (tree and items for the
  page), `FolderAccessRule.php` (private files).
- `Policies/FileAccessPolicy.php` + `PolicyRegistry.php` (event `filesAccessPolicies`).
- `Services/PickerBackend.php`: the Core's `Config\Storage::$pickerBackend` (registrar); the
  avatar folder `users/{id}/avatar` is created with `authenticated: view`.
- `Controllers/FileManager.php` (page), `FileApi.php` (JSON); `Views/manager.php`;
  `Assets/js/file-manager.js` (`files/file-manager`), `Assets/css/file-manager.css` (loaded by the
  script: no inline styles).

## Decisions

D-22-01 (names, URLs, settings, space paths) decided by the owner as built; D-22-04 decided: other
users' personal spaces only for superadmins (not `files.all`), each read logged
(`personal_file_read`); D-22-02, D-22-03, D-22-05, D-22-06 accepted (`docs/decisions.md`). Session 23: D-23-01 (picker and
avatar URLs, decided as built), D-23-02 (picker in the Core, this module's backend), D-23-03 (browse/choose
rules).

## Open tasks

- Session 26: files in the API (if wanted). Session 27: backups of `users/` and `shared/`.
- No automatic purge of the trash yet; module folders are listed read-only only through a
  module's `FileAccessPolicy` (none exists yet: Events in session 24).
- Users are chosen by id in the permissions dialog (no user search yet).

## How to test

`vendor/bin/phpunit modules/Files` (base `Tests/FilesTestCase.php`: Auth fixture, declarations,
temporary Storage disks, `folder()`, `upload()`, `apiJson()`, `status()`). By hand:
`php spark modules:demo Auth` and `php spark modules:demo Files`, then `backend/files` as
editor@ (personal space, Secretaria and Circulars but not Actes, Documents comuns, Imatges web)
and as admin@ (every shared folder, permissions dialog, quotas; no other personal spaces).
