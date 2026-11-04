<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Every row and file created by a module's demo seeder, so the demo data can be removed exactly.
 */
final class CreateDemoRecords extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'table_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'key_column'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'record_id'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'file_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('module');
        $this->forge->createTable('demo_records');
    }

    public function down(): void
    {
        $this->forge->dropTable('demo_records', true);
    }
}
