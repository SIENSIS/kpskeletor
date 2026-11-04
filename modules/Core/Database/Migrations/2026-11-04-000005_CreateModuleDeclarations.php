<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * What the manifests declare (settings, widgets, tasks, notification types, menu link types,
 * calendars, calendar providers, API resources), registered by `modules:sync` so that regular
 * requests never load the manifests.
 */
final class CreateModuleDeclarations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'module'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'kind'       => ['type' => 'VARCHAR', 'constraint' => 32],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 191],
            'definition' => ['type' => 'TEXT'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['module', 'kind', 'key']);
        $this->forge->addKey('kind');
        $this->forge->createTable('module_declarations');
    }

    public function down(): void
    {
        $this->forge->dropTable('module_declarations', true);
    }
}
