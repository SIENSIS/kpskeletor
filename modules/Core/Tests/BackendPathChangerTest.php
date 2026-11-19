<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Factories;
use CodeIgniter\Events\Events;
use Config\Auth;
use Config\Filters;
use Config\Services;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Routing\BackendPathChangeFailed;
use Modules\Core\Routing\BackendPathChanger;
use RuntimeException;

/**
 * `php spark app:backend-path` (D-40-01): validation, `.env`, stored links, caches, the security
 * log and maintenance mode.
 *
 * @internal
 */
final class BackendPathChangerTest extends AuthTestCase
{
    private string $dir;

    private MaintenanceMode $maintenance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = WRITEPATH . 'tests-backend-path-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/.env', "CI_ENVIRONMENT = testing\napp.backendPath = admin\n");
        $this->maintenance = new MaintenanceMode($this->dir . '/maintenance.json');
        self::usePrefix('admin');
    }

    protected function tearDown(): void
    {
        self::usePrefix('backend');

        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        @rmdir($this->dir);
        parent::tearDown();
    }

    private static function usePrefix(string $prefix): void
    {
        config('App')->backendPath = $prefix;
        Factories::injectMock('config', 'Filters', new Filters());
        Factories::injectMock('config', 'Auth', new Auth());
        Services::resetSingle('routes');
        Services::resetSingle('router');
        Services::resetSingle('filters');
    }

    private function changer(?string $env = null): BackendPathChanger
    {
        return new BackendPathChanger($env ?? $this->dir . '/.env', $this->maintenance);
    }

    public function testRefusesInvalidReservedAndCurrentPaths(): void
    {
        foreach (['Admin', 'x', 'admin/x', 'api', 'login', 'es', 'news', 'admin'] as $path) {
            $this->assertNotNull($this->changer()->error($path), $path);
        }

        $this->assertNull($this->changer()->error('intranet'));
        $this->assertNull($this->changer()->error('backend'));

        $this->expectException(RuntimeException::class);
        $this->changer()->change('api');
    }

    public function testChangesTheEnvAndTheStoredLinks(): void
    {
        $db   = db_connect();
        $now  = date('Y-m-d H:i:s');
        $db->table('menus')->insert(['name' => 'Main', 'location' => 'header', 'created_at' => $now, 'updated_at' => $now]);
        $menu = (int) $db->insertID();
        $db->table('menu_items')->insert(['menu_id' => $menu, 'type' => 'url', 'value' => '/admin/news', 'created_at' => $now, 'updated_at' => $now]);
        $typed = (int) $db->insertID();
        $db->table('menu_items')->insert(['menu_id' => $menu, 'type' => 'url', 'value' => '/backend/users', 'created_at' => $now, 'updated_at' => $now]);
        $canonical = (int) $db->insertID();
        $db->table('menu_items')->insert(['menu_id' => $menu, 'type' => 'news.category', 'value' => 'admin', 'created_at' => $now, 'updated_at' => $now]);
        $other = (int) $db->insertID();
        $db->table('menu_items')->insert(['menu_id' => $menu, 'type' => 'url', 'value' => '/administration', 'created_at' => $now, 'updated_at' => $now]);
        $public = (int) $db->insertID();

        $invalidated = [];
        Events::on(CacheInvalidator::EVENT, static function (array $scopes) use (&$invalidated): void {
            $invalidated = [...$invalidated, ...$scopes];
        });

        $rows = $this->changer()->change('intranet');

        $this->assertStringContainsString("app.backendPath = intranet\n", (string) file_get_contents($this->dir . '/.env'));
        $this->assertStringNotContainsString('app.backendPath = admin', (string) file_get_contents($this->dir . '/.env'));
        $this->assertSame('/backend/news', $db->table('menu_items')->where('id', $typed)->get()->getRow()->value);
        $this->assertSame('/backend/users', $db->table('menu_items')->where('id', $canonical)->get()->getRow()->value);
        $this->assertSame('admin', $db->table('menu_items')->where('id', $other)->get()->getRow()->value);
        $this->assertSame('/administration', $db->table('menu_items')->where('id', $public)->get()->getRow()->value);
        $this->assertSame(1, $rows['menu_items.value'] ?? null);
        $this->assertContains('menus', $invalidated);
        $this->assertSame(1, $db->table('activity_log')->where('action', 'backend_path_changed')->where('type', 'security')->countAllResults());
        $this->assertFalse($this->maintenance->isOn());
        $this->assertSame('intranet', backend_path());

        // The site answers under the new prefix and 404 under the old one.
        self::usePrefix('intranet');
        $this->send('GET', 'intranet')->assertRedirectTo(site_url('login'));
        $this->assertNotFound('GET', 'admin');
    }

    public function testAFailureLeavesMaintenanceOn(): void
    {
        try {
            $this->changer($this->dir . '/missing/.env')->change('intranet');
            $this->fail('The change did not fail.');
        } catch (BackendPathChangeFailed $e) {
            $this->assertSame('env', $e->step);
            $this->assertSame('admin', $e->old);
        }

        $this->assertTrue($this->maintenance->isOn());
        $this->assertSame('admin', backend_path());
    }

    public function testTheEnvLineToCopy(): void
    {
        $this->assertSame('app.backendPath = intranet', BackendPathChanger::envLine('intranet'));
        $this->assertTrue($this->changer()->envWritable());
        $this->assertFalse($this->changer($this->dir . '/missing/.env')->envWritable());
    }
}
