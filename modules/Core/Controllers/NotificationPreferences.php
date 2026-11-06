<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Notifications\NotificationType;

/**
 * The logged-in user's notification preferences (resource `core-notifications`): the channels
 * of each type, by module; mandatory types are shown but cannot be changed.
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
        $this->breadcrumb(lang('Core.notifications.title'), site_url('backend/notifications'))->breadcrumb(lang('Core.notifications.preferences'));

        return $this->render('Modules\Core\Views\notifications\preferences', [
            'title'    => lang('Core.notifications.preferences'),
            'icon'     => 'bi bi-sliders',
            'groups'   => $groups,
            'channels' => NotificationType::CHANNELS,
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

        service('notifications')->savePreferences((int) auth()->id(), $choices);
        activity_log('Core', 'preferences_saved', ['resource' => 'core-notifications', 'changes' => ['channels' => $choices]]);

        return redirect()->to(site_url('backend/profile/notifications'))->with('success', lang('Core.notifications.preferencesSaved'));
    }
}
