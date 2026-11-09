<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests;

use CodeIgniter\Database\Migration;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Modules\ManifestValidator;
use Modules\Dashboard\Config\Module;
use Modules\Dashboard\Database\Migrations\CreateDashboardGroupWidgets;
use Modules\Dashboard\Database\Migrations\CreateDashboardUserLayouts;

/**
 * The manifest (names `dashboard.view` / `dashboard.configure`, granted to every group) and the
 * migrations (up, down, up).
 *
 * @internal
 */
final class DashboardModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testManifestIsValid(): void
    {
        $manifest = new Module();

        $this->assertSame([], (new ManifestValidator())->validate($manifest, dirname(__DIR__)));
        $this->assertSame('dashboard', $manifest->permissionPrefix('dashboard'));

        foreach (['superadmin', 'admin', 'editor', 'user'] as $group) {
            $this->assertNotEmpty(array_intersect(['dashboard.view', 'dashboard.*'], $manifest->defaultGrants[$group]), "{$group} opens the dashboard.");
        }

        $this->assertSame(['dashboard.*'], $manifest->defaultGrants['admin']);
    }

    public function testUpDownUp(): void
    {
        $migrations = array_map(
            static fn (string $class): Migration => new $class(\Config\Database::forge('tests')),
            [CreateDashboardGroupWidgets::class, CreateDashboardUserLayouts::class],
        );
        $tables = ['dashboard_group_widgets', 'dashboard_user_layouts'];

        foreach (array_reverse($migrations) as $migration) {
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
