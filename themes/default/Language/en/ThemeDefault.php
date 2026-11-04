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
