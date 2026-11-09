<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `news`: one row per post (article or notice). Title, summary and body live in
 * `news_translations`; the groups of a `groups` post in `news_groups`.
 *
 * - `status`: `draft`, `scheduled` (published with a future `published_at`), `published` or
 *   `expired` (a notice past its `expires_at`, set by the ExpireNotices task).
 * - `visibility`: `public` (public site), `private` (signed-in users) or `groups`.
 * - `priority` and `pinned` only matter for notices.
 * - `cover`: the key of a file of the Storage service (public disk), never a path.
 * - `notified_at`: when the audience of a private notice was notified (sent once).
 */
final class CreateNews extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'category_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'slug'         => ['type' => 'VARCHAR', 'constraint' => 190],
            'type'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'article'],
            'priority'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'info'],
            'pinned'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'visibility'   => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'private'],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'draft'],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            'cover'        => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'author_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'notified_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['status', 'published_at']);
        $this->forge->addKey('author_id');
        $this->forge->addForeignKey('category_id', 'news_categories', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('author_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('news');
    }

    public function down(): void
    {
        $this->forge->dropTable('news', true);
    }
}
