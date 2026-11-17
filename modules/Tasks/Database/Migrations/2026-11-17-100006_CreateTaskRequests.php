<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `task_requests` (tutorial 12): the "request a to-do task" form of the public site.
 *
 * - `status`: `new`, `accepted` (a to-do task was created from it, `task_id`) or `rejected`;
 * - `locale`: the visitor's language, used for the confirmation email.
 */
final class CreateTaskRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'details'    => ['type' => 'TEXT', 'null' => true],
            'locale'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ca'],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'new'],
            'task_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addForeignKey('task_id', 'tasks', 'id', '', 'SET NULL');
        $this->forge->createTable('task_requests');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_requests', true);
    }
}
