<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/** A mandatory type, database channel only. */
final class MandatoryNotice extends NotificationType
{
    public static function title(): string
    {
        return 'Mandatory notice';
    }

    public static function defaultChannels(): array
    {
        return [self::DATABASE];
    }

    public static function mandatory(): bool
    {
        return true;
    }

    public function render(): NotificationMessage
    {
        return new NotificationMessage('Mandatory');
    }
}
