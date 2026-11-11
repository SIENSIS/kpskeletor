<?php

declare(strict_types=1);

return [
    'name'    => 'Por defecto',
    'pageTemplates' => [
        'default' => 'Por defecto (columna de lectura)',
        'wide'    => 'Ancha',
        'sidebar' => 'Con las subpáginas al lado',
    ],
    'menu'    => [
        'primary' => 'Menú principal',
        'footer'  => 'Menú del pie',
        'social'  => 'Redes sociales',
    ],
    'options' => [
        'logo'         => 'Logotipo',
        'logoHelp'     => 'Ruta o URL de la imagen del logotipo. Vacío: el nombre del sitio con un icono.',
        'primaryColor' => 'Color principal',
        'primaryColorDark'     => 'Color principal en modo oscuro',
        'primaryColorDarkHelp' => 'Vacío: el mismo que en modo claro.',
        'logoDark'             => 'Logotipo para fondos oscuros',
        'logoDarkHelp'         => 'Se usa en modo oscuro. Vacío: el logotipo normal.',
        'defaultMode'          => 'Modo de color por defecto',
        'forceMode'            => 'Forzar un modo de color',
        'forceModeHelp'        => 'Ignora la elección de los visitantes y oculta el selector.',
        'forceModeNone'        => 'No forzar (eligen los visitantes)',
        'modeSwitch'           => 'Mostrar el selector claro/oscuro',
        'sidebarColor' => 'Color de la barra lateral del back office',
        'font'         => 'Tipo de letra',
        'fontSystem'   => 'Del sistema (sin serifa)',
        'fontSerif'    => 'Con serifa',
        'fontMono'     => 'Monoespaciado',
        'footerText'   => 'Texto del pie',
        'cookieNotice' => 'Mostrar el aviso de cookies',
    ],
    'cookies' => [
        'label'  => 'Aviso de cookies',
        'text'   => 'Este sitio solo usa las cookies que necesita para funcionar.',
        'accept' => 'Cerrar el aviso de cookies',
    ],
];
