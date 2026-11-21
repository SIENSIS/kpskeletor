<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_block_section_translations` (D-47-02): the translated values of the sections of
 * reusable blocks (one row per section and language, JSON), like `page_section_translations`.
 */
final class CreatePageBlockSectionTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'section_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'     => ['type' => 'VARCHAR', 'constraint' => 10],
            'data'       => ['type' => 'MEDIUMTEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['section_id', 'locale']);
        $this->forge->addForeignKey('section_id', 'page_block_sections', 'id', '', 'CASCADE');
        $this->forge->createTable('page_block_section_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_block_section_translations', true);
    }
}
