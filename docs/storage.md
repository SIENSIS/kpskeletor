# File storage and uploads

The Core's **Storage service** (`service('storage')`, `Modules\Core\Storage\Storage`) is the only
way to write files. It has two disks over Flysystem:

| Disk | Folder | Served by | Permissions |
|---|---|---|---|
| `public` | `public/uploads` | the web server (`/uploads/…`), with no execution (`docs/web-server.md`) | folders `0755`, files `0644` |
| `private` | `writable/storage` | only `files/private/{key}`, after the access rule | folders `0750`, files `0640` |

Disks, permissions, forbidden extensions, image limits and the access rule are configured in
`app/Config/Storage.php`. Modules never use these paths: they call the service, so another adapter
(S3, SFTP) can be added later. Decisions: D-13-01 … D-13-05 in `docs/decisions.md`.

## Storing an upload

```php
use Modules\Core\Storage\UploadRefused;
use Modules\Core\Storage\UploadRules;

try {
    $file = service('storage')->store(
        $this->request->getFile('attachment'),     // UploadedFile
        'private',                                  // disk
        'modules/news/' . $postId,                  // folder inside the disk
        UploadRules::make('pdf,jpg,png', 5 * 1024 * 1024), // this field's rules
    );
} catch (UploadRefused $e) {
    return redirect()->back()->with('error', $e->getMessage()); // translated, already logged
}

$file->key;          // random id of files/private/{key}
$file->originalName; // "Informe final.pdf" (metadata only)
service('storage')->url($file);       // site_url('files/private/' . key), or the public URL
service('storage')->url($file, true); // ?download=1
```

`storeContents($bytes, 'report.csv', 'private', 'modules/reports')` stores generated contents
through the same rules. `find($key)`, `findById($id)` and `delete($file)` complete the API;
`disk('private')` gives the disk (`write`, `read`, `readStream`, `exists`, `delete`, `move`,
`copy`, `list`, `url` for the public disk) for module files that are not uploads.

`store()` does, in order:

1. normalises the folder (`PathNormalizer`: no `..`, `.`, absolute paths, backslashes, control
   characters or hidden segments such as `.htaccess`);
2. checks the upload rules (`UploadValidator`, below); a refusal throws `UploadRefused`
   (`reason()`: `invalid`, `empty`, `too_large`, `type`, `forbidden`, `mime`, `image`, `svg`,
   `content`; message `Core.storage.refused.<reason>`) and writes a `security` entry
   `Core`/`upload_refused` (original name, reason, disk, folder) to the activity log;
3. writes the file with a random name (`<folder>/<32 hex>.<ext>`) and the disk's permissions;
4. records it in `stored_files` (key, disk, path, original name, extension, MIME type, size,
   SHA-256, image size, `uploaded_by`).

Storing a file is not logged by the service: the module logs its own action (a CRUD write is
logged by the Core, a file-manager upload by the Files module).

## Upload rules

Global rules (settings, edited in Settings › Security; forbidden extensions are refused there too):

| Setting | Default | Meaning |
|---|---|---|
| `Core.uploadTypes` | `jpg,jpeg,png,gif,webp,pdf,txt,csv,odt,ods,odp,doc,docx,xls,xlsx,ppt,pptx` | extensions allowed anywhere |
| `Core.uploadMaxSize` | `10` | MB (PHP's `upload_max_filesize` and `post_max_size` must allow it) |
| `Core.uploadAllowSvg` | off | SVG accepted (sanitised) by fields that list `svg` |

A field's `UploadRules` can only **narrow** them: its extensions must also be in the global list
and the smaller size wins. `reencode: false` keeps the original bytes of an image (only for a
field that needs them).

Checks of every upload:

- **Extension** of the client's name, lower case, in both lists. Never accepted, whatever the
  settings say: `Config\Storage::$forbiddenExtensions` (PHP and other server code, HTML, XML,
  JavaScript, executables, `.htaccess`, `.env`…), and a name without extension.
- **MIME type** detected from the contents (`finfo`, never the browser's) must belong to the
  extension (`Config\Mimes` + `Config\Storage::$extraMimes`). The file is stored and served with
  the canonical type of the extension. HTML, XML, SVG or PHP contents under another extension and
  `<?php` in the first 64 KB are refused.
- **Size**: not empty and within the limit.
- **Images** (`jpg`, `jpeg`, `png`, `gif`, `webp`) must decode and match their extension, at most
  40 megapixels (`Config\Storage::$maxImagePixels`), and are **re-encoded** with GD by default:
  EXIF/XMP/IPTC metadata (GPS position…) and anything hidden or appended are dropped; JPEG
  orientation is applied first; animated GIFs keep their first frame.
- **SVG** only with `Core.uploadAllowSvg` and a field that lists it: DOCTYPEs are refused and
  `SvgSanitizer` keeps only drawing elements and presentation attributes (no scripts, event
  handlers, `<style>`, `style`, `foreignObject`, links, external or `data:`/`javascript:`
  references). Private SVGs are always served as downloads.

## Serving private files

`GET files/private/{key}` (`Modules\Core\Controllers\PrivateFiles`) is the only way to read a file
of the private disk:

- the access rule (`Config\Storage::$accessRule`, interface `FileAccessRule`) decides. The Core's
  default is `OwnerAccess` (the user who uploaded the file and superadmins); the Files module
  replaces it through its registrar with `Modules\Files\Services\FolderAccessRule`: the `view`
  right of the file's folder (folder ACL), a module's `FileAccessPolicy` for its folder, and
  `OwnerAccess` for files outside every managed folder or while Files is disabled
  (`docs/files.md`);
- anything not allowed, an unknown or malformed key, a public-disk file or a file missing on disk
  is a **404** (the existence of a file is not revealed);
- headers: the stored `Content-Type`, `X-Content-Type-Options: nosniff`,
  `Content-Disposition: inline` only for raster images and PDF (otherwise, and with
  `?download=1`, `attachment`) with the original name, `Content-Security-Policy: default-src
  'none'; …; sandbox` (PDF without `sandbox`), `X-Frame-Options: SAMEORIGIN`,
  `Cross-Origin-Resource-Policy: same-origin` and `Cache-Control: private, no-store`, plus the
  global security headers.

Application access control never relies on file permissions: a private file is only read through
this controller. The web server must never serve `writable/` (see `docs/web-server.md`).

## Tests

`modules/Core/Tests/UploadRulesTest.php` (traversal, forbidden types, SVG refused and sanitised,
MIME checks, sizes, EXIF stripped), `StorageTest.php` (random names, permissions `0750`/`0640`,
refusals logged, settings) and `PrivateFilesTest.php` (owner, other users, guests, superadmins,
headers, 404s). `StorageFixturesTrait` creates disks in `writable/tests/` and small test files.
