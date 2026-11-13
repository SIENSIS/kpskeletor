<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The optional event date of a notice (docs/news.md, "Calendar"): `event_starts_at`,
 * `event_ends_at` (optional) and `event_all_day`. A published notice with a date is published to
 * the `news.notices` calendar (calendar API, push).
 */
final class AddEventDateToNews extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('news', [
            'event_starts_at' => ['type' => 'DATETIME', 'null' => true],
            'event_ends_at'   => ['type' => 'DATETIME', 'null' => true],
            'event_all_day'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('news', ['event_starts_at', 'event_ends_at', 'event_all_day']);
    }
}
