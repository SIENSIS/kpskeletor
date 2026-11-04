<?php

declare(strict_types=1);

return [
    'name'    => 'Per defecte',
    'menu'    => [
        'primary' => 'Menú principal',
        'footer'  => 'Menú del peu',
        'social'  => 'Xarxes socials',
    ],
    'options' => [
        'logo'         => 'Logotip',
        'logoHelp'     => 'Camí o URL de la imatge del logotip. Buit: el nom del lloc amb una icona.',
        'primaryColor' => 'Color principal',
        'primaryColorDark'     => 'Color principal en mode fosc',
        'primaryColorDarkHelp' => 'Buit: el mateix que en mode clar.',
        'logoDark'             => 'Logotip per a fons foscos',
        'logoDarkHelp'         => 'S\'utilitza en mode fosc. Buit: el logotip normal.',
        'defaultMode'          => 'Mode de color per defecte',
        'forceMode'            => 'Força un mode de color',
        'forceModeHelp'        => 'Ignora l\'elecció dels visitants i amaga el selector.',
        'forceModeNone'        => 'No forcis (trien els visitants)',
        'modeSwitch'           => 'Mostra el selector clar/fosc',
        'sidebarColor' => 'Color de la barra lateral del back office',
        'font'         => 'Tipus de lletra',
        'fontSystem'   => 'Del sistema (sense serif)',
        'fontSerif'    => 'Amb serif',
        'fontMono'     => 'Monoespaiat',
        'footerText'   => 'Text del peu',
        'cookieNotice' => 'Mostra l\'avís de galetes',
    ],
    'cookies' => [
        'label'  => 'Avís de galetes',
        'text'   => 'Aquest lloc només fa servir les galetes que necessita per funcionar.',
        'accept' => 'Tanca l\'avís de galetes',
    ],
];
