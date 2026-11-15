<?php

declare(strict_types=1);

namespace Modules\Core\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Kpapy\Auth\Context;
use Kpapy\Resource;
use Modules\Core\Notifications\Inbox;
use Modules\Core\Notifications\Notifier;

/**
 * API resource `notifications` (`api/v1/notifications`, kpapy, D-26-01): the token's user's own
 * notifications of the `database` channel (the bell), rendered in the request's language. Every
 * query is limited to the user (`where`), so someone else's id is 404.
 *
 * - `GET notifications[/{id}]` (`core-notifications.list` / `.show`), newest first;
 * - `GET notifications/unread`: `{"unread": n}` (`core-notifications.list`);
 * - `POST notifications/{id}/read`, `POST notifications/read-all` (`core-notifications.read`).
 *
 * Deleting stays in the back office. Read state is personal and, as in the back office, not
 * written to the activity log (D-26-03).
 */
final class Notifications extends Controller
{
    public const PATH = 'api/v1/notifications';

    public function index(): ResponseInterface
    {
        $user   = Context::user();
        $userId = is_numeric($user->id ?? null) ? (int) $user->id : 0;

        return (new Resource())
            ->setBasePath(self::PATH)
            ->setTable('notifications')
            ->setResource('core-notifications')
            ->where('user_id', $userId)
            ->fields(['id', 'module', 'type', 'title', 'body', 'icon', 'url', 'read', 'created_at'])
            ->fieldType('read', 'boolean')
            ->callbackField('type', static fn ($value, array $row): string => self::shortType((string) ($row['type'] ?? '')))
            ->callbackField('title', static fn ($value, array $row): string => Notifier::messageOf($row)->title)
            ->callbackField('body', static fn ($value, array $row): string => Notifier::messageOf($row)->body)
            ->callbackField('icon', static fn ($value, array $row): string => Notifier::iconOf($row))
            ->callbackField('url', static function ($value, array $row): ?string {
                $path = Notifier::messageOf($row)->path();

                return $path === null ? null : site_url($path);
            })
            ->callbackField('read', static fn ($value, array $row): bool => $row['read_at'] !== null)
            ->filters(['module'])
            ->searchFields(['module'])
            ->defaultOrder('created_at', 'desc')
            ->readOnly()
            ->unsetTrash()
            ->addOperation('unread', static fn (): array => ['unread' => (new Inbox($userId))->unreadCount()], [
                'row' => false, 'method' => 'get', 'permission' => 'list', 'description' => lang('Core.api.notifications.unread'),
            ])
            ->addOperation('read', static function (?string $id, ?array $row) use ($userId): array {
                $wasUnread = is_array($row) && $row['read_at'] === null;
                (new Inbox($userId))->markRead((int) ($row['id'] ?? 0));

                return ['read' => true, 'marked' => $wasUnread, 'read_at' => Time::now()->format(DATE_ATOM)];
            }, ['description' => lang('Core.api.notifications.read')])
            ->addOperation('read-all', static fn (): array => ['marked' => (new Inbox($userId))->markAllRead()], [
                'row' => false, 'permission' => 'read', 'description' => lang('Core.api.notifications.readAll'),
            ])
            ->describe(lang('Core.api.notifications.summary'), lang('Core.api.notifications.description'))
            ->render();
    }

    private static function shortType(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
