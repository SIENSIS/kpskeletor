<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `pages`: one row per content page of the public site. Title, slug, summary, body and the
 * SEO texts live in `page_translations`; the groups of a `groups` page in `page_groups`; every
 * saved version in `page_revisions`.
 *
 * - `parent_id`: the parent page (tree; slugs are unique per parent and language).
 * - `template`: a page template of the public theme (`ThemeManifest::$pageTemplates`).
 * - `status`: `draft`, `scheduled` (published with a future `publish_at`) or `published`.
 * - `visibility`: `public`, `private` (signed-in users) or `groups`.
 * - `noindex`: ask search engines not to index the page (non-public pages always are).
 * - `featured_image`: the key of a file of the Storage service (public disk), never a path.
 */
final class CreatePages extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'parent_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'position'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'template'       => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'default'],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'draft'],
            'publish_at'     => ['type' => 'DATETIME', 'null' => true],
            'visibility'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'public'],
            'noindex'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'featured_image' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'author_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['parent_id', 'position']);
        $this->forge->addKey(['status', 'publish_at']);
        $this->forge->addForeignKey('parent_id', 'pages', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('author_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('pages');
    }

    public function down(): void
    {
        $this->forge->dropTable('pages', true);
    }
}
