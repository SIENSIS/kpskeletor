<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Example table of the {{Name}} module (one migration per table; down() reverses up()).
 * Rename or delete it before the module's first release.
 */
final class Create{{Name}}Items extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('{{name}}_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('{{name}}_items', true);
    }
}
