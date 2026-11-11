<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `menu_item_translations`: the label of a menu item per language (Catalan required,
 * Spanish then Catalan as fallbacks).
 */
final class CreateMenuItemTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'  => ['type' => 'VARCHAR', 'constraint' => 10],
            'label'   => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['item_id', 'locale']);
        $this->forge->addForeignKey('item_id', 'menu_items', 'id', '', 'CASCADE');
        $this->forge->createTable('menu_item_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('menu_item_translations', true);
    }
}
