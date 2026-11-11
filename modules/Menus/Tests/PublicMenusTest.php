<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use CodeIgniter\I18n\Time;
use Kpcrudy\Crud;
use Modules\Core\Menus\PublicMenuItem;
use Modules\Menus\Services\MenuRenderer;

/**
 * Public menus: the CRUD of menus, the items' editor (addresses, cycles, depth, moves, delete),
 * the rendering for each kind of visitor (visibility, the route's permissions, gone targets,
 * languages, the active item), the cache and the permissions of every route.
 *
 * @internal
 */
final class PublicMenusTest extends MenusTestCase
{
    public function testTheMenuOfALocationForEachVisitor(): void
    {
        $menu = $this->menu();
        $this->item($menu, 'Tothom', 'url', 'https://example.com/a', ['position' => 1]);
        $this->item($menu, 'Convidats', 'url', 'https://example.com/b', ['position' => 2, 'visibility' => 'guests']);
        $this->item($menu, 'Usuaris', 'url', 'https://example.com/c', ['position' => 3, 'visibility' => 'users']);
        $this->item($menu, 'Editors', 'url', 'https://example.com/d', ['position' => 4, 'visibility' => 'groups'], ['editor']);
        $this->item($menu, 'Usuaris del back-office', 'url', '/backend/users', ['position' => 5]);

        $renderer = new MenuRenderer();
        $labels   = static fn (array $items): array => array_map(static fn (PublicMenuItem $i): string => $i->label, $items);

        $this->assertSame(['Tothom', 'Convidats'], $labels($renderer->items('primary', null, 'ca')));
        $this->assertSame(['Tothom (en)', 'Convidats (en)'], $labels($renderer->items('primary', null, 'en')), 'Labels per language.');
        $this->assertSame(['Tothom', 'Convidats'], $labels($renderer->items('primary', null, 'es')), 'Missing language: Catalan.');
        $this->assertSame(['Tothom', 'Usuaris'], $labels($renderer->items('primary', $this->createUser('ana@example.com', 'user'), 'ca')));
        $this->assertSame(['Tothom', 'Usuaris', 'Editors'], $labels($renderer->items('primary', $this->createUser('ed@example.com', 'editor'), 'ca')));
        $this->assertSame(['Tothom', 'Usuaris', 'Usuaris del back-office'], $labels($renderer->items('primary', $this->createUser('boss@example.com', 'admin'), 'ca')), 'A link to a back-office route needs its permission.');
        $this->assertSame([], $renderer->items('footer', null, 'ca'), 'No menu placed there.');
    }

    public function testTheThemeShowsThePrimaryAndFooterMenus(): void
    {
        $primary = $this->menu();
        $about   = $this->item($primary, 'Qui som', 'url', '/qui-som', ['position' => 1]);
        $this->item($primary, 'Equip', 'url', '/qui-som/equip', ['parent_id' => $about]);
        $this->item($primary, 'Externa', 'url', 'https://example.com/x', ['position' => 2, 'target' => '_blank', 'css_class' => 'menu-ext']);
        $footer = $this->menu('Peu', 'footer');
        $this->item($footer, 'Avís legal', 'url', '/avis-legal');

        $html = (string) $this->send('GET', '/')->response()->getBody();

        $this->assertStringContainsString('data-menu="primary"', $html);
        $this->assertStringContainsString('dropdown-toggle', $html, 'Items with children are dropdowns.');
        $this->assertStringContainsString('Equip (en)', $html);
        $this->assertMatchesRegularExpression('/menu-ext[^>]*>\s*<a class="nav-link" href="https&#x3A;&#x2F;&#x2F;example.com&#x2F;x" target="_blank" rel="noopener noreferrer"/', $html);
        $this->assertStringContainsString('data-menu="footer"', $html);
        $this->assertStringContainsString('Avís legal (en)', $html);
    }

    public function testGoneTargetsAreHiddenAndTheActiveItemIsMarked(): void
    {
        $menu   = $this->menu();
        $parent = $this->item($menu, 'Pare', 'route', 'no-such-route', ['position' => 1]);
        $this->item($menu, 'Fill', 'url', 'https://example.com/child', ['parent_id' => $parent]);
        $this->item($menu, 'Sense fills', 'route', 'no-such-route', ['position' => 2]);
        $this->item($menu, 'Pàgina esborrada', 'pages.page', '999999', ['position' => 3]);
        $this->item($menu, 'Notícies', 'route', 'news-public', ['position' => 4]);

        $items = (new MenuRenderer())->items('primary', null, 'ca', site_url('news/some-post'));

        $this->assertSame(['Pare', 'Notícies'], array_map(static fn (PublicMenuItem $i): string => $i->label, $items));
        $this->assertNull($items[0]->url, 'A parent whose target is gone stays as a heading for its visible children.');
        $this->assertTrue($items[1]->active, 'The longest item URL that is a parent of the current one.');
    }

