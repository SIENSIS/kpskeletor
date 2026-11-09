<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `news_reads`: read receipts ("mark as read"), one row per user and post.
 */
final class CreateNewsReads extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'news_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'read_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['news_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('news_id', 'news', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('news_reads');
    }

    public function down(): void
    {
        $this->forge->dropTable('news_reads', true);
    }
}
