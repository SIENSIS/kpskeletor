<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use Modules\Menus\Services\BackendLayout;
use Modules\Menus\Services\CustomisationApplier;

/**
 * The customisation layer of the back-office menu: permissions always win, relocations, custom
 * groups and links, per-group precedence, cycles, orphans, resets, cache invalidation, the
 * editor's routes and their permissions.
 *
 * @internal
 */
final class BackendMenuTest extends MenusTestCase
{
    public function testARelocatedEntryIsStillFilteredByItsPermission(): void
    {
        $this->customise('Users.users', null, ['parent' => 'Pages.pages', 'zone' => 'sidebar']);

        $admin = $this->createUser('boss@example.com', 'admin');
        $this->assertSame('sidebar/Pages.pages', $this->placeOf($admin, 'Users.users'), 'Moved under Pages.');

        $user = $this->createUser('ana@example.com', 'user');
        $user->addPermission('pages.list');
        $this->assertSame('sidebar', $this->placeOf($user, 'Pages.pages'));
        $this->assertNull($this->placeOf($user, 'Users.users'), 'Relocating never grants: still hidden without users-users.list.');
    }

    public function testHidingNeverGrantsAndHidesTheChildren(): void
    {
        $this->customise('News.read', null, ['hidden' => 1]);
        $admin = $this->createUser('boss@example.com', 'admin');

        $this->assertNull($this->placeOf($admin, 'News.read'));
        $this->assertNull($this->placeOf($admin, 'News.posts'), 'Hidden with its children.');
        $this->send('GET', 'backend/news/posts'); // not logged in: the route still decides by itself
        $this->login('boss@example.com');
        $this->assertSame(200, $this->send('GET', 'backend/news/posts')->response()->getStatusCode(), 'Hiding does not remove access.');

        $this->customise('News.read', 'admin', ['hidden' => 0]);
        $this->assertSame('sidebar', $this->placeOf($admin, 'News.read'), 'A group scope can show it again.');
    }

    public function testRenameIconAndOrder(): void
    {
        $this->customise('Pages.pages', null, ['labels' => json_encode(['ca' => 'Webs', 'en' => 'Websites']), 'icon' => 'bi bi-globe', 'position' => 1]);
        $admin = $this->createUser('boss@example.com', 'admin');
        $entry = $this->entry($admin, 'Pages.pages');

        $this->assertNotNull($entry);
        $this->assertSame('Websites', $entry->text());
        $this->assertSame('bi bi-globe', $entry->icon);
        $this->assertSame('backend/pages', $entry->path, 'The route never changes.');
        $this->assertSame(1, $entry->order);
    }

    public function testACustomGroupGathersEntriesAndIsHiddenWithoutVisibleOnes(): void
    {
        $group = $this->customLink(['kind' => 'group', 'type' => '', 'value' => '', 'labels' => json_encode(['ca' => 'Contingut', 'en' => 'Content'])]);
        $this->customise('Pages.pages', null, ['parent' => 'custom.' . $group]);
        $this->customise('Users.users', null, ['parent' => 'custom.' . $group]);

        $admin = $this->createUser('boss@example.com', 'admin');
        $this->assertSame('sidebar/custom.' . $group, $this->placeOf($admin, 'Pages.pages'));
        $entry = $this->entry($admin, 'custom.' . $group);
        $this->assertNotNull($entry);
        $this->assertNull($entry->url(), 'A group is a heading.');
        $this->assertSame('Content', $entry->text());

        $user = $this->createUser('ana@example.com', 'user');
        $this->assertNull($this->placeOf($user, 'custom.' . $group), 'No visible entry inside: hidden.');
    }

