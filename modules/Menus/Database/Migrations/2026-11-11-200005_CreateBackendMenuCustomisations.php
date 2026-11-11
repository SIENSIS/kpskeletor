<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `backend_menu_customisations`: the layer applied on top of the generated back-office
 * menu. One row per entry key (`<Module>.<resource>`, a route name or `custom.<id>`) and scope
 * (`group_name` null = everyone, else one group). Each column is null when the entry keeps its
 * generated value; `parent` `''` means "at the root". `labels` is JSON (locale => text).
 */
final class CreateBackendMenuCustomisations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entry_key'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'group_name' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'parent'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'zone'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'labels'     => ['type' => 'TEXT', 'null' => true],
            'icon'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'hidden'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['entry_key', 'group_name']);
        $this->forge->createTable('backend_menu_customisations');
    }

    public function down(): void
    {
        $this->forge->dropTable('backend_menu_customisations', true);
    }
}
