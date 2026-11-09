<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuDefinitions;
use Modules\Core\Menus\MenuEntry;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * The generated back-office menu: definitions from the manifests, permission filter, enabled
 * modules, parents, zones, order, active entry, breadcrumbs and the cache per group set.
 *
 * @internal
 */
final class MenuBuilderTest extends RbacTestCase
{
    /** Route name => path of the fake News module (and Auth's). */
    private const ROUTES = [
        'news-posts'      => 'backend/news/posts',
        'news-categories' => 'backend/news/categories',
        'news-stats'      => 'backend/news/stats',
        'auth-profile'    => 'backend/profile',
        'auth-sessions'   => 'backend/profile/sessions',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeMenu(new NewsMenuManifest());
    }

    public function testDefinitionsFromAManifest(): void
    {
        $definitions = MenuDefinitions::of(new NewsMenuManifest());

        $this->assertSame(['News.posts', 'News.categories', 'news-stats', 'news-reports', 'News.posts@settings'], array_keys($definitions));
        $this->assertSame('news-posts', $definitions['News.posts']['route']);
        $this->assertSame('news-posts.list', $definitions['News.posts']['permission'], 'crud resources use .list');
        $this->assertSame('News.posts', $definitions['News.categories']['parent'], 'a resource parent becomes its key');
        $this->assertSame('news-posts.list', $definitions['news-stats']['permission']);
        $this->assertSame('news-reports', $definitions['news-stats']['parent'], 'another key stays as given');
        $this->assertSame('sidebar', $definitions['News.posts']['zone']);
        $this->assertSame(100, $definitions['News.categories']['order']);
        $this->assertSame('settings', $definitions['News.posts@settings']['zone'], 'the same resource in a second zone');

        $page = MenuDefinitions::of(new \Modules\Auth\Config\Module());
        $this->assertSame('auth-profile.view', $page['Auth.profile']['permission'], 'pages use .view');
        $this->assertSame('user', $page['Auth.profile']['zone']);
    }

    public function testGuestsGetNoMenu(): void
    {
        $this->assertSame([], $this->builder()->tree(null));
    }

    public function testEntriesAreFilteredByPermission(): void
    {
        $user = $this->createUser('ana@example.com');
        $this->assertSame([], $this->keys($this->builder()->zone('sidebar', $user)), 'No News permission: nothing.');
        $this->assertSame(['Auth.profile', 'Auth.sessions'], $this->keys($this->builder()->zone('user', $user)));

        $editor = $this->grant('editor', ['news-posts.list']);
        $sidebar = $this->builder()->zone('sidebar', $editor);

        $this->assertSame(['News.posts', 'news-reports'], $this->keys($sidebar));
        $this->assertSame([], $this->keys($sidebar[0]->children), 'Categories need news-categories.list.');
        $this->assertSame(site_url('backend/news/posts'), $sidebar[0]->url());
    }

    public function testParentWithoutVisibleChildrenOrPermissionIsHidden(): void
    {
        // "news-reports" is a group (route without a path) holding "news-stats".
        $editor = $this->grant('editor', ['news-categories.list']);
        $sidebar = $this->builder()->zone('sidebar', $editor);

        $this->assertSame(['News.posts'], $this->keys($sidebar), 'Reports has no visible child.');
        $this->assertNull($sidebar[0]->path, 'Posts is kept as a group without its own link.');
        $this->assertSame(['News.categories'], $this->keys($sidebar[0]->children));

        $editor = $this->grant('editor', ['news-posts.list']);
        $reports = $this->builder()->zone('sidebar', $editor)[1];
        $this->assertSame('news-reports', $reports->key);
        $this->assertNull($reports->path, 'A group without its own page.');
        $this->assertSame(['news-stats'], $this->keys($reports->children));
    }

    public function testOrderZonesAndSuperadmin(): void
    {
        $super = $this->createUser('root@example.com', 'superadmin');
        $tree  = $this->builder()->tree($super);

        $this->assertEqualsCanonicalizing(['sidebar', 'settings', 'user'], array_keys($tree));
        $this->assertSame(['News.posts', 'news-reports'], $this->keys($tree['sidebar']), 'Order 20 before 30.');
        $this->assertSame(['News.categories'], $this->keys($tree['sidebar'][0]->children));
        $this->assertSame(['News.posts@settings'], $this->keys($tree['settings']));
    }

    public function testDisabledModulesAndMissingRoutesAreHidden(): void
    {
        $super = $this->createUser('root@example.com', 'superadmin');
        $this->setModuleEnabled('News', false);
        (new CacheInvalidator())->invalidate();

        $this->assertArrayNotHasKey('sidebar', $this->builder()->tree($super));

        $this->setModuleEnabled('News', true);
        (new CacheInvalidator())->invalidate();
        $builder = new MenuBuilder(routes: static fn (string $name): ?string => $name === 'news-categories' ? 'backend/news/categories' : null);

        $sidebar = $builder->tree($super)['sidebar'];
        $this->assertSame(['News.posts'], $this->keys($sidebar), 'Entries whose route does not exist are hidden.');
        $this->assertNull($sidebar[0]->path);
    }

