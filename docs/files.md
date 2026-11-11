# Files: file manager and folder permissions

The **Files** module (`modules/Files`, session 22) is a file manager over the Core's Storage
service (`docs/storage.md`) with folder permissions inherited by subfolders. The file picker,
signed upload tokens and avatars come in session 23.

## Spaces and the virtual folder tree

The tree is virtual: a folder is a row of `folders` (disk + normalised path); a file's folder is
the `folder` column of its `files` row, so moving a file never touches the disk (the stored file
keeps its random name and path; see `stored_files`, D-13-03). Three spaces, by path prefix:

| Space | Path | Who |
|---|---|---|
| Personal | `users/{user_id}` (private disk) | the owner has every right; nobody else except `files.all`, unless a subfolder's ACL shares it (e.g. the avatar folder of session 23). Shown in the file manager to members of the groups in `Files.personalSpaceGroups` (default `admin`, `editor`); created on first use |
| Shared | `shared/…` (public or private disk) | the rights of the folder ACL. The two roots (`shared` on each disk) are created on first use with an empty ACL: only `files.all` until an administrator shares them |
| Modules | `modules/<module>/…` | written only by the module's code (Storage service). Hidden in the file manager, or listed read-only for `files.all` when the module's `FileAccessPolicy` says `readonly` |

Anything outside these prefixes is refused by the file manager (404).

## Permissions: RBAC is the ceiling, the ACL decides where

Permissions of the single resource `files` (D-00-18, D-22-01): `files.list` (open the file
manager), `files.upload`, `files.edit` (rename, metadata, move), `files.delete` (trash, restore,
delete for good), `files.folders` (create, rename and delete folders), `files.acl` (edit folder
permissions), `files.all` (every folder, every right: administrators), `files.private` (the
shared folders of the private disk). Default grants: `admin` `files.*`; `editor` `files.list`,
`upload`, `edit`, `delete`, `folders`, `private`.

Folder ACL (`folder_permissions`): subjects `group` (group name), `user` (user id) and
`authenticated` (every logged-in user); rights `view`, `upload`, `edit`, `delete`, `manage`. A
folder with `own_permissions` uses its own entries (an empty list hides it); otherwise it
inherits the nearest parent that has its own. No such parent: no rights (default deny). The
entries that match the user (their id, their groups, "all logged-in") add up.

`Modules\Files\Services\FolderAcl`:

- `rights($user, $disk, $path)` — the ACL alone (what serving a private file checks):
  superadmins and `files.all` get every right except in module folders; the owner of a personal
  space gets every right inside it; otherwise the inherited entries;
- `managerRights()` — what the file manager allows: `rights()` capped by the RBAC permission of
  each right (`view`→`files.list`, `upload`→`files.upload`, `edit`→`files.edit`,
  `delete`→`files.delete`, `manage`→`files.acl`), personal spaces only for the groups of the
  setting, private shared folders only with `files.private`, and the ACL of a personal space only
  edited by `files.all`.

Folder operations need `files.folders` and `manage` in the folder (in one's own personal space,
`upload`). Space roots cannot be renamed or deleted; only empty folders (no files, no files in
the trash, no subfolders) can be deleted.

## Quotas

`folders.quota` (bytes, edited with the permissions by `files.all`) limits everything below a
folder; every quota of the chain must hold. A personal space without its own quota uses
`Files.personalQuota` (MB, default 100; 0 = none). Files in the trash count until they are
deleted for good. The quota is checked before storing and again with the stored size (images are
re-encoded).

## Uploads, thumbnails, trash

Uploads go through `Modules\Files\Services\Uploader`: folder `upload` right, quotas, then
`service('storage')->store()` (the Core's upload rules: `Core.uploadTypes`, `Core.uploadMaxSize`,
`Core.uploadAllowSvg`, MIME detection, re-encoding, random names). Raster images get a 320 px
WebP thumbnail (JPEG without WebP) on the private disk in `modules/files/thumbnails/`, served by
`backend/files/api/file/{id}/thumbnail` after the `view` right. Deleting moves to the trash
(`deleted_at`); restoring clears it; "delete for good" removes the stored file (disk and
`stored_files`), its thumbnail and the row. The upload route is rate limited (`ratelimit:uploads`,
`Core.rateLimitUploads` per hour and user; the page sends one request per file).

