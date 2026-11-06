<?php

declare(strict_types=1);

return [
    'title' => 'Configuración',
    'module' => [
        'name' => 'Configuración',
    ],
    'menu' => [
        'settings' => 'Configuración',
        'security' => 'Configuración de seguridad',
    ],
    'res' => [
        'settings' => 'Configuración',
    ],
    'perm' => [
        'update' => 'Abrir la configuración y ejecutar las acciones de correo de prueba y caché',
        'security' => 'Cambiar la configuración de seguridad y publicar recursos (con reautenticación)',
    ],
    'tabs' => [
        'general' => 'General',
        'theme' => 'Tema',
        'security' => 'Seguridad',
        'actions' => 'Acciones',
    ],
    'groups' => [
        'general' => 'General',
    ],
    'ui' => [
        'intro' => 'Solo se guardan los valores que cambie; un valor vacío o por defecto vuelve al del archivo de configuración.',
        'readOnly' => 'Puede ver esta configuración pero no cambiarla.',
        'securityIntro' => 'Esta configuración protege el sitio. Los valores que debilitarían la seguridad de base se rechazan, y cada cambio se escribe en el registro de seguridad.',
        'saved' => '{0, plural, =1{Se ha guardado un parámetro.} other{Se han guardado # parámetros.}}',
        'unchanged' => 'No ha cambiado nada.',
        'changed' => 'Cambiado',
        'default' => 'Por defecto: {0}',
        'empty' => '(vacío)',
        'on' => 'activado',
        'off' => 'desactivado',
        'onePerLine' => 'uno por línea',
    ],
    'theme' => [
        'intro' => 'Elija los temas del back office y del sitio público, y las opciones de un tema.',
        'areas' => 'Temas activos',
        'backend' => 'Tema del back office',
        'backendHelp' => 'Lo usan todas las páginas del back office.',
        'public' => 'Tema del sitio público',
        'publicHelp' => 'Lo usan el sitio público y las páginas de inicio de sesión.',
        'options' => 'Opciones del tema «{0}»',
        'edit' => 'Tema cuyas opciones edita',
        'choose' => 'Editar sus opciones',
    ],
    'actions' => [
        'email' => 'Enviar un correo de prueba',
        'emailHelp' => 'Pone en la cola un correo para {0}; la tarea programada que envía los correos lo entrega en un minuto ({1} correos en espera).',
        'emailSubject' => 'Correo de prueba de {0}',
        'emailBody' => 'Este es un correo de prueba de {0}. Si puede leerlo, la configuración del correo funciona.',
        'emailQueued' => 'Se ha puesto en la cola un correo de prueba para {0}; se envía en la próxima ejecución de las tareas programadas.',
        'emailFailed' => 'No se ha podido poner en la cola el correo de prueba.',
        'noEmail' => 'Su cuenta no tiene dirección de correo.',
        'caches' => 'Vaciar la caché',
        'cachesHelp' => 'Vuelve a generar los menús, los permisos, la configuración, los widgets y las listas de recursos. Se mantienen las sesiones y los límites de peticiones.',
        'cachesCleared' => 'Se ha vaciado la caché.',
        'assets' => 'Publicar los recursos',
        'assetsHelp' => 'Copia el JavaScript, los estilos y las imágenes de los módulos y de los temas a public/assets y public/themes, como php spark assets:publish.',
        'assetsConfirm' => '¿Publicar ahora los recursos de todos los módulos y temas?',
        'assetsPublished' => '{0, plural, =0{Los recursos ya están al día.} =1{Se ha publicado un archivo.} other{Se han publicado # archivos.}}',
        'assetsFailed' => 'No se han podido publicar los recursos; consulte el registro de seguridad y ejecute php spark assets:publish.',
        'unavailable' => 'No disponible',
    ],
    'activity' => [
        'settings_changed' => 'Configuración cambiada',
        'security_settings_changed' => 'Configuración de seguridad cambiada',
        'security_settings_refused' => 'Configuración de seguridad rechazada',
        'test_email' => 'Correo de prueba en la cola',
        'caches_cleared' => 'Caché vaciada',
        'assets_published' => 'Recursos publicados',
        'assets_publish_failed' => 'No se han podido publicar los recursos',
    ],
];
