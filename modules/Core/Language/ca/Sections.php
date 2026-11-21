<?php

declare(strict_types=1);

/**
 * Seccions de les pàgines fetes amb seccions (registre del Core, tipus de camp i formularis;
 * docs/sections.md).
 */
return [
    'items' => '{0} element(s)',
    'form'  => [
        'content'     => 'Contingut',
        'appearance'  => 'Aparença',
        'advanced'    => 'Opcions avançades',
        'languages'   => 'Idioma dels textos:',
        'addItem'     => 'Afegeix un element',
        'removeItem'  => 'Elimina l’element',
        'moveUp'      => 'Puja',
        'moveDown'    => 'Baixa',
        'item'        => 'Element {0}',
        'itemActions' => 'Accions de l’element',
        'noChoices'   => 'No hi ha cap opció per triar.',
        'itemsLimit'  => 'de {0} a {1}',
    ],
    'pager' => [
        'previous' => 'Pàgina anterior',
        'next'     => 'Pàgina següent',
        'page'     => 'Pàgina',
    ],
    'options' => [
        'style'               => 'Estil',
        'background'          => 'Fons',
        'background_image'    => 'Imatge de fons',
        'backgroundImageHelp' => 'Una capa fosca sobre la imatge fa que el text es llegeixi bé. La imatge és decorativa.',
        'spacing'             => 'Espai',
        'width'               => 'Amplada',
        'anchor'              => 'Àncora',
        'anchorHelp'          => 'Opcional: minúscules, xifres i guions. Els enllaços a «#nom» porten a aquesta secció.',
        'hidden'              => 'Amagada (no es mostra a la pàgina)',
    ],
    'backgrounds' => [
        'none'    => 'Cap',
        'light'   => 'Clar',
        'dark'    => 'Fosc',
        'primary' => 'Color principal',
        'image'   => 'Imatge',
    ],
    'spacings' => [
        's' => 'Petit',
        'm' => 'Mitjà',
        'l' => 'Gran',
    ],
    'widths' => [
        'contained' => 'Centrada',
        'full'      => 'Tota l’amplada',
    ],
    'image' => [
        'alt'        => 'Text alternatiu',
        'altHelp'    => 'Descriviu què mostra la imatge per a les persones que no la poden veure.',
        'decorative' => 'Imatge decorativa (no aporta informació: sense text alternatiu)',
    ],
    'link' => [
        'type'  => 'Enllaç a',
        'none'  => 'Sense enllaç',
        'url'   => 'Adreça (https://… o /…)',
        'route' => 'Ruta del lloc (nom)',
        'value' => '{0}',
        'label' => 'Text de l’enllaç',
    ],
    'icon' => [
        'choose' => 'Tria',
        'search' => 'Cerca una icona',
        'help'   => 'El nom d’una icona de Bootstrap (p. ex. bi-star), o trieu-la a la graella.',
    ],
    'errors' => [
        'required'        => 'Cal omplir «{0}».',
        'requiredIn'      => 'Cal omplir «{0}» en {1}.',
        'invalid'         => '«{0}» té un valor que no és vàlid.',
        'tooLong'         => '«{0}» és massa llarg (com a màxim {1} caràcters).',
        'choice'          => '«{0}» té una opció que no s’ofereix.',
        'number'          => '«{0}» ha de ser un nombre enter.',
        'between'         => '«{0}» ha d’estar entre {1} i {2}.',
        'icon'            => '«{0}» no és una icona de la llista.',
        'image'           => '«{0}»: aquesta imatge no es pot fer servir aquí.',
        'alt'             => '«{0}» necessita un text alternatiu en {1}, o marqueu-la com a decorativa.',
        'url'             => 'l’adreça ha de començar per https:// o ser un camí d’aquest lloc que comenci per /.',
        'route'           => 'no hi ha cap ruta del lloc amb aquest nom.',
        'linkType'        => 'aquest tipus d’enllaç no s’ofereix.',
        'linkChoice'      => 'trieu una de les opcions.',
        'linkLabel'       => '«{0}» necessita el text de l’enllaç en {1}.',
        'tooMany'         => '«{0}» té massa elements (com a màxim {1}).',
        'tooFew'          => '«{0}» necessita com a mínim {1} element(s).',
        'unknownField'    => 'El camp «{0}» no forma part d’aquesta secció.',
        'options'         => 'Les opcions de la secció no són vàlides.',
        'anchor'          => 'L’àncora només pot tenir minúscules, xifres i guions, i ha de començar per una lletra.',
        'backgroundImage' => 'Trieu la imatge de fons, o un altre fons.',
        'unknownType'     => 'Aquest tipus de secció no està disponible.',
    ],
];
