<?php

declare(strict_types=1);

namespace Modules\News\Notifications;

use Kpcrudy\Crud;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * "New notice": sent once to the audience of a `private` or `groups` notice when it is published
 * (`Publisher::notifyAudience()`); links to the reader screen. Data: id, title, priority.
 */
final class NewNotice extends NotificationType
{
    public static function title(): string
    {
        return lang('News.notifications.newNotice');
    }

    public static function description(): string
    {
        return lang('News.notifications.newNoticeHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-megaphone';
    }

    public function render(): NotificationMessage
    {
        $id       = (int) ($this->data['id'] ?? 0);
        $priority = (string) ($this->data['priority'] ?? 'info');
        $label    = lang('News.priorities.' . $priority);

        return new NotificationMessage(
            lang('News.notifications.newNoticeTitle', [(string) ($this->data['title'] ?? '')]),
            lang('News.notifications.newNoticeBody', [is_string($label) ? $label : $priority]),
            url: $id > 0 ? 'backend/news/read/' . Crud::maskId($id, 'news') : 'backend/news',
        );
    }
}
