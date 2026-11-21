<?php

declare(strict_types=1);

namespace Modules\Contact\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `contact_recipients`: the recipients offered by the contact form. `emails` holds the
 * addresses that receive each message (one per line, never shown on the site), `group_name` the
 * group whose members are notified and may read the messages; `position` orders the list.
 */
final class CreateContactRecipients extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'emails'     => ['type' => 'TEXT', 'null' => true],
            'group_name' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['active', 'position']);
        $this->forge->createTable('contact_recipients');
    }

    public function down(): void
    {
        $this->forge->dropTable('contact_recipients', true);
    }
}
