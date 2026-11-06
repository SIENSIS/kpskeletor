<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Types;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * A scheduled task failed (sent by TaskRunner to the holders of `schedule.list`).
 */
final class ScheduledTaskFailed extends NotificationType
{
    public static function title(): string
    {
        return lang('Core.notifications.types.taskFailed');
    }

    public static function description(): string
    {
        return lang('Core.notifications.types.taskFailedHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-exclamation-triangle';
    }

    public function render(): NotificationMessage
    {
        $class = (string) ($this->data['class'] ?? '');
        $short = str_contains($class, '\\') ? substr((string) strrchr($class, '\\'), 1) : $class;

        return new NotificationMessage(
            lang('Core.notifications.taskFailedTitle', [$short]),
            (string) ($this->data['output'] ?? ''),
            'backend/schedule',
        );
    }
}
