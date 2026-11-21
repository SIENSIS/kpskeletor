<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_blocks` (D-47-01; docs/sections.md, "Reusable blocks"): a named list of sections
 * edited once and shown inside pages (section type `pages.block`) or in theme zones
 * (`page_block_zones`). Its sections are rows of `page_sections` whose `block_id` is set. A
 * `draft` block is shown nowhere.
 */
final class CreatePageBlocks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'published'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->createTable('page_blocks');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_blocks', true);
    }
}