    public function testCustomLinksFollowTheRouteThePermissionAndTheGroups(): void
    {
        $users    = $this->customLink(['value' => '/backend/users', 'labels' => json_encode(['ca' => 'Persones'])]);
        $external = $this->customLink(['value' => 'https://example.com/help', 'target' => '_blank', 'groups' => json_encode(['editor'])]);
        $perm     = $this->customLink(['value' => 'https://example.com/stats', 'permission' => 'pages.publish']);
        $route    = $this->customLink(['type' => 'route', 'value' => 'pages']);

        $admin = $this->createUser('boss@example.com', 'admin');
        $this->assertSame('backend/users', $this->entry($admin, 'custom.' . $users)?->path, 'An internal path.');
        $this->assertNull($this->entry($admin, 'custom.' . $external), 'Only for editors.');
        $this->assertNotNull($this->entry($admin, 'custom.' . $perm));
        $this->assertSame('backend/pages', $this->entry($admin, 'custom.' . $route)?->path);

        $editor = $this->createUser('ed@example.com', 'editor');
        $this->assertNull($this->entry($editor, 'custom.' . $users), 'The link to Users takes its route\'s can: permission: no 403 links.');
        $link = $this->entry($editor, 'custom.' . $external);
        $this->assertNotNull($link);
        $this->assertSame('https://example.com/help', $link->url());
        $this->assertTrue($link->newTab());
        $this->assertNotNull($this->entry($editor, 'custom.' . $perm), 'Editors hold pages.publish.');

        $user = $this->createUser('ana@example.com', 'user');
        $this->assertNull($this->entry($user, 'custom.' . $perm));
        $this->assertNull($this->entry($user, 'custom.' . $route));

        $html = $this->renderedSidebar('ed@example.com');
        $this->assertStringContainsString('href="https://example.com/help" title="Enllaç" target="_blank" rel="noopener noreferrer"', $html);
    }

    public function testAStoredLinkThatBecameInvalidIsNeverShown(): void
    {
        $bad = $this->customLink(['value' => 'javascript:alert(1)']);
        $this->assertNull($this->entry($this->createUser('boss@example.com', 'admin'), 'custom.' . $bad));
    }

    public function testTheMostSpecificScopeWins(): void
    {
        $this->customise('Pages.pages', null, ['position' => 90, 'icon' => 'bi bi-star']);
        $this->customise('Pages.pages', 'editor', ['position' => 2]);

        $editor = $this->createUser('ed@example.com', 'editor');
        $entry  = $this->entry($editor, 'Pages.pages');
        $this->assertNotNull($entry);
        $this->assertSame(2, $entry->order, 'The group wins…');
        $this->assertSame('bi bi-star', $entry->icon, '…field by field over everyone.');
        $this->assertSame(90, $this->entry($this->createUser('boss@example.com', 'admin'), 'Pages.pages')?->order);

        $this->assertSame([null, 'editor', 'admin'], CustomisationApplier::scopesFor(['editor', 'admin'], ['superadmin', 'admin', 'editor', 'user']), 'The group listed first is the most specific.');
    }

    public function testCyclesAreImpossible(): void
    {
        // Stored data that would loop: the entry keeps its place.
        $this->customise('News.read', null, ['parent' => 'News.posts']);
        $admin = $this->createUser('boss@example.com', 'admin');
        $this->assertSame('sidebar', $this->placeOf($admin, 'News.read'));
        $this->assertSame('sidebar/News.read', $this->placeOf($admin, 'News.posts'));

        // The editor refuses it.
        $this->login('boss@example.com');
        $response = $this->send('POST', 'backend/menus/backend/entry', ['key' => 'News.read', 'parent' => 'News.categories', 'zone' => 'sidebar', 'hidden' => '0', 'icon' => '']);
        $this->assertTrue($response->isRedirect());
        $this->assertSame(lang('Menus.errors.cycle'), $_SESSION['error'] ?? null);

        $tree = json_encode([['key' => 'News.read', 'parent' => 'News.mine', 'zone' => 'sidebar'], ['key' => 'News.mine', 'parent' => 'News.read', 'zone' => 'sidebar']]);
        $this->send('POST', 'backend/menus/backend/order', ['tree' => $tree]);
        $this->assertSame(lang('Menus.errors.cycle'), $this->flash('error'), 'The tree editor refuses it too.');
    }

