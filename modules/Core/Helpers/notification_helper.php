<?php

declare(strict_types=1);

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Notifications\NotificationType;

if (! function_exists('notify')) {
    /**
     * Sends a notification to users (entities or ids) through the channels each one chose
     * (docs/notifications.md). Returns the number of users reached.
     *
     * ```php
     * notify($post->author_id, new PostPublished(['id' => $post->id, 'title' => $post->title]));
     * ```
     *
     * @param User|int|list<User|int> $recipients
     */
    function notify(User|int|array $recipients, NotificationType $notification): int
    {
        return service('notifications')->send($recipients, $notification);
    }
}

if (! function_exists('notifyGroup')) {
    /**
     * Sends a notification to every member of the groups (e.g. `notifyGroup('admin', …)`).
     *
     * @param string|list<string> $groups
     */
    function notifyGroup(string|array $groups, NotificationType $notification): int
    {
        return service('notifications')->toGroup($groups, $notification);
    }
}

if (! function_exists('notifyPermission')) {
    /**
     * Sends a notification to every user holding the permission (checked with
     * `Authorization::for($user)`, e.g. `notifyPermission('backup.restore', …)`).
     */
    function notifyPermission(string $permission, NotificationType $notification): int
    {
        return service('notifications')->toPermission($permission, $notification);
    }
}
