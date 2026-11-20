<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Push;
use Modules\Core\Http\OutboundException;
use Throwable;

/**
 * The queue of the `push` channel (`push_queue`): `push()` stores one message per device;
 * `sendPending()` (the scheduled task SendQueuedPush, every minute) encrypts and sends a batch
 * through PushSender (OutboundClient). Answers of the push service:
 *
 * - 2xx: sent (the device's failures are reset);
 * - 404, 410: the subscription expired or was removed in the browser: the device is deleted;
 * - 429, 5xx, a network failure: retried later with an exponential backoff, up to `maxAttempts`;
 * - anything else (400, 401, 403, 413, a refused URL): the message fails and the device counts
 *   a failure; a device with `maxFailures` failures in a row is deleted.
 *
 * Messages are never sent inside a web request.
 */
final class PushQueue
{
    public const URGENCY_NORMAL = 'normal';
    public const URGENCY_HIGH   = 'high';

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly ?PushSender $sender = null,
        private readonly ?Push $config = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload what the service worker shows (title, body, url, icon, tag)
     */
    public function push(int $subscriptionId, array $payload, string $urgency = self::URGENCY_NORMAL): bool
    {
        $now = Time::now()->toDateTimeString();

        return (bool) $this->db()->table('push_queue')->insert([
            'subscription_id' => $subscriptionId,
            'payload'         => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'urgency'         => $urgency === self::URGENCY_HIGH ? self::URGENCY_HIGH : self::URGENCY_NORMAL,
            'status'          => 'pending',
            'attempts'        => 0,
            'available_at'    => $now,
            'created_at'      => $now,
        ]);
    }

    public function pendingCount(): int
    {
        return $this->db()->table('push_queue')->where('status', 'pending')->countAllResults();
    }

    /**
     * Sends up to `$limit` pending messages that are due.
     *
     * @return array{sent: int, retry: int, failed: int, removed: int}
     */
    public function sendPending(?int $limit = null, ?Time $now = null): array
    {
        $now ??= Time::now();
        $result = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'removed' => 0];
        $rows   = $this->db()->table('push_queue')
            ->where('status', 'pending')
            ->where('available_at <=', $now->toDateTimeString())
            ->orderBy('id')
            ->limit($limit ?? $this->config()->batch)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            // Claim the row so a parallel run does not send it twice.
            $claimed = $this->db()->table('push_queue')
                ->where('id', $row['id'])->where('status', 'pending')->where('attempts', $row['attempts'])
                ->update(['attempts' => (int) $row['attempts'] + 1, 'available_at' => $now->addMinutes(15)->toDateTimeString()]);

            if (! $claimed || $this->db()->affectedRows() !== 1) {
                continue;
            }

            $outcome = $this->deliver($row, $now);
            $result[$outcome]++;
        }

        return $result;
    }

    /**
     * Deletes sent and failed messages older than `keepDays`.
     */
    public function prune(?Time $now = null): int
    {
        $limit = ($now ?? Time::now())->subDays($this->config()->keepDays)->toDateTimeString();
        $this->db()->table('push_queue')->whereIn('status', ['sent', 'failed'])->where('created_at <', $limit)->delete();

        return $this->db()->affectedRows();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return 'sent'|'retry'|'failed'|'removed'
     */
    private function deliver(array $row, Time $now): string
    {
        $subscriptions = new PushSubscriptions($this->db, $this->config);
        $subscription  = $this->db()->table('push_subscriptions')->where('id', (int) $row['subscription_id'])->get()->getRowArray();

        if ($subscription === null) {
            $this->finish($row, 'failed', 'The device was removed.');

            return 'removed';
        }

        if (! $this->userIsActive((int) $subscription['user_id'])) {
            $subscriptions->removeUser((int) $subscription['user_id']);

            return 'removed';
        }

        try {
            $response = $this->sender()->send($subscription, (string) $row['payload'], (string) $row['urgency']);
            $status   = $response->status;
            $error    = 'HTTP ' . $status;
            $retry    = $status === 429 || $status >= 500;
        } catch (OutboundException $e) {
            $status = 0;
            $error  = $e->getMessage();
            $retry  = in_array($e->reason, [OutboundException::REASON_TRANSPORT, OutboundException::REASON_DNS], true);
        } catch (Throwable $e) {
            $status = 0;
            $error  = $e->getMessage();
            $retry  = false;
        }

        if ($status >= 200 && $status < 300) {
            $this->finish($row, 'sent', null, $now);
            $this->db()->table('push_subscriptions')->where('id', (int) $subscription['id'])->update(['failures' => 0, 'last_used_at' => $now->toDateTimeString()]);

            return 'sent';
        }

        if ($status === 404 || $status === 410) {
            $subscriptions->remove((int) $subscription['id']);
            log_message('info', 'Push device {id} removed: the push service answered {status}.', ['id' => $subscription['id'], 'status' => $status]);

            return 'removed';
        }

        $attempts = (int) $row['attempts'] + 1;

        if ($retry && $attempts < $this->config()->maxAttempts) {
            $this->db()->table('push_queue')->where('id', $row['id'])->update([
                'last_error'   => mb_substr($error, 0, 255),
                'available_at' => $now->addMinutes(2 ** ($attempts - 1))->toDateTimeString(),
            ]);

            return 'retry';
        }

        $this->finish($row, 'failed', $error);
        $failures = (int) $subscription['failures'] + 1;

        if ($failures >= $this->config()->maxFailures) {
            $subscriptions->remove((int) $subscription['id']);
            log_message('warning', 'Push device {id} removed after {failures} failed deliveries.', ['id' => $subscription['id'], 'failures' => $failures]);

            return 'removed';
        }

        $this->db()->table('push_subscriptions')->where('id', (int) $subscription['id'])->update(['failures' => $failures]);
        log_message('error', 'Push message {id} to device {device} failed: {error}', ['id' => $row['id'], 'device' => $subscription['id'], 'error' => $error]);

        return 'failed';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function finish(array $row, string $status, ?string $error, ?Time $now = null): void
    {
        $this->db()->table('push_queue')->where('id', $row['id'])->update([
            'status'     => $status,
            'last_error' => $error === null ? null : mb_substr($error, 0, 255),
            'sent_at'    => $now?->toDateTimeString(),
        ]);
    }

    /**
     * Deleted (or missing) users lose their devices; banned users keep them but receive
     * nothing (the Notifier never queues for them).
     */
    private function userIsActive(int $userId): bool
    {
        $user = $this->db()->table('users')->select('id, deleted_at')->where('id', $userId)->get()->getRowArray();

        return $user !== null && $user['deleted_at'] === null;
    }

    private function sender(): PushSender
    {
        return $this->sender ?? new PushSender($this->config());
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
