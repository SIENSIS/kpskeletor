<?php

declare(strict_types=1);

namespace Modules\News\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the News module, the reference module every other module copies
 * (docs/specification.md, "Modules" › "News"; docs/news.md).
 *
 * Resources (permission names `news-<resource>.<operation>`):
 * - `posts` (kpcrudy): every post; extras `publish` (operation and bulk action), `author` (see
 *   and change the author, and change posts of other authors) and `readers` (read receipts);
 * - `categories` (kpcrudy): the categories;
 * - `mine` (kpcrudy, "My posts"): the posts of the signed-in author, `where()` pattern;
 * - `read` (page, D-00-42): the reader screen every group gets (`news-read.view`);
 * - `api` (D-17-01): the `news` API resource (`news-api.list`, `news-api.show`), every group.
 *
 * No `import`: posts and categories are written through the forms (sanitised body, slugs).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'News';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-newspaper';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2', 'Dashboard' => '^0.1'];

    public array $resources = [
        'posts' => [
            'label'  => 'News.res.posts',
            'type'   => 'crud',
            'except' => ['import'],
            'extra'  => [
                'publish' => 'News.perm.publish',
                'author'  => 'News.perm.author',
                'readers' => 'News.perm.readers',
            ],
        ],
        'categories' => [
            'label'  => 'News.res.categories',
            'type'   => 'crud',
            'except' => ['import', 'trash', 'restore', 'purge'],
        ],
        'mine' => [
            'label'  => 'News.res.mine',
            'type'   => 'crud',
            'except' => ['import', 'export', 'trash', 'restore', 'purge'],
            'extra'  => [
                'publish' => 'News.perm.publish',
            ],
        ],
        'read' => [
            'label' => 'News.res.read',
            'type'  => 'page',
        ],
        'api' => [
            'label'  => 'News.res.api',
            'type'   => 'crud',
            'except' => ['add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'],
        ],
    ];

    public array $defaultGrants = [
        'superadmin' => ['news-read.*', 'news-api.*'],
        'admin'      => ['news-posts.*', 'news-categories.*', 'news-mine.*', 'news-read.*', 'news-api.*'],
        'editor'     => [
            'news-posts.list', 'news-posts.show', 'news-posts.add', 'news-posts.edit', 'news-posts.publish',
            'news-categories.list', 'news-categories.show',
            'news-mine.*', 'news-read.*', 'news-api.*',
        ],
        'user' => ['news-read.*', 'news-api.*'],
    ];

    public array $menu = [
        ['resource' => 'read', 'label' => 'News.menu.read', 'icon' => 'bi bi-newspaper', 'order' => 20],
        ['resource' => 'posts', 'label' => 'News.menu.posts', 'icon' => 'bi bi-journal-text', 'order' => 1, 'parent' => 'read'],
        ['resource' => 'mine', 'label' => 'News.menu.mine', 'icon' => 'bi bi-person-lines-fill', 'order' => 2, 'parent' => 'read'],
        ['resource' => 'categories', 'label' => 'News.menu.categories', 'icon' => 'bi bi-tags', 'order' => 3, 'parent' => 'read'],
    ];

    /** Link types of the public menu editor (Menus, session 21). */
    public array $menuLinks = [\Modules\News\Menu\CategoryLink::class];

    public array $settings = [
        'perPage' => [
            'type'    => 'int',
            'default' => 10,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
            'label'   => 'News.settings.perPage',
            'help'    => 'News.settings.perPageHelp',
            'group'   => 'public',
        ],
        'feedItems' => [
            'type'    => 'int',
            'default' => 20,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
            'label'   => 'News.settings.feedItems',
            'help'    => 'News.settings.feedItemsHelp',
            'group'   => 'public',
        ],
    ];

    public array $widgets = [
        \Modules\News\Widgets\Notices::class,
        \Modules\News\Widgets\LatestNews::class,
    ];

    public array $tasks = [\Modules\News\Tasks\ExpireNotices::class];

    public array $notifications = [\Modules\News\Notifications\NewNotice::class];

    /** Covers: files of the Storage service in the folder `modules/news` (public disk). */
    public array $backup = ['files' => ['modules/news']];

    /** No extra Content-Security-Policy sources: covers and images are served by the site itself. */
    public array $csp = [];

    /** No outbound requests (the RSS feed is generated, not fetched). */
    public array $outboundHosts = [];

    /** Calendar `news.notices`: published notices with an event date (calendar API, push; D-00-41: no permission, each notice's audience decides). */
    public array $calendars = [
        'notices' => ['label' => 'News.cal.notices', 'color' => '#dc3545', 'source' => \Modules\News\Services\NoticeCalendar::class],
    ];

    public array $api = [
        'news' => ['path' => 'news', 'handler' => '\Modules\News\Controllers\Api\Posts::index'],
    ];
}
