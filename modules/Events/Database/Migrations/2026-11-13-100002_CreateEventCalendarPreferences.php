<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `event_calendar_preferences`: the calendars each user has hidden in the calendar view
 * (`hidden`: comma-separated calendar ids such as `events,users.birthdays`).
 */
final class CreateEventCalendarPreferences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'hidden'     => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('event_calendar_preferences');
    }

    public function down(): void
    {
        $this->forge->dropTable('event_calendar_preferences', true);
    }
}
