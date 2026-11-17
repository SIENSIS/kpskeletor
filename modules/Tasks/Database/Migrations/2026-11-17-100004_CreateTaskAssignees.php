<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Junction table `task_assignees` (tutorial 1, an N-N relation with kpcrudy `setRelationNN()`):
 * the users a to-do task is assigned to. The surrogate `id` lets demo data record its rows.
 */
final class CreateTaskAssignees extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'task_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['task_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('task_id', 'tasks', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('task_assignees');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_assignees', true);
    }
}
