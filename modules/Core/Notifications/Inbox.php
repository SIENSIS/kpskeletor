<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;

/**
 * One user's notifications of the `database` channel: unread count, latest, mark as read. Every
 * query is limited to the user, so an id of someone else's notification is simply not found.
 */
final class Inbox
{
    /** Notifications shown in the bell. */
    public const LATEST = 8;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly int $userId,
        private readonly ?BaseConnection $db = null,
    ) {
    }

    public function unreadCount(): int
    {
        return $this->db()->table('notifications')->where('user_id', $this->userId)->where('read_at', null)->countAllResults();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = self::LATEST): array
    {
        return $this->db()->table('notifications')->where('user_id', $this->userId)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->limit($limit)->get()->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db()->table('notifications')->where('user_id', $this->userId)->where('id', $id)->get()->getRowArray();
    }

    /**
     * @return bool Whether the notification exists (and is the user's)
     */
    public function markRead(int $id): bool
    {
        if ($this->find($id) === null) {
            return false;
        }

        $this->db()->table('notifications')->where('user_id', $this->userId)->where('id', $id)->where('read_at', null)->update(['read_at' => Time::now()->toDateTimeString()]);

        return true;
    }

    /**
     * @param list<int>|null $ids Only these (null: all)
     *
     * @return int Notifications marked
     */
    public function markAllRead(?array $ids = null): int
    {
        $builder = $this->db()->table('notifications')->where('user_id', $this->userId)->where('read_at', null);

        if ($ids !== null) {
            if ($ids === []) {
                return 0;
            }

            $builder->whereIn('id', $ids);
        }

        $builder->update(['read_at' => Time::now()->toDateTimeString()]);

        return $this->db()->affectedRows();
    }

    /**
     * The bell's data: unread count and the latest notifications rendered in the current language.
     *
     * @return array{unread: int, items: list<array{id: int, title: string, body: string, icon: string, url: string, date: string, read: bool}>}
     */
    public function bell(): array
    {
        $items = [];

        foreach ($this->latest() as $row) {
            $message = Notifier::messageOf($row);
            $items[] = [
                'id'    => (int) $row['id'],
                'title' => $message->title,
                'body'  => mb_strimwidth($message->body, 0, 160, '…'),
                'icon'  => Notifier::iconOf($row),
                'url'   => site_url('backend/notifications/open/' . (int) $row['id']),
                'date'  => $row['created_at'] !== null ? (string) Time::parse((string) $row['created_at'])->toLocalizedString('d MMM y, HH:mm') : '',
                'read'  => $row['read_at'] !== null,
            ];
        }

        return ['unread' => $this->unreadCount(), 'items' => $items];
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
