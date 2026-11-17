<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Staged package operations of the Modules screen (docs/packages.md, D-29-04): an uploaded ZIP
 * (`action` install, `file` in writable/packages/) or an uninstall request, approved by a
 * superadmin (`pending`) and run from the console by `php spark modules:install --pending`.
 * `manifest` is the validated PackageInfo (JSON); `log` is the console output of the run.
 * Status: uploaded → pending → installing → installed | failed; discarded; expired.
 */
final class CreateModulePackages extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'action'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'install'],
            'type'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'module'],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'version'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'file'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'sha256'       => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'manifest'     => ['type' => 'TEXT', 'null' => true],
            'keep_data'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'uploaded'],
            'uploaded_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_at'  => ['type' => 'DATETIME', 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'log'          => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME'],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('name');
        $this->forge->createTable('module_packages');
    }

    public function down(): void
    {
        $this->forge->dropTable('module_packages', true);
    }
}
