<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Column `user_profiles.share_birthday`: whether the user shares their birthday (day and month) in
 * the "Birthdays" calendar (D-25-08). Off by default: consent is explicit.
 */
final class AddShareBirthdayToUserProfiles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('user_profiles', [
            'share_birthday' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
    }

    public function down(): void
    {
        // The table may already be gone (a down() of its creating migration ran first).
        if (! $this->db->tableExists('user_profiles')) {
            return;
        }

        $this->db->resetDataCache();
        $columns = array_values(array_filter((array) 'share_birthday', fn (string $column): bool => $this->db->fieldExists($column, 'user_profiles')));

        if ($columns !== []) {
            $this->forge->dropColumn('user_profiles', $columns);
        }
    }
}
