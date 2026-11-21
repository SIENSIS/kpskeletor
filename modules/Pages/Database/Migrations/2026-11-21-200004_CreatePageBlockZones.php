<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `page_block_zones` (D-47-01): where a block is shown outside pages. `zone` is a zone of
 * the public theme (`public.before_footer`); `rule` decides on which pages: `all`, `home` (the
 * site's home page), `only` (the pages in `pages`) or `except` (every page but those); `pages`
 * is a JSON list of page ids; `position` orders the blocks of one zone.
 */
final class CreatePageBlockZones extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'block_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'zone'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'rule'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'all'],
            'pages'      => ['type' => 'TEXT', 'null' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['zone', 'position']);
        $this->forge->addKey('block_id');
        $this->forge->addForeignKey('block_id', 'page_blocks', 'id', '', 'CASCADE');
        $this->forge->createTable('page_block_zones');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_block_zones', true);
    }
}
