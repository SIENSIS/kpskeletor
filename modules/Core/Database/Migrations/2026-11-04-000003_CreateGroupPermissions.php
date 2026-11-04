<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Group grants (atomic permission names or Shield wildcards): the source of truth of RBAC.
 */
final class CreateGroupPermissions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'group'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 191],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['group', 'permission']);
        $this->forge->createTable('group_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('group_permissions', true);
    }
}
