<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\ModuleMigrator;
use Modules\Core\Modules\ModuleToggleException;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Registry\ModuleState;

/**
 * modules:enable / modules:disable: routes answer 404 while a module is disabled, registries
 * hide it, dependency rules hold and core modules cannot be disabled.
 *
 * @internal
 */
final class ModuleToggleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use FeatureTestTrait;
    use ModuleFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private const PING = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Modules\{{Name}}\Controllers;

        use CodeIgniter\Controller;

        final class Ping extends Controller
        {
            public function index(): string
            {
                return 'pong';
            }
        }
        PHP;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->removeTempRoot();
        parent::tearDown();
    }

    private function toggler(): ModuleToggler
    {
        return new ModuleToggler($this->discovery());
    }

    public function testDisabledModuleRoutesAnswer404AndEnablingRestoresThem(): void
    {
        $name = $this->fixtureModule("public array \$resources = ['main' => ['label' => 'x', 'type' => 'page']];\n    public array \$widgets = [\\Modules\\{{Name}}\\Widgets\\{{Name}}Widget::class];", [], ['Controllers/Ping.php' => self::PING]);
        $this->synchronizer()->sync();

        $routes = [['GET', 'fixture-ping', "\\Modules\\{$name}\\Controllers\\Ping::index"]];

        $result = $this->withRoutes($routes)->get('fixture-ping');
        $result->assertOK();
        $result->assertSee('pong');

        $this->assertTrue($this->toggler()->disable($name));
        $this->assertFalse((new ModuleState())->isEnabled($name));
        $this->assertRouteNotFound($routes);
        $this->assertNotContains("Modules\\{$name}\\Widgets\\{$name}Widget", (new DeclarationRegistry())->widgets(), 'hidden from the registries');
        $this->assertNotContains(strtolower($name) . '-main.view', (new PermissionCatalogue())->enabledNames());
        $this->assertContains(strtolower($name) . '-main.view', array_keys((new PermissionCatalogue())->stored()), 'nothing is deleted');

        $this->assertSame([$name], $this->toggler()->enable($name));
        $result = $this->withRoutes($routes)->get('fixture-ping');
        $result->assertOK();
        $result->assertSee('pong');
        $this->assertContains("Modules\\{$name}\\Widgets\\{$name}Widget", (new DeclarationRegistry())->widgets());
        $this->assertContains(strtolower($name) . '-main.view', (new PermissionCatalogue())->enabledNames());
    }

    /**
     * The 404 page: FeatureTestTrait rethrows PageNotFoundException instead of rendering it.
     *
     * @param list<array{0: string, 1: string, 2: string}> $routes
     */
    private function assertRouteNotFound(array $routes): void
    {
        try {
            $this->withRoutes($routes)->get('fixture-ping');
            $this->fail('The route of a disabled module must answer 404');
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testCoreModulesCannotBeDisabled(): void
    {
        $this->synchronizer()->sync();

        foreach (['Core'] as $core) {
            try {
                $this->toggler()->disable($core);
                $this->fail("{$core} must not be disabled");
            } catch (ModuleToggleException $e) {
                $this->assertSame("{$core} is a core module and cannot be disabled.", $e->getMessage());
            }
        }

        $this->assertSame(['Core', 'Auth', 'Api', 'Users', 'Settings', 'Dashboard'], ModuleToggler::CORE_MODULES);
    }

    public function testDependantsBlockDisablingAndDependenciesAreOffered(): void
    {
        $base  = $this->fixtureModule();
        $child = $this->fixtureModule('', [$base => '^0.1']);
        $this->synchronizer()->sync();

        try {
            $this->toggler()->disable($base);
            $this->fail('A module with enabled dependants must not be disabled');
        } catch (ModuleToggleException $e) {
            $this->assertSame([$child], $e->modules);
            $this->assertStringContainsString($child, $e->getMessage());
        }

        $this->assertTrue($this->toggler()->disable($child));
        $this->assertTrue($this->toggler()->disable($base));
        $this->assertFalse($this->toggler()->disable($base), 'already disabled');

        try {
            $this->toggler()->enable($child);
            $this->fail('Enabling a module with disabled dependencies needs them too');
        } catch (ModuleToggleException $e) {
            $this->assertTrue($e->missingDependencies);
            $this->assertSame([$base], $e->modules);
        }

        $this->assertSame([$base, $child], $this->toggler()->enable($child, true));
        $this->assertSame([], $this->toggler()->enable($child), 'already enabled');
    }

    public function testEnablingRunsPendingMigrations(): void
    {
        $name = $this->fixtureModule();
        $this->synchronizer()->sync();
        $this->toggler()->disable($name);

        (new ModuleMigrator())->rollback('Modules\\' . $name);
        $this->db->resetDataCache();
        $this->assertFalse($this->db->tableExists(strtolower($name) . '_items'));

        $this->toggler()->enable($name);
        $this->db->resetDataCache();

        $this->assertTrue($this->db->tableExists(strtolower($name) . '_items'));
        $this->assertTrue(model(ModuleModel::class, false)->allByName()[$name]['enabled']);
    }

    public function testUnknownModuleIsReported(): void
    {
        $this->synchronizer()->sync();

        $this->expectException(ModuleToggleException::class);
        $this->expectExceptionMessage('The module Nope is not installed');

        $this->toggler()->disable('Nope');
    }
}
