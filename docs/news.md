# News (reference module)

`modules/News` is the module every other module copies (specification "Modules" › "News"). It
uses every piece of the skeleton: a manifest with resources, grants, menus, settings, widgets, a
task, a notification type, a menu link type, backups and an API resource; kpcrudy CRUDs through
the Core; a kpapy resource; the Storage service; HTMLPurifier; demo data; tests.

## What it does

- **Posts** (`news`, texts in `news_translations`: title, summary, body; Catalan required,
  Spanish and English optional): type `article` or `notice`, category, visibility, status,
  publication and expiry dates, a cover image, an author. Notices also have a **priority**
  (`info`, `warning`, `critical`) and can be **pinned**.
- **Categories** (`news_categories` + `news_category_translations`): slug, order, active.
- **Visibility:** `public` (public site, feed), `private` (signed-in users) or `groups` (the
  groups in `news_groups`, edited with kpcrudy `setRelationNN()`).
- **Read receipts** (`news_reads`): "mark as read" from the *Notices* widget and the reader
  screen; authors with `news-posts.readers` see who read a post.

## Screens and URLs

| URL | Resource / permission | What |
|---|---|---|
| `backend/news` | `news-read.view` (every group) | Reader screen: posts the user may read, filters (type, category, unread) |
| `backend/news/read/{id}` | `news-read.view` | A post (masked id; anything unreadable is a 404) |
| `POST backend/news/read/{id}/mark` | `news-read.view` | Mark as read (`return` = internal path) |
| `backend/news/posts` | `news-posts.*` | Every post (kpcrudy); operations `publish`, `unpublish`, `readers`; bulk "Publish" |
| `backend/news/mine` | `news-mine.*` | "My posts": the `where('author_id', me)` pattern |
| `backend/news/categories` | `news-categories.*` | Categories (kpcrudy) |
| `news`, `news/{slug}`, `news/category/{slug}` | public | Public posts only (`News.perPage` per page) |
| `news/feed.xml` | public | RSS 2.0 of the latest public posts (`News.feedItems`) |
| `GET api/v1/news`, `GET api/v1/news/{id}` | `news-api.list` / `news-api.show` (every group) | Readable posts as JSON (kpapy, read-only) |

Permission names and URLs: decision **D-17-01** (decided: the API has its own resource
`news-api`). Images inside the body: decision **D-17-08** (decided: an "Insert image" button with
the file picker, session 23).

## Rules

- **Who reads what** (`Services/NewsVisibility.php`, D-17-04): a post is readable when it is not
  deleted, its status is `published` or `scheduled`, `published_at` has passed and `expires_at`
  (if any) has not. Guests and the public site: only `public`. Signed-in users: `public`,
  `private` and `groups` posts of one of their groups. The same rule serves the reader screen, the
  widgets, the public site, the feed and the API (there, through kpapy's `where(Closure)`, D-17-07).
- **Own posts** (`Controllers/Posts.php`): without `news-posts.author` the author field is hidden
  everywhere (`hideFields()`) and a user only edits, deletes and (un)publishes their own posts
  (per-record `setPermission()` rules, checked again by kpcrudy on every row and bulk action). New
  posts belong to their author.
- **Dates** are edited in the site's time zone (`Core.timezone`) and stored in UTC (kpcrudy 1.2,
  set by `CrudFactory`).
- **Statuses** (`Services/Publisher.php`, D-17-03): new posts are drafts; `publish` makes a post
  `published` (or `scheduled` when `published_at` is in the future); `unpublish` turns it back
  into a draft; the form never sets the status. The task *Publish scheduled news and expire
  notices* (`Tasks/ExpireNotices.php`, every 5 minutes) releases due scheduled posts and marks
  notices past `expires_at` as `expired` (unpinned). Readers never wait for it.
- **Notifications** (`Notifications/NewNotice.php`): a `private` notice notifies every user with
  `news-read.view`, a `groups` notice the members of its groups, once (`news.notified_at`), when
  it is published or released. Users choose the channels in their notification preferences.
- **Body**: sanitised with HTMLPurifier (`Services/BodySanitizer.php`) before kpcrudy reads the
  form (D-17-02) and again when a post is shown. The rich-text editor replaces the textarea in
  session 20.
- **Slugs** (`Services/Slugger.php`): from the Catalan title (or the given slug), transliterated,
  unique (`-2`, `-3`…); kept when the title changes unless the slug field is edited.
- **Cover**: kpcrudy `image` field with an upload handler on the Storage service
  (`Storage/CoverUploads.php`, D-17-06): public disk, folder `modules/news`, JPG/PNG/WebP up to
  5 MB, re-encoded; the column keeps the file key. Session 23 switches it to the file picker.

## Dashboard widgets

| Id | Title | Permission | Options |
|---|---|---|---|
| `news.notices` | Notices | `news-read.view` | `limit` (5), `unreadOnly` |
| `news.latest` | Latest news | `news-read.view` | `limit` (5) |

*Notices*: pinned first, then critical, warning, info, newest first; each with "mark as read"
(back to the dashboard). Administrators enable or reorder them per group in Settings › Dashboard
widgets (`docs/widgets.md`).

## Settings, menus and other declarations

- Settings (Settings › News, group "Public site"): `News.perPage` (10, 1–100), `News.feedItems`
  (20, 1–100).
- Menu: "News" (sidebar, the reader) with "All posts", "My posts" and "Categories" under it, each
  shown only with its permission.
- Menu link type `news.category` (`Menu/CategoryLink.php`, D-17-05) for the public menus of
  session 21.
- Backup: the tables and the files of `modules/news` (Backup, session 27). The `news.notices`
  calendar arrives in session 25; read receipts and notices in the API in session 26.

## Activity log

CRUD writes, `publish`/`unpublish`, bulk actions and exports are logged by the Core
(`AuditedCrud`). The task writes `News.notices_updated` when it changes something. "Mark as read"
is a personal action and is not logged.

## Demo data

`php spark modules:demo News` (after `modules:demo Auth` for the authors): two categories, three
public articles, a private article, a pinned critical notice for everybody, a warning notice for
the `editor` group, an information notice, a scheduled article and a draft, all in Catalan.

## Tests

`vendor/bin/phpunit modules/News`: `PostsCrudTest` (read-only user, forged POSTs, own posts,
`author` hidden, "My posts", sanitised body, slugs, publish and notifications, readers),
`ReaderTest` (visibility, details, mark as read), `WidgetsAndTaskTest` (notices per audience,
widget "mark as read", the task), `PublicSiteTest` (public pages and feed), `NewsApiTest` (list
and details with visibility, scopes, read-only), `NewsModuleTest` (manifest, migrations
up/down/up, sanitiser, slugs, link type). Base class `Tests/NewsTestCase.php`.
