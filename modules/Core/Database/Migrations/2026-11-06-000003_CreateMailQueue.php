<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Emails of the notification `email` channel, sent by the scheduled task SendQueuedEmails
 * (never inline in a request). Status: pending, sent, failed.
 */
final class CreateMailQueue extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'to_email'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'subject'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'body'         => ['type' => 'TEXT'],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'],
            'attempts'     => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'last_error'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'available_at' => ['type' => 'DATETIME', 'null' => true],
            'sent_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'available_at']);
        $this->forge->createTable('mail_queue');
    }

    public function down(): void
    {
        $this->forge->dropTable('mail_queue', true);
    }
}
