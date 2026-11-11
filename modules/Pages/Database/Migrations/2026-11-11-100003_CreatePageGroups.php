<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Junction table `page_groups`: the groups that can read a page with visibility `groups`
 * (kpcrudy `setRelationNN()`, the Core's `Audience`). The surrogate `id` lets demo data record
 * its rows.
 */
final class CreatePageGroups extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'page_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['page_id', 'group_id']);
        $this->forge->addKey('group_id');
        $this->forge->addForeignKey('page_id', 'pages', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('group_id', 'groups', 'id', '', 'CASCADE');
        $this->forge->createTable('page_groups');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_groups', true);
    }
}
