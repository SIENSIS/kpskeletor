<?php

declare(strict_types=1);

namespace Modules\Files\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `folders`: the registered folders of the file manager (the tree is virtual: a folder is
 * a disk and a normalised path). `owner_id` is the user of a personal space, `quota` a limit in
 * bytes for everything below the folder (null: none), `own_permissions` whether the folder
 * defines its own ACL (`folder_permissions`) instead of inheriting its parent's.
 */
final class CreateFolders extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'disk'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'path'            => ['type' => 'VARCHAR', 'constraint' => 500],
            'owner_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'quota'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'own_permissions' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['disk', 'path']);
        $this->forge->addKey('owner_id');
        $this->forge->addForeignKey('owner_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('folders');
    }

    public function down(): void
    {
        $this->forge->dropTable('folders', true);
    }
}
