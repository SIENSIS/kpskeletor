<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `menus`: the public menus. `location` is the theme's menu location the menu is placed in
 * (`primary`, `footer`…; null = not placed); one menu per location.
 */
final class CreateMenus extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'location'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('location');
        $this->forge->createTable('menus');
    }

    public function down(): void
    {
        $this->forge->dropTable('menus', true);
    }
}
