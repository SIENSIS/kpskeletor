# Tutorial 3 — A new view with JavaScript

The to-do task **board** (`backend/tasks`): three columns (to do, doing, done), cards moved with
drag and drop or with a small form, filtered by project. It shows how a back-office page with
JavaScript is built under the project's rules: the page is drawn on the server and works without
JavaScript, an ES module improves it, every endpoint checks permissions, and nothing runs inline
(strict Content-Security-Policy).

The full walk-through, with every excerpt copied from the code, is in
[`frontend.md`, "Tutorial: a new view with JavaScript"](../frontend.md#tutorial-a-new-view-with-javascript).
This page is the summary, the steps to follow it on a fresh install, and its tests.

## Files

| Piece | File |
|---|---|
| Resource, grants, menu | `modules/Tasks/Config/Module.php` (`'board' => ['type' => 'page']`) |
| Routes | `modules/Tasks/Config/Routes.php` (`backend/tasks`, `board/data`, `board/(:segment)/move`, all with `can:tasks-board.view`) |
| Controller | `modules/Tasks/Controllers/Board.php` (`index()`, `data()`, `move()`) |
| Rules | `modules/Tasks/Services/TaskRepository.php` (`canSee()`, `canMove()`, `setStatus()`) |
| Views | `modules/Tasks/Views/board/index.php`, `modules/Tasks/Views/board/card.php` |
| ES module | `modules/Tasks/Assets/js/board.js` (`data-component="tasks/board"`) |
| Stylesheet | `modules/Tasks/Assets/css/tasks.css` |
| Texts | `modules/Tasks/Language/{ca,es,en}/Tasks.php` (`boardTitle`, `board.*`) |
| Tests | `modules/Tasks/Tests/BoardTest.php` |

## The steps

1. **A `page` resource** gives the permission `tasks-board.view`; the menu entry and every route
   use it ([step 1](../frontend.md#1-the-permission-the-route-and-the-menu-entry)).
2. **The controller renders a view** through the active theme (`$this->render()`), which extends
   `$layout` and fills the `content` section; the server draws the columns and, for each card the
   user may move, a form with `csrf_field()` and a status select
   ([step 2](../frontend.md#2-the-controller-method-and-the-view)).
3. **The markup** names the component (`data-component="tasks/board"`) and passes its options as
   escaped JSON in `data-options` (URLs from `site_url()`/`asset()`, texts from `lang()`)
   ([step 3](../frontend.md#3-the-markup-data-component-data-options-and-json-blocks)).
4. **The ES module** exports `mount(element, options)`; it imports Core helpers with bare
   specifiers (`core/http.js`), never relative paths
   ([step 4](../frontend.md#4-the-es-module)).
5. **The JSON endpoints** (`board/data`, `board/{code}/move`) are ordinary routes with the same
   `can:` filter; `move()` also checks the record rule (an editor or an assignee) and answers
   404 for a to-do task the user cannot see, 403 for one they see but may not move, 422 for an
   unknown status ([step 5](../frontend.md#5-calling-the-json-endpoint)).
6. **The stylesheet** is loaded by the component from `options.css` (no `<style>` and no
   `style=""` in the views) ([step 6](../frontend.md#6-the-modules-stylesheet)).
7. **Texts** reach the script through `data-options`, already in the user's language
   ([step 7](../frontend.md#7-translations-for-the-script)).
8. **Publishing**: `php spark assets:publish` (or `--watch` while editing) copies
   `modules/Tasks/Assets/` to `public/assets/modules/tasks/`; never edit the copies
   ([step 8](../frontend.md#8-syncing-assets)).

## Follow it on a fresh install

```bash
php spark app:install --env=development --db=sqlite --demo
php spark modules:enable Tasks
php spark modules:demo Tasks
php spark serve
```

Sign in as `admin` (the password you gave the installer) and open `backend/tasks`:

- the board shows the demo to-do tasks in three columns; the project pills filter it
  (`?project=<id>`);
- drag a card to another column (or use its *Move* form): the card moves without a reload and
  `GET backend/tasks/board/data` redraws it; the details of the to-do task now show the new status
  and, when it left "To do", its start date;
- open the browser console: no Content-Security-Policy violation;
- disable JavaScript and reload: the *Move* forms still work (post/redirect/get).

Sign in as a demo `user`: the board shows only the to-do tasks assigned to them, and they can move
those.

`php spark csp:check` must pass (no inline `<script>`, `on*=` handlers or `style=""` in views).

## Tests

`modules/Tasks/Tests/BoardTest.php` (feature tests of the page and of the component's JSON
endpoints; the skeleton has no JavaScript unit-test runner, see tutorial 14):

- `testTheBoardNeedsItsPermission`
- `testAUserSeesOnlyTheToDoTasksAssignedToThem`, `testUsersWhoListToDoTasksSeeThemAll`
- `testAnAssigneeMovesTheirToDoTaskAndTheMoveIsLogged`
- `testForgedMovesAreRefused` (another user's card → 404, a card seen but not movable → 403
  logged as `move_refused`, an unknown status → 422, a `GET` on the move URL)
- `testEditorsMoveAnyToDoTask`

`modules/Core/Tests/FrontendAssetsTest.php` checks every module's ES modules (bare specifiers,
no relative imports).

```bash
vendor/bin/phpunit modules/Tasks/Tests/BoardTest.php
```

Next: [tutorial 4](04-widget.md), dashboard widgets.
