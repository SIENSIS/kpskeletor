<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_translations`: the texts of a page per language (kpcrudy `setTranslations()`;
 * Catalan required): title, slug (unique among the pages of the same parent in that language,
 * checked by the Pages module), summary, body (HTML sanitised with HTMLPurifier) and the SEO
 * fields (meta title, meta description, canonical URL).
 */
final class CreatePageTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'page_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'           => ['type' => 'VARCHAR', 'constraint' => 10],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 190],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 190],
            'summary'          => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'body'             => ['type' => 'MEDIUMTEXT', 'null' => true],
            'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'canonical'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['page_id', 'locale']);
        $this->forge->addKey(['slug', 'locale']);
        $this->forge->addForeignKey('page_id', 'pages', 'id', '', 'CASCADE');
        $this->forge->createTable('page_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_translations', true);
    }
}
