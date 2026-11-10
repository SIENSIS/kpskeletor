<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Tests;

use CodeIgniter\Database\Migration;
use Config\Database;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Modules\ManifestValidator;
use Modules\{{Name}}\Config\Module;

/**
 * The {{Name}} module: manifest, permissions (also forged requests) and migrations. The base
 * class (Auth) migrates every module, syncs the permission catalogue and default grants and
 * sends requests with the session and a CSRF token; `loginWith()`, `assertNotFound()` and
 * `assertCrudRefused()` help with the permission tests.
 *
 * @internal
 */
final class {{Name}}ModuleTest extends AuthTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
    }

    public function testTheScreenNeedsItsPermission(): void
    {
        $this->send('GET', 'backend/{{name}}')->assertRedirectTo(site_url('login'));

        $this->loginWith();
        $this->send('GET', 'backend/{{name}}')->assertStatus(403);
    }

    public function testAUserWithThePermissionSeesTheScreen(): void
    {
        $this->loginWith('{{name}}-main.view');

        $this->send('GET', 'backend/{{name}}')->assertOK()->assertSee(lang('{{Name}}.title'));
    }

    public function testForgedPostsAreRefused(): void
    {
        $this->loginWith();

        // No POST route yet: without the permission a forged POST never reaches a controller.
        $this->assertNotSame(200, $this->send('POST', 'backend/{{name}}', ['anything' => '1'])->response()->getStatusCode());
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
        $migrations = [];

        foreach ($files as $file) {
            $class = 'Modules\{{Name}}\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertNotSame([], $migrations);

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();
        $this->assertTrue($this->db->tableExists('{{table}}'));
    }
}
