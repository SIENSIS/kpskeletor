<?php

declare(strict_types=1);

namespace Modules\Dashboard\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A user's own arrangement of their dashboard (order, sizes, hidden widgets) as JSON; no row
 * means the group layout. Deleted with "restore the group layout".
 */
final class CreateDashboardUserLayouts extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'layout'     => ['type' => 'TEXT'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('dashboard_user_layouts');
    }

    public function down(): void
    {
        $this->forge->dropTable('dashboard_user_layouts', true);
    }
}
