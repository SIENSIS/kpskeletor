# Front end: assets, JavaScript, CSP, dark mode and PWA

How the browser side of kpskeletor works (specification: "Front-end assets and JavaScript",
"Themes" › light and dark mode, responsive design, minimal PWA, and "Security baseline" › CSP).
The step-by-step tutorial ["A new view with JavaScript"](#tutorial-a-new-view-with-javascript)
at the end builds the `Tasks` module's board.

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
| `core/ui/flash.js` | Component `core/ui/flash` (theme partial `partials/flash`): shows the session messages as toasts and removes the alerts kept for users without JavaScript. |
| `core/ui/bell.js` | Notifications bell (`core/ui/bell`, `partials/notifications_bell`): loads the latest notifications, refreshes the unread count, marks all as read. |
| `core/cookies.js` | `getCookie(name)`, `setCookie(name, value, days)` for the preference cookies (no personal data, SameSite=Lax). |
| `core/ui/sidebar.js` | Back-office sidebar toggle (icon-only/full), remembered in the `kp_sidebar` cookie. |
| `core/ui/widget.js` | Async dashboard widget (`data-component="core/ui/widget"`): loads `data-url`, shows `html`, refreshes; `load(root, render)` for widgets with their own component (`docs/widgets.md`). |
| `core/ui/tabs-memory.js` | Remembers the active tab of each `[data-tabs]` component per page (session storage, URL hash). |
| `core/ui/theme-switch.js` | Light/dark/auto switch, `kp_theme` cookie. |
| `core/ui/cookie-notice.js` | Remembers the closed cookie notice (`kp_cookies`). |
| `core/forms/dirty-guard.js` | `form[data-dirty-guard]` (or `form_layout` with `dirtyGuard`): warns before leaving with unsaved changes. |
| `core/forms/date.js` | `data-component="core/forms/date"` with `{"min":"today","max":"+1y"}`; `formatDate()`. |
| `core/forms/editor.js` | Rich-text editor on a `<textarea>` (Quill 2, loaded on demand; see "Rich-text editor"). |
| `core/forms/file-picker.js` | File picker of module fields (`data-component="core/forms/file-picker"`, rendered by `Modules\Core\Storage\Picker\PickerField::render()`; [`files.md`](files.md) "File picker"): a hidden `[data-picker-value]` input holds the chosen keys; *Upload* sends each file with the field's signed token to `files/picker/upload`, *Choose* lists `files/picker/browse`. Exports `uploadFile(options, file, progress)`, `pickDialog(options, { alt: true })`, `precheck()` and `loadStyle()` for other components (the editor's "Insert image"). |
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
// A field of setTranslations(): its `attrs` option (kpcrudy >= 1.3) keeps the language tabs.
$crud->setTranslations('news_translations', 'news_id', 'locale', ['title', 'body'], $locales, [
    'attrs' => ['body' => RichTextEditor::attrs()],
]);
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

### Calendar

The Events module's `events/calendar` component loads the vendored FullCalendar 7.1.3 (MIT,
`public/assets/vendor/fullcalendar/`) on demand, like the editor: version 7 ships its CSS as
files and only uses the CSSOM, so it needs no CSP exception. Its colours come from Bootstrap's
variables (`events::css/calendar.css`). Each event's text colour (`contrastColor` in the feed,
black or white) is computed from its colour for WCAG AA contrast
(`EventRepository::textColor()`). See `docs/events.md`.

### Password fields

Every `<input type="password">` on a page that loads the Core's JavaScript gets a show/hide
button automatically (`core/forms/password-reveal.js`, started by `core/app.js`): module views,
the forms kpcrudy renders (Users › new user…) and fields added later (a `MutationObserver`). A
module writes a plain password input and does nothing else.

- **Markup:** the input (or its `.form-floating` wrapper) goes into a Bootstrap `.input-group`
  (an existing one is reused) followed by a `type="button"` toggle (`bi-eye` / `bi-eye-slash`,
  `aria-hidden`) with `aria-controls`, `aria-pressed` and an accessible name that changes
  ("Mostra la contrasenya" / "Amaga la contrasenya": `Core.js.password.*`, ca/es/en). Validation
  messages (`.invalid-feedback`) that followed the input move into the group
  (`.has-validation`), so `is-invalid` errors keep showing; help texts, `required`, autofocus
  and `data-dirty-guard` are unchanged.
