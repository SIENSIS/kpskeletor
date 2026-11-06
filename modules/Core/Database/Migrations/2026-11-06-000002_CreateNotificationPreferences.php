<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The channels each user wants per notification type (no row: the type's default channels).
 */
final class CreateNotificationPreferences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 191],
            'channels'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'type']);
        $this->forge->createTable('notification_preferences');
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_preferences', true);
    }
}
