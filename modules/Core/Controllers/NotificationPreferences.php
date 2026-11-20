<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Notifications\NotificationType;
use Modules\Core\Push\PushSubscriptions;
use Modules\Core\Push\WebPush;

/**
 * The logged-in user's notification preferences (resource `core-notifications`): the channels
 * of each type, by module; mandatory types are shown but cannot be changed. While Web Push is on
 * (`Core.pushEnabled`) the page also lists the user's push devices (`core/push.js`) and shows the
 * `push` column; otherwise the stored push choices are kept untouched.
 */
final class NotificationPreferences extends BackendController
{
    public function index(): string
    {
        $notifier = service('notifications');
        $userId   = (int) auth()->id();
        $groups   = [];

        foreach ($notifier->types() as $class => $info) {
            $groups[$info['module']][$class] = $info + ['channels' => $notifier->channelsFor($userId, $class)];
        }

        ksort($groups);
        $this->breadcrumb(lang('Core.notifications.title'), backend_url('notifications'))->breadcrumb(lang('Core.notifications.preferences'));

        return $this->render('Modules\Core\Views\notifications\preferences', [
            'title'    => lang('Core.notifications.preferences'),
            'icon'     => 'bi bi-sliders',
            'groups'   => $groups,
            'channels' => self::visibleChannels(),
            'push'     => WebPush::switchedOn() ? [
                'enabled' => WebPush::enabled(),
                'devices' => (new PushSubscriptions())->forUser($userId),
            ] : null,
        ]);
    }

    public function save(): RedirectResponse
    {
        $posted  = $this->request->getPost('channels');
        $posted  = is_array($posted) ? $posted : [];
        $choices = [];

        // Every type in the form is sent (an unchecked type has no entry: no channels).
        foreach ((array) $this->request->getPost('types') as $type) {
            if (! is_string($type)) {
                continue;
            }

            $selected       = $posted[$type] ?? [];
            $choices[$type] = is_array($selected) ? array_values(array_filter($selected, is_string(...))) : [];
        }

        service('notifications')->savePreferences((int) auth()->id(), $choices, self::visibleChannels());
        activity_log('Core', 'preferences_saved', ['resource' => 'core-notifications', 'changes' => ['channels' => $choices]]);

        return redirect()->to(backend_url('profile/notifications'))->with('success', lang('Core.notifications.preferencesSaved'));
    }

    /**
     * The channels of the form: `push` only while it works for the site.
     *
     * @return list<string>
     */
    private static function visibleChannels(): array
    {
        return WebPush::enabled() ? NotificationType::CHANNELS : array_values(array_diff(NotificationType::CHANNELS, [NotificationType::PUSH]));
    }
}
