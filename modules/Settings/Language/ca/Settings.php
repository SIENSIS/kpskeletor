<?php

declare(strict_types=1);

return [
    'title' => 'Configuració',
    'module' => [
        'name' => 'Configuració',
    ],
    'menu' => [
        'settings' => 'Configuració',
        'security' => 'Configuració de seguretat',
    ],
    'res' => [
        'settings' => 'Configuració',
    ],
    'perm' => [
        'update' => 'Obrir la configuració i executar les accions de correu de prova i memòria cau',
        'security' => 'Canviar la configuració de seguretat i publicar recursos (amb reautenticació)',
    ],
    'tabs' => [
        'general' => 'General',
        'theme' => 'Tema',
        'security' => 'Seguretat',
        'actions' => 'Accions',
    ],
    'groups' => [
        'general' => 'General',
    ],
    'ui' => [
        'intro' => 'Només es desen els valors que canvieu; un valor buit o per defecte torna al del fitxer de configuració.',
        'readOnly' => 'Podeu veure aquesta configuració però no canviar-la.',
        'securityIntro' => 'Aquesta configuració protegeix el lloc. Els valors que afeblirien la seguretat de base es rebutgen, i cada canvi s’escriu al registre de seguretat.',
        'saved' => '{0, plural, =1{S’ha desat un paràmetre.} other{S’han desat # paràmetres.}}',
        'unchanged' => 'No ha canviat res.',
        'changed' => 'Canviat',
        'default' => 'Per defecte: {0}',
        'empty' => '(buit)',
        'on' => 'activat',
        'off' => 'desactivat',
        'onePerLine' => 'un per línia',
    ],
    'theme' => [
        'intro' => 'Trieu els temes del back office i del lloc públic, i les opcions d’un tema.',
        'areas' => 'Temes actius',
        'backend' => 'Tema del back office',
        'backendHelp' => 'El fan servir totes les pàgines del back office.',
        'public' => 'Tema del lloc públic',
        'publicHelp' => 'El fan servir el lloc públic i les pàgines d’inici de sessió.',
        'options' => 'Opcions del tema «{0}»',
        'edit' => 'Tema les opcions del qual editeu',
        'choose' => 'Editar-ne les opcions',
    ],
    'actions' => [
        'email' => 'Enviar un correu de prova',
        'emailHelp' => 'Posa a la cua un correu per a {0}; la tasca programada que envia els correus l’entrega en un minut ({1} correus en espera).',
        'emailSubject' => 'Correu de prova de {0}',
        'emailBody' => 'Aquest és un correu de prova de {0}. Si el podeu llegir, la configuració del correu funciona.',
        'emailQueued' => 'S’ha posat a la cua un correu de prova per a {0}; s’envia a la propera execució de les tasques programades.',
        'emailFailed' => 'No s’ha pogut posar a la cua el correu de prova.',
        'noEmail' => 'El vostre compte no té adreça electrònica.',
        'caches' => 'Buidar la memòria cau',
        'cachesHelp' => 'Torna a generar els menús, els permisos, la configuració, els ginys i les llistes de recursos. Es mantenen les sessions i els límits de peticions.',
        'cachesCleared' => 'S’ha buidat la memòria cau.',
        'assets' => 'Publicar els recursos',
        'assetsHelp' => 'Copia el JavaScript, els estils i les imatges dels mòduls i dels temes a public/assets i public/themes, com php spark assets:publish.',
        'assetsConfirm' => 'Voleu publicar ara els recursos de tots els mòduls i temes?',
        'assetsPublished' => '{0, plural, =0{Els recursos ja estan al dia.} =1{S’ha publicat un fitxer.} other{S’han publicat # fitxers.}}',
        'assetsFailed' => 'No s’han pogut publicar els recursos; consulteu el registre de seguretat i executeu php spark assets:publish.',
        'unavailable' => 'No disponible',
    ],
    'activity' => [
        'settings_changed' => 'Configuració canviada',
        'security_settings_changed' => 'Configuració de seguretat canviada',
        'security_settings_refused' => 'Configuració de seguretat rebutjada',
        'test_email' => 'Correu de prova a la cua',
        'caches_cleared' => 'Memòria cau buidada',
        'assets_published' => 'Recursos publicats',
        'assets_publish_failed' => 'No s’han pogut publicar els recursos',
    ],
];
