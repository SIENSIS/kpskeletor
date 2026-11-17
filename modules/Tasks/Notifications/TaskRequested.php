<?php

declare(strict_types=1);

namespace Modules\Tasks\Notifications;

use Kpcrudy\Crud;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * "New to-do task request" (tutorial 12): sent to the users who may review requests
 * (`tasks-requests.list`) when a visitor sends the public form. Data: id, title, name (the
 * visitor's name; the email address is not copied into the notification).
 */
final class TaskRequested extends NotificationType
{
    public static function title(): string
    {
        return lang('Tasks.notifications.requested');
    }

    public static function description(): string
    {
        return lang('Tasks.notifications.requestedHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-inbox';
    }

    public function render(): NotificationMessage
    {
        $id = (int) ($this->data['id'] ?? 0);

        return new NotificationMessage(
            lang('Tasks.notifications.requestedTitle', [(string) ($this->data['title'] ?? '')]),
            lang('Tasks.notifications.requestedBody', [(string) ($this->data['name'] ?? '')]),
            url: $id > 0 ? 'backend/tasks/requests/show/' . Crud::maskId($id, 'task_requests') : 'backend/tasks/requests',
        );
    }
}
