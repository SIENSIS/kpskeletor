<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `event_audience`: who sees a `restricted` event. Each row targets **either** a group
 * (`group_id`, `groups.id`) **or** a user (`user_id`); the other column is null (D-24-03). The two
 * columns let kpcrudy edit both lists with `setRelationNN()` on the same table: rewriting the
 * groups never touches the user rows and vice versa.
 */
final class CreateEventAudience extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'event_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'user_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('event_id');
        $this->forge->addKey('group_id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('event_id', 'events', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('event_audience');
    }

    public function down(): void
    {
        $this->forge->dropTable('event_audience', true);
    }
}
