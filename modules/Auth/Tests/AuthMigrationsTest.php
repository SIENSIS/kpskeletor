<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * The Auth migrations run up, down and up again.
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

    private const TABLES = ['user_profiles', 'user_sessions', 'known_devices'];

    public function testUpDownUp(): void
    {
        $runner = service('migrations');
        $runner->setNamespace('Modules\Auth');

        foreach (self::TABLES as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }

        $runner->regress(0, 'tests');
        $this->db->resetDataCache();

        foreach (self::TABLES as $table) {
            $this->assertFalse($this->db->tableExists($table), $table);
        }

        $runner->latest('tests');
        $this->db->resetDataCache();

        foreach (self::TABLES as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
    }
}
