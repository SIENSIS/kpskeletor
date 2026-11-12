# Front end: assets, JavaScript, CSP, dark mode and PWA

How the browser side of kpskeletor works (specification: "Front-end assets and JavaScript",
"Themes" › light and dark mode, responsive design, minimal PWA, and "Security baseline" › CSP).

## Rule zero: no JavaScript or styles inside the HTML

Views never contain inline `<script>` code, `on*=""` handlers, `javascript:` URLs, `style=""`
attributes or `<style>` blocks. Markup declares behaviour with `data-*` attributes and ES modules
react to it. This is what allows the strict Content-Security-Policy below.

Allowed exceptions, all with the CSP nonce: the import map, `<script type="application/json">` data
blocks (not executable), the theme-variables `<style>` of a theme's `Views/partials/head.php`
(D-00-25) and the blocks kpcrudy and kpapy render from their own views (D-00-24).
`php spark csp:check` (run in CI) enforces this on `app/Views`, `modules/*/Views`,
`themes/*/Views` and the generator templates. Scripts set dynamic sizes through the CSSOM
(`element.style.width = '40%'` is allowed; a `style` attribute in the HTML is not).

## Where the files live

| Source (edit these) | Published copy (never edit, git-ignored) | `asset()` | Import map |
|---|---|---|---|
| `modules/Core/Assets/` | `public/assets/core/` | `core::js/app.js` | `core/<path>` |
| `modules/<Name>/Assets/` | `public/assets/modules/<name>/` (`TasksDemo` → `tasks-demo`) | `tasks-demo::css/x.css` | `tasks-demo/<path>` |
| `themes/<slug>/Assets/` | `public/themes/<slug>/` | `theme::img/logo.png` (active theme chain) | `theme/<path>` |
| `public/assets/vendor/<lib>/` (committed, `vendor.json`) | — | `vendor::bootstrap/css/bootstrap.min.css` | — |

`php spark assets:publish` copies the sources (CodeIgniter's Publisher; allowed extensions only —
css, js, mjs, map, json, images, fonts —, never PHP or HTML; symbolic links skipped; stale copies
removed) and writes `public/assets/manifest.json`: the URL and a SHA-256 prefix of every file.
It runs on `modules:sync`, `modules:enable`, `make:theme` and on deploy; `--check` fails when a
copy is out of date (CI) and `--watch` republishes on every change.

**Development:** with `CI_ENVIRONMENT = development` and `assets.devRefresh = true` (default), the
first asset lookup of a request republishes everything when a source is newer than the manifest,
so edits show up on reload without running the command. Production only reads the manifest.

`asset('core::css/app.css')` returns `/assets/core/css/app.css?v=<hash>`; a file missing from the
manifest gets its unversioned URL and a debug log line (run `assets:publish`). Module and theme
owners are lower case; `core`, `theme` and `vendor` are reserved (a module with such a name is not
published).

## ES modules and the import map

The layouts print, before `</body>`: the theme's scripts (Bootstrap), the page configuration
`<script type="application/json" id="kp-config">` (base URL, locale, the UI texts of the scripts
from `Core.js.*` in the language files), the import map and `core/app.js`, all with the nonce.

The import map lists **every published JS file with its hashed URL** (D-00-26), so imported files
are cache-busted as well. Always import with these bare specifiers, never with relative paths
(a test checks it):

```js
import { confirm } from 'core/ui/confirm.js';
import { get, post } from 'core/http.js';
import { toast } from 'core/ui/toast.js';
```

| Module | What it gives |
|---|---|
| `core/app.js` | Entry point: starts the UI behaviours and mounts the components. |
| `core/components.js` | `data-component` registry: `mountAll(root)`, `register(name, fn)`, `observe()`. |
| `core/config.js` | `config()`, `t('confirm.ok')`, `baseUrl(path)`, `jsonData(id)`. |
| `core/http.js` | `request/get/post/put/patch/del(url, { json, body, headers, silent })`: same origin only, CSRF header on unsafe methods (the regenerated token comes back in the CSRF header from the global `csrftoken` after filter, `Modules\\Core\\Filters\\CsrfTokenHeader`; `refreshCsrf()` updates the meta tag and the forms' hidden token fields, so a form submitted after a script's request still passes), JSON parsed, errors (`message`, `messages`, `error`) shown as toasts and thrown as `HttpError`. |
| `core/ui/toast.js` | `toast(text, { type: 'success'\|'danger'\|'warning'\|'info', delay })`. Text only. |
| `core/ui/modal.js` | `openModal({ title, body, buttons, size })` → value of the button pressed; full-screen on phones. |
| `core/ui/confirm.js` | `confirm(message, { title, okLabel, variant })` → boolean; `data-confirm="…"` on links, buttons and forms. |
| `core/ui/sidebar.js` | Back-office sidebar toggle (icon-only/full), remembered in the `kp_sidebar` cookie. |
| `core/ui/widget.js` | Async dashboard widget (`data-component="core/ui/widget"`): loads `data-url`, shows `html`, refreshes; `load(root, render)` for widgets with their own component (`docs/widgets.md`). |
| `core/ui/tabs-memory.js` | Remembers the active tab of each `[data-tabs]` component per page (session storage, URL hash). |
| `core/ui/theme-switch.js` | Light/dark/auto switch, `kp_theme` cookie. |
| `core/ui/cookie-notice.js` | Remembers the closed cookie notice (`kp_cookies`). |
| `core/forms/dirty-guard.js` | `form[data-dirty-guard]` (or `form_layout` with `dirtyGuard`): warns before leaving with unsaved changes. |
| `core/forms/date.js` | `data-component="core/forms/date"` with `{"min":"today","max":"+1y"}`; `formatDate()`. |
| `core/forms/editor.js` | Rich-text editor on a `<textarea>` (Quill 2, loaded on demand; see "Rich-text editor"). |
| `core/forms/file-picker.js` | File picker of module fields (`PickerField::render()`; docs/files.md "File picker"); exports `uploadFile()` and `pickDialog()` for the editor. |
| `core/theme-mode.js` | Classic script in `<head>`: resolves `auto` before the first paint. |
| `core/pwa.js` | Registers (or removes) the service worker. |

