<?php

declare(strict_types=1);

// Web Push notifications (the `push` channel, D-44-02).
return [
    'title'         => 'Notificaciones en mis dispositivos',
    'intro'         => 'Reciba sus notificaciones como notificaciones del sistema en este teléfono u ordenador, incluso con el sitio cerrado. Actívelas en cada navegador o aplicación instalada; abajo, elija qué notificaciones llegan (columna «En mis dispositivos»).',
    'off'           => 'Ahora mismo las notificaciones en los dispositivos no están disponibles en este sitio.',
    'noScript'      => 'Para activar las notificaciones en un dispositivo hace falta JavaScript.',
    'enable'        => 'Activar las notificaciones en este dispositivo',
    'disable'       => 'Desactivarlas en este dispositivo',
    'devices'       => 'Sus dispositivos',
    'noDevices'     => 'Todavía ningún dispositivo recibe sus notificaciones.',
    'added'         => 'Añadido el {0}',
    'remove'        => 'Eliminar',
    'removeConfirm' => '¿Quiere eliminar este dispositivo? Dejará de recibir notificaciones.',
    'removed'       => 'Se ha eliminado el dispositivo.',
    'renamed'       => 'Se ha cambiado el nombre del dispositivo.',
    'notFound'      => 'El dispositivo no existe o no es suyo.',
    'subscribed'    => 'Este dispositivo recibirá sus notificaciones.',
    'refused'       => [
        'url'    => 'El navegador ha enviado una suscripción no válida.',
        'scheme' => 'El servicio push del navegador debe usar HTTPS.',
        'host'   => 'Este sitio no acepta el servicio push del navegador.',
        'keys'   => 'El navegador ha enviado claves de cifrado no válidas.',
        'limit'  => 'Ha llegado al número máximo de dispositivos: primero elimine uno.',
    ],
    'test' => [
        'button' => 'Enviar una prueba',
        'title'  => 'Notificación de prueba',
        'body'   => 'Las notificaciones funcionan en este dispositivo.',
        'queued' => 'Se ha puesto la prueba en la cola: llegará en menos de un minuto.',
    ],
    'device'  => ['unknown' => 'Dispositivo desconocido'],
    'generic' => ['body' => 'Tiene una notificación nueva.'],
    'settings' => [
        'enabled'        => 'Notificaciones push en los dispositivos',
        'enabledHelp'    => 'Los usuarios pueden recibir sus notificaciones como notificaciones del sistema en sus teléfonos y ordenadores (Web Push, gratuito, sin cuenta de ningún proveedor). Hacen falta las claves VAPID en el .env (php spark push:keys).',
        'content'        => 'Contenido de las notificaciones push',
        'contentHelp'    => 'Las pantallas de bloqueo muestran las notificaciones push: «Solo un aviso» envía «Tiene una notificación nueva» y el texto se lee en el sitio.',
        'contentFull'    => 'Título y texto',
        'contentGeneric' => 'Solo un aviso',
    ],
    'task' => [
        'name'   => 'Enviar las notificaciones push de la cola',
        'result' => 'Enviadas: {0}. Para reintentar: {1}. Fallidas: {2}. Dispositivos eliminados: {3}. Aún en la cola: {4}. Mensajes antiguos borrados: {5}.',
        'off'    => 'Las notificaciones push están desactivadas: no se ha enviado nada. Aún en la cola: {0}. Mensajes antiguos borrados: {1}.',
    ],
    'cli' => [
        'envNotWritable' => 'El archivo .env no existe o no se puede escribir.',
        'exists'         => 'Las claves VAPID ya existen. Use --force para sustituirlas (se eliminan todas las suscripciones push).',
        'rotateWarning'  => 'Sustituir las claves VAPID elimina todas las suscripciones push ({0} dispositivos): los usuarios deberán volver a activar las notificaciones en cada dispositivo.',
        'confirm'        => '¿Quiere sustituir las claves?',
        'cancelled'      => 'No se ha cambiado nada.',
        'generated'      => 'Se han escrito las claves VAPID en el .env. Active el push en Configuración › Seguridad › Notificaciones.',
        'rotated'        => 'Se han sustituido las claves VAPID en el .env; se han eliminado {0} dispositivos.',
    ],
];
