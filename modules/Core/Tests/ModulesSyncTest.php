<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Modules as ModulesConfig;
use Config\Services;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\NamespaceFile;
use Modules\Core\Modules\SyncFailedException;

/**
 * modules:sync end to end on the test database, with the real Core and a generated module in a
 * temporary folder.
 *
 * @internal
 */
final class ModulesSyncTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use TempModulesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private string $module = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');

        $this->module = $this->uniqueModuleName();
        (new ModuleGenerator($this->tempRoot()))->generate($this->module);
    }

    protected function tearDown(): void
    {
        $this->removeTempRoot();
        parent::tearDown();
    }

    private function synchronizer(): ModuleSynchronizer
    {
        return new ModuleSynchronizer(
            new ModuleDiscovery([ROOTPATH . 'modules', $this->tempRoot()]),
            new NamespaceFile($this->tempRoot() . '/ModuleNamespaces.php'),
        );
    }

    public function testFirstSyncInstallsSecondSyncChangesNothing(): void
    {
        $invalidated = 0;
        Events::on(CacheInvalidator::EVENT, static function () use (&$invalidated): void {
            $invalidated++;
        });

        $first = $this->synchronizer()->sync();

        $this->assertTrue($first->namespacesChanged);
        $this->assertSame(6, $first->migrations['Modules\Core']);
        $this->assertSame(1, $first->migrations['Modules\\' . $this->module]);
        $this->assertArrayHasKey('Core', $first->installed);
        $this->assertArrayHasKey($this->module, $first->installed);
        $this->assertContains($this->module, $first->seeded);
        $this->assertSame('Core', $first->order[0]);
        $this->assertTrue($this->db->tableExists('modules'));
        $this->assertTrue($this->db->tableExists(strtolower($this->module) . '_items'));
        $this->assertTrue($this->db->tableExists('users'), 'Shield migrations run first');
        $this->assertSame(1, $invalidated);

        $namespaces = require $this->tempRoot() . '/ModuleNamespaces.php';
        $this->assertIsArray($namespaces);
        $this->assertArrayHasKey('Modules\Core', $namespaces);
        $this->assertArrayHasKey('Modules\\' . $this->module, $namespaces);

        $registry = model(ModuleModel::class, false)->allByName();
        $this->assertSame('0.1.0', $registry[$this->module]['version']);
        $this->assertTrue($registry[$this->module]['enabled']);

        $second = $this->synchronizer()->sync();

        $this->assertFalse($second->hasChanges());
        $this->assertSame([], $second->lines());
        $this->assertSame(1, $invalidated, 'caches are not invalidated when nothing changed');
        $this->assertSame($registry, model(ModuleModel::class, false)->allByName());
    }

    public function testNewModuleRoutesAreDiscoveredAfterSync(): void
    {
        $this->synchronizer()->sync();

        $routes = new RouteCollection(Services::locator(), new ModulesConfig(), config('Routing'));
        $routes->loadRoutes();

        $this->assertArrayHasKey('backend/' . strtolower($this->module), $routes->getRoutes('GET'));
    }

    public function testVersionBumpIsRecordedAsUpgrade(): void
    {
        $this->synchronizer()->sync();

        $file = $this->tempRoot() . '/' . $this->module . '/Config/Module.php';
        file_put_contents($file, str_replace("'0.1.0'", "'0.2.0'", (string) file_get_contents($file)));

        // The manifest class is already loaded: check the registry update through a fresh class.
        $registry = model(ModuleModel::class, false);
        $row      = $registry->allByName()[$this->module];
        $registry->update($row['id'], ['version' => '0.0.9']);

        $report = $this->synchronizer()->sync();

        $this->assertSame([$this->module => ['0.0.9', '0.1.0']], $report->upgraded);
        $this->assertSame('0.1.0', $registry->allByName()[$this->module]['version']);
    }

    public function testInvalidManifestFailsAndChangesNothing(): void
    {
        $broken = $this->uniqueModuleName();
        (new ModuleGenerator($this->tempRoot()))->generate($broken);

        $file = $this->tempRoot() . '/' . $broken . '/Config/Module.php';
        file_put_contents($file, str_replace("'type' => 'page'", "'type' => 'pages'", (string) file_get_contents($file)));

        try {
            $this->synchronizer()->sync();
            $this->fail('An invalid manifest must stop the synchronisation');
        } catch (SyncFailedException $e) {
            $this->assertStringContainsString("{$broken}: \$resources['main']['type'] must be one of: crud, page", implode("\n", $e->errors));
        }

        $this->assertFalse($this->db->tableExists('modules'), 'nothing was migrated');
        $this->assertFileDoesNotExist($this->tempRoot() . '/ModuleNamespaces.php');
    }
}
