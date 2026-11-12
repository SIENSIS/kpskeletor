<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `events`: one row per event (docs/events.md). Title and description live in
 * `event_translations`; the audience of a `restricted` event in `event_audience`.
 *
 * - Dates are stored in the application's time zone (UTC) and shown in the site's (`Core.timezone`);
 *   `ends_at` is optional (an event without an end lasts one hour, or the whole day when
 *   `all_day`).
 * - `visibility`: `public` (everyone, also the public site), `private` (any signed-in user) or
 *   `restricted` (only its audience: groups and users).
 * - `status`: `confirmed`, `tentative` or `cancelled`; `sequence` grows on every change (iCal).
 * - Recurrence (expanded when queried, never stored): `recur_freq` (`daily`, `weekly`, `monthly`,
 *   `yearly` or null), `recur_interval`, `recur_days` (weekly: `mo,tu,…`), `recur_until` (a date)
 *   or `recur_count`, and `recur_exceptions` (skipped dates, `YYYY-MM-DD`, one per line).
 * - `calendar`, `source_module`, `source_key`, `source_url`: events published by other modules
 *   through the calendar API (session 25); null for events created by hand.
 * - `created_by`: who created it (the administration list of a user without `events-events.all`
 *   also shows the events they created).
 */
final class CreateEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'category_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'starts_at'        => ['type' => 'DATETIME'],
            'ends_at'          => ['type' => 'DATETIME', 'null' => true],
            'all_day'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'location'         => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'color'            => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true],
            'url'              => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'visibility'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'private'],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'confirmed'],
            'organiser'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'recur_freq'       => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'recur_interval'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'recur_days'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'recur_until'      => ['type' => 'DATE', 'null' => true],
            'recur_count'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'recur_exceptions' => ['type' => 'TEXT', 'null' => true],
            'calendar'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'source_module'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'source_key'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'source_url'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'sequence'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['starts_at', 'ends_at']);
        $this->forge->addKey(['source_module', 'source_key']);
        $this->forge->addKey('calendar');
        $this->forge->addKey('created_by');
        $this->forge->addForeignKey('category_id', 'event_categories', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('events');
    }

    public function down(): void
    {
        $this->forge->dropTable('events', true);
    }
}
