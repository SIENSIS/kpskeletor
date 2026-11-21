<?php

declare(strict_types=1);

namespace Modules\Contact\Notifications;

use Kpcrudy\Crud;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * "New contact message": sent to the members of the recipient's group who may read messages (or,
 * for a recipient without group or addresses, to the holders of `contact-messages.all`). Data:
 * id, subject, the visitor's name and the recipient's name (the visitor's address is not copied).
 */
final class MessageReceived extends NotificationType
{
    public static function title(): string
    {
        return lang('Contact.notifications.received');
    }

    public static function description(): string
    {
        return lang('Contact.notifications.receivedHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-envelope';
    }

    public function render(): NotificationMessage
    {
        $id = (int) ($this->data['id'] ?? 0);

        return new NotificationMessage(
            lang('Contact.notifications.receivedTitle', [(string) ($this->data['subject'] ?? '')]),
            lang('Contact.notifications.receivedBody', [(string) ($this->data['name'] ?? ''), (string) ($this->data['recipient'] ?? '')]),
            url: $id > 0 ? backend_link('contact/messages/show/' . Crud::maskId($id, 'contact_messages')) : backend_link('contact/messages'),
        );
    }
}
