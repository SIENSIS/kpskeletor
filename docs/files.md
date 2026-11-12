# Files: file manager and folder permissions

The **Files** module (`modules/Files`, session 22) is a file manager over the Core's Storage
service (`docs/storage.md`) with folder permissions inherited by subfolders (session 22), and the
backend of the Core's **file picker** with signed upload tokens and avatars (session 23, below).

## Spaces and the virtual folder tree

The tree is virtual: a folder is a row of `folders` (disk + normalised path); a file's folder is
the `folder` column of its `files` row, so moving a file never touches the disk (the stored file
keeps its random name and path; see `stored_files`, D-13-03). Three spaces, by path prefix:

| Space | Path | Who |
|---|---|---|
| Personal | `users/{user_id}` (private disk) | the owner has every right; nobody else except superadmins (each read of a file written to the security log as `personal_file_read`; administrators impersonate the user when needed, D-22-04), unless a subfolder's ACL shares it (e.g. the avatar folder of session 23). Shown in the file manager to members of the groups in `Files.personalSpaceGroups` (default `admin`, `editor`); created on first use |
| Shared | `shared/…` (public or private disk) | the rights of the folder ACL. The two roots (`shared` on each disk) are created on first use with an empty ACL: only `files.all` until an administrator shares them |
| Modules | `modules/<module>/…` | written only by the module's code (Storage service). Hidden in the file manager, or listed read-only for `files.all` when the module's `FileAccessPolicy` says `readonly` |

Anything outside these prefixes is refused by the file manager (404).

## Permissions: RBAC is the ceiling, the ACL decides where

Permissions of the single resource `files` (D-00-18, D-22-01): `files.list` (open the file
manager), `files.upload`, `files.edit` (rename, metadata, move), `files.delete` (trash, restore,
delete for good), `files.folders` (create, rename and delete folders), `files.acl` (edit folder
permissions), `files.all` (every shared folder, every right: administrators; not other users' personal
spaces), `files.private` (the
shared folders of the private disk). Default grants: `admin` `files.*`; `editor` `files.list`,
`upload`, `edit`, `delete`, `folders`, `private`.

Folder ACL (`folder_permissions`): subjects `group` (group name), `user` (user id) and
`authenticated` (every logged-in user); rights `view`, `upload`, `edit`, `delete`, `manage`. A
folder with `own_permissions` uses its own entries (an empty list hides it); otherwise it
inherits the nearest parent that has its own. No such parent: no rights (default deny). The
entries that match the user (their id, their groups, "all logged-in") add up.

`Modules\Files\Services\FolderAcl`:

- `rights($user, $disk, $path)` — the ACL alone (what serving a private file checks):
  superadmins get every right except in module folders; the owner of a personal space gets every
  right inside it; `files.all` gets every right except in module folders and in other users'
  personal spaces; otherwise the inherited entries;
- `managerRights()` — what the file manager allows: `rights()` capped by the RBAC permission of
  each right (`view`→`files.list`, `upload`→`files.upload`, `edit`→`files.edit`,
  `delete`→`files.delete`, `manage`→`files.acl`), personal spaces only for the groups of the
  setting, private shared folders only with `files.private`, and the ACL (and quota) of a personal
  space only edited by superadmins.

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
  5. a folder governed by the ACL → its `view` right (a superadmin reading a file of someone
     else's personal space is logged, also through the file manager's download and preview;
     thumbnails in listings are not);
  6. otherwise (files of other modules outside the managed folders) → `OwnerAccess`.
  Anything refused is a 404.
- In the file manager, `download` and `preview` (`backend/files/api/file/{id}/…`) check the same
  rights plus the RBAC ceiling, with the Core's private-file headers (`nosniff`, sandboxing CSP;
  text files previewed as `text/plain`).

## File picker (session 23)

Any module field that needs a file uses the Core's picker (`Modules\Core\Storage\Picker`), not
kpcrudy's upload types and not the file manager:

```php
use Modules\Core\Storage\Picker\PickerField;

$field = PickerField::make('cover')
    ->mode('both')                       // upload | browse | both
    ->disk('public')                     // public | private
    ->folder('modules/news')             // variables: {user_id}
    ->types('jpg,png,webp')->maxSize(5 * 1024 * 1024)->maxFiles(1)
    ->browseRoot(['modules/news', 'shared']);

echo $field->render($user, $currentKey, ['label' => …, 'help' => …, 'error' => …]);
// Before saving: the posted key must come through the field.
if (! $field->accepts($user, $postedKey, $currentKey)) { /* refuse */ }
```

