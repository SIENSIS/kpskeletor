<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `menu_items`: the items of a public menu, in a tree (`parent_id`, siblings by
 * `position`). `type` is `url`, `route` or a module link type (`pages.page`, `news.category`…)
 * and `value` its target (the URL, the route name or the link type's choice). `visibility`:
 * `all`, `guests`, `users` or `groups` (`menu_item_groups`).
 */
final class CreateMenuItems extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'menu_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'parent_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'url'],
            'value'      => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
            'icon'       => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
            'target'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '_self'],
            'css_class'  => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'all'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['menu_id', 'parent_id', 'position']);
        $this->forge->addForeignKey('menu_id', 'menus', 'id', '', 'CASCADE');
        $this->forge->createTable('menu_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('menu_items', true);
    }
}
