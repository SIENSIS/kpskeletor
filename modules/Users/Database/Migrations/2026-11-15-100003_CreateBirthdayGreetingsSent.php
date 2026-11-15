<?php

declare(strict_types=1);

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `birthday_greetings_sent` (D-25-09): one row per user and year once the birthday greeting
 * was sent, so a user is greeted once a year however often the task runs. Rows older than two
 * years are purged by the task.
 */
final class CreateBirthdayGreetingsSent extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'year'    => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true],
            'sent_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'year']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('birthday_greetings_sent');
    }

    public function down(): void
    {
        $this->forge->dropTable('birthday_greetings_sent', true);
    }
}
