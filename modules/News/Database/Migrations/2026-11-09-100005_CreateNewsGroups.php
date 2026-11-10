<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Junction table `news_groups`: the groups that can read a post with visibility `groups`
 * (kpcrudy `setRelationNN()`; "visible to any of my groups" is a portable query, D-00-36). The
 * surrogate `id` lets demo data record its rows (`DemoData` tracks one key column).
 */
final class CreateNewsGroups extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'news_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['news_id', 'group_id']);
        $this->forge->addKey('group_id');
        $this->forge->addForeignKey('news_id', 'news', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->createTable('news_groups');
    }

    public function down(): void
    {
        $this->forge->dropTable('news_groups', true);
    }
}
