<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use CodeIgniter\Database\Migration;
use Config\Database;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Menus\MenuLinkType;
use Modules\Core\Modules\ManifestValidator;
use Modules\Menus\Config\Module;
use Modules\Menus\Services\LinkTypes;
use Modules\Menus\Services\MenuRenderer;

/**
 * The Menus module itself: manifest, permission names and grants, the link types of the other
 * modules, the demo data and the migrations (down and up again).
 *
 * @internal
 */
final class MenusModuleTest extends MenusTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
    }

    public function testPermissionNamesAndGrants(): void
    {
        $catalogue = array_column(db_connect()->table('permissions')->like('name', 'menus-', 'after')->get()->getResultArray(), 'name');
        sort($catalogue);
        $this->assertSame([
            'menus-backend.customise', 'menus-backend.links', 'menus-backend.reset', 'menus-backend.view',
            'menus-public.add', 'menus-public.delete', 'menus-public.edit', 'menus-public.list',
        ], $catalogue);

        $editor = $this->createUser('ed@example.com', 'editor');
        $this->assertTrue($editor->can('menus-public.edit'));
        $this->assertFalse($editor->can('menus-public.delete'));
        $this->assertFalse($editor->can('menus-backend.view'));
        $this->assertTrue($this->createUser('boss@example.com', 'admin')->can('menus-backend.reset'));
        $this->assertFalse($this->createUser('ana@example.com', 'user')->can('menus-public.list'));
    }

    public function testTheLinkTypesOfTheOtherModules(): void
    {
        $types = (new LinkTypes())->all();

        $keys = array_keys($types);
        sort($keys);
        $this->assertSame(['events.calendar', 'news.category', 'pages.page'], $keys);
        $this->assertInstanceOf(MenuLinkType::class, $types['pages.page']);

        $validator = new ManifestValidator();
        $bad       = new class () extends \Modules\Core\Config\ModuleManifest {
            public string $name   = 'Bad';
            public array $menuLinks = [\stdClass::class];
        };
        $this->assertNotSame([], $validator->validate($bad, dirname(__DIR__)), 'A link type must implement MenuLinkType.');
    }

    public function testDemoDataLoadsAfterPagesAndIsRemovedCompletely(): void
    {
        $this->assertSame(['Auth', 'News', 'Pages', 'Menus'], DemoDataManager::ordered(['Auth', 'Menus', 'News', 'Pages']), 'Menus waits for Pages and News.');
        $this->assertSame(['Menus'], DemoDataManager::ordered(['Menus']));

        $manager = new DemoDataManager(null, 'development');
        $this->assertTrue($manager->load('Menus'));
        $this->assertSame(2, db_connect()->table('menus')->countAllResults());
        $this->assertNotSame([], (new MenuRenderer())->items('primary', null, 'ca'));
        $this->seeInDatabase('backend_menu_customisations', ['entry_key' => 'Pages.pages']);

        $manager->remove('Menus');

        foreach (['menus', 'menu_items', 'menu_item_translations', 'backend_menu_links', 'backend_menu_customisations'] as $table) {
            $this->assertSame(0, db_connect()->table($table)->countAllResults(), $table);
        }
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
        $migrations = [];
        $tables     = ['menus', 'menu_items', 'menu_item_translations', 'menu_item_groups', 'backend_menu_customisations', 'backend_menu_links'];

        foreach ($files as $file) {
            $class = 'Modules\Menus\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertCount(6, $migrations);

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertFalse($this->db->tableExists($table), $table);
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
    }
}