- **Behaviour:** hidden by default; clicking keeps the focus in the field; Tab + Space/Enter work;
  the field goes back to `type="password"` when its form is submitted (capture phase, before
  other handlers) and on `pagehide`, so the browser never stores or restores it as plain text.
  Without JavaScript nothing changes; nothing changes on the server.
- **Opt out:** `data-no-reveal` on the input (e.g. a field that must never be shown, such as a
  confirmation typed blind on purpose).
- The web installer's pages load no JavaScript yet (assets are published during installation):
  their password fields get the button when the installer loads the Core's scripts (session 39).

## Vendored libraries

Third-party front-end libraries are **committed** under `public/assets/vendor/<lib>/` (no CDN, no
npm build): Bootstrap 5.3.8, Bootstrap Icons 1.13.1, Quill 2.0.3 and FullCalendar 7.1.3 today.
`public/assets/vendor/vendor.json` records, per library, `version`, `licence`, `source` (an
`https://` download URL), `homepage` and the SHA-256 of **every** file in its folder.
`modules/Core/Tests/VendorAssetsTest.php` fails when a file is missing, extra or changed, and
`php spark assets:publish --check` (CI) and `php spark security:check` (`dependencies.vendor`)
check the same checksums through `Modules\Core\Frontend\VendorChecksums` (a changed or missing
file, or a file the manifest does not list, `+ <lib>/<file>`).

Reference them with `asset('vendor::<lib>/<path>')` (versioned with the checksum) or, in a theme
manifest, `vendor:<path>`. Libraries that are not ES modules are loaded on demand by the component
that needs them (the editor and the calendar take the script URLs in their options) instead of on
every page. A library must work under the CSP (no `eval`, styles through the CSSOM or CSS files).
How to add or update one (download, licence, checksums): [`dependencies.md`](dependencies.md).

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

Keyboard access to scrolled regions: `core/app.js` gives `tabindex="0"` to every
`.table-responsive` (or `[data-scroll-region]`) whose content is wider than its box and holds no
focusable element (`core/ui/scroll-regions.js`, updated on resize), so it can be focused and
scrolled with the arrow keys (WCAG 2.1.1). The end-to-end tests check every main screen with axe
in both modes ([`development.md`](development.md), "End-to-end and accessibility tests").

## Minimal PWA

The web app manifest, the generated icons and the service worker (which caches nothing) are
described in [`themes.md`](themes.md) ("PWA manifest"); `core/pwa.js` registers the worker when
the page links the manifest and unregisters it otherwise.

## Tutorial: a new view with JavaScript

This tutorial walks through a complete page with JavaScript: the **to-do task board** of the
`Tasks` tutorial module (`backend/tasks`). The code is real and finished; every excerpt below is
copied from it. Files:

| Piece | File |
|---|---|
| Routes | `modules/Tasks/Config/Routes.php` |
| Controller | `modules/Tasks/Controllers/Board.php` (`index()`, `data()`, `move()`) |
| Views | `modules/Tasks/Views/board/index.php`, `modules/Tasks/Views/board/card.php` |
| Component | `modules/Tasks/Assets/js/board.js` (`data-component="tasks/board"`) |
| Stylesheet | `modules/Tasks/Assets/css/tasks.css` |
| Texts | `modules/Tasks/Language/{ca,es,en}/Tasks.php` (`boardTitle`, `board.*`) |
| Test | `modules/Tasks/Tests/BoardTest.php` |

To try it: `php spark modules:enable Tasks` (the module is installed disabled), `php spark
modules:demo Auth`, `php spark modules:demo Tasks`, then open `backend/tasks`.

### 1. The permission, the route and the menu entry

The page is a resource of type `page` in the manifest, so the Core generates the permission
`tasks-board.view`; `$defaultGrants` gives `tasks-board.*` to every group, and `$menu` adds the
sidebar entry (`['resource' => 'board', 'label' => 'Tasks.menu.board', 'icon' => 'bi bi-kanban', 'order' => 40]`).

```php
'board' => [
    'label' => 'Tasks.res.board',
    'type'  => 'page',
],
```

Every route of the page — the page itself, its JSON data and the move endpoint — carries the
`can:` filter (the `backend` chain adds the login and the rest):

```php
$routes->group('backend/tasks', ['namespace' => 'Modules\Tasks\Controllers'], static function (RouteCollection $routes): void {
    $board = ['filter' => 'can:tasks-board.view'];

    $routes->get('/', 'Board::index', $board + ['as' => 'tasks-board']);
    $routes->get('board/data', 'Board::data', $board);
    $routes->post('board/(:segment)/move', 'Board::move/$1', $board);
});
```

