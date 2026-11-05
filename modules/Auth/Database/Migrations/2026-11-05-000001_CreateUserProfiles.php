<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Profile fields Shield's `users` table does not have (decision D-00-37): one row per user.
 */
final class CreateUserProfiles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'locale'     => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'timezone'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'theme_mode' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'avatar'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'birth_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('user_profiles');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_profiles', true);
    }
}
