<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The owner of a row of `page_sections` is a page or a reusable block (D-47-01, D-47-02): the sections of a reusable block are rows of `page_sections` with `block_id` set and no `page_id`
 * (exactly one of the two is set). `page_id` becomes nullable and `block_id` references
 * `page_blocks` (deleting a block deletes its rows).
 */
final class AddBlockOwnerToPageSections extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('page_sections', [
            'block_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'page_id'],
        ]);
        $this->forge->modifyColumn('page_sections', [
            'page_id' => ['name' => 'page_id', 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey(['block_id', 'id'], false, false, 'page_sections_block');
        $this->forge->addForeignKey('block_id', 'page_blocks', 'id', '', 'CASCADE');
        $this->forge->processIndexes('page_sections');
    }

    public function down(): void
    {
        $this->db->table('page_sections')->where('page_id', null)->delete();
        $this->forge->dropForeignKey('page_sections', $this->db->DBPrefix . 'page_sections_block_id_foreign');

        if (array_key_exists($this->db->DBPrefix . 'page_sections_block', $this->db->getIndexData('page_sections'))) {
            $this->forge->dropKey('page_sections', 'page_sections_block');
        }

        $this->forge->dropColumn('page_sections', 'block_id');
        $this->forge->modifyColumn('page_sections', [
            'page_id' => ['name' => 'page_id', 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
        ]);
    }
}
