<?php

declare(strict_types=1);

// Web Push notifications (the `push` channel, D-44-02).
return [
    'title'         => 'Notifications on my devices',
    'intro'         => 'Receive your notifications as system notifications on this phone or computer, even with the site closed. Turn them on in each browser or installed app; below, choose which notifications go there ("On my devices" column).',
    'off'           => 'Notifications on devices are not available on this site right now.',
    'noScript'      => 'Turning notifications on in a device needs JavaScript.',
    'enable'        => 'Activate notifications on this device',
    'disable'       => 'Turn off on this device',
    'devices'       => 'Your devices',
    'noDevices'     => 'No device receives your notifications yet.',
    'added'         => 'Added {0}',
    'remove'        => 'Remove',
    'removeConfirm' => 'Remove this device? It will no longer receive notifications.',
    'removed'       => 'The device was removed.',
    'renamed'       => 'The device was renamed.',
    'notFound'      => 'The device does not exist or is not yours.',
    'subscribed'    => 'This device will receive your notifications.',
    'refused'       => [
        'url'    => 'The browser sent an invalid subscription.',
        'scheme' => 'The browser\'s push service must use HTTPS.',
        'host'   => 'The browser\'s push service is not one this site accepts.',
        'keys'   => 'The browser sent invalid encryption keys.',
        'limit'  => 'You have reached the maximum number of devices: remove one first.',
    ],
    'test' => [
        'button' => 'Send a test',
        'title'  => 'Test notification',
        'body'   => 'Notifications work on this device.',
        'queued' => 'Test queued: it arrives within a minute.',
    ],
    'device'  => ['unknown' => 'Unknown device'],
    'generic' => ['body' => 'You have a new notification.'],
    'settings' => [
        'enabled'        => 'Push notifications on devices',
        'enabledHelp'    => 'Users can receive their notifications as system notifications on their phones and computers (Web Push, free, no provider account). Needs the VAPID keys in .env (php spark push:keys).',
        'content'        => 'Content of push notifications',
        'contentHelp'    => 'Lock screens show push notifications: "Only a notice" sends "You have a new notification" and the text is read on the site.',
        'contentFull'    => 'Title and text',
        'contentGeneric' => 'Only a notice',
    ],
    'task' => [
        'name'   => 'Send the queued push notifications',
        'result' => 'Sent: {0}. To retry: {1}. Failed: {2}. Devices removed: {3}. Still queued: {4}. Old messages deleted: {5}.',
        'off'    => 'Push notifications are off: nothing sent. Still queued: {0}. Old messages deleted: {1}.',
    ],
    'cli' => [
        'envNotWritable' => 'The .env file does not exist or cannot be written.',
        'exists'         => 'The VAPID keys already exist. Use --force to replace them (every push subscription is removed).',
        'rotateWarning'  => 'Replacing the VAPID keys removes every push subscription ({0} devices): users must turn notifications on again on each device.',
        'confirm'        => 'Replace the keys?',
        'cancelled'      => 'Nothing changed.',
        'generated'      => 'VAPID keys written to .env. Turn push on in Settings › Security › Notifications.',
        'rotated'        => 'VAPID keys replaced in .env; {0} devices removed.',
    ],
];
