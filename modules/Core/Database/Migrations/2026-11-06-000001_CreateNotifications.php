<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Notifications of the `database` channel (the bell): one row per recipient. The text is
 * rendered from `data` by the notification type when it is shown, in the reader's language.
 */
final class CreateNotifications extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'module'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 191],
            'data'       => ['type' => 'TEXT', 'null' => true],
            'read_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('notifications');
    }

    public function down(): void
    {
        $this->forge->dropTable('notifications', true);
    }
}
