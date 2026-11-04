<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\Database;

/**
 * Starts a test from an empty test database (drops every table, including `migrations`), for
 * tests that run the module migrations themselves.
 */
trait CleanDatabaseTrait
{
    private function dropAllTables(): void
    {
        $db     = Database::connect('tests');
        $forge  = Database::forge('tests');
        $prefix = $db->getPrefix();

        $db->disableForeignKeyChecks();

        foreach ($db->listTables(true) as $table) {
            $forge->dropTable(substr((string) $table, strlen($prefix)), true);
        }

        $db->enableForeignKeyChecks();
        $db->resetDataCache();
    }
}
