<?php

declare(strict_types=1);

namespace Modules\Events\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

/**
 * Column `events.allow_reminders`: whether users may ask for a reminder of the event (always for
 * events created by hand; the `reminders` flag of the calendar API for published ones).
 */
final class AddAllowRemindersToEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('events', [
            'allow_reminders' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
    }

    public function down(): void
    {
        // The table may already be gone (a down() of its creating migration ran first).
        $db = $this->db;

        if (! $db instanceof BaseConnection || ! $db->tableExists('events')) {
            return;
        }

        $db->resetDataCache();

        if ($db->fieldExists('allow_reminders', 'events')) {
            $this->forge->dropColumn('events', 'allow_reminders');
        }
    }
}
