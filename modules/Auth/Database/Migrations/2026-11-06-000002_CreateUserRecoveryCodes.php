<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Single-use 2FA recovery codes: only their SHA-256 hash is stored; a used code keeps its row
 * with `used_at` until the codes are regenerated.
 */
final class CreateUserRecoveryCodes extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'code_hash'  => ['type' => 'CHAR', 'constraint' => 64],
            'used_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'code_hash']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('user_recovery_codes');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_recovery_codes', true);
    }
}
