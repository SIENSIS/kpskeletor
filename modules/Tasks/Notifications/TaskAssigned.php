<?php

declare(strict_types=1);

namespace Modules\Tasks\Notifications;

use Kpcrudy\Crud;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * "To-do task assigned to you" (tutorial 6): sent to the users who become assignees of a to-do
 * task (form, API), never to the user who made the change. Data: id, title, due_on.
 */
final class TaskAssigned extends NotificationType
{
    public static function title(): string
    {
        return lang('Tasks.notifications.assigned');
    }

    public static function description(): string
    {
        return lang('Tasks.notifications.assignedHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-person-check';
    }

    public function render(): NotificationMessage
    {
        $id  = (int) ($this->data['id'] ?? 0);
        $due = (string) ($this->data['due_on'] ?? '');

        return new NotificationMessage(
            lang('Tasks.notifications.assignedTitle', [(string) ($this->data['title'] ?? '')]),
            $due !== '' ? lang('Tasks.notifications.assignedDue', [$due]) : lang('Tasks.notifications.assignedBody'),
            url: $id > 0 ? 'backend/tasks/tasks/show/' . Crud::maskId($id, 'tasks') : 'backend/tasks',
        );
    }
}
