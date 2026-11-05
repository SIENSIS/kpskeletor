<?php

declare(strict_types=1);

namespace Modules\Api\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The last IP address each API access token was used from (Shield's `auth_identities` keeps the
 * time of last use but not the address). Written by `Modules\Api\Auth\TokenAuthenticator` only
 * when the address changes; the row goes with its token.
 */
final class CreateApiTokenIps extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'token_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('token_id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('token_id', 'auth_identities', 'id', '', 'CASCADE');
        $this->forge->createTable('api_token_ips');
    }

    public function down(): void
    {
        $this->forge->dropTable('api_token_ips', true);
    }
}
