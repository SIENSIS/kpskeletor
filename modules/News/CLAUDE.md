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
- `news-read` (page: `view`; extras `list`, `show` for the API, D-17-01 open).
- Grants: admin all; editor posts list/show/add/edit/publish, categories list/show, `news-mine.*`,
  `news-read.*`; user and superadmin `news-read.*`.
- Settings `News.perPage`, `News.feedItems`. Menu: "News" (reader) with "All posts", "My posts",
  "Categories". Widgets `news.notices`, `news.latest`. Task `Tasks/ExpireNotices.php`.
  Notification `Notifications/NewNotice.php`. Link type `Menu/CategoryLink.php`. API
  `Controllers/Api/Posts.php` (`api/v1/news`).
- Tables: `news_categories`, `news_category_translations`, `news`, `news_translations`,
  `news_groups`, `news_reads` (one migration each).

## Main pieces

- `Services/NewsVisibility.php` (who reads what; the only place the rule lives),
  `NewsRepository.php` (read queries with texts in the user's language, Catalan fallback),
  `Publisher.php` (publish, notify once, release scheduled, expire), `ReadReceipts.php`,
  `BodySanitizer.php` (HTMLPurifier), `Slugger.php`, `FeedWriter.php` (RSS with XMLWriter).
- `Controllers/Posts.php` (`index` = `news-posts`, `mine` = `news-mine`), `Categories.php`,
  `Reader.php`, `PublicNews.php`, `Api/Posts.php`; `Storage/CoverUploads.php` (kpcrudy upload
  handler on the Storage service).

## Decisions

D-17-01 (names, URLs, settings, API permissions; **open**), D-17-02 … D-17-07 accepted
(`docs/decisions.md`); D-00-35, D-00-36, D-00-40, D-00-41, D-00-42.

## Open tasks

- Session 20: the body uses the rich-text editor (`forms/editor.js`); update the tests.
- Session 23: the cover uses the file picker (TODO in `Storage/CoverUploads.php`).
- Session 21: the Menus module consumes `Menu/CategoryLink.php` (formalise the contract).
- Session 25: the `news.notices` calendar (`groups` → `restricted`, D-00-41).
- Session 26: read receipts and notices in the API.
- Session 31: dates in the user's time zone in the forms.

## How to test

`vendor/bin/phpunit modules/News` (base `Tests/NewsTestCase.php`: Auth fixture, catalogue and
grants, News widgets, `newsPost()`, `code()`, `body()` with decoded entities, `assertNotFound()`).
By hand: `php spark modules:demo Auth`, `php spark modules:demo News`, then `backend/news`,
`backend/news/posts`, `news`, `news/feed.xml`.
