<?php

declare(strict_types=1);

return [
    'name'    => 'Default',
    'menu'    => [
        'primary' => 'Main menu',
        'footer'  => 'Footer menu',
        'social'  => 'Social networks',
    ],
    'options' => [
        'logo'         => 'Logo',
        'logoHelp'     => 'Path or URL of the logo image. Empty: the site name with an icon.',
        'primaryColor' => 'Primary colour',
        'primaryColorDark'     => 'Primary colour in dark mode',
        'primaryColorDarkHelp' => 'Empty: the same as in light mode.',
        'logoDark'             => 'Logo for dark backgrounds',
        'logoDarkHelp'         => 'Used in dark mode. Empty: the normal logo.',
        'defaultMode'          => 'Default colour mode',
        'forceMode'            => 'Force a colour mode',
        'forceModeHelp'        => 'Ignores the visitors\' choice and hides the switch.',
        'forceModeNone'        => 'Do not force (visitors choose)',
        'modeSwitch'           => 'Show the light/dark switch',
        'sidebarColor' => 'Back-office sidebar colour',
        'font'         => 'Font',
        'fontSystem'   => 'System (sans-serif)',
        'fontSerif'    => 'Serif',
        'fontMono'     => 'Monospace',
        'footerText'   => 'Footer text',
        'cookieNotice' => 'Show the cookie notice',
    ],
    'cookies' => [
        'label'  => 'Cookie notice',
        'text'   => 'This site only uses the cookies it needs to work.',
        'accept' => 'Close the cookie notice',
    ],
];
