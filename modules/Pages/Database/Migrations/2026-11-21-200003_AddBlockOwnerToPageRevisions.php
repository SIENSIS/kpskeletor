<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The owner of a row of `page_revisions` is a page or a reusable block (D-47-01, D-47-02): the revisions of a reusable block (its name, status and sections) are rows of `page_revisions` with `block_id` set and no `page_id`
 * (exactly one of the two is set). `page_id` becomes nullable and `block_id` references
 * `page_blocks` (deleting a block deletes its rows).
 */
final class AddBlockOwnerToPageRevisions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('page_revisions', [
            'block_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'page_id'],
        ]);
        $this->forge->modifyColumn('page_revisions', [
            'page_id' => ['name' => 'page_id', 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey(['block_id', 'id'], false, false, 'page_revisions_block');
        $this->forge->addForeignKey('block_id', 'page_blocks', 'id', '', 'CASCADE');
        $this->forge->processIndexes('page_revisions');
    }

    public function down(): void
    {
        $this->db->table('page_revisions')->where('page_id', null)->delete();
        $this->forge->dropForeignKey('page_revisions', $this->db->DBPrefix . 'page_revisions_block_id_foreign');

        if (array_key_exists($this->db->DBPrefix . 'page_revisions_block', $this->db->getIndexData('page_revisions'))) {
            $this->forge->dropKey('page_revisions', 'page_revisions_block');
        }

        $this->forge->dropColumn('page_revisions', 'block_id');
        $this->forge->modifyColumn('page_revisions', [
            'page_id' => ['name' => 'page_id', 'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
        ]);
    }
}
