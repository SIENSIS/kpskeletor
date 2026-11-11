<?php

declare(strict_types=1);

namespace Modules\Files\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `files`: the file manager's metadata of a stored file (`stored_files`, D-13-03: disk,
 * stored path, original name, MIME type, size, dimensions, checksum and uploader live there).
 * `folder` is the virtual folder the file is shown in (moving a file changes only this column),
 * `name` its display and download name, `visibility` public/private (the disk), `alt` and `tags`
 * (comma separated), `thumbnail` the path of its thumbnail on the private disk, `deleted_at` the
 * trash.
 */
final class CreateFiles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'stored_file_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'disk'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'folder'         => ['type' => 'VARCHAR', 'constraint' => 500],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'visibility'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'private'],
            'alt'            => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'tags'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'thumbnail'      => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'deleted_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('stored_file_id');
        $this->forge->addKey(['disk', 'folder']);
        $this->forge->addKey('deleted_at');
        $this->forge->addForeignKey('stored_file_id', 'stored_files', 'id', '', 'CASCADE');
        $this->forge->createTable('files');
    }

    public function down(): void
    {
        $this->forge->dropTable('files', true);
    }
}
