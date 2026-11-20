<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Web Push subscriptions of the `push` notification channel: one row per browser or installed
 * PWA a user turned notifications on in (D-44-02). `endpoint_hash` (SHA-256 of the endpoint)
 * keeps one row per endpoint; `p256dh` and `auth` are the browser's public key and secret
 * (base64url) used to encrypt messages for it.
 */
final class CreatePushSubscriptions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'endpoint'      => ['type' => 'TEXT'],
            'endpoint_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'p256dh'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'auth'          => ['type' => 'VARCHAR', 'constraint' => 50],
            'label'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'failures'      => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'last_used_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('endpoint_hash');
        $this->forge->addKey('user_id');
        $this->forge->createTable('push_subscriptions');
    }

    public function down(): void
    {
        $this->forge->dropTable('push_subscriptions', true);
    }
}
