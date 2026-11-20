<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The `push` channel (D-44-02) for the preferences stored before it existed: a type the user
 * receives through at least one channel also goes to push (which reaches only the devices the
 * user turns push on in); a type turned off entirely stays off. down() removes `push` again.
 */
final class AddPushToNotificationPreferences extends Migration
{
    public function up(): void
    {
        foreach ($this->db->table('notification_preferences')->select('id, channels')->get()->getResultArray() as $row) {
            $channels = array_values(array_filter(explode(',', (string) $row['channels']), static fn (string $c): bool => $c !== ''));

            if ($channels === [] || in_array('push', $channels, true)) {
                continue;
            }

            $this->db->table('notification_preferences')->where('id', $row['id'])->update(['channels' => implode(',', [...$channels, 'push'])]);
        }
    }

    public function down(): void
    {
        foreach ($this->db->table('notification_preferences')->select('id, channels')->like('channels', 'push')->get()->getResultArray() as $row) {
            $channels = array_values(array_filter(explode(',', (string) $row['channels']), static fn (string $c): bool => $c !== '' && $c !== 'push'));
            $this->db->table('notification_preferences')->where('id', $row['id'])->update(['channels' => implode(',', $channels)]);
        }
    }
}