    public function testActiveEntryAndBreadcrumbs(): void
    {
        $super = $this->createUser('root@example.com', 'superadmin');

        $sidebar = $this->builder()->zone('sidebar', $super, 'backend/news/categories/edit/3');
        $this->assertTrue($sidebar[0]->open);
        $this->assertFalse($sidebar[0]->active);
        $this->assertTrue($sidebar[0]->children[0]->active, 'The longest matching path wins.');

        $trail = $this->builder()->trail($super, 'backend/news/categories/edit/3');
        $this->assertSame(['News.posts', 'News.categories'], $this->keys($trail));

        $sidebar = $this->builder()->zone('sidebar', $super, 'backend/other', 'news-stats');
        $this->assertTrue($sidebar[1]->children[0]->active, 'The route name wins over the path.');

        $this->assertSame([], $this->builder()->trail($super, 'backend/unknown'));
        $this->assertSame(['Auth.sessions'], $this->keys($this->builder()->trail($super, 'backend/profile/sessions')));
    }

    public function testCachedPerGroupSetAndInvalidatedByGrantChanges(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $joan = $this->createUser('joan@example.com', 'user');
        $this->assertSame(MenuBuilder::cacheKey($ana), MenuBuilder::cacheKey($joan));
        $this->assertNotSame(MenuBuilder::cacheKey($ana), MenuBuilder::cacheKey($this->createUser('eva@example.com', 'admin')));

        $this->assertSame([], $this->keys($this->builder()->zone('sidebar', $ana)), 'The `user` group holds no news-posts permission.');

        // A cached tree is served to the same group set even if the declarations change…
        db_connect()->table('module_declarations')->where('kind', 'menu')->delete();
        (new CoreCache())->invalidate('modules');
        $this->storeMenu(new NewsMenuManifest());

        // …until a grant change invalidates the menus.
        (new GrantManager())->setGroupGrants(null, 'user', ['news-posts.list']);
        $this->assertSame(['News.posts', 'news-reports'], $this->keys($this->builder()->zone('sidebar', $this->reload($joan))));

        // Individual permissions change the key.
        $user = $this->createUser('pau@example.com');
        $before = MenuBuilder::cacheKey($user);
        (new GrantManager())->grantToUser(null, $user, ['news-posts.list']);
        $this->assertNotSame($before, MenuBuilder::cacheKey($this->reload($user)));
        $this->assertSame(['News.posts', 'news-reports'], $this->keys($this->builder()->zone('sidebar', $this->reload($user))));
    }

    public function testCyclesAndUnknownParentsDoNotHideEntries(): void
    {
        $super = $this->createUser('root@example.com', 'superadmin');
        $db    = db_connect();
        $now   = Time::now()->toDateTimeString();

        foreach (['a' => 'b', 'b' => 'a', 'c' => 'missing'] as $key => $parent) {
            $db->table('module_declarations')->insert(['module' => 'News', 'kind' => 'menu', 'key' => 'x-' . $key, 'updated_at' => $now,
                'definition' => json_encode(['key' => 'x-' . $key, 'module' => 'News', 'label' => 'X', 'icon' => 'bi bi-x', 'order' => 90,
                    'zone' => 'sidebar', 'parent' => 'x-' . $parent, 'route' => 'news-posts', 'permission' => 'news-posts.list'])]);
        }
        (new CoreCache())->invalidate('menus');

        $keys = $this->keys($this->builder()->zone('sidebar', $super));
        $this->assertContains('x-c', $keys, 'An entry whose parent is missing goes to the root.');
        $this->assertNotContains('x-a', $keys, 'Entries in a cycle have no root.');
    }

    private function storeMenu(ModuleManifest $manifest): void
    {
        $registry = new DeclarationRegistry();
        $auth     = new \Modules\Auth\Config\Module();
        $registry->store($registry->build(['Auth' => $auth, 'News' => $manifest]));
        (new CoreCache())->invalidate('menus');
        (new CoreCache())->invalidate('modules');
    }

    /**
     * @param list<string> $grants
     */
    private function grant(string $group, array $grants): \CodeIgniter\Shield\Entities\User
    {
        (new PermissionCatalogue())->replaceGrants($group, $grants);
        $this->refreshMatrix();
        (new CoreCache())->invalidate('menus');

        return $this->createUser($group . random_int(1, 99999) . '@example.com', $group);
    }

    private function builder(): MenuBuilder
    {
        return new MenuBuilder(routes: static fn (string $name): ?string => self::ROUTES[$name] ?? null);
    }

    /**
     * @param list<MenuEntry> $entries
     *
     * @return list<string>
     */
    private function keys(array $entries): array
    {
        return array_map(static fn (MenuEntry $e): string => $e->key, $entries);
    }
}

/**
 * The menu of the fake News module of RbacTestCase.
 */
final class NewsMenuManifest extends ModuleManifest
{
    public string $name = 'News';

    public array $resources = [
        'posts'      => ['label' => 'News', 'type' => 'crud'],
        'categories' => ['label' => 'Categories', 'type' => 'crud'],
    ];

    public array $menu = [
        ['resource' => 'posts', 'label' => 'Core.title', 'icon' => 'bi bi-newspaper', 'order' => 20],
        ['resource' => 'categories', 'label' => 'Core.title', 'parent' => 'posts'],
        ['route' => 'news-stats', 'permission' => 'news-posts.list', 'label' => 'Core.title', 'parent' => 'news-reports'],
        ['route' => 'news-reports', 'permission' => 'news-posts.list', 'label' => 'Core.title', 'order' => 30],
        ['resource' => 'posts', 'label' => 'Core.title', 'zone' => 'settings'],
    ];
}
