<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Services;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Permissions\PermissionCatalogue;

/**
 * The console commands of this session against the real modules folder: --prune and the demo
 * commands ask first; core modules cannot be disabled.
 *
 * @internal
 */
final class ModuleCommandsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private MockInputOutput $io;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        Services::resetSingle('settings');

        (new ModuleSynchronizer())->sync();

        $this->io = new MockInputOutput();
        CLI::setInputOutput($this->io);
    }

    protected function tearDown(): void
    {
        CLI::resetInputOutput();
        parent::tearDown();
    }

    /**
     * @param list<string> $inputs
     */
    private function spark(string $command, array $inputs = []): string
    {
        $this->io->setInputs($inputs);
        command($command);

        return $this->io->getOutput();
    }

    public function testPruneAsksBeforeRemoving(): void
    {
        $this->db->table('permissions')->insert(['module' => 'Ghost', 'resource' => 'things', 'operation' => 'list', 'name' => 'ghost-things.list', 'description' => 'Core.perm.list']);

        $output = $this->spark('modules:sync --prune', ['n']);
        $this->assertStringContainsString('Remove these 1 permission(s)', $output);
        $this->assertArrayHasKey('ghost-things.list', (new PermissionCatalogue())->stored());

        $output = $this->spark('modules:sync --prune', ['y']);
        $this->assertStringContainsString('Permissions removed: ghost-things.list.', $output);
        $this->assertArrayNotHasKey('ghost-things.list', (new PermissionCatalogue())->stored());
    }

    public function testSyncWithoutPruneOnlyReportsStalePermissions(): void
    {
        $this->db->table('permissions')->insert(['module' => 'Ghost', 'resource' => 'things', 'operation' => 'list', 'name' => 'ghost-things.list', 'description' => 'Core.perm.list']);

        $output = $this->spark('modules:sync');

        $this->assertStringContainsString('remove them with --prune', $output);
        $this->assertArrayHasKey('ghost-things.list', (new PermissionCatalogue())->stored());
    }

    public function testCoreCannotBeDisabledFromTheConsole(): void
    {
        $output = $this->spark('modules:disable Core');

        $this->assertStringContainsString('Core is a core module and cannot be disabled.', $output);
        $this->assertSame(1, $this->db->table('modules')->where('name', 'Core')->where('enabled', 1)->countAllResults());
    }

    public function testDemoCommandAsksForConfirmation(): void
    {
        $output = $this->spark('modules:demo Core', ['n']);

        $this->assertStringContainsString('Load the demo data of Core?', $output);
        $this->assertStringContainsString('Cancelled', $output);

        $output = $this->spark('modules:demo Core', ['y']);
        $this->assertStringContainsString('Core has no demo data', $output);
    }
}
