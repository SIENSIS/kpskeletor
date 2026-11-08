<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * User groups as data (decision D-00-38): name (immutable), title, description, reserved flag and
 * 2FA policy. Mirrored into `setting('AuthGroups.groups')` by `Modules\Core\Groups\GroupRegistry`;
 * the four reserved groups are created by `modules:sync`.
 */
final class CreateGroups extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'reserved'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'two_factor'  => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'optional'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('groups');
    }

    public function down(): void
    {
        $this->forge->dropTable('groups', true);
    }
}