The CRUD routes of `backend/tasks/tasks`, `…/projects` and `…/requests` are registered before
this group, so `backend/tasks/…` does not swallow them. `php spark permissions:check` fails if a
`backend/` route has no `can:` filter.

### 2. The controller method and the view

`Board` extends `BackendController`; `render()` resolves the view through the active theme and
adds the layout, the breadcrumbs and — because the data has a `title` (and an `icon`) — the
standard page header (`components/page_header`):

```php
return $this->render('Modules\Tasks\Views\board\index', [
    'title'    => lang('Tasks.boardTitle'),
    'icon'     => 'bi bi-kanban',
    'columns'  => $this->columns($user, $project),
    // … projects, project, seesAll, canAdd
    'options'  => [
        'dataUrl' => site_url('backend/tasks/board/data') . ($project !== null ? '?project=' . $project : ''),
        'css'     => asset('tasks::css/tasks.css'),
        'texts'   => [
            'moved'      => lang('Tasks.board.moved'),
            'moveTo'     => lang('Tasks.board.moveTo'),
            'move'       => lang('Tasks.board.move'),
            'priorities' => Tasks::options('priorities', TaskRepository::PRIORITIES),
            'empty'      => lang('Tasks.board.emptyColumn'),
            'statuses'   => Tasks::options('statuses', TaskRepository::STATUSES),
        ],
    ],
]);
```

The view extends the theme layout and fills `content`; the page header is printed by the layout,
not by the view:

```php
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="tasks-board" data-component="tasks/board" data-options="<?= esc((string) json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'attr') ?>">
    …
</div>
<?= $this->endSection() ?>
```

**The page works without JavaScript.** The columns are drawn on the server, and every card the
user may move has a small form (`<form method="post" … data-board-move>` with `csrf_field()`, a
status `<select>` and a *Move* button). The component only improves it (drag and drop, no reload).
Build pages this way: the server decides what is shown and allowed, the script adds comfort.

### 3. The markup: `data-component`, `data-options` and JSON blocks

- `data-component="tasks/board"`: `core/app.js` finds the element, imports `tasks/board.js`
  through the import map and calls its `mount(element, options)`. The name is
  `<owner>/<path>` without `.js`; the owner of a module is its name in lower case with hyphens
  (`Tasks` → `tasks`, `TasksDemo` → `tasks-demo`).
- `data-options`: a small JSON object, escaped with `esc(…, 'attr')`. The board passes the data
  URL, its stylesheet URL and its texts.
