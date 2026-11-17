<?php

declare(strict_types=1);

namespace Modules\Tasks\Notifications;

use Kpcrudy\Crud;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * "Overdue to-do task" (tutorial 7): sent by the scheduled task `OverdueReminder` to the
 * assignees of an open to-do task past its due date, at most once a day. Data: id, title, due_on.
 */
final class TaskOverdue extends NotificationType
{
    public static function title(): string
    {
        return lang('Tasks.notifications.overdue');
    }

    public static function description(): string
    {
        return lang('Tasks.notifications.overdueHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-alarm';
    }

    public function render(): NotificationMessage
    {
        $id = (int) ($this->data['id'] ?? 0);

        return new NotificationMessage(
            lang('Tasks.notifications.overdueTitle', [(string) ($this->data['title'] ?? '')]),
            lang('Tasks.notifications.overdueBody', [(string) ($this->data['due_on'] ?? '')]),
            url: $id > 0 ? 'backend/tasks/tasks/show/' . Crud::maskId($id, 'tasks') : 'backend/tasks',
        );
    }
}
