<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Junction table `menu_item_groups`: the groups that see a menu item with visibility `groups`.
 */
final class CreateMenuItemGroups extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'item_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['item_id', 'group_id']);
        $this->forge->addKey('group_id');
        $this->forge->addForeignKey('item_id', 'menu_items', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->createTable('menu_item_groups');
    }

    public function down(): void
    {
        $this->forge->dropTable('menu_item_groups', true);
    }
}
