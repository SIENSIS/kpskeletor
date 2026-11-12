<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `event_categories`: categories of the events created by hand (name and description in
 * `event_category_translations`), with a colour for the calendar and a unique slug for the public
 * calendar's filter (`calendar?category=<slug>`).
 */
final class CreateEventCategories extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'color'      => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('event_categories');
    }

    public function down(): void
    {
        $this->forge->dropTable('event_categories', true);
    }
}
