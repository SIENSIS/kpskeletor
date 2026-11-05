<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Database\Migration;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Auth\Database\Migrations\CreateKnownDevices;
use Modules\Auth\Database\Migrations\CreateUserProfiles;
use Modules\Auth\Database\Migrations\CreateUserRecoveryCodes;
use Modules\Auth\Database\Migrations\CreateUserSessions;
use Modules\Auth\Database\Migrations\CreateUserTwoFactor;

/**
 * The Auth migrations run up, down and up again. Only the Auth migrations are reversed: the
 * tables reference Shield's `users`, which must stay (MariaDB enforces the foreign keys).
 *
 * @internal
 */
final class AuthMigrationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    private const TABLES = ['user_profiles', 'user_sessions', 'known_devices', 'user_two_factor', 'user_recovery_codes'];

    /**
     * @return list<Migration>
     */
    private function migrations(): array
    {
        return array_map(
            static fn (string $class): Migration => new $class(\Config\Database::forge('tests')),
            [CreateUserProfiles::class, CreateUserSessions::class, CreateKnownDevices::class, CreateUserTwoFactor::class, CreateUserRecoveryCodes::class],
        );
    }

    private function assertTables(bool $exist): void
    {
        $this->db->resetDataCache();

        foreach (self::TABLES as $table) {
            $this->assertSame($exist, $this->db->tableExists($table), $table);
        }
    }

    public function testUpDownUp(): void
    {
        $this->assertTables(true);

        foreach (array_reverse($this->migrations()) as $migration) {
            $migration->down();
        }

        $this->assertTables(false);
        $this->assertTrue($this->db->tableExists('users'), "Shield's users table is untouched.");

        foreach ($this->migrations() as $migration) {
            $migration->up();
        }

        $this->assertTables(true);
    }
}
