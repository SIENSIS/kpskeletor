<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/** A mandatory notification type with every channel (push tests). */
final class UrgentNotice extends NotificationType
{
    public static function title(): string
    {
        return 'Urgent';
    }

    public static function mandatory(): bool
    {
        return true;
    }

    public function render(): NotificationMessage
    {
        return new NotificationMessage('Urgent');
    }
}
