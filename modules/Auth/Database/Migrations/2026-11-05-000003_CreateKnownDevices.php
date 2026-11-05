<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Devices and browsers a user has signed in from, recognised by a random device cookie whose
 * SHA-256 hash is stored here (new-device notices).
 */
final class CreateKnownDevices extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_hash'   => ['type' => 'CHAR', 'constraint' => 64],
            'user_agent'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'first_seen_at' => ['type' => 'DATETIME', 'null' => true],
            'last_seen_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'device_hash']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('known_devices');
    }

    public function down(): void
    {
        $this->forge->dropTable('known_devices', true);
    }
}
