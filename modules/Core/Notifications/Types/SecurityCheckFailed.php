<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Types;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * One or more security checks turned red (data: `checks`, the titles of the checks that turned
 * red, and `errors`, how many checks are in error now). Sent to the superadmins by
 * SecurityStatus::store() (daily task, "check again", `security:check`).
 */
final class SecurityCheckFailed extends NotificationType
{
    public static function title(): string
    {
        return lang('SecurityCheck.notification.type');
    }

    public static function description(): string
    {
        return lang('SecurityCheck.notification.help');
    }

    public static function icon(): string
    {
        return 'bi bi-shield-exclamation';
    }

    public function render(): NotificationMessage
    {
        $checks = array_map('strval', (array) ($this->data['checks'] ?? []));

        return new NotificationMessage(
            lang('SecurityCheck.notification.title', [(int) ($this->data['errors'] ?? count($checks))]),
            lang('SecurityCheck.notification.body', [implode(', ', $checks)]),
            backend_link('security'),
        );
    }
}
