<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Metadata of the files written by the Storage service (docs/storage.md): the random public key
 * used in `files/private/{key}`, the disk and path, the original name (never used on disk), the
 * stored MIME type, size, SHA-256 checksum, image dimensions and who uploaded it. The Files
 * module (session 22) adds its file-manager metadata on top of this table.
 */
final class CreateStoredFiles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'file_key'      => ['type' => 'CHAR', 'constraint' => 32],
            'disk'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'path'          => ['type' => 'VARCHAR', 'constraint' => 500],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'extension'     => ['type' => 'VARCHAR', 'constraint' => 16],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 127],
            'size'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'checksum'      => ['type' => 'CHAR', 'constraint' => 64],
            'width'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'height'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'uploaded_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('file_key');
        $this->forge->addKey(['disk', 'path'], false, false, 'stored_files_disk_path');
        $this->forge->addKey('uploaded_by');
        $this->forge->createTable('stored_files');
    }

    public function down(): void
    {
        $this->forge->dropTable('stored_files', true);
    }
}
