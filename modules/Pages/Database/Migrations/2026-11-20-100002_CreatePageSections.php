<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_sections` (D-45-01; docs/sections.md): the sections of a page in `sections` layout,
 * in `position` order. `type` is a section type key (`pages.hero`); `options` the common options
 * (background, spacing, width, anchor, style) and `data` the non-translated values, both as JSON;
 * the translated values are in `page_section_translations`. A hidden section is kept but not shown.
 */
final class CreatePageSections extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'page_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 101],
            'options'    => ['type' => 'TEXT', 'null' => true],
            'data'       => ['type' => 'MEDIUMTEXT', 'null' => true],
            'hidden'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['page_id', 'position']);
        $this->forge->addForeignKey('page_id', 'pages', 'id', '', 'CASCADE');
        $this->forge->createTable('page_sections');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_sections', true);
    }
}
