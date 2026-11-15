# News module

**Purpose:** the reference module every other module copies: posts (articles and notices) with
translations, categories, visibility (public / private / groups), read receipts, a reader screen
for every user, the back office with kpcrudy, the public site and RSS, two dashboard widgets, a
scheduled task, a notification type, a menu link type and the `news` API resource. Full
description: `docs/news.md`.

## Resources and permissions

- `news-posts` (crud − `import`; extras `publish`, `author`, `readers`): every post. Without
  `author`: author hidden, only own posts changed.
- `news-mine` (crud − `import`, `export`, trash; extra `publish`): "My posts" (`where()`).
- `news-categories` (crud − `import`, trash).
- `news-read` (page: `view`): the reader screen.
- `news-api` (crud − everything but `list`, `show`): the `news` API resource (D-17-01).
- Grants: admin all; editor posts list/show/add/edit/publish, categories list/show, `news-mine.*`,
  `news-read.*`, `news-api.*`; user and superadmin `news-read.*`, `news-api.*`.
- Settings `News.perPage`, `News.feedItems`. Menu: "News" (reader) with "All posts", "My posts",
  "Categories". Widgets `news.notices`, `news.latest`. Task `Tasks/ExpireNotices.php`.
  Notification `Notifications/NewNotice.php`. Link type `Menu/CategoryLink.php`. API
  `Controllers/Api/Posts.php` (`api/v1/news`).
- Tables: `news_categories`, `news_category_translations`, `news`, `news_translations`,
  `news_groups` (surrogate `id`, unique pair), `news_reads` (one migration each).

## Main pieces

- `Services/NewsVisibility.php` (who reads what; the only place the rule lives),
  `NewsRepository.php` (read queries with texts in the user's language, Catalan fallback),
  `Publisher.php` (publish, notify once, release scheduled, expire), `ReadReceipts.php`,
  `FeedWriter.php` (RSS with XMLWriter). From the Core: `Modules\Core\Security\HtmlSanitizer`
  (HTMLPurifier) and `Modules\Core\Text\Slugger` (moved there in session 19 for Pages and Events);
  `Modules\Core\Content\{ContentTranslations,Audience,PublishWorkflow}` and
  `Modules\Core\Storage\CrudUploadHandler` (extracted in session 20, D-20-07). The body is edited
  with the Core's rich-text editor (`RichTextEditor::attrs()`, D-20-03).
- `Controllers/Posts.php` (`index` = `news-posts`, `mine` = `news-mine`), `Categories.php`,
  `Reader.php`, `PublicNews.php`, `Api/Posts.php`; `Storage/CoverUploads.php` (the cover's file
  picker: `callbackField()` + `CoverUploads::accepts()` in `beforeSave()`, session 23). The body's
  editor has "Insert image" (`RichTextEditor::images('news')`, folder `modules/news`).

## Decisions

D-17-01 (separate API resource `news-api`) and D-17-08 ("Insert image" with the file picker in session 23) decided by the owner, D-17-02 … D-17-06 accepted, D-17-07 applied (kpapy `where(Closure)`)
(`docs/decisions.md`); D-00-35, D-00-36, D-00-40, D-00-41, D-00-42.

## Calendar (session 25)

Notices with an event date (`event_starts_at`, `event_ends_at`, `event_all_day`; migration
`AddEventDateToNews`) are published to `news.notices` while published and readable
(`Services/NoticeCalendar.php`, also the calendar's `CalendarSource`); every write and state
change calls `NoticeCalendar::sync()` (CRUD `afterWrite`, publish/unpublish, `Publisher`).
Tests `Tests/NoticeCalendarTest.php`. D-25-06.

## Open tasks

- Session 21: the Menus module consumes `Menu/CategoryLink.php` (formalise the contract).
- Session 26: read receipts and notices in the API.

## How to test

`vendor/bin/phpunit modules/News` (base `Tests/NewsTestCase.php`: Auth fixture, catalogue and
grants, News widgets, `newsPost()`, `code()`, `body()` with decoded entities, `assertNotFound()`).
By hand: `php spark modules:demo Auth`, `php spark modules:demo News`, then `backend/news`,
`backend/news/posts`, `news`, `news/feed.xml`.
