<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `news_categories`: one row per category (slug for the public URL `news/category/{slug}`,
 * order, active flag). Names and descriptions live in `news_category_translations`.
 */
final class CreateNewsCategories extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('news_categories');
    }

    public function down(): void
    {
        $this->forge->dropTable('news_categories', true);
    }
}
