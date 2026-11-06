<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Each run of a scheduled task: when, how long, the result (success, failed, skipped), its output
 * and what started it (`source`: cron, manual, web).
 */
final class CreateTaskRuns extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'task_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'started_at'  => ['type' => 'DATETIME', 'null' => true],
            'finished_at' => ['type' => 'DATETIME', 'null' => true],
            'duration'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'output'      => ['type' => 'TEXT', 'null' => true],
            'source'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'cron'],
            'user_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['task_id', 'started_at']);
        $this->forge->createTable('task_runs');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_runs', true);
    }
}
