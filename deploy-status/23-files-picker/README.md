# Session 23 — Files module: file picker, upload tokens and avatars

Branch: `claude/wizardly-meitner-t9fp4s` · Pull request: [SIENSIS/kpskeletor#31](https://github.com/SIENSIS/kpskeletor/pull/31) · Date: 2026-11-12

## What was built

- **A reusable file picker** for every module field (`core/forms/file-picker`): modes `upload`,
  `browse`, `both`; disk; folder with variables (`{user_id}`); types, size, number of files;
  browse roots. The current file(s) are shown with a remove button; "Upload" sends one request
  per file with a native `<progress>`; "Choose" opens a dialog with the folders and files the
  field allows. The form value is the file key (comma separated for several).
- **Signed upload tokens:** the field grants the upload, not the file manager. Rendering a field
  issues a token (user, mode, disk, resolved folder, types, size, files, roots, re-encoding,
  expiry 4 h) signed with HMAC-SHA256; the endpoints accept nothing else, so the user can change
  neither the folder nor the type, and needs no `files.*` permission. A tampered, expired or
  foreign token is a 403 and a `security` log entry. The saved value is checked again on the
  server (`accepts()`): a key that did not come through the field is refused.
- **Folders on demand** (Files module backend): the target folder and its parents are created
  with the space's default rights; quotas apply; uploads get a `files` row, a thumbnail and an
  activity entry. Browsing/choosing only inside the roots, the field's own folder or folders whose
  ACL gives `view`.
- **Avatars** in the profile: upload-only picker, private disk, `users/{user_id}/avatar` (that
  folder's ACL: `view` for every signed-in user), JPG/PNG/WebP ≤ 2 MB, re-encoded; saved with the
  profile form, the replaced file deleted; served at `files/avatar/{key}` only to signed-in users
  (or with a signed URL), `Cache-Control: private, max-age=31536000, immutable` — the key versions
  the URL. `GET api/v1/me` now returns a signed `avatar_url` (one hour).
- **News cover** and **Pages featured image** use the picker (upload or choose from
  `modules/news` / `modules/pages` and the public shared folders the user may view).
- **"Insert image"** in the rich-text editor (D-17-08) for News and Pages: the picker dialog
  uploads or chooses a JPG/PNG/WebP/GIF of `modules/<module>` (public disk), asks for the alt text
  and inserts `<img src alt>`; pasted or dropped images use the same signed upload (no `data:`).
- **Only the site's own images** in rich text: an HTMLPurifier URI filter keeps relative URLs and
  the site's hosts; external, scheme-relative and `data:` images are removed.
- **CSRF gap fixed** (found while testing the picker in a browser): with
  `Config\Security::$regenerate`, no response returned the regenerated token, so a form submitted
  after a script's POST failed. New global after filter `csrftoken` and `refreshCsrf()` in
  `core/http.js` (updates the meta tag and the forms' hidden token fields).

## How it was built

| Piece | Where |
|---|---|
| Field builder, token, backend interface, Core backend | `modules/Core/Storage/Picker/{PickerField,UploadToken,PickerBackend,StorageBackend}.php` |
| Signing keys (HKDF from `encryption.key`/`app.maskKey`, one context per use) | `modules/Core/Security/SigningKey.php` |
| Endpoints `POST files/picker/upload` (`ratelimit:uploads`), `GET files/picker/browse` | `modules/Core/Controllers/FilePicker.php`, `modules/Core/Config/Routes.php` |
| Config `pickerBackend`, `pickerTokenTtl` | `app/Config/Storage.php` |
| Component, theme component, styles | `modules/Core/Assets/js/forms/file-picker.js`, `modules/Core/Views/components/file_picker.php`, `modules/Core/Assets/css/file-picker.css` |
| kpcrudy helpers (`pickerField()`, `renderPicker()`, `accepts()`) | `modules/Core/Storage/CrudUploadHandler.php` |
| Editor "Insert image", paste/drop | `modules/Core/Assets/js/forms/editor.js`, `modules/Core/Frontend/RichTextEditor.php` (`attrs($images)`, `images('<module>')`) |
| Site images only | `modules/Core/Security/{SiteImagesOnly,HtmlSanitizer}.php` |
| CSRF token back to scripts | `modules/Core/Filters/CsrfTokenHeader.php`, `modules/Core/Config/Registrar.php`, `app/Config/Filters.php`, `modules/Core/Assets/js/http.js` |
| Files backend (registrar) | `modules/Files/Services/PickerBackend.php`, `modules/Files/Config/Registrar.php` |
| Avatars | `modules/Auth/Services/Avatars.php`, `Controllers/AvatarController.php` (route `files/avatar/(:segment)`), `Controllers/ProfileController.php`, `Views/profile.php`; `modules/Api/Controllers/Me.php` |
| News / Pages | `modules/News/Controllers/Posts.php`, `Storage/CoverUploads.php`; `modules/Pages/Controllers/Pages.php`, `Storage/FeaturedImages.php` |
| Texts ca/es/en | `Core.picker.*`, `Core.js.picker.*`, `Core.js.editor.image`, `Core.activity.upload_token_refused`, `Auth.profile.avatar*` |
| Docs | `docs/files.md` ("File picker"), `docs/frontend.md`, `docs/storage.md`, `docs/security.md`, modules' `CLAUDE.md` |

No new table (the avatar key lives in `user_profiles.avatar`), permission, setting or command.

## Decisions and deviations

- **D-23-01 (decided by the owner as built, Decisions 23):** URLs `POST files/picker/upload`, `GET files/picker/browse`,
  `GET files/avatar/{key}` (signed URLs for the API), outside `backend/` and without a permission
  (the token is the authorisation); the field API and `Config\Storage::$pickerBackend`. Built so
  the URLs can still be renamed.
- **D-23-02:** the picker lives in the Core with a pluggable backend (the plan put it in Files;
  Files requires Auth, so Auth's avatar could not require Files).
- **D-23-03:** browse and choose only on the field's disk, inside its roots, the field's folder or
  folders with ACL `view` (no `files.list` needed). The specification's example "a news cover
  from the personal space" mixes the public and private disks and is not offered.
- **D-23-04:** avatars saved with the profile form, the old file deleted, key-versioned URL.
- **D-23-05:** "site images" = relative URLs or `app.baseURL`/`app.allowedHostnames` hosts.
- Core change outside the picker: the `csrftoken` after filter (it blocked the picker; it also
  fixes a second script POST on the same page, e.g. the file manager).

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --db=sqlite --demo --email=you@example.com
php spark serve
```

- `http://localhost:8080/backend/profile` as **usuari@example.com** (no `files.*` permission;
  password printed once by the installer): "Puja un fitxer", then "Desa". The avatar shows on the
  profile; `files/avatar/{key}` answers 404 when signed out.
- `backend/news/posts/add` as **editor@example.com**: the cover field (upload / "Tria un fitxer")
  and the image button of the body's toolbar ("Insereix una imatge": upload or choose, alt text).
  Paste an image into the body to upload it directly.
- `backend/pages/add`: the featured image and the same image button.

## Tests and checks

New tests (23):

- `Core/Tests/FilePickerTest` (8): tokens signed, bound and expiring (tampered folder refused);
  folder variables cannot escape the disk; guests 401, tampered token 403 with a security entry,
  upload-only token cannot browse, someone else's token 403; browsing stays inside the roots;
  the Core backend stores into the token's folder with its rules; the value must come through the
  field (other users' uploads, other folders, unknown/garbage keys, `maxFiles`, types); the
  rendered field (no inline style, no folder outside the token); a script's POST gets the
  regenerated CSRF token.
- `Files/Tests/PickerBackendTest` (6): the Files backend is configured; **an avatar needs no
  files.\* permission** and lands in `users/{id}/avatar` (folders created, ACL `authenticated:
  view`, other users view, guests never); **an upload cannot target another folder** (extra
  parameters ignored, traversal and tampered tokens refused); quotas; browse/choose follow the
  roots and the ACL; discarding removes the row.
- `Auth/Tests/AvatarTest` (4): the profile saves an avatar uploaded through its field (old file
  deleted, empty value removes it); forged keys refused; **avatars not served to guests**, cache
  headers for signed-in users, only current avatars; signed URLs and their expiry.
- `News/Tests/NewsPickerTest` (3): the form has the picker and "Insert image"; **an image inserted
  in a body is stored through the Storage service** (public disk, `modules/news`, re-encoded) and
  kept, **an external `img src` is removed**; the cover from the picker, forged keys refused.
- `Pages/Tests/PagesPickerTest` (1): featured image from the picker, forged key refused.
- `Core/Tests/HtmlSanitizerTest::testOnlyImagesOfTheSiteItselfAreKept` (1).

Results:

- `vendor/bin/phpunit`: **OK (967 tests, 5445 assertions)**, 5 min 57 s on SQLite (start of the session: 944 tests).
- `vendor/bin/phpstan analyse` (level 6): **No errors**.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: **0 files** to fix.
- `php spark permissions:check`: **Permissions OK: 108 permission(s) in the catalogue, 205 route(s) checked.**
- `php spark csp:check`: **No inline scripts, handlers or styles in the project views.**
- `php spark modules:sync`: nothing to do (no manifest change).
- `php spark security:check`: does not exist yet (session 30).
- Screenshot script: no CSP violation, page error or horizontal scroll. By hand with `curl`:
  `files/avatar/{key}` and `files/private/{key}` answer 404 to a guest.

## Screenshots

Taken with `deploy-status/23-files-picker/screenshots.mjs` (Playwright, device scale factor 2)
against `php spark serve` after a fresh `app:install --demo`, with two generated images.

| File | Caption |
|---|---|
| `screenshots/01-profile-avatar-uploaded.png` | A user without any `files.*` permission uploads an avatar in the profile (picker, upload only) |
| `screenshots/02-profile-avatar-saved.png` | After "Desa": the avatar shown in the account card, served from `files/avatar/{key}` |
| `screenshots/03-cover-choose-dialog.png` | News cover: "Tria un fitxer" lists `modules/news` (and the shared folders the editor may view, in the roots selector) |
| `screenshots/04-insert-image-dialog.png` | "Insereix una imatge" in the body's toolbar: upload or choose, alt text |
| `screenshots/05-editor-with-image.png` | The image inserted in the editor |
| `screenshots/06-phone-profile-avatar.png` | Phone width: the avatar field |
| `screenshots/07-dark-insert-image.png` | Dark mode: the insert-image dialog |
| `screenshots/08-public-post-with-image.png` | The public post with its cover and the inserted image (alt text kept) |

## Known issues

- The theme's user menu still shows initials (the avatar is shown on the profile and in `me`).
- The browse dialog lists one folder at a time (no search or paging beyond 200 files in the Core
  backend).
- `maxFiles` is enforced when the form is saved, not per token.
- Existing rich text with external images loses them when shown (by design, D-23-05).
- Environment: Composer's GitHub token is rejected (known issue 04): `phpstan/phpstan`'s zip was
  seeded into Composer's cache from a shallow clone of the locked release commit, then
  `env -u COMPOSER_AUTH composer install --prefer-source`.

## What the next session must know

- Module fields that take a file: `PickerField` (or `CrudUploadHandler::pickerField()` /
  `renderPicker()` in `callbackField()` + `accepts()` in the hooks); never kpcrudy's upload types.
- Rich text with images: `RichTextEditor::attrs(RichTextEditor::images('<module>'))`.
- Events (session 24): attachments on the private disk under `modules/events/…` need a
  `FileAccessPolicy` so they follow the event's audience.
- D-23-01 was decided as built (Decisions 23): the URLs stay.
