<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `ical_tokens`: the personal iCal feed of each user (docs/events.md, "iCal").
 *
 * - `token_hash`: SHA-256 of the random secret of the feed URL (the secret itself is shown once,
 *   when it is created or regenerated, and never stored);
 * - `categories`, `calendars`: the feed's filters (comma-separated category ids and calendar ids;
 *   empty = all);
 * - `last_access_at`, `last_access_ip`: shown in the profile; `revoked_at`: a revoked feed answers
 *   404. One row per user: regenerating replaces the hash.
 */
final class CreateIcalTokens extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token_hash'     => ['type' => 'CHAR', 'constraint' => 64],
            'categories'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'calendars'      => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'last_access_at' => ['type' => 'DATETIME', 'null' => true],
            'last_access_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'revoked_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('ical_tokens');
    }

    public function down(): void
    {
        $this->forge->dropTable('ical_tokens', true);
    }
}
