<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The module registry: one row per installed module.
 */
final class CreateModules extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'version'      => ['type' => 'VARCHAR', 'constraint' => 32],
            'enabled'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'installed_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('modules');
    }

    public function down(): void
    {
        $this->forge->dropTable('modules', true);
    }
}
