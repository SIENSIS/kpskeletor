<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `news_translations`: title, summary and body (HTML sanitised with HTMLPurifier) of a post
 * per language (kpcrudy/kpapy `setTranslations()`; Catalan required).
 */
final class CreateNewsTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'news_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'  => ['type' => 'VARCHAR', 'constraint' => 10],
            'title'   => ['type' => 'VARCHAR', 'constraint' => 190],
            'summary' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'body'    => ['type' => 'MEDIUMTEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['news_id', 'locale']);
        $this->forge->addForeignKey('news_id', 'news', 'id', '', 'CASCADE');
        $this->forge->createTable('news_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('news_translations', true);
    }
}
