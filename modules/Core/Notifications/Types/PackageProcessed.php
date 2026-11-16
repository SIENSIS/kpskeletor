<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Types;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

/**
 * A staged package operation (install or uninstall approved in the Modules screen) ran from the
 * console (`modules:install --pending`): sent to the superadmin who approved it.
 */
final class PackageProcessed extends NotificationType
{
    public static function title(): string
    {
        return lang('Core.notifications.types.packageProcessed');
    }

    public static function description(): string
    {
        return lang('Core.notifications.types.packageProcessedHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-box-seam';
    }

    public function render(): NotificationMessage
    {
        $ok   = ($this->data['status'] ?? '') === 'installed';
        $name = (string) ($this->data['name'] ?? '');

        return new NotificationMessage(
            lang($ok ? 'Core.notifications.packageDone' : 'Core.notifications.packageFailed', [
                $name,
                lang('Core.packages.actions.' . (($this->data['action'] ?? '') === 'uninstall' ? 'uninstall' : 'install')),
            ]),
            (string) ($this->data['message'] ?? ''),
            'backend/modules/packages',
        );
    }
}
