<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `task_attachments` (tutorial 10): which to-do task each attached file belongs to, kept in
 * step with `tasks.attachments` after every write. The `FileAccessPolicy` of the folder
 * `modules/tasks` reads it to decide who may download a private file.
 */
final class CreateTaskAttachments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'task_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'file_key'   => ['type' => 'VARCHAR', 'constraint' => 32],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['task_id', 'file_key']);
        $this->forge->addKey('file_key');
        $this->forge->addForeignKey('task_id', 'tasks', 'id', '', 'CASCADE');
        $this->forge->createTable('task_attachments');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_attachments', true);
    }
}