For a kpcrudy field, `CrudUploadHandler` (Core) gives the same thing from a module's handler class
(folder, disk, `TYPES`, `MAX_SIZE`): `->callbackField('cover', fn ($v, $row, $field) =>
CoverUploads::renderPicker('cover', $v, $field))` and `CoverUploads::accepts('cover', $data['cover'],
$old['cover'])` in `beforeInsert` / `beforeUpdate` (News cover, Pages featured image).

- **The field grants the upload, not the file manager.** Rendering the field issues a **signed
  upload token** (`UploadToken`: user, mode, disk, folder with its variables resolved, types,
  size, number of files, browse roots, re-encoding, expiry `Config\Storage::$pickerTokenTtl`, 4 h;
  HMAC-SHA256 with a key derived from `encryption.key`/`app.maskKey`). The endpoints accept nothing
  else: the user cannot change the folder, the disk, the types or the size, and needs no `files.*`
  permission. A missing, tampered, expired or someone else's token is a **403** and a `security`
  entry `Core` / `upload_token_refused`.
- **Endpoints** (Core, `Controllers/FilePicker.php`; signed-in users only, else 401):
  `POST files/picker/upload` (`token`, `file`; CSRF; `ratelimit:uploads`) → `201 {"file": {key,
  name, url, thumbnail, size, mime, image, width, height}}` or `422 {"message"}`;
  `GET files/picker/browse?token=…&path=…` → `{path, parent, roots, folders, files}` (404 outside
  the roots).
- **Backend** (`Config\Storage::$pickerBackend`, interface `PickerBackend`). The Core's
  `StorageBackend` (Files disabled) stores into the token's folder and lets users choose only
  their own uploads of it. The Files module's `Services/PickerBackend.php` (registrar):
  - creates the target folder and its parents **on demand** with the space's default rights
    (`users/{id}` belongs to its owner; `users/{id}/avatar` gets its own ACL: `view` for every
    signed-in user; other folders inherit);
  - checks the **quotas** of the chain (before and after re-encoding), stores through the Storage
    service with the field's rules, adds a `files` row (thumbnail) and logs `file_uploaded`
    (`via: picker`);
  - **browse / choose:** only inside the token's roots, and only the field's own folder or a
    folder whose **ACL** gives `view` (the ACL alone: the picker is not the file manager, so no
    `files.list` is needed); trashed files never; only the field's disk. Files a module stored
    before the picker (no `files` row) are listed in the field's own folder.
- **Component** `core/forms/file-picker` (`Modules/Core/Assets/js/forms/file-picker.js`, theme
  component `components/file_picker.php`, styles `core::css/file-picker.css`): a hidden input
  with the key(s) (comma separated), the current file(s) with a remove button, "Upload" (one
  request per file, native `<progress>`, types and size checked first) and "Choose" (a dialog
  listing the roots' folders and files). Without JavaScript the current value is kept.
- **Images in rich text** (D-17-08): `RichTextEditor::attrs(RichTextEditor::images('news'))` adds
  "Insert image" to the editor: the picker dialog (upload or choose JPG, PNG, WebP, GIF; public
  disk, `modules/<module>`, also the public shared folders the user may view) asks for the alt
  text and inserts `<img src alt>`; pasted or dropped images use the same signed upload.
  `HtmlSanitizer` then keeps only images of the site itself (`SiteImagesOnly`: relative URLs or
  the hosts of `app.baseURL` / `app.allowedHostnames`); external and `data:` images are removed.
- **Avatars** (Auth, `Services/Avatars.php`): upload only, private disk,
  `users/{user_id}/avatar`, JPG/PNG/WebP up to 2 MB, re-encoded; `user_profiles.avatar` = the key,
  saved with the profile form (a key that did not come through the field is refused; the
  replaced avatar is deleted). Served at **`files/avatar/{key}`** only to signed-in users (or with
  a signed URL `?expires&signature`, valid one hour, given to API clients in `me.avatar_url`) and
  only for keys that are someone's current avatar; `Cache-Control: private, max-age=31536000,
  immutable` (the key changes with each new avatar, so the URL is versioned). Guests get a 404.

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
(`Files.activity.*`); permission changes (`acl_changed`) and superadmin reads of other users'
personal files (`personal_file_read`) are `security` entries.

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
