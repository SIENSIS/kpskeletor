<?php

declare(strict_types=1);

namespace Modules\Auth\Notifications;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * An administrator reset the user's two-factor authentication (`twoFactorReset` event). Mandatory:
 * the user is always told, in the application and by email.
 */
final class TwoFactorReset extends NotificationType
{
    public static function title(): string
    {
        return lang('Auth.notifications.twoFactorReset');
    }

    public static function description(): string
    {
        return lang('Auth.notifications.twoFactorResetHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-shield-exclamation';
    }

    public static function mandatory(): bool
    {
        return true;
    }

    public function render(): NotificationMessage
    {
        return new NotificationMessage(
            lang('Auth.notifications.twoFactorResetTitle'),
            lang('Auth.notifications.twoFactorResetBody'),
            'backend/profile/two-factor',
        );
    }
}
