<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_section_translations` (D-45-01): one row per section and language with the section's
 * translated values as JSON (titles, texts, alternative texts, link labels…). Rows of a language
 * the site no longer offers are kept but neither shown nor edited.
 */
final class CreatePageSectionTranslations extends Migration
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
        $this->forge->addForeignKey('section_id', 'page_sections', 'id', '', 'CASCADE');
        $this->forge->createTable('page_section_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_section_translations', true);
    }
}
