<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Each user's second factor: the method (`email` or `totp`; no row means none), the TOTP secret
 * encrypted with CodeIgniter's Encryption service and the last accepted TOTP time step (a code
 * is never accepted twice).
 */
final class CreateUserTwoFactor extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'method'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'totp_secret'    => ['type' => 'TEXT', 'null' => true],
            'totp_last_step' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'enabled_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('user_two_factor');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_two_factor', true);
    }
}
