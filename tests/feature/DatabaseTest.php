<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Smoke test: the test database (SQLite in memory, or MariaDB in CI) is reachable
 * and migrations from every namespace (Shield, Settings, kpapy…) run.
 *
 * @internal
 */
final class DatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testDatabaseAnswersAndMigrationsRan(): void
    {
        $this->assertSame(1, (int) $this->db->query('SELECT 1 AS one')->getRow()->one);
        $this->assertTrue($this->db->tableExists('users'), 'Shield migrations did not run');
        $this->assertTrue($this->db->tableExists('settings'), 'Settings migrations did not run');
    }
}
