<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_block_revisions` (D-47-02): revisions of reusable blocks (name, status and
 * sections), like `page_revisions` with the block as owner.
 */
final class CreatePageBlockRevisions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'block_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'save'],
            'changes'    => ['type' => 'TEXT', 'null' => true],
            'snapshot'   => ['type' => 'MEDIUMTEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['block_id', 'id']);
        $this->forge->addForeignKey('block_id', 'page_blocks', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('page_block_revisions');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_block_revisions', true);
    }
}
