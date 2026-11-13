<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `event_reminders`: a user asked to be reminded of an event `minutes_before` its start (of
 * every occurrence, for a recurring event). `last_sent_for` is the start of the last occurrence
 * reminded, so the SendReminders task never sends the same reminder twice.
 */
final class CreateEventReminders extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'event_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'minutes_before' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 60],
            'last_sent_for'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['event_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('event_id', 'events', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('event_reminders');
    }

    public function down(): void
    {
        $this->forge->dropTable('event_reminders', true);
    }
}
