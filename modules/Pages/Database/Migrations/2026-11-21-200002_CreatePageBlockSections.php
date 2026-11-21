<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_block_sections` (D-47-01, D-47-02): the sections of reusable blocks, the same
 * columns as `page_sections` with the block as owner (`block_id`). A table of its own instead of
 * a second owner column in `page_sections`: making `page_id` nullable rebuilds the table on
 * SQLite, which loses its `ON DELETE` actions.
 */
final class CreatePageBlockSections extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'block_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 101],
            'options'    => ['type' => 'TEXT', 'null' => true],
            'data'       => ['type' => 'MEDIUMTEXT', 'null' => true],
            'hidden'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['block_id', 'position']);
        $this->forge->addForeignKey('block_id', 'page_blocks', 'id', '', 'CASCADE');
        $this->forge->createTable('page_block_sections');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_block_sections', true);
    }
}
