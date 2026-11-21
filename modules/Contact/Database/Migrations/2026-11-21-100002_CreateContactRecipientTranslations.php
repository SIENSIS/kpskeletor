<?php

declare(strict_types=1);

namespace Modules\Contact\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `contact_recipient_translations`: the name of a recipient per language (kpcrudy
 * `setTranslations()`; the site's default language required).
 */
final class CreateContactRecipientTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'recipient_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 120],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['recipient_id', 'locale']);
        $this->forge->addForeignKey('recipient_id', 'contact_recipients', 'id', '', 'CASCADE');
        $this->forge->createTable('contact_recipient_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('contact_recipient_translations', true);
    }
}
