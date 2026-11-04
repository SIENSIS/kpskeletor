<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Modules\ModuleMigrator;

/**
 * Migrations of the Core: up → down → up leaves the same tables.
 *
 * @internal
 */
final class CoreMigrationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
    }

    public function testUpDownUp(): void
    {
        $migrator = new ModuleMigrator();

        $this->assertSame(1, $migrator->migrate('Modules\Core'));
        $this->assertTrue($this->db->tableExists('modules'));
        $this->assertSame([], $migrator->pending('Modules\Core'));

        $this->assertSame(1, $migrator->rollback('Modules\Core'));
        $this->assertFalse($this->db->tableExists('modules'));
        $this->assertSame(['2026-11-04-000001_CreateModules'], $migrator->pending('Modules\Core'));

        $this->assertSame(1, $migrator->migrate('Modules\Core'));
        $this->assertTrue($this->db->tableExists('modules'));
    }

    public function testPackageMigrationsAreNeverRolledBack(): void
    {
        $this->expectException(\LogicException::class);

        (new ModuleMigrator())->rollback('CodeIgniter\Shield');
    }
}
