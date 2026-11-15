<?php

declare(strict_types=1);

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\Migration;
use Modules\Users\Services\BirthdayAudiences;

/**
 * Table `group_birthday_audiences` (D-25-08): the members of `group_id` see, in the "Birthdays"
 * calendar, the shared birthdays of the members of `target_group_id`. Edited in Users › Groups
 * ("Sees the birthdays of"). Defaults: `BirthdayAudiences::seedDefaults()`.
 */
final class CreateGroupBirthdayAudiences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'group_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'target_group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['group_id', 'target_group_id']);
        $this->forge->addKey('target_group_id');
        $this->forge->addForeignKey('group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('target_group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->createTable('group_birthday_audiences');

        // Defaults for sites whose groups already exist (a fresh install: Config/Events.php).
        BirthdayAudiences::seedDefaults($this->db);
    }

    public function down(): void
    {
        $this->forge->dropTable('group_birthday_audiences', true);
    }
}
