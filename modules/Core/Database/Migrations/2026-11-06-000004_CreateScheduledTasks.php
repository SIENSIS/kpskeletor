<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The scheduled tasks declared by the modules (`$tasks`), their next run, last result and lock.
 * `active` is 0 when the declaring module is disabled or no longer declares the task.
 */
final class CreateScheduledTasks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'class'         => ['type' => 'VARCHAR', 'constraint' => 191],
            'module'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'expression'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'next_run_at'   => ['type' => 'DATETIME', 'null' => true],
            'last_run_at'   => ['type' => 'DATETIME', 'null' => true],
            'last_status'   => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'last_duration' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'locked_until'  => ['type' => 'DATETIME', 'null' => true],
            'lock_owner'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('class');
        $this->forge->createTable('scheduled_tasks');
    }

    public function down(): void
    {
        $this->forge->dropTable('scheduled_tasks', true);
    }
}
