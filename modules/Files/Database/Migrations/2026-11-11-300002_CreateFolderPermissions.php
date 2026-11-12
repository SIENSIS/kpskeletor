<?php

declare(strict_types=1);

namespace Modules\Files\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `folder_permissions`: the ACL of a folder with `own_permissions`. Subject: `group`
 * (subject_id = group name), `user` (subject_id = user id) or `authenticated` (every logged-in
 * user, subject_id ''). Rights: comma-separated `view,upload,edit,delete,manage`.
 */
final class CreateFolderPermissions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'folder_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'subject_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'subject_id'   => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => ''],
            'rights'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['folder_id', 'subject_type', 'subject_id']);
        $this->forge->addForeignKey('folder_id', 'folders', 'id', '', 'CASCADE');
        $this->forge->createTable('folder_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('folder_permissions', true);
    }
}
