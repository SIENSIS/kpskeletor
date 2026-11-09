<?php

declare(strict_types=1);

namespace Modules\Dashboard\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-group widget configuration (Dashboard › Group layouts): whether the widget is available to
 * the group, its default position and size and its options (JSON). A widget without a row uses
 * its defaults (enabled, manifest order, first size, default options).
 */
final class CreateDashboardGroupWidgets extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'group_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'widget'     => ['type' => 'VARCHAR', 'constraint' => 101],
            'enabled'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'size'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'options'    => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['group_name', 'widget']);
        $this->forge->createTable('dashboard_group_widgets');
    }

    public function down(): void
    {
        $this->forge->dropTable('dashboard_group_widgets', true);
    }
}
