<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `event_translations`: title and description (rich text, sanitised) of an event, one row
 * per language (kpcrudy `setTranslations()`; Catalan required).
 */
final class CreateEventTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'event_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 190],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['event_id', 'locale']);
        $this->forge->addForeignKey('event_id', 'events', 'id', '', 'CASCADE');
        $this->forge->createTable('event_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('event_translations', true);
    }
}