- Larger data goes in a JSON data block referenced by `data-options-id`, which is not executable
  and therefore allowed by the CSP. The board does not need one (its columns come from the
  server's HTML and from its endpoint); the dashboard does:

  ```php
  <div class="dashboard" data-component="dashboard/dashboard" data-options-id="dashboard-config">
  …
  <script type="application/json" id="dashboard-config"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  ```

  (`modules/Dashboard/Views/dashboard.php`; always encode with the `JSON_HEX_*` flags so the data
  cannot close the `<script>` element.) Any script can also read a block with
  `jsonData('<id>')` from `core/config.js`.
- Hooks for the script are `data-*` attributes (`data-board-column`, `data-board-card`,
  `data-move-url`, `data-board-status`, `data-board-count`), never `id`s shared with styling and
  never `on*=""` handlers.

### 4. The ES module

`modules/Tasks/Assets/js/board.js` is a native ES module that exports `mount(root, options)`:

```js
import { get, post } from 'core/http.js';

export function mount(root, options) {
    const texts = options.texts || {};
    const status = root.querySelector('[data-board-status]');
    …
}
```

Shared code is imported with the import-map prefixes, never with relative paths (relative URLs
would not carry the version hash; `modules/Core/Tests/FrontendAssetsTest.php` fails on any
`./` or `../` import and on any specifier missing from the import map):

| Import | From |
|---|---|
| `import { get, post } from 'core/http.js'` | JSON requests with CSRF (used by the board) |
| `import { confirm } from 'core/ui/confirm.js'` | a confirmation modal (`await confirm(text, { variant: 'danger' })`) |
| `import { toast } from 'core/ui/toast.js'` | a toast (`toast(text, { type: 'success' })`) |
| `import { t, jsonData } from 'core/config.js'` | the Core's script texts, JSON data blocks |
| `import { … } from 'tasks/<file>.js'` | another file of the **same module** (`modules/Tasks/Assets/js/<file>.js`), also by bare specifier |

The board is a single file: it needs no confirmation (a move is reversible) and no toast of its
own, because `core/http.js` already shows errors as toasts; it announces success in a live region
instead. A bigger component splits its code into files of its own module and imports them as
`'<owner>/<path>.js'` (for example a hypothetical `modules/Tasks/Assets/js/board/card.js` would be
`'tasks/board/card.js'`).

Build the DOM with `createElement` and `textContent`, never `innerHTML` with data: the board's
`buildCard()` builds the same markup as `Views/board/card.php` with DOM methods when it redraws the
columns.

### 5. Calling the JSON endpoint

The component turns each move form into a JSON request, then reloads the columns from the data
endpoint so the server stays the only judge of what the user sees and may move:

```js
const move = async (url, target) => {
    try {
        const result = await post(url, { json: { status: target } });
        say(result && result.message ? result.message : texts.moved);
    } catch (error) {
        // core/http.js already showed the error as a toast.
    }

    if (options.dataUrl) {
        try {
            const data = await get(options.dataUrl, { silent: true });

            if (data && data.columns) {
                redraw(data.columns);
            }
        } catch (error) {
            window.location.reload();
        }
    }
};
```

`core/http.js` sends `X-Requested-With` and `Accept: application/json`, adds the CSRF header on
unsafe methods (from the `csrf-header` / `csrf-token` meta tags of `partials/head`), takes the
regenerated token from the response and updates the page's forms, refuses other origins, and turns
a JSON error (`message`, `messages` or `error`) into a danger toast and a thrown `HttpError`
(`status`, `data`); `silent: true` skips the toast.

On the server, the route filter checks `tasks-board.view` and `move()` checks the record rules
(`TaskRepository`):

| Case | Answer |
|---|---|
| unknown masked id, or a to-do task the user may not see | 404 |
| visible but neither `tasks-tasks.edit` nor an assignee | 403 JSON `{"message": …}` (or a redirect with an error for a form post) and `security_log('Tasks', 'move_refused', …)` |
| a status outside `todo`, `doing`, `done` | 422 JSON |
| allowed | `{"id", "status", "message"}` and `activity_log('Tasks', 'moved', …)` with the change |

The same endpoint serves the no-JavaScript form: it answers JSON when the request accepts JSON or
is an AJAX request, and redirects to `backend/tasks` with a flash message otherwise. It reads the
status from a JSON body or from the form field.

### 6. The module's stylesheet

`modules/Tasks/Assets/css/tasks.css` styles the drop targets and the dragged card with
Bootstrap's CSS variables (so dark mode works):

```css
.tasks-board-list.is-drop-target {
    outline: 2px dashed var(--bs-primary);
    outline-offset: -4px;
    background-color: var(--bs-primary-bg-subtle);
}
```

The layouts have no `head` section, so a page does not add `<link>` tags to `<head>` from its
view. The controller passes the versioned URL (`'css' => asset('tasks::css/tasks.css')`) in the
options and the component adds the stylesheet once:

```js
if (options.css && !document.querySelector('link[data-tasks-css]')) {
    const link = element('link');
    link.rel = 'stylesheet';
    link.href = options.css;
    link.dataset.tasksCss = '';
    document.head.append(link);
}
```

The Core's editor and file picker and the Events calendar and Files manager load their CSS the
same way. A stylesheet every page needs belongs in a theme's `$styles` instead.

### 7. Translations for the script

UI texts live only in the language files (ca, es, en, same keys). The board's texts are read with
`lang()` in the controller and passed in `options.texts` (see step 2):

```php
'board' => [
    'help'         => 'Drag a card to another column, or choose its status and press "Move".',
    'emptyColumn'  => 'No to-do tasks.',
    'moveTo'       => 'Move to',
    'move'         => 'Move',
    'moved'        => 'To-do task moved.',
    // …
],
```

The Core's own script texts (`Core.js.*`: confirmations, HTTP errors, widgets…) reach every page
in the `kp-config` data block and are read with `t('http.forbidden')` from `core/config.js`. A
module does not add keys to `Core.js`; it passes its texts in its options or in its own JSON
data block.

### 8. Syncing assets

The browser never reads `modules/<Name>/Assets/`; it reads the published copies:

| Source | Copy | URL / specifier |
|---|---|---|
| `modules/Tasks/Assets/js/board.js` | `public/assets/modules/tasks/js/board.js` | `tasks/board.js` in the import map |
| `modules/Tasks/Assets/css/tasks.css` | `public/assets/modules/tasks/css/tasks.css` | `asset('tasks::css/tasks.css')` → `…/tasks.css?v=<hash>` |

- After adding or changing a file in `Assets/`, run `php spark assets:publish` (or
  `php spark modules:sync`, which also publishes). It copies the allowed file types, removes stale
  copies and rewrites `public/assets/manifest.json` with a SHA-256 prefix per file; that prefix is
  the `?v=` of every URL and of every import-map entry, so browsers fetch a changed file at once
  and cache an unchanged one.
- While developing: `php spark assets:publish --watch` republishes on every change, or rely on
  `assets.devRefresh` (default `true`, only with `CI_ENVIRONMENT = development`): the first asset
  lookup of a request republishes when a source is newer than the manifest, so a reload is enough.
- In CI: `php spark assets:publish --check` fails when a copy is out of date.
- **Never edit `public/assets/modules/…`**: the next publish overwrites or deletes your change.

### 9. Debugging

- **The import map**: view the page source; before `</body>` the layout prints
  `<script type="importmap" nonce="…">{"imports":{"core/app.js":"…", "tasks/board.js":"…?v=…", …}}`
  (`partials/scripts.php`). If your file is not listed, it was not published.
- **Devtools**: the Console shows the component registry's warnings and CSP reports; the Network
  tab shows the JSON requests (status, response body, the CSRF header); the Elements tab shows
  `data-component-mounted="true"` on an element whose `mount()` succeeded.

| Symptom | Cause | Fix |
|---|---|---|
| `The component tasks/board could not be mounted` with "Failed to resolve module specifier" or a 404 | the file is not published, or the name does not match the file | `php spark assets:publish`; check `data-component` = `<owner>/<path without .js>` |
| `Invalid component name …` | upper case or a missing prefix in `data-component` | use `tasks/board`, not `Tasks/board` or `board` |
| `The component … has no mount() function` | the module does not export `mount` | `export function mount(root, options)` |
| an import fails to resolve | a relative path or a wrong prefix (`core/ui/toast` without `.js`, `Tasks/…`) | bare specifier with the extension: `core/ui/toast.js`, `tasks/…` |
| `Invalid data-options on component` | the attribute is not valid JSON | build it with `json_encode()` and `esc(…, 'attr')` |
| "Refused to execute inline script / apply inline style" | inline code, an `on*=` handler or `style=""` in a view | move the code to the ES module; `php spark csp:check` lists them |
| a POST refused by the CSRF check | the request did not go through `core/http.js` (no CSRF header), or the page has no `csrf-token` meta tag | use `post()` from `core/http.js` |
| old code still runs | the browser cached a copy published before | republish; the `?v=` hash changes with the content |

### 10. A minimal test

The endpoints are tested with a feature test (`modules/Tasks/Tests/BoardTest.php`, base
`TasksTestCase`): the page contains the component, the JSON endpoint returns only what the user may
see, and forged moves are refused.

```php
public function testAUserSeesOnlyTheToDoTasksAssignedToThem(): void
{
    $pau   = $this->createUser('pau@example.com', 'user');
    $other = $this->createUser('laia@example.com', 'user');
    $this->task('La meva', [], [(int) $pau->id]);
    $this->task('D’una altra persona', [], [(int) $other->id]);
    $this->login('pau@example.com');

    $page = $this->body('GET', 'backend/tasks');
    $this->assertStringContainsString('La meva', $page);
    $this->assertStringNotContainsString('D’una altra persona', $page);
    $this->assertStringContainsString('data-component="tasks/board"', $page);

    $data = json_decode((string) $this->send('GET', 'backend/tasks/board/data')->response()->getBody(), true);
    $this->assertSame(['La meva'], array_column($data['columns']['todo'], 'title'));
    $this->assertTrue($data['columns']['todo'][0]['canMove'], 'An assignee may move their to-do task.');
}
```

The same file checks 403 without `tasks-board.view` (page and data), a move as JSON and as a form
post (with the activity log entries), and forged moves: 404 for an invisible to-do task or a bad
code, 422 for an unknown status, 403 plus a `move_refused` security entry for a reader, and 404
for a `GET` on the move URL. Run it with `vendor/bin/phpunit modules/Tasks/Tests/BoardTest.php`.
The skeleton has no JavaScript unit-test runner; `FrontendAssetsTest` checks every module's
imports, and the Playwright screenshots of each session (`package.json`) exercise the pages.
