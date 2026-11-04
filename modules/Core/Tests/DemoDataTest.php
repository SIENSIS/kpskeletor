<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Modules\Core\Demo\DemoDataException;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ModuleToggler;

/**
 * Demo data: tracked rows and files, idempotent loading, removal that leaves real data untouched,
 * and the refusal in production.
 *
 * @internal
 */
final class DemoDataTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use ModuleFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private const SEEDER = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Modules\{{Name}}\Database\Seeds;

        use Modules\Core\Demo\DemoData;
        use Modules\Core\Demo\DemoSeeder;

        final class {{Name}}DemoSeeder extends DemoSeeder
        {
            public function load(DemoData $demo): void
            {
                $demo->insert('{{name}}_items', ['name' => 'Primer element']);
                $demo->insert('{{name}}_items', ['name' => 'Segon element']);

                if (! is_dir(WRITEPATH . 'uploads/demo')) {
                    mkdir(WRITEPATH . 'uploads/demo', 0777, true);
                }

                file_put_contents(WRITEPATH . 'uploads/demo/{{name}}.txt', 'demo');
                $demo->recordFile('uploads/demo/{{name}}.txt');
            }
        }
        PHP;

    private string $module = '';

    private string $table = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        Services::resetSingle('settings');

        $this->module = $this->fixtureModule('', [], ['Database/Seeds/{{Name}}DemoSeeder.php' => self::SEEDER]);
        $this->table  = strtolower($this->module) . '_items';
        $this->synchronizer()->sync();
    }

    protected function tearDown(): void
    {
        @unlink(WRITEPATH . 'uploads/demo/' . strtolower($this->module) . '.txt');
        @rmdir(WRITEPATH . 'uploads/demo');
        $this->removeTempRoot();
        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return array_map('strval', array_column($this->db->table($this->table)->orderBy('id')->get()->getResultArray(), 'name'));
    }

    public function testLoadingTwiceDoesNotDuplicate(): void
    {
        $manager = new DemoDataManager(null, 'development');

        $this->assertTrue($manager->load($this->module));
        $this->assertFalse($manager->load($this->module), 'already loaded');

        $this->assertSame(['Primer element', 'Segon element'], $this->names());
        $this->assertTrue($manager->isLoaded($this->module));
        $this->assertSame([$this->module], $manager->loadedModules());
        $this->assertSame(3, $this->db->table('demo_records')->where('module', $this->module)->countAllResults());
        $this->assertFileExists(WRITEPATH . 'uploads/demo/' . strtolower($this->module) . '.txt');
    }

    public function testRemovingKeepsRealRows(): void
    {
        $manager = new DemoDataManager(null, 'development');
        $this->db->table($this->table)->insert(['name' => 'Real abans']);
        $manager->load($this->module);
        $this->db->table($this->table)->insert(['name' => 'Real després']);

        $removal = $manager->remove($this->module);

        $this->assertSame(2, $removal->rows);
        $this->assertSame(1, $removal->files);
        $this->assertSame([], $removal->kept);
        $this->assertSame(['Real abans', 'Real després'], $this->names());
        $this->assertFalse($manager->isLoaded($this->module));
        $this->assertFileDoesNotExist(WRITEPATH . 'uploads/demo/' . strtolower($this->module) . '.txt');

        $reloaded = $manager->reload($this->module);
        $this->assertSame([], $reloaded->kept);
        $this->assertSame(['Real abans', 'Real després', 'Primer element', 'Segon element'], $this->names());
    }

    public function testRowsThatRealDataDependsOnAreKeptAndReported(): void
    {
        $manager = new DemoDataManager(null, 'development');
        $manager->load($this->module);

        $forge = \Config\Database::forge('tests');
        $forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->addForeignKey('item_id', $this->table, 'id', '', 'RESTRICT');
        $forge->createTable('fixture_children');

        $first = $this->db->table($this->table)->where('name', 'Primer element')->get()->getRowArray();
        $this->db->table('fixture_children')->insert(['item_id' => $first['id']]);

        $removal = $manager->remove($this->module);

        $this->assertSame(["{$this->table}#{$first['id']}"], $removal->kept);
        $this->assertSame(['Primer element'], $this->names());
        $this->assertTrue($manager->isLoaded($this->module), 'the kept row is still known as demo data');

        $forge->dropTable('fixture_children', true);
    }

    public function testRefusedInProductionUnlessTheSettingAllowsIt(): void
    {
        $manager = new DemoDataManager(null, 'production');

        try {
            $manager->load($this->module);
            $this->fail('Demo data must be refused in production');
        } catch (DemoDataException $e) {
            $this->assertStringContainsString('Packages.allowDemoInProduction', $e->getMessage());
        }

        $this->assertSame([], $this->names());
        $this->assertFalse(setting('Packages.allowDemoInProduction'), 'off by default');

        setting('Packages.allowDemoInProduction', true);
        $this->assertTrue($manager->load($this->module));
        $this->assertCount(2, $this->names());
    }

    public function testDisabledModulesAndModulesWithoutSeederAreRefused(): void
    {
        $manager = new DemoDataManager(null, 'development');

        try {
            $manager->load('Core');
            $this->fail('Core has no demo data');
        } catch (DemoDataException $e) {
            $this->assertStringContainsString('Core has no demo data', $e->getMessage());
        }

        (new ModuleToggler($this->discovery()))->disable($this->module);

        $this->expectException(DemoDataException::class);
        $this->expectExceptionMessage('is disabled');
        $manager->load($this->module);
    }

    public function testFailingSeederWritesNothing(): void
    {
        $broken = $this->fixtureModule('', [], ['Database/Seeds/{{Name}}DemoSeeder.php' => str_replace(
            "\$demo->insert('{{name}}_items', ['name' => 'Segon element']);",
            "\$demo->insert('{{name}}_missing', ['name' => 'x']);",
            self::SEEDER,
        )]);
        $this->synchronizer()->sync();

        try {
            (new DemoDataManager(null, 'development'))->load($broken);
            $this->fail('A failing seeder must be reported');
        } catch (DemoDataException) {
        }

        $this->assertSame(0, $this->db->table(strtolower($broken) . '_items')->countAllResults());
        $this->assertSame(0, $this->db->table('demo_records')->where('module', $broken)->countAllResults());

        // The failed transaction must not make the next load roll back silently.
        $this->assertTrue((new DemoDataManager(null, 'development'))->load($this->module));
        $this->assertCount(2, $this->names());
    }

    public function testSyncWithDemoLoadsEveryEnabledModuleWithASeeder(): void
    {
        $report = $this->synchronizer()->sync(false, null, []);

        $this->assertContains($this->module, $report->demoLoaded);
        $this->assertCount(2, $this->names());

        $again = $this->synchronizer()->sync(false, null, []);
        $this->assertNotContains($this->module, $again->demoLoaded, 'idempotent');
        $this->assertCount(2, $this->names());
    }

    public function testRunningTheSeederDirectlyIsRefused(): void
    {
        $this->expectException(\LogicException::class);

        \Config\Database::seeder('tests')->call(DemoDataManager::seederClass($this->module));
    }
}
