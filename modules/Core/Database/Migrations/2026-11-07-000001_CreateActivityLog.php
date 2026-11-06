<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The append-only activity and security log (docs/specification.md, "Activity log"): who did what
 * (user, module, resource, action, record), a short summary, the masked before/after changes as
 * JSON, the type (`activity` or `security`), the IP address, the user agent and the date. Rows
 * are never updated; only the retention task deletes them.
 */
final class CreateActivityLog extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'activity'],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'module'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => ''],
            'resource'   => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'record_id'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'summary'    => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'changes'    => ['type' => 'MEDIUMTEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey(['type', 'created_at']);
        $this->forge->addKey(['module', 'action']);
        $this->forge->createTable('activity_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_log', true);
    }
}
