<?php

declare(strict_types=1);

// Web Push notifications (the `push` channel, D-44-02).
return [
    'title'         => 'Notificacions als meus dispositius',
    'intro'         => 'Rebeu les notificacions com a notificacions del sistema en aquest telèfon o ordinador, fins i tot amb el lloc tancat. Activeu-les a cada navegador o aplicació instal·lada; a sota, trieu quines notificacions hi arriben (columna «Als meus dispositius»).',
    'off'           => 'Ara mateix les notificacions als dispositius no estan disponibles en aquest lloc.',
    'noScript'      => 'Per activar les notificacions en un dispositiu cal JavaScript.',
    'enable'        => 'Activa les notificacions en aquest dispositiu',
    'disable'       => 'Desactiva-les en aquest dispositiu',
    'devices'       => 'Els vostres dispositius',
    'noDevices'     => 'Encara cap dispositiu no rep les vostres notificacions.',
    'added'         => 'Afegit el {0}',
    'remove'        => 'Elimina',
    'removeConfirm' => 'Voleu eliminar aquest dispositiu? Deixarà de rebre notificacions.',
    'removed'       => 'S\'ha eliminat el dispositiu.',
    'renamed'       => 'S\'ha canviat el nom del dispositiu.',
    'notFound'      => 'El dispositiu no existeix o no és vostre.',
    'subscribed'    => 'Aquest dispositiu rebrà les vostres notificacions.',
    'refused'       => [
        'url'    => 'El navegador ha enviat una subscripció no vàlida.',
        'scheme' => 'El servei push del navegador ha de fer servir HTTPS.',
        'host'   => 'Aquest lloc no accepta el servei push del navegador.',
        'keys'   => 'El navegador ha enviat claus de xifratge no vàlides.',
        'limit'  => 'Heu arribat al nombre màxim de dispositius: primer n\'heu d\'eliminar un.',
    ],
    'test' => [
        'button' => 'Envia una prova',
        'title'  => 'Notificació de prova',
        'body'   => 'Les notificacions funcionen en aquest dispositiu.',
        'queued' => 'S\'ha posat la prova a la cua: arribarà en menys d\'un minut.',
    ],
    'device'  => ['unknown' => 'Dispositiu desconegut'],
    'generic' => ['body' => 'Teniu una notificació nova.'],
    'settings' => [
        'enabled'        => 'Notificacions push als dispositius',
        'enabledHelp'    => 'Els usuaris poden rebre les notificacions com a notificacions del sistema als telèfons i ordinadors (Web Push, gratuït, sense compte de cap proveïdor). Calen les claus VAPID al .env (php spark push:keys).',
        'content'        => 'Contingut de les notificacions push',
        'contentHelp'    => 'Les pantalles de bloqueig mostren les notificacions push: «Només un avís» envia «Teniu una notificació nova» i el text es llegeix al lloc.',
        'contentFull'    => 'Títol i text',
        'contentGeneric' => 'Només un avís',
    ],
    'task' => [
        'name'   => 'Envia les notificacions push de la cua',
        'result' => 'Enviades: {0}. Per reintentar: {1}. Fallides: {2}. Dispositius eliminats: {3}. Encara a la cua: {4}. Missatges antics esborrats: {5}.',
        'off'    => 'Les notificacions push estan desactivades: no s\'ha enviat res. Encara a la cua: {0}. Missatges antics esborrats: {1}.',
    ],
    'cli' => [
        'envNotWritable' => 'El fitxer .env no existeix o no s\'hi pot escriure.',
        'exists'         => 'Les claus VAPID ja existeixen. Feu servir --force per substituir-les (s\'eliminen totes les subscripcions push).',
        'rotateWarning'  => 'Substituir les claus VAPID elimina totes les subscripcions push ({0} dispositius): els usuaris hauran de tornar a activar les notificacions a cada dispositiu.',
        'confirm'        => 'Voleu substituir les claus?',
        'cancelled'      => 'No s\'ha canviat res.',
        'generated'      => 'S\'han escrit les claus VAPID al .env. Activeu el push a Configuració › Seguretat › Notificacions.',
        'rotated'        => 'S\'han substituït les claus VAPID al .env; s\'han eliminat {0} dispositius.',
    ],
];
