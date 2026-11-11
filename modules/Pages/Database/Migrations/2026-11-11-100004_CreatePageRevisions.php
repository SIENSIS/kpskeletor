<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_revisions`: every save of a page keeps a revision (who, when, which fields
 * changed and a snapshot of the page, its texts and its groups as JSON), restorable as a custom
 * operation. Append-only: a restore writes a new revision.
 */
final class CreatePageRevisions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'page_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'save'],
            'changes'    => ['type' => 'TEXT', 'null' => true],
            'snapshot'   => ['type' => 'MEDIUMTEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['page_id', 'id']);
        $this->forge->addForeignKey('page_id', 'pages', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('page_revisions');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_revisions', true);
    }
}
