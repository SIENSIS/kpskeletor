<?php

declare(strict_types=1);

return [
    'name'    => 'Por defecto',
    'menu'    => [
        'primary' => 'Menú principal',
        'footer'  => 'Menú del pie',
        'social'  => 'Redes sociales',
    ],
    'options' => [
        'logo'         => 'Logotipo',
        'logoHelp'     => 'Ruta o URL de la imagen del logotipo. Vacío: el nombre del sitio con un icono.',
        'primaryColor' => 'Color principal',
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
