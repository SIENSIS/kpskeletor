<?php

declare(strict_types=1);

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `group_birthday_greetings` (D-25-09): the groups whose members are greeted on their
 * birthday (switch "Greet the members on their birthday" in Users › Groups). None by default.
 */
final class CreateGroupBirthdayGreetings extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('group_id');
        $this->forge->addForeignKey('group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->createTable('group_birthday_greetings');
    }

    public function down(): void
    {
        $this->forge->dropTable('group_birthday_greetings', true);
    }
}
