<?php

declare(strict_types=1);

return [
    'title'      => 'Eines de desenvolupament',
    'intro'      => 'Eines que ajuden a construir mòduls. Només existeixen a l’entorn de desenvolupament, des d’aquest ordinador, per als superadministradors.',
    'banner'     => 'Eines de desenvolupament: mai activades en producció.',
    'bannerHelp' => 'Només responen en aquest ordinador (http://localhost) mentre CI_ENVIRONMENT és development.',
    'module'     => ['description' => 'Eines només per a desenvolupament: receptes de CRUD i, més endavant, el generador de codi i el constructor de paquets.'],
    'menu'       => ['group' => 'Desenvolupament', 'tools' => 'Eines de desenvolupament'],
    'res'        => ['tools' => 'Eines de desenvolupament'],
    'perm'       => ['write' => 'Escriure fitxers i executar ordres des de les eines de desenvolupament'],
    'locks'      => [
        'title'       => 'Per què és segur',
        'environment' => 'Les seves rutes només existeixen quan CI_ENVIRONMENT és development: a qualsevol altre lloc totes les adreces de Devtools responen 404.',
        'manifest'    => 'És un mòdul només per a desenvolupament: no es pot activar ni instal·lar fora de desenvolupament, i un modules:sync allà el desactiva.',
        'check'       => 'security:check informa d’un error quan està activat fora de desenvolupament.',
        'local'       => 'Només respon peticions d’aquest ordinador (adreça loopback i un Host localhost); fes servir php spark serve.',
        'permission'  => 'Només superadministradors (devtools.view i devtools.write no s’atorguen a ningú); tot el que escriu torna a demanar la contrasenya.',
        'packaging'   => 'Forma part del repositori, i per tant és als arxius de les versions, però els panys anteriors el deixen inert.',
    ],
    'recipes' => [
        'title'    => 'Receptes de CRUD',
        'intro'    => 'Totes les variants d’un CRUD del back office i de la seva API, amb codi real de l’esquelet i la documentació d’on surt.',
        'contents' => 'Receptes',
        'what'     => 'Què és',
        'when'     => 'Quan fer-lo servir',
        'source'   => 'D’on surt',
        'copy'     => 'Copia',
        'copied'   => 'Copiat',
        'more'     => 'Més receptes: vendor/siensis/kpcrudy/docs/howto_EN.md (kpcrudy) i vendor/siensis/kpapy/docs/howto_EN.md (kpapy).',
        'plain'    => [
            'title' => 'CRUD senzill',
            'what'  => 'Una llista amb cerca, filtres, detall i formularis sobre una taula: CrudFactory::routes() a les rutes, $this->crud() i $this->renderCrud() en un BackendController.',
            'when'  => 'Cada pantalla del back office que llista i edita registres. Comença amb php spark make:module <Nom> --crud=<taula>.',
        ],
        'readonly' => [
            'title' => 'CRUD de només lectura',
            'what'  => 'El recurs només declara list i show («except» al manifest) i el controlador desactiva la resta amb unset*().',
            'when'  => 'Registres, historials i dades escrites pel codi (registre d’activitat, notificacions, tokens).',
        ],
        'operation' => [
            'title' => 'Operació pròpia',
            'what'  => 'Un botó a cada registre (i una acció massiva) que executa el teu codi, amb permís propi declarat a «extra».',
            'when'  => 'Accions que no són afegir/editar/esborrar: completar, publicar, acceptar, reenviar…',
        ],
        'reauth' => [
            'title' => 'Operacions amb reautenticació',
            'what'  => 'El filtre reauth torna a demanar la contrasenya abans d’executar la ruta; afegeix ratelimit:forms a les escriptures.',
            'when'  => 'Accions sensibles: restaurar còpies, descarregar dades, gestionar claus o la seguretat d’altres usuaris.',
        ],
        'recordRules' => [
            'title' => 'Regles per registre',
            'what'  => 'setPermission() amb una funció decideix per a cada registre per sobre del permís del rol (p. ex. només l’autor edita).',
            'when'  => 'Tothom veu els registres però només alguns en poden canviar alguns.',
        ],
        'ownRows' => [
            'title' => 'Només els registres propis',
            'what'  => 'where() limita la llista, el detall, les edicions, els esborrats, les accions massives i l’exportació; un id falsificat és «no trobat».',
            'when'  => 'Pantalles personals («les meves notícies», «la meva activitat»): un recurs propi atorgat a tots els grups.',
        ],
        'hiddenFields' => [
            'title' => 'Camps amagats segons el permís',
            'what'  => 'hideFields() després de comprovar un permís extra treu un camp de totes les vistes, de l’exportació i de la importació.',
            'when'  => 'Columnes sensibles: estimacions, notes internes, adreces IP, autors.',
        ],
        'translations' => [
            'title' => 'Camps traduïts',
            'what'  => 'setTranslations() desa els camps per idioma en una taula de traduccions; ContentTranslations dona els idiomes del lloc i exigeix el per defecte.',
            'when'  => 'Contingut que els visitants veuen en diversos idiomes (noms, títols, descripcions).',
        ],
        'richText' => [
            'title' => 'Text enriquit',
            'what'  => 'L’editor del Core en un textarea (RichTextEditor::attrs(), l’opció attrs d’un camp traduït) i HtmlSanitizer abans de desar i en mostrar.',
            'when'  => 'Cossos de pàgines, notícies i esdeveniments: text amb format, enllaços i imatges.',
        ],
        'uploads' => [
            'title' => 'Pujades i selector de fitxers',
            'what'  => 'Una subclasse de CrudUploadHandler mostra el selector de fitxers del Core i desa amb el servei Storage (llistes permeses, noms aleatoris).',
            'when'  => 'Adjunts, portades i imatges d’un registre. Mai els camps de pujada de kpcrudy sols.',
        ],
        'export' => [
            'title' => 'Exportació i importació',
            'what'  => 'Exportació CSV de les columnes triades (valors en brut amb callbackExport()) i importació, cadascuna amb el seu permís.',
            'when'  => 'Dades que es porten a un full de càlcul o es carreguen en bloc.',
        ],
        'trash' => [
            'title' => 'Paperera',
            'what'  => 'Una columna deleted_at converteix esborrar en «moure a la paperera» amb restaurar i purgar; desactiva-les si no es declaren.',
            'when'  => 'Registres que es poden esborrar per error (continguts, tasques).',
        ],
        'maskedIds' => [
            'title' => 'Ids emmascarats',
            'what'  => 'Tots els CRUD mostren codis en lloc d’ids (app.maskKey, compartida amb kpapy). Amaguen la numeració; no són control d’accés.',
            'when'  => 'Sempre activat. Mantén igualment els permisos i les regles per registre.',
        ],
        'activity' => [
            'title' => 'Registre d’activitat',
            'what'  => 'El Core registra escriptures, operacions, accions massives, importacions i exportacions (AuditedCrud); skipActivity() deixa fora les operacions que només canvien la vista.',
            'when'  => 'Res a fer en un CRUD; activity_log() per a altres accions.',
        ],
        'api' => [
            'title' => 'Recurs de l’API',
            'what'  => 'Un Resource de kpapy a api/v1/<camí> amb el filtre kpapy, declarat a $api, amb el mateix nom de recurs que el CRUD: els tokens fan servir els mateixos permisos.',
            'when'  => 'Aplicacions i integracions que llegeixen o canvien els mateixos registres.',
        ],
        'menu' => [
            'title' => 'Entrada de menú i permisos per defecte',
            'what'  => 'El $menu del manifest (recurs, zona, pare, ordre) i els $defaultGrants (s’apliquen un cop en instal·lar).',
            'when'  => 'Cada recurs nou: qui el rep i on apareix.',
        ],
    ],
];
