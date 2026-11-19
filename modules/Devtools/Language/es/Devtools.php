<?php

declare(strict_types=1);

return [
    'title'      => 'Herramientas de desarrollo',
    'intro'      => 'Herramientas que ayudan a construir módulos. Solo existen en el entorno de desarrollo, desde este ordenador, para los superadministradores.',
    'banner'     => 'Herramientas de desarrollo: nunca activadas en producción.',
    'bannerHelp' => 'Solo responden en este ordenador (http://localhost) mientras CI_ENVIRONMENT es development.',
    'module'     => ['description' => 'Herramientas solo para desarrollo: recetas de CRUD y, más adelante, el generador de código y el constructor de paquetes.'],
    'menu'       => ['tools' => 'Herramientas de desarrollo'],
    'res'        => ['tools' => 'Herramientas de desarrollo'],
    'perm'       => ['write' => 'Escribir ficheros y ejecutar órdenes desde las herramientas de desarrollo'],
    'locks'      => [
        'title'       => 'Por qué es seguro',
        'environment' => 'Sus rutas solo existen cuando CI_ENVIRONMENT es development: en cualquier otro sitio todas las direcciones de Devtools responden 404.',
        'manifest'    => 'Es un módulo solo para desarrollo: no se puede activar ni instalar fuera de desarrollo, y un modules:sync allí lo desactiva.',
        'check'       => 'security:check informa de un error cuando está activado fuera de desarrollo.',
        'local'       => 'Solo responde peticiones de este ordenador (dirección loopback y un Host localhost); usa php spark serve.',
        'permission'  => 'Solo superadministradores (devtools.view y devtools.write no se otorgan a nadie); todo lo que escribe vuelve a pedir la contraseña.',
        'packaging'   => 'Forma parte del repositorio, y por tanto está en los archivos de las versiones, pero los cierres anteriores lo dejan inerte.',
    ],
    'recipes' => [
        'title'    => 'Recetas de CRUD',
        'intro'    => 'Todas las variantes de un CRUD del back office y de su API, con código real del esqueleto y la documentación de la que sale.',
        'contents' => 'Recetas',
        'what'     => 'Qué es',
        'when'     => 'Cuándo usarlo',
        'source'   => 'De dónde sale',
        'copy'     => 'Copiar',
        'copied'   => 'Copiado',
        'more'     => 'Más recetas: vendor/siensis/kpcrudy/docs/howto_EN.md (kpcrudy) y vendor/siensis/kpapy/docs/howto_EN.md (kpapy).',
        'plain'    => [
            'title' => 'CRUD sencillo',
            'what'  => 'Una lista con búsqueda, filtros, detalle y formularios sobre una tabla: CrudFactory::routes() en las rutas, $this->crud() y $this->renderCrud() en un BackendController.',
            'when'  => 'Cada pantalla del back office que lista y edita registros. Empieza con php spark make:module <Nombre> --crud=<tabla>.',
        ],
        'readonly' => [
            'title' => 'CRUD de solo lectura',
            'what'  => 'El recurso solo declara list y show («except» en el manifiesto) y el controlador desactiva el resto con unset*().',
            'when'  => 'Registros, historiales y datos escritos por el código (registro de actividad, notificaciones, tokens).',
        ],
        'operation' => [
            'title' => 'Operación propia',
            'what'  => 'Un botón en cada registro (y una acción masiva) que ejecuta tu código, con permiso propio declarado en «extra».',
            'when'  => 'Acciones que no son añadir/editar/borrar: completar, publicar, aceptar, reenviar…',
        ],
        'reauth' => [
            'title' => 'Operaciones con reautenticación',
            'what'  => 'El filtro reauth vuelve a pedir la contraseña antes de ejecutar la ruta; añade ratelimit:forms a las escrituras.',
            'when'  => 'Acciones sensibles: restaurar copias, descargar datos, gestionar claves o la seguridad de otros usuarios.',
        ],
        'recordRules' => [
            'title' => 'Reglas por registro',
            'what'  => 'setPermission() con una función decide para cada registro por encima del permiso del rol (p. ej. solo el autor edita).',
            'when'  => 'Todos ven los registros pero solo algunos pueden cambiar algunos.',
        ],
        'ownRows' => [
            'title' => 'Solo los registros propios',
            'what'  => 'where() limita la lista, el detalle, las ediciones, los borrados, las acciones masivas y la exportación; un id falsificado es «no encontrado».',
            'when'  => 'Pantallas personales («mis noticias», «mi actividad»): un recurso propio otorgado a todos los grupos.',
        ],
        'hiddenFields' => [
            'title' => 'Campos ocultos según el permiso',
            'what'  => 'hideFields() tras comprobar un permiso extra quita un campo de todas las vistas, de la exportación y de la importación.',
            'when'  => 'Columnas sensibles: estimaciones, notas internas, direcciones IP, autores.',
        ],
        'translations' => [
            'title' => 'Campos traducidos',
            'what'  => 'setTranslations() guarda los campos por idioma en una tabla de traducciones; ContentTranslations da los idiomas del sitio y exige el predeterminado.',
            'when'  => 'Contenido que los visitantes ven en varios idiomas (nombres, títulos, descripciones).',
        ],
        'richText' => [
            'title' => 'Texto enriquecido',
            'what'  => 'El editor del Core en un textarea (RichTextEditor::attrs(), la opción attrs de un campo traducido) y HtmlSanitizer antes de guardar y al mostrar.',
            'when'  => 'Cuerpos de páginas, noticias y eventos: texto con formato, enlaces e imágenes.',
        ],
        'uploads' => [
            'title' => 'Subidas y selector de ficheros',
            'what'  => 'Una subclase de CrudUploadHandler muestra el selector de ficheros del Core y guarda con el servicio Storage (listas permitidas, nombres aleatorios).',
            'when'  => 'Adjuntos, portadas e imágenes de un registro. Nunca los campos de subida de kpcrudy solos.',
        ],
        'export' => [
            'title' => 'Exportación e importación',
            'what'  => 'Exportación CSV de las columnas elegidas (valores en bruto con callbackExport()) e importación, cada una con su permiso.',
            'when'  => 'Datos que se llevan a una hoja de cálculo o se cargan en bloque.',
        ],
        'trash' => [
            'title' => 'Papelera',
            'what'  => 'Una columna deleted_at convierte borrar en «mover a la papelera» con restaurar y purgar; desactívalas si no se declaran.',
            'when'  => 'Registros que se pueden borrar por error (contenidos, tareas).',
        ],
        'maskedIds' => [
            'title' => 'Ids enmascarados',
            'what'  => 'Todos los CRUD muestran códigos en lugar de ids (app.maskKey, compartida con kpapy). Ocultan la numeración; no son control de acceso.',
            'when'  => 'Siempre activado. Mantén igualmente los permisos y las reglas por registro.',
        ],
        'activity' => [
            'title' => 'Registro de actividad',
            'what'  => 'El Core registra escrituras, operaciones, acciones masivas, importaciones y exportaciones (AuditedCrud); skipActivity() deja fuera las operaciones que solo cambian la vista.',
            'when'  => 'Nada que hacer en un CRUD; activity_log() para otras acciones.',
        ],
        'api' => [
            'title' => 'Recurso de la API',
            'what'  => 'Un Resource de kpapy en api/v1/<ruta> con el filtro kpapy, declarado en $api, con el mismo nombre de recurso que el CRUD: los tokens usan los mismos permisos.',
            'when'  => 'Aplicaciones e integraciones que leen o cambian los mismos registros.',
        ],
        'menu' => [
            'title' => 'Entrada de menú y permisos por defecto',
            'what'  => 'El $menu del manifiesto (recurso, zona, padre, orden) y los $defaultGrants (se aplican una vez al instalar).',
            'when'  => 'Cada recurso nuevo: quién lo recibe y dónde aparece.',
        ],
    ],
];