    public function testSavingTheTreeStoresOnlyDifferencesAndInvalidatesTheCache(): void
    {
        $admin = $this->loginAs('admin');
        $this->assertSame('sidebar', $this->placeOf($admin, 'Pages.pages')); // cached now

        $layout = new BackendLayout();
        $places = [];

        foreach ($layout->tree(null)['sidebar'] as $node) {
            $places[] = ['key' => $node['key'], 'parent' => null, 'zone' => 'sidebar'];
        }

        // Move Pages to the settings zone.
        $places   = array_values(array_filter($places, static fn (array $p): bool => $p['key'] !== 'Pages.pages'));
        $places[] = ['key' => 'Pages.pages', 'parent' => null, 'zone' => 'settings'];

        $response = $this->send('POST', 'backend/menus/backend/order', ['tree' => json_encode($places)]);
        $this->assertTrue($response->isRedirect());
        $this->assertSame(lang('Menus.backend.orderSaved'), $_SESSION['success'] ?? null);

        $this->assertSame('settings', $this->placeOf($admin, 'Pages.pages'), 'The cached menu was invalidated.');
        $this->assertSame(1, db_connect()->table('backend_menu_customisations')->countAllResults(), 'Only the moved entry is stored.');
        $this->seeInDatabase('backend_menu_customisations', ['entry_key' => 'Pages.pages', 'zone' => 'settings', 'group_name' => null]);
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'backend_reordered']);
    }

    public function testMovesWithoutJavaScript(): void
    {
        $admin = $this->loginAs('admin');
        $this->send('POST', 'backend/menus/backend/order', ['move' => 'News.mine:indent', 'tree' => '']);
        $this->assertSame('sidebar/News.read/News.posts', $this->placeOf($admin, 'News.mine'));

        $this->send('POST', 'backend/menus/backend/order', ['move' => 'News.mine:outdent', 'tree' => '', 'group' => 'editor']);
        $editor = $this->createUser('ed@example.com', 'editor');
        $this->assertSame('sidebar/News.read', $this->placeOf($editor, 'News.mine'), 'Editors: back out of «Posts».');
        $this->assertSame('sidebar/News.read/News.posts', $this->placeOf($admin, 'News.mine'), 'Others keep the change for everyone.');
    }

    public function testRetouchAnEntryForAGroup(): void
    {
        $this->loginAs('admin');
        $response = $this->send('POST', 'backend/menus/backend/entry', [
            'key' => 'Pages.pages', 'group' => 'editor', 'labels' => ['ca' => 'Web', 'es' => '', 'en' => 'Website'],
            'icon' => 'bi bi-globe', 'hidden' => '0', 'parent' => '', 'zone' => 'sidebar',
        ]);
        $this->assertTrue($response->isRedirect());
        $this->assertSame(lang('Menus.backend.entrySaved'), $_SESSION['success'] ?? null);

        $editor = $this->createUser('ed@example.com', 'editor');
        $this->assertSame('Website', $this->entry($editor, 'Pages.pages')?->text());
        $this->assertSame('Pages', $this->entry($this->createUser('x@example.com', 'admin'), 'Pages.pages')?->text());

        $this->send('POST', 'backend/menus/backend/entry', ['key' => 'Pages.pages', 'icon' => 'bi bi-x" onclick="x', 'hidden' => '0', 'parent' => '', 'zone' => 'sidebar']);
        $this->assertSame(lang('Menus.errors.icon'), $_SESSION['error'] ?? null);
        $this->assertNotFound('POST', 'backend/menus/backend/entry', ['key' => 'Pages.pages', 'group' => 'nobody', 'zone' => 'sidebar']);
        $this->assertNotFound('GET', 'backend/menus/backend/entry?key=Unknown.entry');
    }

    public function testCustomLinkAddressesAreValidated(): void
    {
        $this->loginAs('admin');

        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.example.com', '/backend/no-such-screen'] as $value) {
            $this->send('POST', 'backend/menus/backend/custom/save', ['kind' => 'link', 'labels' => ['ca' => 'X'], 'type' => 'url', 'value' => $value, 'target' => '_self', 'zone' => 'sidebar', 'position' => '10']);
            $this->assertNotNull($_SESSION['error'] ?? null, $value);
            $this->dontSeeInDatabase('backend_menu_links', ['value' => $value]);
        }

        $this->send('POST', 'backend/menus/backend/custom/save', ['kind' => 'link', 'labels' => ['ca' => 'Ajuda'], 'type' => 'url', 'value' => 'https://example.com/help', 'target' => '_blank', 'zone' => 'sidebar', 'position' => '10', 'permission' => 'no.such.permission']);
        $this->assertSame(lang('Menus.errors.permission'), $_SESSION['error'] ?? null);

        $this->send('POST', 'backend/menus/backend/custom/save', ['kind' => 'link', 'labels' => ['ca' => 'Ajuda'], 'type' => 'url', 'value' => 'https://example.com/help', 'target' => '_blank', 'zone' => 'sidebar', 'position' => '10', 'groups' => ['editor', 'ghosts']]);
        $this->assertSame(lang('Menus.custom.saved'), $_SESSION['success'] ?? $_SESSION['error'] ?? null);
        $this->seeInDatabase('backend_menu_links', ['value' => 'https://example.com/help', 'groups' => '["editor"]']);
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'backend_custom_added']);
    }

    public function testOrphansAreListedKeptInactiveAndDeleted(): void
    {
        $this->customise('Gone.module', null, ['position' => 1]);
        $this->customise('Pages.pages', null, ['position' => 1]);

        $layout  = new BackendLayout();
        $orphans = $layout->store()->orphans($layout->knownKeys());
        $this->assertSame(['Gone.module'], array_column($orphans, 'key'));

        $this->loginAs('admin');
        $html = $this->send('GET', 'backend/menus/backend')->response()->getBody();
        $this->assertStringContainsString('Gone.module', (string) $html);

        $active = (int) db_connect()->table('backend_menu_customisations')->where('entry_key', 'Pages.pages')->get()->getRow()->id;
        $this->send('POST', 'backend/menus/backend/orphans', ['ids' => [(string) $orphans[0]['id'], (string) $active]]);
        $this->dontSeeInDatabase('backend_menu_customisations', ['entry_key' => 'Gone.module']);
        $this->seeInDatabase('backend_menu_customisations', ['entry_key' => 'Pages.pages']); // an active customisation is never deleted as an orphan
    }

    public function testResetAnEntryAScopeOrEverything(): void
    {
        $this->customise('Pages.pages', null, ['position' => 1]);
        $this->customise('News.read', null, ['position' => 1]);
        $this->customise('Pages.pages', 'editor', ['position' => 2]);
        $this->loginAs('admin');

        $this->send('POST', 'backend/menus/backend/reset', ['reset' => 'entry', 'key' => 'Pages.pages']);
        $this->dontSeeInDatabase('backend_menu_customisations', ['entry_key' => 'Pages.pages', 'group_name' => null]);
        $this->seeInDatabase('backend_menu_customisations', ['entry_key' => 'Pages.pages', 'group_name' => 'editor']);

        $this->send('POST', 'backend/menus/backend/reset', ['reset' => 'scope', 'group' => 'editor']);
        $this->dontSeeInDatabase('backend_menu_customisations', ['group_name' => 'editor']);
        $this->seeInDatabase('backend_menu_customisations', ['entry_key' => 'News.read']);

        $this->send('POST', 'backend/menus/backend/reset', ['reset' => 'everything']);
        $this->assertSame(0, db_connect()->table('backend_menu_customisations')->countAllResults());
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'backend_reset']);
    }

    public function testEveryRouteNeedsItsPermissionAndForgedPostsAreRefused(): void
    {
        $group = $this->customLink(['kind' => 'group', 'type' => '', 'value' => '']);
        $link  = $this->customLink([]);
        $this->customise('Pages.pages', null, ['position' => 1]);
        $user = $this->loginWith('menus-backend.view');

        $this->assertSame(200, $this->send('GET', 'backend/menus/backend')->response()->getStatusCode());

        $forged = [
            ['POST', 'backend/menus/backend/order', ['move' => 'Pages.pages:down']],
            ['POST', 'backend/menus/backend/entry', ['key' => 'Pages.pages', 'hidden' => '1', 'zone' => 'sidebar']],
            ['GET', 'backend/menus/backend/entry?key=Pages.pages', []],
            ['GET', 'backend/menus/backend/group/new', []],
            ['GET', 'backend/menus/backend/link/new', []],
            ['POST', 'backend/menus/backend/custom/save', ['kind' => 'link', 'labels' => ['ca' => 'X'], 'type' => 'url', 'value' => 'https://example.com', 'target' => '_self', 'zone' => 'sidebar', 'position' => '1']],
            ['POST', 'backend/menus/backend/custom/' . $link . '/delete', []],
            ['POST', 'backend/menus/backend/reset', ['reset' => 'everything']],
            ['POST', 'backend/menus/backend/orphans', ['ids' => ['1']]],
        ];

        foreach ($forged as [$method, $path, $params]) {
            $this->assertSame(403, $this->send($method, $path, $params)->response()->getStatusCode(), "{$method} {$path}");
        }

        // `links` alone may not touch custom groups, `customise` alone may not touch links.
        $user->addPermission('menus-backend.links');
        $this->assertSame(403, $this->send('GET', 'backend/menus/backend/custom/' . $group)->response()->getStatusCode());
        $this->assertSame(403, $this->send('POST', 'backend/menus/backend/custom/' . $group . '/delete')->response()->getStatusCode());
        $this->assertSame(200, $this->send('GET', 'backend/menus/backend/custom/' . $link)->response()->getStatusCode());

        $user->removePermission('menus-backend.links');
        $user->addPermission('menus-backend.customise');
        $this->assertSame(403, $this->send('POST', 'backend/menus/backend/custom/' . $link . '/delete')->response()->getStatusCode());
        $this->assertSame(403, $this->send('POST', 'backend/menus/backend/custom/save', ['kind' => 'link', 'labels' => ['ca' => 'X'], 'type' => 'url', 'value' => 'https://example.com', 'target' => '_self', 'zone' => 'sidebar', 'position' => '1'])->response()->getStatusCode());

        $this->seeInDatabase('backend_menu_links', ['id' => $link]);
        $this->seeInDatabase('backend_menu_customisations', ['entry_key' => 'Pages.pages', 'position' => 1, 'hidden' => null]);
        $this->assertSame(1, db_connect()->table('backend_menu_links')->where('kind', 'link')->countAllResults());
    }

    public function testTheEditorShowsEveryEntryWithItsModule(): void
    {
        $this->customise('News.read', null, ['hidden' => 1]);
        $this->loginAs('admin');
        $html = (string) $this->send('GET', 'backend/menus/backend')->response()->getBody();

        foreach (['data-key="Pages.pages"', 'data-key="News.read"', 'data-key="Users.users"', 'data-key="Auth.profile"', 'data-component="menus/tree-editor"', 'menu-tree-hidden'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function testADisabledModuleLayerIsInactive(): void
    {
        $this->customise('Pages.pages', null, ['zone' => 'settings']);
        $admin = $this->createUser('boss@example.com', 'admin');
        $this->assertSame('settings', $this->placeOf($admin, 'Pages.pages'));

        (new \Modules\Core\Modules\ModuleToggler())->disable('Menus');
        $this->assertSame('sidebar', $this->placeOf($admin, 'Pages.pages'), 'Customisations need the Menus module.');
        (new \Modules\Core\Modules\ModuleToggler())->enable('Menus');
    }

    private function renderedSidebar(string $email): string
    {
        $this->login($email);

        return (string) $this->send('GET', 'backend')->response()->getBody();
    }
}
