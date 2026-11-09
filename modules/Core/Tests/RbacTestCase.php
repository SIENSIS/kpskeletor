<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * Base of the RBAC tests: every namespace migrated, the catalogue and default grants as
 * `modules:sync` leaves them, plus the "News" permissions the tests rely on (the real module's and
 * a fixture `news-postsx.edit`) so wildcards, implied permissions and disabled modules can be exercised.
 */
abstract class RbacTestCase extends CIUnitTestCase
{
    use CleanDatabaseTrait;
    use DatabaseTestTrait;
    use RbacFixtureTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected const NEWS = [
        'news-posts'      => ['list', 'show', 'add', 'edit', 'delete', 'export', 'trash', 'restore', 'purge', 'publish'],
        'news-categories' => ['list', 'show', 'add', 'edit', 'delete'],
        'news-postsx'     => ['edit'],
    ];

    protected function setUp(): void
    {
        // Earlier tests may leave migrations of temporary modules behind.
        $this->dropAllTables();
        parent::setUp();
        Services::resetSingle('settings');
        Services::resetSingle('auth');
        $_SESSION = [];
        $this->syncPermissions();
        $this->addNewsModule();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Services::resetSingle('auth');
        CrudPermissions::reset();
        parent::tearDown();
    }

    protected function addNewsModule(bool $enabled = true): void
    {
        $now = Time::now()->toDateTimeString();
        $db  = db_connect();

        // The real News module (session 17) is already registered by syncPermissions(): reuse its
        // row and add only the fixture's permissions it does not declare (e.g. `news-postsx.edit`).
        if ($db->table('modules')->where('name', 'News')->countAllResults() === 0) {
            $db->table('modules')->insert([
                'name' => 'News', 'version' => '1.0.0', 'enabled' => $enabled ? 1 : 0,
                'grants_applied' => 1, 'installed_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $db->table('modules')->where('name', 'News')->update(['enabled' => $enabled ? 1 : 0]);
        }

        foreach (self::NEWS as $resource => $operations) {
            foreach ($operations as $operation) {
                if ($db->table('permissions')->where('name', "{$resource}.{$operation}")->countAllResults() > 0) {
                    continue;
                }

                $db->table('permissions')->insert([
                    'module' => 'News', 'resource' => substr($resource, 5), 'operation' => $operation,
                    'name' => "{$resource}.{$operation}", 'description' => 'Core.perm.' . $operation,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        (new CoreCache())->invalidate('permissions');
        $this->refreshMatrix();
    }

    protected function setModuleEnabled(string $module, bool $enabled): void
    {
        db_connect()->table('modules')->where('name', $module)->update(['enabled' => $enabled ? 1 : 0]);
        $this->refreshMatrix();
    }

    protected function refreshMatrix(): void
    {
        (new CoreCache())->invalidate('permissions');
        (new ShieldMatrix())->write();
    }

    protected function createUser(string $email, ?string $group = 'user'): User
    {
        $users = model(UserModel::class);
        $user  = new User(['username' => strstr($email, '@', true), 'email' => $email, 'password' => 'Correct-Horse-Battery-42', 'active' => 1]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());

        if ($group !== null) {
            $user->addGroup($group);
        }

        return $user;
    }

    /**
     * A fresh copy of the user (Shield caches groups and permissions on the entity).
     */
    protected function reload(User $user): User
    {
        return model(UserModel::class)->findById($user->id);
    }

    protected function actAs(?User $user): void
    {
        Services::resetSingle('auth');
        $_SESSION = [];

        if ($user !== null) {
            auth('session')->getAuthenticator()->login($user);
        }
    }
}