    public function testMenusWithModuleLinkTypes(): void
    {
        $db  = db_connect();
        $now = Time::now();
        $db->table('pages')->insert(['parent_id' => null, 'position' => 0, 'template' => 'default', 'status' => 'published', 'publish_at' => $now->subHours(1)->toDateTimeString(), 'visibility' => 'public', 'noindex' => 0, 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString()]);
        $page = (int) $db->insertID();
        $db->table('page_translations')->insert(['page_id' => $page, 'locale' => 'ca', 'title' => 'Contacte', 'slug' => 'contacte', 'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'canonical' => '']);

        $menu = $this->menu();
        $this->item($menu, 'Contacte', 'pages.page', (string) $page);
        $items = (new MenuRenderer())->items('primary', null, 'ca');

        $this->assertSame(site_url('contacte'), $items[0]->url);

        $db->table('pages')->where('id', $page)->update(['status' => 'draft']);
        (new \Modules\Core\Modules\CacheInvalidator())->invalidate(['menus']);
        $this->assertSame([], (new MenuRenderer())->items('primary', null, 'ca'), 'An unpublished page disappears.');
    }

    public function testTheCacheIsInvalidatedByTheEditor(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu, 'Abans', 'url', 'https://example.com/');
        $this->assertSame('Abans', (new MenuRenderer())->items('primary', null, 'ca')[0]->label);

        $this->loginAs('admin');
        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['id' => (string) $item, 'labels' => ['ca' => 'Després', 'es' => '', 'en' => '']]));
        $this->assertSame(lang('Menus.items.saved'), $_SESSION['success'] ?? $_SESSION['error'] ?? null);

