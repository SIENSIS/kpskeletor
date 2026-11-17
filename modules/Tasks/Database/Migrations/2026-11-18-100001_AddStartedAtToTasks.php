<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Version 0.2.0 of the Tasks module (tutorial 13, "Releasing a new version of a module"): a
 * schema change on a released table. Released migrations are never edited, so the new column
 * comes in a new migration:
 *
 * - `tasks.started_at`: when the to-do task left `todo` (`TaskRepository::statusDates()`);
 * - existing rows are filled in once: to-do tasks already `doing` or `done` take their
 *   `updated_at` (the best date known), the others stay empty.
 *
 * `down()` drops the column again (rolling back the version, `modules:uninstall`).
 */
final class AddStartedAtToTasks extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tasks', [
            'started_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'reminded_on'],
        ]);

        $this->db->table('tasks')
            ->whereIn('status', ['doing', 'done'])
            ->set('started_at', 'COALESCE(updated_at, created_at)', false)
            ->update();
    }

    public function down(): void
    {
        $this->forge->dropColumn('tasks', 'started_at');
    }
}
