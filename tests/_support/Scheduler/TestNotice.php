<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/** A notification type with both channels by default and a link. */
final class TestNotice extends NotificationType
{
    public static function title(): string
    {
        return 'Test notice';
    }

    public function render(): NotificationMessage
    {
        return new NotificationMessage(
            lang('Core.notifications.taskFailedTitle', [(string) ($this->data['name'] ?? '')]),
            'Body of ' . ($this->data['name'] ?? ''),
            (string) ($this->data['url'] ?? 'backend/somewhere'),
        );
    }
}
