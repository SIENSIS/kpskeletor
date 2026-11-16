<?php

declare(strict_types=1);

namespace Modules\Backup\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `backups`: the index of the backup files in `writable/backups` (file name, size, type,
 * source, result, who, whether it contains demo data, last verification). The files themselves
 * are the truth: the list is reconciled with the folder (a restore brings back an older index).
 */
final class CreateBackups extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'file'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'size'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'type'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'full'],
            'source'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'console'],
            'result'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'success'],
            'message'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'demo'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'core_version'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME'],
            'verified_at'   => ['type' => 'DATETIME', 'null' => true],
            'verify_result' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('file');
        $this->forge->addKey('created_at');
        $this->forge->createTable('backups');
    }

    public function down(): void
    {
        $this->forge->dropTable('backups', true);
    }
}
