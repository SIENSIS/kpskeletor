<?php

declare(strict_types=1);

namespace Modules\Contact\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `contact_messages`: the messages sent with the contact form. The recipient's name is
 * copied (`recipient_name`) so a deleted recipient leaves its messages readable to the holders of
 * `contact-messages.all`. No IP address is stored (rate limits use hashed cache counters).
 * Status: `new`, `answered`, `archived`. Deleted after `Contact.retentionDays`.
 */
final class CreateContactMessages extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'recipient_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'recipient_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 190],
            'subject'        => ['type' => 'VARCHAR', 'constraint' => 190],
            'message'        => ['type' => 'TEXT'],
            'locale'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'new'],
            'answered_at'    => ['type' => 'DATETIME', 'null' => true],
            'answered_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey('recipient_id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('recipient_id', 'contact_recipients', 'id', '', 'SET NULL');
        $this->forge->createTable('contact_messages');
    }

    public function down(): void
    {
        $this->forge->dropTable('contact_messages', true);
    }
}
