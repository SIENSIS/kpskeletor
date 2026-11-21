<?php

declare(strict_types=1);

return [
    'name'    => 'Per defecte',
    'pageTemplates' => [
        'default' => 'Per defecte (columna de lectura)',
        'wide'    => 'Ampla',
        'sidebar' => 'Amb les subpàgines al costat',
    ],
    'menu'    => [
        'primary' => 'Menú principal',
        'footer'  => 'Menú del peu',
        'social'  => 'Xarxes socials',
    ],
    'zones'   => [
        'afterHeader'  => 'Blocs reutilitzables després de la capçalera',
        'beforeFooter' => 'Blocs reutilitzables abans del peu de pàgina',
    ],
    'footer'  => ['contact' => 'Contacte'],
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
        'languageSwitch'       => 'Mostra el selector d\'idioma a la web pública',
        'showAccountLink'      => 'Mostra l\'enllaç d\'accés a la web pública',
        'showAccountLinkHelp'  => '«Entra» per als visitants, «Administració» per als usuaris que poden obrir l\'administració i «El meu compte» per a la resta.',
        'sidebarColor' => 'Color de la barra lateral del back office',
        'font'         => 'Tipus de lletra',
        'fontSystem'   => 'Del sistema (sense serif)',
        'fontSerif'    => 'Amb serif',
        'fontMono'     => 'Monoespaiat',
        'footerText'   => 'Text del peu',
        'footerColumns'     => 'Columnes del peu de pàgina (en pantalles amples)',
        'footerColumnsHelp' => 'Quan el menú del peu té elements amb subelements, cadascun és una columna amb els seus enllaços.',
        'footerContact'     => 'Columna de contacte del peu',
        'footerContactHelp' => 'Adreça i dades de contacte mostrades com a primera columna del peu. Separa les línies amb |. Buit: sense columna.',
        'cookieNotice' => 'Mostra l\'avís de galetes',
    ],
    'cookies' => [
        'label'  => 'Avís de galetes',
        'text'   => 'Aquest lloc només fa servir les galetes que necessita per funcionar.',
        'accept' => 'Tanca l\'avís de galetes',
    ],
];
