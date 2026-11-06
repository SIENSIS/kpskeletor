<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Channels;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Config\Database;
use Modules\Core\Notifications\NotificationType;

/**
 * The `database` channel: a row in `notifications` for the bell and the list page.
 */
final class DatabaseChannel
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    public function deliver(User $user, NotificationType $notification, string $module): bool
    {
        return (bool) ($this->db ?? Database::connect())->table('notifications')->insert([
            'user_id'    => (int) $user->id,
            'module'     => $module,
            'type'       => $notification::class,
            'data'       => json_encode($notification->data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => Time::now()->toDateTimeString(),
        ]);
    }
}
