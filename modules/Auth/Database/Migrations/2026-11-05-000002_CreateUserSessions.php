<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per login session ("my sessions", remote logout, absolute lifetime). The session holds
 * a random tracking token; only its SHA-256 hash is stored.
 */
final class CreateUserSessions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token_hash'        => ['type' => 'CHAR', 'constraint' => 64],
            'ip_address'        => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'remembered'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'remember_selector' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'regenerate'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'last_seen_at'      => ['type' => 'DATETIME', 'null' => true],
            'ended_at'          => ['type' => 'DATETIME', 'null' => true],
            'end_reason'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['user_id', 'ended_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('user_sessions');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_sessions', true);
    }
}
