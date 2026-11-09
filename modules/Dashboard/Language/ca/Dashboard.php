<?php

declare(strict_types=1);

/*
 * Dashboard module (ca).
 */
return [
    'res'  => ['dashboard' => 'Tauler'],
    'perm' => ['configure' => 'Configurar les disposicions dels grups'],
    'menu' => [
        'home'   => 'Tauler',
        'groups' => 'Ginys del tauler',
    ],
    'title'          => 'Tauler',
    'customize'      => 'Personalitza',
    'customizeHelp'  => 'Tria l’ordre, la mida i la visibilitat dels teus ginys.',
    'done'           => 'Fet',
    'editHelp'       => 'Arrossega els ginys per la nansa (o fes servir les fletxes del teclat a sobre) per reordenar-los, canvia’n la mida o amaga’ls. Els canvis es desen a l’instant.',
    'restore'        => 'Restaura la disposició del grup',
    'restoreConfirm' => 'S’oblidaran el teu ordre, les mides i els ginys amagats. Vols continuar?',
    'empty'          => 'No hi ha ginys per mostrar',
    'emptyHelp'      => 'Els teus grups no tenen cap giny activat, o no tens els permisos que necessiten.',
    'emptyHidden'    => 'Has amagat tots els ginys. Fes servir «Personalitza» per tornar-los a mostrar.',
    'hiddenTitle'    => 'Ginys amagats',
    'hiddenHelp'     => 'Fes servir «Personalitza» per tornar-los a mostrar.',
    'widget'         => 'Giny',
    'position'       => 'Posició',
    'size'           => 'Mida',
    'visible'        => 'Visible',
    'cancel'         => 'Cancel·la',
    'save'           => 'Desa',
    'saved'          => 'S’ha desat la disposició del teu tauler.',
    'restored'       => 'S’ha restaurat la disposició del grup.',
    'invalid'        => 'No s’ha pogut llegir la disposició.',
    'forbidden'      => 'No pots veure aquest giny.',
    'groups'         => [
        'title'           => 'Disposicions dels grups',
        'subtitle'        => 'Quins ginys té cada grup, en quin ordre, mida i opcions.',
        'groups'          => 'Grups',
        'help'            => 'Ginys activats per a «{0}» i els seus valors per defecte. Els usuaris només veuen un giny si també tenen el seu permís, i poden reordenar, canviar la mida i amagar els seus ginys.',
        'none'            => 'Cap mòdul ofereix ginys.',
        'noPermission'    => 'Aquest grup no té {0}: els seus usuaris no veuran el giny tret que tinguin el permís per una altra via.',
        'permission'      => 'Permís:',
        'saved'           => 'S’ha desat la disposició del grup.',
        'invalidPosition' => '«{0}»: la posició ha de ser un nombre enter entre 0 i 9999.',
        'invalidSize'     => '«{0}»: tria una de les mides oferides.',
    ],
    'js' => [
        'move'  => 'Mou (tecles de fletxa)',
        'size'  => 'Mida',
        'hide'  => 'Amaga',
        'show'  => 'Mostra',
        'saved' => 'Desat',
        'moved' => 'Mogut a la posició {0}',
    ],
    'activity' => [
        'group_layout_saved' => 'Disposició del tauler d’un grup desada',
        'widget_forbidden'   => 'Dades d’un giny denegades',
    ],
];
