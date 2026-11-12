<?php

declare(strict_types=1);

namespace Modules\Events\Notifications;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * "Event reminder": sent by the SendReminders task to a user who asked for it in the event's
 * details, before each occurrence. Data: code (masked id), title, start (site time), date.
 */
final class EventReminder extends NotificationType
{
    public static function title(): string
    {
        return lang('Events.notifications.reminder');
    }

    public static function description(): string
    {
        return lang('Events.notifications.reminderHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-alarm';
    }

    public function render(): NotificationMessage
    {
        $code = (string) ($this->data['code'] ?? '');
        $date = (string) ($this->data['date'] ?? '');

        return new NotificationMessage(
            lang('Events.notifications.reminderTitle', [(string) ($this->data['title'] ?? '')]),
            lang('Events.notifications.reminderBody', [(string) ($this->data['start'] ?? '')]),
            url: $code !== '' ? 'backend/calendar/event/' . rawurlencode($code) . ($date !== '' ? '?date=' . rawurlencode($date) : '') : 'backend/calendar',
        );
    }
}
