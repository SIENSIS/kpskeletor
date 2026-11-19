<?php

declare(strict_types=1);

namespace Modules\Users\Notifications;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;
use Modules\Users\Services\BirthdayGreetings;

/**
 * "Birthday greeting" (D-25-09): sent by the SendBirthdayGreetings task to a user on their
 * birthday. The text is the administrator's message for the user's language
 * (translated setting `Users.birthdayGreeting`, `{name}` = the user's name). Bell by default; the user can
 * add email or turn it off in their notification preferences. Data: name.
 */
final class BirthdayGreeting extends NotificationType
{
    public static function title(): string
    {
        return lang('Users.greetings.notification');
    }

    public static function description(): string
    {
        return lang('Users.greetings.notificationHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-balloon-heart';
    }

    /**
     * @return list<string>
     */
    public static function defaultChannels(): array
    {
        return [self::DATABASE];
    }

    public function render(): NotificationMessage
    {
        return new NotificationMessage(
            lang('Users.greetings.title'),
            BirthdayGreetings::message(service('language')->getLocale(), (string) ($this->data['name'] ?? '')),
            url: backend_link('profile'),
        );
    }
}
