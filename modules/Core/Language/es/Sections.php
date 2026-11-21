<?php

declare(strict_types=1);

/**
 * Secciones de las páginas hechas con secciones (registro del Core, tipos de campo y formularios;
 * docs/sections.md).
 */
return [
    'items' => '{0} elemento(s)',
    'form'  => [
        'content'     => 'Contenido',
        'appearance'  => 'Apariencia',
        'advanced'    => 'Opciones avanzadas',
        'languages'   => 'Idioma de los textos:',
        'addItem'     => 'Añadir un elemento',
        'removeItem'  => 'Eliminar el elemento',
        'moveUp'      => 'Subir',
        'moveDown'    => 'Bajar',
        'item'        => 'Elemento {0}',
        'itemActions' => 'Acciones del elemento',
        'itemsLimit'  => 'de {0} a {1}',
    ],
    'options' => [
        'style'               => 'Estilo',
        'background'          => 'Fondo',
        'background_image'    => 'Imagen de fondo',
        'backgroundImageHelp' => 'Una capa oscura sobre la imagen hace que el texto se lea bien. La imagen es decorativa.',
        'spacing'             => 'Espacio',
        'width'               => 'Anchura',
        'anchor'              => 'Ancla',
        'anchorHelp'          => 'Opcional: minúsculas, cifras y guiones. Los enlaces a «#nombre» llevan a esta sección.',
        'hidden'              => 'Oculta (no se muestra en la página)',
    ],
    'backgrounds' => [
        'none'    => 'Ninguno',
        'light'   => 'Claro',
        'dark'    => 'Oscuro',
        'primary' => 'Color principal',
        'image'   => 'Imagen',
    ],
    'spacings' => [
        's' => 'Pequeño',
        'm' => 'Mediano',
        'l' => 'Grande',
    ],
    'widths' => [
        'contained' => 'Centrada',
        'full'      => 'Toda la anchura',
    ],
    'image' => [
        'alt'        => 'Texto alternativo',
        'altHelp'    => 'Describid qué muestra la imagen para las personas que no la pueden ver.',
        'decorative' => 'Imagen decorativa (no aporta información: sin texto alternativo)',
    ],
    'link' => [
        'type'  => 'Enlace a',
        'none'  => 'Sin enlace',
        'url'   => 'Dirección (https://… o /…)',
        'route' => 'Ruta del sitio (nombre)',
        'value' => '{0}',
        'label' => 'Texto del enlace',
    ],
    'icon' => [
        'choose' => 'Elegir',
        'search' => 'Buscar un icono',
        'help'   => 'El nombre de un icono de Bootstrap (p. ej. bi-star), o elegidlo en la cuadrícula.',
    ],
    'errors' => [
        'required'        => 'Hay que rellenar «{0}».',
        'requiredIn'      => 'Hay que rellenar «{0}» en {1}.',
        'invalid'         => '«{0}» tiene un valor que no es válido.',
        'tooLong'         => '«{0}» es demasiado largo (como máximo {1} caracteres).',
        'choice'          => '«{0}» tiene una opción que no se ofrece.',
        'number'          => '«{0}» debe ser un número entero.',
        'between'         => '«{0}» debe estar entre {1} y {2}.',
        'icon'            => '«{0}» no es un icono de la lista.',
        'image'           => '«{0}»: esta imagen no se puede usar aquí.',
        'alt'             => '«{0}» necesita un texto alternativo en {1}, o marcadla como decorativa.',
        'url'             => 'la dirección debe empezar por https:// o ser una ruta de este sitio que empiece por /.',
        'route'           => 'no hay ninguna ruta del sitio con este nombre.',
        'linkType'        => 'este tipo de enlace no se ofrece.',
        'linkChoice'      => 'elegid una de las opciones.',
        'linkLabel'       => '«{0}» necesita el texto del enlace en {1}.',
        'tooMany'         => '«{0}» tiene demasiados elementos (como máximo {1}).',
        'tooFew'          => '«{0}» necesita como mínimo {1} elemento(s).',
        'unknownField'    => 'El campo «{0}» no forma parte de esta sección.',
        'options'         => 'Las opciones de la sección no son válidas.',
        'anchor'          => 'El ancla solo puede tener minúsculas, cifras y guiones, y debe empezar por una letra.',
        'backgroundImage' => 'Elegid la imagen de fondo, u otro fondo.',
        'unknownType'     => 'Este tipo de sección no está disponible.',
    ],
];
