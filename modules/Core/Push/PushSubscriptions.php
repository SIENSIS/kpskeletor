<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Push;

/**
 * The devices users turned push notifications on in (`push_subscriptions`). Every method that
 * reads or changes a device takes the user id: a user only sees and removes their own devices.
 */
final class PushSubscriptions
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(private readonly ?BaseConnection $db = null, private readonly ?Push $config = null)
    {
    }

    /**
     * Validates and stores a subscription of the browser (the JSON of `PushSubscription`). An
     * endpoint already stored (the same browser, perhaps used by another account before) moves
     * to this user with the new keys.
     *
     * @return array{id: int}|array{error: string} the device id, or why it is refused (url,
     *                                             scheme, host, keys, limit)
     */
    public function subscribe(int $userId, string $endpoint, string $p256dh, string $auth, string $label): array
    {
        $problem = PushEndpoint::problem($endpoint, $this->config()->serviceHosts);

        if ($problem !== null) {
            return ['error' => $problem];
        }

        $public = Base64Url::decode($p256dh);
        $secret = Base64Url::decode($auth);

        if ($public === null || $secret === null || strlen($secret) !== 16 || ! EcKey::isPublicKey($public)) {
            return ['error' => 'keys'];
        }

        $now      = Time::now()->toDateTimeString();
        $hash     = hash('sha256', $endpoint);
        $existing = $this->db()->table('push_subscriptions')->where('endpoint_hash', $hash)->get()->getRowArray();
        $values   = [
            'user_id'  => $userId,
            'p256dh'   => Base64Url::encode($public),
            'auth'     => Base64Url::encode($secret),
            'failures' => 0,
        ];

        if ($existing !== null) {
            if ((int) $existing['user_id'] !== $userId) {
                // Another account's messages must not reach the new user: drop what is queued.
                $this->db()->table('push_queue')->where('subscription_id', (int) $existing['id'])->delete();
                $values['label'] = DeviceLabel::clean($label);
            }

            $this->db()->table('push_subscriptions')->where('id', (int) $existing['id'])->update($values);

            return ['id' => (int) $existing['id']];
        }

        if ($this->countFor($userId) >= $this->config()->maxDevices) {
            return ['error' => 'limit'];
        }

        $this->db()->table('push_subscriptions')->insert($values + [
            'endpoint'      => $endpoint,
            'endpoint_hash' => $hash,
            'label'         => DeviceLabel::clean($label),
            'created_at'    => $now,
        ]);

        return ['id' => (int) $this->db()->insertID()];
    }

    /**
     * The user's devices, newest first (without the keys).
     *
     * @return list<array{id: int, label: string, created_at: string|null, last_used_at: string|null, failures: int, endpoint_hash: string}>
     */
    public function forUser(int $userId): array
    {
        $rows = $this->db()->table('push_subscriptions')
            ->select('id, label, created_at, last_used_at, failures, endpoint_hash')
            ->where('user_id', $userId)->orderBy('id', 'DESC')->get()->getResultArray();

        return array_map(static fn (array $row): array => [
            'id'            => (int) $row['id'],
            'label'         => (string) $row['label'],
            'created_at'    => $row['created_at'] !== null ? (string) $row['created_at'] : null,
            'last_used_at'  => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            'failures'      => (int) $row['failures'],
            'endpoint_hash' => (string) $row['endpoint_hash'],
        ], $rows);
    }

    /**
     * Full rows (with endpoint and keys) of the user's devices, for queueing.
     *
     * @return list<array<string, mixed>>
     */
    public function rowsFor(int $userId): array
    {
        return $this->db()->table('push_subscriptions')->where('user_id', $userId)->orderBy('id')->get()->getResultArray();
    }

    public function countFor(int $userId): int
    {
        return $this->db()->table('push_subscriptions')->where('user_id', $userId)->countAllResults();
    }

    /**
     * A device of the user, or null (another user's device is never returned).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $userId, int $id): ?array
    {
        return $this->db()->table('push_subscriptions')->where('id', $id)->where('user_id', $userId)->get()->getRowArray();
    }

    /**
     * Removes a device of the user and its queued messages.
     */
    public function delete(int $userId, int $id): bool
    {
        if ($this->find($userId, $id) === null) {
            return false;
        }

        $this->remove($id);

        return true;
    }

    public function rename(int $userId, int $id, string $label): bool
    {
        $label = DeviceLabel::clean($label);

        if ($label === '' || $this->find($userId, $id) === null) {
            return false;
        }

        return $this->db()->table('push_subscriptions')->where('id', $id)->where('user_id', $userId)->update(['label' => $label]);
    }

    /**
     * Removes a device whatever its user (the push service said it is gone, too many failures,
     * the user was deleted).
     */
    public function remove(int $id): void
    {
        $this->db()->table('push_queue')->where('subscription_id', $id)->delete();
        $this->db()->table('push_subscriptions')->where('id', $id)->delete();
    }

    /**
     * Removes every device of a user.
     */
    public function removeUser(int $userId): int
    {
        $ids = array_map(intval(...), array_column($this->db()->table('push_subscriptions')->select('id')->where('user_id', $userId)->get()->getResultArray(), 'id'));

        foreach ($ids as $id) {
            $this->remove($id);
        }

        return count($ids);
    }

    /** Devices of every user (shown before rotating the keys). */
    public function countAll(): int
    {
        return $this->db()->table('push_subscriptions')->countAllResults();
    }

    /**
     * Removes every device (the VAPID keys were rotated: the browsers' subscriptions are bound to
     * the old public key).
     */
    public function removeAll(): int
    {
        $count = $this->db()->table('push_subscriptions')->countAllResults();
        $this->db()->table('push_queue')->emptyTable();
        $this->db()->table('push_subscriptions')->emptyTable();

        return $count;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }

    private function config(): Push
    {
        return $this->config ?? config(Push::class);
    }
}
