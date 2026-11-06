<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Database;
use Modules\Core\Notifications\Channels\DatabaseChannel;
use Modules\Core\Notifications\Channels\EmailChannel;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * Sends notifications to users, groups or the holders of a permission (docs/notifications.md;
 * the `notifications` service and the helpers `notify()`, `notifyGroup()`, `notifyPermission()`).
 *
 * - Only types declared by enabled modules are sent (others are logged and dropped).
 * - Banned and deleted users receive nothing; each user once per call.
 * - Channels: the user's preferences for the type, or its default channels; mandatory types
 *   always use their default channels.
 * - Delivery problems are logged and never thrown to the caller.
 */
final class Notifier
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly DeclarationRegistry $registry = new DeclarationRegistry(),
        private readonly DatabaseChannel $database = new DatabaseChannel(),
        private readonly EmailChannel $email = new EmailChannel(),
    ) {
    }

    /**
     * @param User|int|list<User|int> $recipients
     *
     * @return int Users reached through at least one channel
     */
    public function send(User|int|array $recipients, NotificationType $notification): int
    {
        $module = $this->moduleOf($notification::class);

        if ($module === null) {
            log_message('warning', 'Notification {type} not sent: it is not declared by an enabled module ($notifications in the manifest).', ['type' => $notification::class]);

            return 0;
        }

        $reached = 0;

        foreach ($this->users(is_array($recipients) ? $recipients : [$recipients]) as $user) {
            $reached += $this->deliver($user, $notification, $module) ? 1 : 0;
        }

        return $reached;
    }

    /**
     * Every member of the groups.
     *
     * @param string|list<string> $groups
     */
    public function toGroup(string|array $groups, NotificationType $notification): int
    {
        $groups = is_array($groups) ? $groups : [$groups];

        if ($groups === []) {
            return 0;
        }

        $ids = array_map(intval(...), array_column(
            $this->db()->table('auth_groups_users')->select('user_id')->distinct()->whereIn('group', $groups)->get()->getResultArray(),
            'user_id',
        ));

        return $ids === [] ? 0 : $this->send($ids, $notification);
    }

    /**
     * Every user holding the permission, checked user by user with `Authorization::for()`
     * (superadmins included; works without a session: CLI, scheduled tasks, API).
     */
    public function toPermission(string $permission, NotificationType $notification): int
    {
        $users = [];

        foreach ($this->allUsers() as $user) {
            if (Authorization::for($user)->can($permission)) {
                $users[] = $user;
            }
        }

        return $users === [] ? 0 : $this->send($users, $notification);
    }

    /**
     * The channels the user receives a type through.
     *
     * @param class-string<NotificationType> $type
     *
     * @return list<string>
     */
    public function channelsFor(int $userId, string $type): array
    {
        $defaults = array_values(array_intersect(NotificationType::CHANNELS, $type::defaultChannels()));

        if ($type::mandatory()) {
            return $defaults;
        }

        $stored = $this->db()->table('notification_preferences')->where('user_id', $userId)->where('type', $type)->get()->getRowArray();

        if ($stored === null) {
            return $defaults;
        }

        return self::parseChannels((string) $stored['channels']);
    }

    /**
     * The types users can receive, by module: class => [module, title, description, icon,
     * mandatory, defaults].
     *
     * @return array<class-string<NotificationType>, array{module: string, title: string, description: string, icon: string, mandatory: bool, defaults: list<string>}>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->registry->all('notification') as $class => $declaration) {
            if (! is_subclass_of($class, NotificationType::class)) {
                continue;
            }

            $types[$class] = [
                'module'      => $declaration['module'],
                'title'       => $class::title(),
                'description' => $class::description(),
                'icon'        => $class::icon(),
                'mandatory'   => $class::mandatory(),
                'defaults'    => array_values(array_intersect(NotificationType::CHANNELS, $class::defaultChannels())),
            ];
        }

        return $types;
    }

    /**
     * Stores a user's choices; types not listed keep their defaults, mandatory and unknown types
     * are ignored.
     *
     * @param array<string, list<string>> $choices type => channels
     */
    public function savePreferences(int $userId, array $choices): void
    {
        $now = Time::now()->toDateTimeString();

        foreach ($this->types() as $type => $info) {
            if ($info['mandatory'] || ! array_key_exists($type, $choices)) {
                continue;
            }

            $channels = implode(',', array_values(array_intersect(NotificationType::CHANNELS, $choices[$type])));
            $table    = $this->db()->table('notification_preferences');

            if ($table->where('user_id', $userId)->where('type', $type)->countAllResults() > 0) {
                $this->db()->table('notification_preferences')->where('user_id', $userId)->where('type', $type)->update(['channels' => $channels, 'updated_at' => $now]);
            } else {
                $this->db()->table('notification_preferences')->insert(['user_id' => $userId, 'type' => $type, 'channels' => $channels, 'updated_at' => $now]);
            }
        }
    }

    /**
     * Rebuilds a stored notification (null when its class no longer exists).
     *
     * @param array<string, mixed> $row A `notifications` row
     */
    public static function hydrate(array $row): ?NotificationType
    {
        $class = (string) ($row['type'] ?? '');

        if (! is_subclass_of($class, NotificationType::class)) {
            return null;
        }

        $data = json_decode((string) ($row['data'] ?? ''), true);

        return $class::fromData(is_array($data) ? $data : []);
    }

    /**
     * The message of a stored notification in the current language, or a generic one when its
     * type is gone or fails to render.
     *
     * @param array<string, mixed> $row
     */
    public static function messageOf(array $row): NotificationMessage
    {
        try {
            $notification = self::hydrate($row);

            if ($notification !== null) {
                return $notification->render();
            }
        } catch (Throwable $e) {
            log_message('error', 'Notification {id} could not be rendered: {message}', ['id' => $row['id'] ?? 0, 'message' => $e->getMessage()]);
        }

        return new NotificationMessage(lang('Core.notifications.unavailable'));
    }

    /**
     * The icon of a stored notification.
     *
     * @param array<string, mixed> $row
     */
    public static function iconOf(array $row): string
    {
        $class = (string) ($row['type'] ?? '');

        return is_subclass_of($class, NotificationType::class) ? $class::icon() : 'bi bi-bell';
    }

    /**
     * @return list<string>
     */
    private static function parseChannels(string $channels): array
    {
        return array_values(array_intersect(NotificationType::CHANNELS, explode(',', $channels)));
    }

    private function deliver(User $user, NotificationType $notification, string $module): bool
    {
        $reached = false;

        foreach ($this->channelsFor((int) $user->id, $notification::class) as $channel) {
            try {
                $sent = $channel === NotificationType::DATABASE
                    ? $this->database->deliver($user, $notification, $module)
                    : $this->email->deliver($user, $notification);
                $reached = $reached || $sent;
            } catch (Throwable $e) {
                log_message('error', 'Notification {type} to user {id} through {channel} failed: {message}', [
                    'type' => $notification::class, 'id' => $user->id, 'channel' => $channel, 'message' => $e->getMessage(),
                ]);
            }
        }

        return $reached;
    }

    /**
     * The module that declares the type, if it is enabled.
     */
    private function moduleOf(string $class): ?string
    {
        $declaration = $this->registry->all('notification')[$class] ?? null;

        return $declaration !== null && is_subclass_of($class, NotificationType::class) ? $declaration['module'] : null;
    }

    /**
     * Distinct users that can receive notifications (not banned, not deleted).
     *
     * @param list<User|int> $recipients
     *
     * @return list<User>
     */
    private function users(array $recipients): array
    {
        $users = [];
        $ids   = [];

        foreach ($recipients as $recipient) {
            if ($recipient instanceof User) {
                $users[(int) $recipient->id] = $recipient;
            } elseif ($recipient > 0) {
                $ids[$recipient] = $recipient;
            }
        }

        $ids = array_diff_key($ids, $users);

        if ($ids !== []) {
            foreach (model(UserModel::class)->whereIn('id', array_values($ids))->findAll() as $user) {
                $users[(int) $user->id] = $user;
            }
        }

        return array_values(array_filter($users, static fn (User $user): bool => $user->id !== null && ! $user->isBanned()));
    }

    /**
     * @return list<User>
     */
    private function allUsers(): array
    {
        return array_values(array_filter(model(UserModel::class)->findAll(), static fn (User $user): bool => ! $user->isBanned()));
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
