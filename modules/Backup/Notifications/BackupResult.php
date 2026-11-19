<?php

declare(strict_types=1);

namespace Modules\Backup\Notifications;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * A scheduled backup finished (data: `result` success|failed, `file`, `size`, `message`); sent
 * by ScheduledBackup to the holders of `backup.list`.
 */
final class BackupResult extends NotificationType
{
    public static function title(): string
    {
        return lang('Backup.notification.title');
    }

    public static function description(): string
    {
        return lang('Backup.notification.help');
    }

    public static function icon(): string
    {
        return 'bi bi-archive';
    }

    public function render(): NotificationMessage
    {
        if (($this->data['result'] ?? '') === 'success') {
            return new NotificationMessage(
                lang('Backup.notification.success'),
                lang('Backup.notification.successBody', [(string) ($this->data['file'] ?? ''), ui_bytes((int) ($this->data['size'] ?? 0))]),
                backend_link('backups'),
            );
        }

        return new NotificationMessage(
            lang('Backup.notification.failed'),
            (string) ($this->data['message'] ?? ''),
            backend_link('backups'),
        );
    }
}