### Components

```html
<div data-component="events/calendar" data-options='{"view":"month"}'></div>
<div data-component="news/stats" data-options-id="news-stats-data"></div>
<script type="application/json" id="news-stats-data"><?= json_encode($stats, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
```

`app.js` imports `events/calendar.js` (that is, `modules/Events/Assets/js/calendar.js`) on
demand and calls its `mount(element, options)`. Escape attributes with `esc()`; build DOM with
`textContent`/`createElement` (no `innerHTML` with data, no `eval`). Elements added later are
mounted automatically. The demo: `/theme-preview/components` (development, `theme.preview = true`),
source `modules/Core/Assets/js/demo/showcase.js`.

### Rich-text editor

`Modules\Core\Frontend\RichTextEditor::attrs()` turns a textarea into the editor
(`data-component="core/forms/editor"` with the URLs of the vendored Quill 2.0.3, BSD-3-Clause,
`public/assets/vendor/quill/`, and of `core::css/editor.css`, which takes the colours from
Bootstrap's variables in light and dark mode). With kpcrudy:

```php
$crud->fieldType('notes', 'textarea', ['attrs' => RichTextEditor::attrs()]);
// A field of setTranslations(): keep kpcrudy's type of translated fields.
$crud->fieldType('body', 'translation', ['attrs' => RichTextEditor::attrs()]);
```

The textarea stays the form value (kept in sync and posted as usual) and is what users get
without JavaScript or if the library cannot load. Toolbar: headings 2–4, bold, italic,
underline, strike, lists, quote, code block, alignment, link, clear formatting; texts from
`Core.js.editor.*`. Quill only uses the CSSOM, so it works under the enforced CSP; alignment is
written as the classes `text-center`, `text-right`, `text-justify` (defined by the theme). The
server always sanitises the HTML with `Modules\Core\Security\HtmlSanitizer` (HTMLPurifier: no
`style`, scripts, handlers or iframes; alignment mapped to those classes, no other class) before
storing it and again when showing it.

**Images** (session 23, D-17-08): `RichTextEditor::attrs(RichTextEditor::images('<module>'))`
adds "Insert image" to the toolbar: the file picker's dialog uploads (signed token, the Storage
service's rules and re-encoding) or chooses a JPG, PNG, WebP or GIF of `modules/<module>` on the
public disk and asks for its alt text; pasted or dropped images go through the same upload (no
`data:` URLs). The sanitiser keeps only images of the site itself (`SiteImagesOnly`).

## Content-Security-Policy

On by default (`Config\App::$CSPEnabled = true`), strict, with nonces, on every response:

```
default-src 'self'; script-src 'self' 'nonce-…'; script-src-elem 'self' 'nonce-…';
script-src-attr 'none'; style-src 'self' 'nonce-…'; style-src-elem 'self' 'nonce-…';
style-src-attr 'none'; img-src 'self' data:; font-src 'self'; connect-src 'self';
object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';
manifest-src 'self'; worker-src 'self'; child-src 'self'
```

Never `unsafe-inline` or `unsafe-eval`. In views: `<script src="…" <?= csp_script_nonce() ?>>`,
`<style <?= csp_style_nonce() ?>>`. Defaults live in `app/Config/ContentSecurityPolicy.php`
and can be overridden from `.env`; to test a change without breaking pages, send
`Content-Security-Policy-Report-Only` instead:

```ini
contentsecuritypolicy.reportOnly = true
# contentsecuritypolicy.reportURI = 'https://reports.example.com/csp'
```

Modules add sources in their manifest; `modules:sync` validates them (only `https://` hosts,
`'self'`, `'none'` and `blob:`; never `unsafe-*`, `*`, `http:` or `data:`) and the global
`cspmodules` filter adds those of enabled modules to every response (`script-src` and
`style-src` sources also go to their `-elem` directive):

```php
public array $csp = ['frame-src' => ['https://www.youtube-nocookie.com']];
```

Per-site extra origins come from Settings › Security (`Core.cspScriptSrc`, `Core.cspImgSrc`…,
added by the same filter; docs/settings.md); the other security headers from
`Config\SecurityHeaders` (docs/security.md).

## Light and dark mode, responsive layout

See `docs/themes.md` ("Light and dark mode", "Responsive layout").

## Minimal PWA

Off by default; enable the setting `Core.pwa` (Settings › General, or
`service('settings')->set('Core.pwa', true)`). Then:

- `/manifest.webmanifest`: name, start URL (setting `Core.pwaStart`: public home or back
  office), `display: standalone`, theme colour, icons;
- `/pwa/icon-192.png`, `/pwa/icon-512.png`: generated with GD from the public theme's `logo`
  (a local PNG/JPEG/GIF/WebP under `public/`; SVG or remote logos are never read, the default
  icon is drawn instead) into `writable/pwa/`, and regenerated whenever the logo or the primary
  colour changes (the file name carries a hash);
- `/sw.js`: served from the root so it controls the whole site; it **caches nothing** (no Cache
  API, no `respondWith()`), so authenticated pages and data always come from the network;
- the layouts add `<link rel="manifest">`, `theme-color` for light and dark and the Apple touch
  icon; `core/pwa.js` registers the worker, and unregisters it when the PWA is switched off.

All three routes answer 404 while `Core.pwa` is off.
