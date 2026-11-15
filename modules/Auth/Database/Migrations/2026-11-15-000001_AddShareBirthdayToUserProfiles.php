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
        $this->forge->dropColumn('user_profiles', 'share_birthday');
    }
}
