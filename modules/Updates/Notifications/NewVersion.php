<?php

declare(strict_types=1);

namespace Modules\Updates\Notifications;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * A new version of the application or of a module is available (data: `name` — `Core` for the
 * application —, `version`, `installed`); sent once per version by ReleaseChecker to the
 * holders of `updates.view`.
 */
final class NewVersion extends NotificationType
{
    public static function title(): string
    {
        return lang('Updates.notification.title');
    }

    public static function description(): string
    {
        return lang('Updates.notification.help');
    }

    public static function icon(): string
    {
        return 'bi bi-cloud-arrow-down';
    }

    public function render(): NotificationMessage
    {
        $name = (string) ($this->data['name'] ?? '');

        return new NotificationMessage(
            $name === 'Core' ? lang('Updates.notification.app', [(string) ($this->data['version'] ?? '')]) : lang('Updates.notification.module', [$name, (string) ($this->data['version'] ?? '')]),
            lang('Updates.notification.body', [(string) ($this->data['installed'] ?? '')]),
            backend_link('updates'),
        );
    }
}
