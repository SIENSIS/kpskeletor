<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `event_category_translations`: name and description of a category, one row per language
 * (kpcrudy `setTranslations()`; Catalan required).
 */
final class CreateEventCategoryTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['category_id', 'locale']);
        $this->forge->addForeignKey('category_id', 'event_categories', 'id', '', 'CASCADE');
        $this->forge->createTable('event_category_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('event_category_translations', true);
    }
}