## Serving files

- Public disk: the public URL (`/uploads/…`) served by the web server; the ACL only decides who
  sees the file in the file manager. "Copy public URL" gives that address.
- Private disk: `files/private/{key}` (Core controller) with the rule
  `Modules\Files\Services\FolderAccessRule` (registered by `modules/Files/Config/Registrar.php`):
  1. Files disabled → the Core's `OwnerAccess`;
  2. the file's folder is inside a module's `FileAccessPolicy` folder → the policy decides
     (superadmins always pass): the file follows the visibility of the module's record;
  3. guests → 404;
  4. a trashed file → only users with `delete` in its folder;
  5. a folder governed by the ACL → its `view` right;
  6. otherwise (files of other modules outside the managed folders) → `OwnerAccess`.
  Anything refused is a 404.
- In the file manager, `download` and `preview` (`backend/files/api/file/{id}/…`) check the same
  rights plus the RBAC ceiling, with the Core's private-file headers (`nosniff`, sandboxing CSP;
  text files previewed as `text/plain`).

## FileAccessPolicy

```php
use Modules\Files\Policies\FileAccessPolicy;
use Modules\Files\Policies\PolicyRegistry;

// modules/Events/Config/Events.php
Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
    $registry->register(new \Modules\Events\Storage\AttachmentPolicy());
});
```

A policy names its folder (`modules/<module>` or below; anything else is ignored), how it shows
in the file manager (`hidden` or `readonly`) and `canView(?User, StoredFile)`. Only policies of
enabled modules count; the longest matching folder wins.

## Screens and endpoints

`backend/files` (`files.list`) is the page; everything else is JSON under `backend/files/api`
(`Controllers/FileApi.php`), each route with `can:all,files.list,<permission>`:

| Route | Permission | ACL right |
|---|---|---|
| `GET tree`, `GET list?disk&path&q&type&from&to&tag&sort`, `GET trash`, `GET file/{id}`, `GET file/{id}/download|preview|thumbnail` | `files.list` | `view` (trash: `delete`) |
| `POST upload` (`files[]`, `disk`, `path`) | `files.upload` + `ratelimit:uploads` | `upload` |
| `POST file/{id}/update` (name, alt, tags), `POST file/{id}/move` (`to`) | `files.edit` | `edit` (+ `upload` in the target) |
| `POST file/{id}/delete|restore|purge` | `files.delete` | `delete` |
| `POST folder`, `folder/rename`, `folder/delete` | `files.folders` | `manage` (own space: `upload`) |
| `GET acl`, `POST acl` (JSON: `disk`, `path`, `own`, `entries`, `quota`) | `files.acl` | `manage` |

A folder or file the user cannot view answers 404; a missing right 403; a path with traversal,
hidden segments or outside the spaces 404. Every change is in the activity log
(`Files.activity.*`); permission changes are `security` entries (`acl_changed`).

The page (`Views/manager.php`, `Assets/js/file-manager.js`, `Assets/css/file-manager.css`): tree,
breadcrumbs, list and grid views (remembered in the browser), search by name/alt/tags, filters by
type, date and tag, sort, upload by button or drag and drop with a `<progress>` bar per file (no
inline styles), details panel (preview, metadata, download, copy URL, move, trash), trash view,
folder actions and the permissions editor.

## Tables

- `folders` (disk, path, owner_id, quota, own_permissions, created_by, timestamps);
- `folder_permissions` (folder_id, subject_type, subject_id, rights);
- `files` (stored_file_id → `stored_files`, disk, folder, name, visibility, alt, tags, thumbnail,
  deleted_by, timestamps, deleted_at).

## Settings

`Files.personalSpaceGroups` (list of group names), `Files.personalQuota` (MB), in the Files tab of
Settings.

## Demo data

`php spark modules:demo Files`: shared private folders "Secretaria" (editor: view, upload, edit;
admin: everything; quota 50 MB) with "Circulars" (inherits) and "Actes" (own: admins only),
"Documents comuns" (every signed-in user: view) and the public "Imatges web", plus a few files
with tags and thumbnails.
