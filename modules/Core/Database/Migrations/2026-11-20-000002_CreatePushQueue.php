<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Messages of the `push` notification channel, one per subscription, sent by the scheduled task
 * SendQueuedPush (never inline in a request). The payload is the JSON shown by the service
 * worker, stored before encryption (it is encrypted for the browser when sent). Status: pending,
 * sent, failed.
 */
final class CreatePushQueue extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'subscription_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'payload'         => ['type' => 'TEXT'],
            'urgency'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'normal'],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'],
            'attempts'        => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'last_error'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'available_at'    => ['type' => 'DATETIME', 'null' => true],
            'sent_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'available_at']);
        $this->forge->addKey('subscription_id');
        $this->forge->createTable('push_queue');
    }

    public function down(): void
    {
        $this->forge->dropTable('push_queue', true);
    }
}