        $this->assertSame('Després', (new MenuRenderer())->items('primary', null, 'ca')[0]->label);
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'item_edited']);
    }

    public function testItemAddressesAreValidated(): void
    {
        $menu = $this->menu();
        $this->loginAs('admin');

        foreach (['javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', '//evil.example.com', 'vbscript:x', '/backend/no-such-screen'] as $value) {
            $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['value' => $value]));
            $this->assertArrayHasKey('error', $_SESSION, $value);
            $this->dontSeeInDatabase('menu_items', ['value' => $value]);
        }

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['type' => 'route', 'value' => 'no-such-route']));
        $this->assertSame(lang('Menus.errors.route'), $_SESSION['error'] ?? null);

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['type' => 'pages.page', 'choice' => ['pages.page' => '424242']]));
        $this->assertSame(lang('Menus.errors.choice'), $_SESSION['error'] ?? null);

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['icon' => 'bi bi-x" onmouseover="alert(1)', 'css_class' => '"><script>']));
        $this->assertStringContainsString(lang('Menus.errors.icon'), (string) ($_SESSION['error'] ?? ''));
        $this->assertStringContainsString(lang('Menus.errors.cssClass'), (string) ($_SESSION['error'] ?? ''));

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['visibility' => 'groups']));
        $this->assertSame(lang('Menus.errors.groups'), $_SESSION['error'] ?? null);

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['type' => 'route', 'value' => 'news-public']));
        $this->assertSame(lang('Menus.items.saved'), $_SESSION['success'] ?? null);
        $this->seeInDatabase('menu_items', ['menu_id' => $menu, 'type' => 'route', 'value' => 'news-public']);
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'item_added']);
    }

    public function testCyclesAndDepthAreRefused(): void
    {
        $menu = $this->menu();
        $a    = $this->item($menu, 'A');
        $b    = $this->item($menu, 'B', 'url', '/', ['parent_id' => $a]);
        $c    = $this->item($menu, 'C', 'url', '/', ['parent_id' => $b]);
        $d    = $this->item($menu, 'D');
        $other = $this->item($this->menu('Altre', null), 'X');
        $this->loginAs('admin');

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['id' => (string) $a, 'parent_id' => (string) $c]));
        $this->assertSame(lang('Menus.errors.cycle'), $_SESSION['error'] ?? null);

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['parent_id' => (string) $c]));
        $this->assertSame(lang('Menus.errors.depth', [3]), $_SESSION['error'] ?? null, 'At most three levels.');

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['parent_id' => (string) $other]));
        $this->assertSame(lang('Menus.errors.parent'), $_SESSION['error'] ?? null, 'A parent of another menu.');

        $tree = json_encode([['id' => $a, 'parent' => $c, 'position' => 10], ['id' => $b, 'parent' => $a, 'position' => 10], ['id' => $c, 'parent' => $b, 'position' => 10]]);
        $this->send('POST', 'backend/menus/items/' . $menu . '/order', ['tree' => $tree]);
        $this->assertSame(lang('Menus.errors.cycle'), $_SESSION['error'] ?? null);

        $this->send('POST', 'backend/menus/items/' . $menu . '/order', ['tree' => json_encode([['id' => $other, 'parent' => null, 'position' => 1]])]);
        $this->assertSame(lang('Menus.errors.unknownItem'), $_SESSION['error'] ?? null, 'Items of another menu.');

        $this->send('POST', 'backend/menus/items/' . $menu . '/order', ['tree' => '{"broken"']);
        $this->assertSame(lang('Menus.errors.tree'), $_SESSION['error'] ?? null);

        $this->seeInDatabase('menu_items', ['id' => $a, 'parent_id' => null]);

        // A valid tree: D first, A under D.
        $tree = json_encode([['id' => $d, 'parent' => null, 'position' => 10], ['id' => $a, 'parent' => null, 'position' => 20], ['id' => $b, 'parent' => $a, 'position' => 10], ['id' => $c, 'parent' => $b, 'position' => 10]]);
        $this->send('POST', 'backend/menus/items/' . $menu . '/order', ['tree' => $tree]);
        $this->assertSame(lang('Menus.items.orderSaved'), $_SESSION['success'] ?? null);
        $this->seeInDatabase('menu_items', ['id' => $d, 'position' => 10]);
        $this->seeInDatabase('menu_items', ['id' => $a, 'position' => 20]);
    }

    public function testMovesWithoutJavaScriptAndDeletingASubtree(): void
    {
        $menu = $this->menu();
        $a    = $this->item($menu, 'A', 'url', '/', ['position' => 10]);
        $b    = $this->item($menu, 'B', 'url', '/', ['position' => 20]);
        $b1   = $this->item($menu, 'B1', 'url', '/', ['parent_id' => $b]);
        $this->loginAs('admin');

        $this->send('POST', 'backend/menus/items/' . $menu . '/order', ['move' => $b . ':up', 'tree' => '']);
        $this->seeInDatabase('menu_items', ['id' => $b, 'position' => 10]);
        $this->seeInDatabase('menu_items', ['id' => $a, 'position' => 20]);

        $this->send('POST', 'backend/menus/items/' . $menu . '/order', ['move' => $a . ':indent', 'tree' => '']);
        $this->seeInDatabase('menu_items', ['id' => $a, 'parent_id' => $b]);

        $this->send('POST', 'backend/menus/items/' . $menu . '/delete/' . $b);
        $this->assertSame(0, db_connect()->table('menu_items')->whereIn('id', [$a, $b, $b1])->countAllResults(), 'Deleted with the items inside.');
        $this->assertSame(0, db_connect()->table('menu_item_translations')->whereIn('item_id', [$a, $b, $b1])->countAllResults());
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'item_deleted']);
    }

    public function testTheCrudOfMenus(): void
    {
        $this->loginAs('admin');
        $this->send('POST', 'backend/menus/public/add', ['name' => 'Principal', 'location' => 'primary']);
        $this->seeInDatabase('menus', ['name' => 'Principal', 'location' => 'primary']);

        $this->send('POST', 'backend/menus/public/add', ['name' => 'Segon', 'location' => 'primary']);
        $this->dontSeeInDatabase('menus', ['name' => 'Segon']); // one menu per location

        $this->send('POST', 'backend/menus/public/add', ['name' => 'Sense lloc', 'location' => '']);
        $this->send('POST', 'backend/menus/public/add', ['name' => 'Sense lloc 2', 'location' => '']);
        $this->seeInDatabase('menus', ['name' => 'Sense lloc 2', 'location' => null]);

        $this->send('POST', 'backend/menus/public/add', ['name' => 'Fora', 'location' => 'nowhere']);
        $this->dontSeeInDatabase('menus', ['name' => 'Fora']);

        $menu = (int) db_connect()->table('menus')->where('name', 'Principal')->get()->getRow()->id;
        $this->item($menu, 'A');
        $this->send('POST', 'backend/menus/public/delete/' . Crud::maskId($menu, 'menus'));
        $this->dontSeeInDatabase('menus', ['id' => $menu]);
        $this->assertSame(0, db_connect()->table('menu_items')->where('menu_id', $menu)->countAllResults(), 'Its items are deleted too.');
    }

    public function testEveryRouteNeedsItsPermissionAndForgedPostsAreRefused(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu, 'A');
        $this->loginWith('menus-public.list');

        $this->assertSame(200, $this->send('GET', 'backend/menus/public')->response()->getStatusCode());

        foreach ([
            ['GET', 'backend/menus/items/' . $menu],
            ['GET', 'backend/menus/items/' . $menu . '/new'],
            ['GET', 'backend/menus/items/' . $menu . '/edit/' . $item],
            ['POST', 'backend/menus/items/' . $menu . '/save'],
            ['POST', 'backend/menus/items/' . $menu . '/order'],
            ['POST', 'backend/menus/items/' . $menu . '/delete/' . $item],
        ] as [$method, $path]) {
            $this->assertSame(403, $this->send($method, $path, $method === 'POST' ? $this->form(['id' => (string) $item]) : [])->response()->getStatusCode(), "{$method} {$path}");
        }

        $this->assertCrudRefused($this->send('POST', 'backend/menus/public/add', ['name' => 'X']), 'backend/menus/public', 'add');
        $this->assertCrudRefused($this->send('POST', 'backend/menus/public/delete/' . Crud::maskId($menu, 'menus')), 'backend/menus/public', 'delete');
        $this->seeInDatabase('menus', ['id' => $menu]);
        $this->seeInDatabase('menu_items', ['id' => $item, 'value' => '/']);
    }

    public function testItemsOfAnotherMenuAreNotFound(): void
    {
        $menu  = $this->menu();
        $other = $this->item($this->menu('Altre', null), 'X');
        $this->loginAs('admin');

        $this->assertNotFound('GET', 'backend/menus/items/' . $menu . '/edit/' . $other);
        $this->assertNotFound('POST', 'backend/menus/items/' . $menu . '/delete/' . $other);
        $this->assertNotFound('POST', 'backend/menus/items/' . $menu . '/save', $this->form(['id' => (string) $other]));
        $this->assertNotFound('GET', 'backend/menus/items/999999');
        $this->seeInDatabase('menu_items', ['id' => $other]);
    }

    public function testTheEditorScreens(): void
    {
        $menu = $this->menu();
        $a    = $this->item($menu, 'Primer');
        $this->loginAs('admin');

        $html = (string) $this->send('GET', 'backend/menus/items/' . $menu)->response()->getBody();
        $this->assertStringContainsString('data-component="menus/tree-editor"', $html);
        $this->assertStringContainsString('value="' . $a . '&#x3A;indent"', $html, 'Move buttons work without JavaScript.');

        $html = (string) $this->send('GET', 'backend/menus/items/' . $menu . '/new')->response()->getBody();
        $this->assertStringContainsString('name="choice[pages.page]"', $html, 'The Pages link type.');
        $this->assertStringContainsString('name="choice[news.category]"', $html, 'The News link type.');

        $this->assertSame(200, $this->send('GET', 'backend/menus/public')->response()->getStatusCode());
    }

    public function testADisabledModuleShowsNoPublicMenu(): void
    {
        $menu = $this->menu();
        $this->item($menu, 'A', 'url', 'https://example.com/');
        $this->assertCount(1, menu('primary'));

        (new \Modules\Core\Modules\ModuleToggler())->disable('Menus');
        $this->assertSame([], menu('primary'));
        (new \Modules\Core\Modules\ModuleToggler())->enable('Menus');
    }

    /**
     * A valid item form, with changes.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function form(array $values = []): array
    {
        return $values + [
            'id' => '0', 'labels' => ['ca' => 'Element', 'es' => '', 'en' => ''], 'type' => 'url', 'value' => 'https://example.com/',
            'parent_id' => '', 'icon' => '', 'target' => '_self', 'css_class' => '', 'visibility' => 'all',
        ];
    }
}
