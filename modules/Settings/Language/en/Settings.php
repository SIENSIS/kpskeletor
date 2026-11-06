<?php

declare(strict_types=1);

return [
    'title' => 'Settings',
    'module' => [
        'name' => 'Settings',
    ],
    'menu' => [
        'settings' => 'Settings',
        'security' => 'Security settings',
    ],
    'res' => [
        'settings' => 'Settings',
    ],
    'perm' => [
        'update' => 'Open the settings and run the test email and cache actions',
        'security' => 'Change the security settings and publish assets (with re-authentication)',
    ],
    'tabs' => [
        'general' => 'General',
        'theme' => 'Theme',
        'security' => 'Security',
        'actions' => 'Actions',
    ],
    'groups' => [
        'general' => 'General',
    ],
    'ui' => [
        'intro' => 'Only the values you change are stored; an empty or default value goes back to the configuration file.',
        'readOnly' => 'You can see these settings but not change them.',
        'securityIntro' => 'These settings protect the site. Values that would weaken the security baseline are refused, and every change is written to the security log.',
        'saved' => '{0, plural, =1{One setting saved.} other{# settings saved.}}',
        'unchanged' => 'Nothing changed.',
        'changed' => 'Changed',
        'default' => 'Default: {0}',
        'empty' => '(empty)',
        'on' => 'on',
        'off' => 'off',
        'onePerLine' => 'one per line',
    ],
    'theme' => [
        'intro' => 'Choose the themes of the back office and the public site, and the options of a theme.',
        'areas' => 'Active themes',
        'backend' => 'Back-office theme',
        'backendHelp' => 'Used by every back-office page.',
        'public' => 'Public site theme',
        'publicHelp' => 'Used by the public site and the login pages.',
        'options' => 'Options of the theme "{0}"',
        'edit' => 'Theme whose options you edit',
        'choose' => 'Edit its options',
    ],
    'actions' => [
        'email' => 'Send a test email',
        'emailHelp' => 'Queues an email to {0}; the scheduled task that sends emails delivers it within a minute ({1} emails waiting).',
        'emailSubject' => 'Test email from {0}',
        'emailBody' => 'This is a test email from {0}. If you can read it, the mail settings work.',
        'emailQueued' => 'A test email to {0} is queued; it is sent by the next run of the scheduled tasks.',
        'emailFailed' => 'The test email could not be queued.',
        'noEmail' => 'Your account has no email address.',
        'caches' => 'Clear caches',
        'cachesHelp' => 'Rebuilds the menus, permissions, settings, widgets and asset lists. Sessions and rate limits are kept.',
        'cachesCleared' => 'Caches cleared.',
        'assets' => 'Publish assets',
        'assetsHelp' => 'Copies the modules\' and themes\' JavaScript, styles and images into public/assets and public/themes, like php spark assets:publish.',
        'assetsConfirm' => 'Publish the assets of every module and theme now?',
        'assetsPublished' => '{0, plural, =0{Assets are up to date.} =1{One file published.} other{# files published.}}',
        'assetsFailed' => 'The assets could not be published; see the security log and run php spark assets:publish.',
        'unavailable' => 'Not available',
    ],
    'activity' => [
        'settings_changed' => 'Settings changed',
        'security_settings_changed' => 'Security settings changed',
        'security_settings_refused' => 'Security settings refused',
        'test_email' => 'Test email queued',
        'caches_cleared' => 'Caches cleared',
        'assets_published' => 'Assets published',
        'assets_publish_failed' => 'Assets could not be published',
    ],
];
