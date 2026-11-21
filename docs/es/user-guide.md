# Guía de usuario (back-office)

**Idiomas:** [English](../user-guide.md) · [Català](../ca/user-guide.md) · Español

Esta guía es para las personas que usan el back-office: cómo iniciar sesión, tu perfil y la
seguridad de la cuenta, el panel, las notificaciones, los archivos, los contenidos (páginas, menús,
noticias), el calendario, las tareas, tu actividad y los tokens personales de la API. La
documentación técnica para desarrolladores y administradores está en inglés en `docs/`.

## Antes de empezar

- **Lo que ves depende de tus permisos.** Los administradores dan permisos a los grupos (por
  ejemplo `admin`, `editor`, `user`) y cada usuario pertenece a uno o más grupos. El menú solo
  muestra las pantallas que puedes abrir, y los botones que no puedes usar no aparecen. Si te
  falta alguna pantalla de esta guía, pídelo a un administrador. Además, algunos módulos se pueden
  desactivar (el módulo de tareas viene desactivado por defecto).
- **Direcciones.** Las rutas como `/backend/profile` son relativas a la dirección de tu sitio (por
  ejemplo `https://example.org/backend/profile`). El back-office empieza en `/backend`.
- **Dónde está cada cosa.** El back-office tiene tres lugares con entradas de menú:
  - la **barra lateral**, a la izquierda: las pantallas principales (Panel, Noticias, Páginas,
    Calendario, Menús, Archivos, Tareas…) y, bajo el encabezado **Configuración**, las pantallas
    de administración;
  - el **menú de usuario**, arriba a la derecha (tu nombre): Mi perfil, Notificaciones, Mi
    actividad, Mis sesiones, Mis tokens de API, Suscripción al calendario y **Cerrar sesión**;
  - la **campana**, en la barra superior: las últimas notificaciones.
- **Idioma.** El back-office se muestra en el idioma de tu perfil (catalán, castellano o inglés).
  Los rótulos que cita esta guía son los castellanos.

## Iniciar sesión

### Entrar

Abre `/login` (en la web pública, **Entrar** al final de la barra de menú), escribe el **Correo
electrónico** y la **Contraseña** y pulsa **Entrar**. Con la sesión iniciada, el mismo lugar de la
barra dice **Administración** (o **Mi cuenta** si no tienes ninguna pantalla de la administración).

- El botón del ojo junto a cada campo de contraseña muestra lo que has escrito (**Mostrar la
  contraseña**) y lo vuelve a ocultar; la contraseña siempre vuelve a quedar oculta al enviar el
  formulario.

- **Recuérdame en este dispositivo** (si aparece) mantiene la sesión abierta en ese navegador
  durante unos días (14 por defecto). No lo uses en ordenadores compartidos.
- Si los administradores han activado el captcha, aparece un campo **Verificación** (una operación
  aritmética dibujada en una imagen): escribe el resultado. **Otra imagen** dibuja una nueva.
- Tras varios intentos fallidos el acceso queda bloqueado unos minutos. Los mensajes de error
  nunca dicen si se ha equivocado el correo o la contraseña, y es así a propósito.
- Si la cuenta tiene la verificación en dos pasos, a continuación aparece la pantalla **Código de
  verificación** (ver [Verificación en dos pasos](#verificación-en-dos-pasos)).
- Si un administrador te ha pedido que cambies la contraseña, primero te lleva al formulario de la
  contraseña («Debes cambiar la contraseña antes de continuar»).
- Cuando entras desde un dispositivo o navegador que el sitio no conoce, recibes un correo
  **Inicio de sesión desde un dispositivo nuevo**. Si no has sido tú, sigue **Revisar mis
  sesiones** en el correo, cierra esa sesión y cambia la contraseña.

### Entrar con un enlace por correo

Si la página de inicio de sesión muestra **Entrar con un enlace por correo**, puedes entrar sin
escribir la contraseña: ábrelo (`/login/magic-link`), escribe tu correo y pulsa **Enviar el
enlace**. Recibirás un correo con un enlace de un solo uso que caduca al poco tiempo. Si la cuenta
tiene la verificación en dos pasos, después del enlace también te pedirá el código.

### Contraseña olvidada

Pulsa **¿Has olvidado la contraseña?** en la página de inicio de sesión (`/reset-password`),
escribe tu correo y pulsa **Enviar el enlace**. El correo lleva un enlace de un solo uso para
elegir una **Contraseña nueva** (escríbela dos veces y pulsa **Guardar la contraseña**). Por
privacidad, la página siempre dice que se ha enviado el correo «si existe una cuenta con esta
dirección»: mira también la carpeta de correo no deseado.

### Bloqueo y caducidad de la sesión

- Si los administradores han fijado un tiempo de inactividad, tras un rato sin actividad aparece la
  pantalla **Sesión bloqueada**: escribe la contraseña y pulsa **Desbloquear** (o **¿No eres tú?
  Cierra la sesión**).
- Una sesión también termina un tiempo fijo después de entrar (12 horas por defecto), aunque la
  estés usando: basta con volver a entrar.
- Si ves que la sesión «se ha cerrado desde otro dispositivo», significa que tú (o un
  administrador) la has cerrado desde otro lugar, o que se ha cambiado la contraseña.

### Cerrar sesión

Abre el menú de usuario y elige **Cerrar sesión**. Si abres `/logout`, antes de cerrar te
pregunta «¿Quieres cerrar la sesión en este dispositivo?».

## Mi perfil

Menú de usuario › **Mi perfil** (`/backend/profile`).

La tarjeta **Datos personales** contiene:

| Campo | Para qué sirve |
|---|---|
| **Avatar** | **Sube un archivo**: JPG, PNG o WebP, hasta 2 MB. La imagen se vuelve a codificar sin metadatos y solo la ven los usuarios que han iniciado sesión. Guarda el perfil para aplicar el cambio. |
| **Nombre** | El nombre que ven los demás. |
| **Correo electrónico** | La dirección con la que entras. Para cambiarla hace falta la **Contraseña actual**. |
| **Idioma** | Catalán, castellano o inglés: el idioma del back-office y de los correos que recibes. |
| **Zona horaria** | Las fechas y horas se muestran en esta zona horaria. |
| **Modo de color** | Automático (el del sistema), Claro u Oscuro. El botón de modo de color de la barra superior también lo cambia. |
| **Fecha de nacimiento** y **Compartir mi cumpleaños en el calendario** | Opcional. Solo se comparten el día y el mes, y solo con los grupos que permitan los administradores. |
| **Contraseña actual** | Solo hace falta si cambias el correo electrónico. |

Pulsa **Guardar** para conservar los cambios. La tarjeta **Cuenta**, a la derecha, muestra tus
grupos y los botones **Cambiar la contraseña**, **Verificación en dos pasos** y **Mis sesiones**.

### Cambiar la contraseña

Perfil › **Cambiar la contraseña** (`/backend/profile/password`): escribe la contraseña actual, la
**Contraseña nueva** y **Repite la contraseña nueva**. El formulario indica la longitud mínima (8
caracteres por defecto; una frase larga es más segura y fácil de recordar). Se rechazan las
contraseñas demasiado comunes o filtradas y las basadas en tu correo. Al cambiar la contraseña
**se cierran las demás sesiones abiertas y se revocan los tokens de las aplicaciones**.

## Verificación en dos pasos

Perfil › **Verificación en dos pasos** (`/backend/profile/two-factor`). Con ella, para entrar
necesitas la contraseña **y** un código de 6 cifras. Algunos grupos la exigen: entonces la página
dice que tu grupo requiere la verificación en dos pasos y tienes que configurarla para continuar;
puedes cambiar el método pero no desactivarla.

Los cambios de esta página te piden que [confirmes tu identidad](#confirmar-la-identidad) (la
contraseña y, si ya usas la verificación, el código actual).

### Elegir el método

La tarjeta **Método** ofrece dos; pulsa **Usar este método** en el que quieras:

- **Aplicación de autenticación** (recomendado): Google Authenticator, Microsoft Authenticator,
  Aegis, 1Password… La página **Configurar una aplicación de autenticación** explica los pasos:
  1. instala una aplicación de autenticación en el móvil;
  2. escanea el código QR o escribe la clave a mano («Clave (si no puede escanear el código)»);
  3. escribe el código de 6 cifras que muestra la aplicación y pulsa **Activar**.
- **Código por correo**: en cada inicio de sesión recibes un código de 6 cifras por correo
  (caduca a los 10 minutos). En la pantalla de verificación, **Enviar un código nuevo** envía otro.

Si dice «No disponible: el servidor no tiene clave de cifrado», los administradores deben
configurar el servidor antes de poder usar la aplicación.

### Códigos de recuperación

Al activar la verificación en dos pasos recibes **10 códigos de recuperación**. Solo se muestran
**una vez**: cópialos y guárdalos en un lugar seguro (un gestor de contraseñas, o impresos en
casa). Cada código te permite entrar una vez si pierdes el acceso al método: en la pantalla de
verificación elige **Usar un código de recuperación**. La tarjeta **Códigos de recuperación**
muestra cuántos códigos te quedan sin usar; **Generar códigos nuevos** te da un juego nuevo e
invalida el anterior.

### Si pierdes el móvil

1. Entra con la contraseña y, en la pantalla **Código de verificación**, elige **Usar un código de
   recuperación**.
2. Ve a Perfil › Verificación en dos pasos y vuelve a configurar la aplicación en el móvil nuevo
   (o pasa a **Código por correo**); después pulsa **Generar códigos nuevos**.
3. Si ya no te quedan códigos de recuperación, pídelo a un administrador: en Usuarios puede usar
   **Restablecer 2FA** en tu cuenta. Recibirás una notificación y, si tu grupo exige la
   verificación, la volverás a configurar en el próximo inicio de sesión.

Si otra persona puede tener tu móvil o tus códigos, cambia también la contraseña y cierra las
demás sesiones.

### Desactivarla

**Desactivar la verificación en dos pasos** (solo si tu grupo no la exige) deja la cuenta
protegida solo con la contraseña.

## Mis sesiones

Menú de usuario › **Mis sesiones** (`/backend/profile/sessions`).

- **Sesiones abiertas** muestra los dispositivos donde tienes la sesión abierta: **Dispositivo**,
  **Dirección IP**, **Inicio** y **Última actividad**. La actual lleva la marca **Esta sesión**;
  **Recordada** indica las sesiones abiertas con «Recuérdame».
- **Cerrar** en una fila cierra esa sesión al instante; **Cerrar todas las demás sesiones** las
  cierra todas menos la actual. Para cerrar la actual, usa **Cerrar sesión** en el menú de
  usuario.
- La tarjeta **Tokens de la API** muestra los tokens de tus aplicaciones (**Aplicación**) y los
  personales (**Personal**) con su último uso; **Revocar** detiene un token al instante.

Si no reconoces alguna sesión, ciérrala y cambia la contraseña.

## Confirmar la identidad

Las acciones delicadas (por ejemplo, cambiar la verificación en dos pasos, crear un token personal
de la API o la dirección del canal del calendario) muestran la pantalla **Confirme su
identidad**: escribe la **Contraseña** y, si usas la verificación en dos pasos, el código actual
(o un código de recuperación), y pulsa **Confirmar**. Con el método por correo, **Enviadme un
código por correo** te envía el código. La identidad queda confirmada unos minutos (15 por
defecto), así que las acciones relacionadas no la vuelven a pedir; si la acción no se ha
completado, repítela. Demasiados intentos fallidos cierran la sesión.

## Panel

Barra lateral › **Panel** (`/backend`), la primera pantalla después de entrar.

- Muestra **widgets** (tarjetas como Próximos eventos, Avisos, Últimas noticias, Enlaces rápidos,
  Mis tareas pendientes…). Los administradores deciden qué widgets tiene cada grupo
  (Configuración › **Widgets del panel**); solo ves un widget si lo tiene tu grupo **y** tienes el
  permiso que necesita.
- **Personalizar** te permite organizar tu panel: arrastra los widgets por el asa (o usa las
  flechas del teclado sobre ella) para reordenarlos, cambia su tamaño (Pequeño, Mediano, Grande,
  Ancho completo) u ocúltalos. Los cambios se guardan al instante; pulsa **Hecho** para terminar.
  Los widgets ocultos aparecen en **Widgets ocultos**, desde donde puedes volver a mostrarlos.
- **Restaurar la disposición del grupo** olvida tu orden, los tamaños y los widgets ocultos.
- Sin JavaScript, `/backend/dashboard/customize` ofrece las mismas opciones en una tabla
  (**Posición**, **Tamaño**, **Visible** y después **Guardar**).

Si un widget falla, solo esa tarjeta muestra el error; el resto del panel sigue funcionando.

## Notificaciones

- **La campana** de la barra superior muestra cuántas notificaciones tienes por leer y abre las
  últimas. Al hacer clic en una se abre y queda marcada como leída. La campana también ofrece
  **Marcar todo como leído**, **Ver todas** y **Preferencias**.
- **La lista:** menú de usuario › **Notificaciones** (`/backend/notifications`) muestra todas las
  notificaciones con la fecha y si están **Leída** o **No leída**. **Marcar como leída** funciona
  con una notificación o con las seleccionadas, y **Marcar todo como leído**, con todas.
- **Preferencias:** **Preferencias de notificaciones** (desde la lista o la campana;
  `/backend/profile/notifications`) te permite elegir, para cada tipo de notificación, si la
  recibes **En la aplicación**, **Por correo**, de las dos formas o de ninguna. Las marcadas como
  **Obligatoria** no se pueden desactivar. Cuando el sitio tiene las notificaciones push
  activadas, una tercera columna, **En mis dispositivos**, elige qué notificaciones llegan a tus
  teléfonos y ordenadores.

### Notificaciones en mis dispositivos (push)

Si los administradores lo activan, puedes recibir las notificaciones como notificaciones del
sistema en un teléfono o un ordenador, incluso con el sitio cerrado (gratuito, sin instalar
ninguna aplicación):

1. Abre **Preferencias de notificaciones** en el navegador de ese dispositivo.
2. Haz clic en **Activar las notificaciones en este dispositivo** y permite las notificaciones
   cuando el navegador lo pida.
3. Usa **Enviar una prueba** para comprobarlo (llega en menos de un minuto).

La lista **Sus dispositivos** muestra cada navegador que recibe las notificaciones («Este
dispositivo» marca el que estás usando): puedes **Cambiar el nombre**, enviar una prueba o
**Eliminarlo**. Al cerrar la sesión en un dispositivo que recibe notificaciones se te pregunta si
quieres mantenerlas o eliminar el dispositivo.

- **iPhone y iPad** (iOS/iPadOS 16.4 o posterior): primero añade el sitio a la pantalla de inicio
  (Compartir › Añadir a pantalla de inicio), ábrelo desde allí y activa las notificaciones.
- Si el navegador ha **bloqueado** las notificaciones, permítelas en la configuración del sitio
  del navegador y vuelve a cargar la página.
- Los administradores pueden elegir que las notificaciones push solo digan «Tiene una
  notificación nueva» (las pantallas de bloqueo las muestran); entonces el texto se lee en el
  sitio.

Ejemplos de notificaciones: un aviso nuevo en las noticias, el recordatorio de un evento que has
pedido, un restablecimiento de la verificación en dos pasos por parte de un administrador o una
tarea programada que ha fallado (para los administradores).

## Archivos

Barra lateral › **Archivos** (`/backend/files`), para los usuarios con acceso al gestor de
archivos. Los demás solo suben archivos desde los campos de los formularios (ver [Archivos en los
formularios](#archivos-en-los-formularios-el-selector-de-archivos)).

### Espacios

El árbol **Carpetas** de la izquierda muestra los espacios que puedes abrir:

| Espacio | Contenido |
|---|---|
| **Mis archivos** | Tu espacio personal (privado). Solo lo ves tú. Se ofrece a los grupos que elijan los administradores (por defecto `admin` y `editor`). |
| **Compartido (público)** | Carpetas compartidas con archivos que tienen una dirección pública (cualquier persona con la dirección puede abrirlos: úsalas para imágenes y documentos de la web). |
| **Compartido (privado)** | Carpetas compartidas con archivos que solo se sirven a las personas que permiten los permisos de la carpeta. |

### Trabajar con archivos

- **Subir** (o arrastrar los archivos a la lista: «Suelta los archivos aquí para subirlos») sube a
  la **Carpeta actual**. El cuadro **Subidas** muestra el progreso de cada archivo. Los tipos
  permitidos y el tamaño máximo los fijan los administradores; los archivos que no cumplen se
  rechazan con un mensaje.
- Busca por nombre, texto alternativo o etiqueta; filtra por **Tipo**, fechas (**Desde**,
  **Hasta**) y **Etiqueta**; **Ordenar por** Nombre, Más recientes o Más grandes; cambia la
  **Vista** entre **Lista** y **Cuadrícula**.
- Al hacer clic en un archivo se abren los **Detalles del archivo**: vista previa, tamaño,
  dimensiones, **Descargar**, **Previsualizar**, **Copiar la URL pública** (archivos públicos) o
  **Copiar la URL** (archivos privados), **Mover** a otra carpeta, y los campos **Nombre**,
  **Etiquetas** (separadas por comas) y **Texto alternativo** (describe las imágenes para quien no
  puede verlas).
- **Mover a la papelera** elimina un archivo. La vista **Papelera** muestra los archivos
  eliminados: **Restaurar** recupera uno y **Eliminar definitivamente** lo borra para siempre (no
  se puede deshacer).

### Carpetas y permisos

Según tus permisos puedes crear (**Nueva carpeta**), **Renombrar** y **Eliminar carpeta** (solo
carpetas vacías: sin archivos, sin archivos en la papelera y sin subcarpetas). El botón
**Permisos** de una carpeta compartida da derechos (**Ver**, **Subir**, **Editar**, **Eliminar**,
**Gestionar**) a un **Grupo**, a un **Usuario** o a **Todos los usuarios con sesión**; las
subcarpetas los heredan si no definen los suyos.

### Cuotas

Un espacio o una carpeta pueden tener un límite de tamaño. El gestor muestra «*X* de *Y* usados»;
los archivos de la papelera cuentan hasta que se eliminan definitivamente. Si una subida lo
superaría, se rechaza («Se superaría la cuota de la carpeta»): vacía la papelera o elimina
archivos. El espacio personal tiene 100 MB por defecto.

### Archivos en los formularios (el selector de archivos)

Los campos que admiten un archivo (un avatar, una imagen de portada, adjuntos…) muestran **Sube un
archivo** y, si el campo lo permite, **Elige un archivo**, que abre un diálogo con las carpetas que
puedes usar para ese campo (**Carpeta superior** para subir un nivel, **Elige** para confirmar). El
archivo actual se muestra con un botón para quitarlo. El campo comprueba el tipo y el tamaño antes
de subirlo. El cambio solo se guarda cuando guardas el formulario.

En el editor de texto enriquecido, **Insertar una imagen** abre el mismo diálogo (**Sube una
imagen** o elige una), pide el **Texto alternativo (describe la imagen)** y la inserta. Deja el
texto alternativo vacío solo si la imagen es decorativa.

## Páginas

Barra lateral › **Páginas** (`/backend/pages`): las páginas de la web pública.

- El formulario tiene una pestaña por idioma (el catalán es obligatorio): **Título**, **Alias**
  (la parte de la dirección; si está vacío se crea a partir del título), **Resumen**, **Texto**,
  los campos para los buscadores (**Metatítulo**, **Metadescripción**) y la **Imagen destacada**.
  Los textos sin traducir se muestran en catalán.
- **Página padre** coloca la página dentro de una sección: su dirección empieza por la del padre.
- **Visibilidad**: **Pública**, **Usuarios identificados** o **Grupos** (solo los miembros de los
  grupos elegidos).
- **Estado**: **Borrador**, **Programada** o **Publicada**. **Publicar** publica la página (con
  una **Fecha de publicación** futura queda programada); **Despublicar** la devuelve a borrador.
- **Previsualizar** muestra cómo quedará la página, aunque sea un borrador. **Revisiones** muestra
  las versiones guardadas con sus cambios, y **Restaurar** recupera una (se guarda una revisión
  nueva). **Duplicar** hace una copia como borrador.

Publicar suele requerir un permiso específico; quien no puede publicar guarda borradores.

### Páginas hechas con secciones

Una página puede mostrar su texto (**Disposición: Artículo**) o hacerse con **secciones**
(**Disposición: Secciones**): banners, enlaces rápidos, tarjetas, imagen y texto, llamadas a la
acción y bloques de texto, una debajo de otra, a toda la anchura de la web. No hace falta código.

1. En la lista de páginas, abrid el menú de la fila › **Secciones**.
2. Si el aviso dice que la página muestra su texto, pulsad **Usar secciones** (el texto se
   conserva).
3. **Añadir una sección**, elegid el tipo en la galería, rellenad el formulario y **Guardar**. Con
   los botones de idioma de arriba del formulario escribid los textos de cada idioma; las imágenes
   necesitan un **texto alternativo** (qué muestra la imagen) si no las marcáis como **decorativas**.
4. Cambiad el orden arrastrando una sección por el asa (o enfocad el asa y usad las flechas arriba
   y abajo) y pulsad **Guardar el orden**. **Ocultar** conserva una sección sin mostrarla;
   **Duplicar** y **Eliminar** hacen lo que dicen.
5. **Previsualizar** muestra la página tal como es ahora. Cada cambio queda en las **Revisiones**
   de la página, y restaurar una revisión recupera sus secciones.

#### Más tipos de sección

- **Lista de noticias**: las últimas noticias (unas cuantas tarjetas y un enlace «más») o la
  **lista completa** con páginas y buscador, de una o más categorías. Solo muestra las noticias que
  muestra la web pública. Para que una página así sea la lista principal de noticias, elegidla en
  Configuración › Noticias › *Página de la lista principal de noticias*: entonces `/news` lleva a
  ella a los visitantes.
- **Calendario** y **Próximas actividades**: el calendario público, o las próximas actividades
  públicas en lista o tarjetas, de unas categorías o de todas.
- **Galería**: las imágenes de una carpeta compartida del gestor de archivos, en cuadrícula o
  mosaico; al hacer clic, la imagen se abre grande. Las imágenes del disco público las ve todo el
  mundo; las de una carpeta privada, solo las personas identificadas que pueden ver esa carpeta.
- **Carrusel**: diapositivas con imagen, título, texto y botón. No se mueve solo si no activáis
  **Gira sola** (entonces los visitantes lo pueden detener).
- **Testimonios** (citas con foto, nombre y cargo) y **Logotipos** (cada logotipo necesita su
  texto alternativo, el nombre de la entidad).
- **Contenido incrustado**: un vídeo, una visita virtual o un mapa de otro sitio. Solo se aceptan
  los sitios que un administrador ha permitido en Configuración › Seguridad › *Sitios que se pueden
  incrustar*; si el sitio no está permitido, el formulario lo dice y lo nombra. Por defecto el
  contenido solo se carga cuando el visitante hace clic.

Una categoría de noticias puede tener un **banner** (Noticias › Categorías › *Banner*): se muestra
arriba de la página de la categoría y de sus noticias.

## Menús

Barra lateral › **Menús** (`/backend/menus/public`): los menús de la web pública.

- Cada menú tiene una **Ubicación** (donde lo muestra el tema). **Elementos** abre sus elementos.
- **Añadir un elemento** (o **Añadir un elemento dentro** de otro) enlaza una página, una
  dirección, una ruta o un enlace que ofrecen los módulos (una categoría de noticias, el
  calendario…), con su visibilidad (por ejemplo, solo usuarios identificados) y si se abre en una
  pestaña nueva.
- Ordena y anida los elementos arrastrando el asa, con las flechas del teclado sobre ella o con los
  botones, y después guarda el orden. Los elementos que apuntan a algo que ya no existe llevan la
  marca **Destino no disponible**.

La parte **Configuración** de la barra lateral está agrupada: **Usuarios y acceso** (usuarios,
grupos, permisos), **Seguridad** (configuración de seguridad, estado de seguridad, registro de
actividad), **API** (tokens, documentación), **Personalización** (widgets del panel, menú del
back-office, módulos) y **Sistema** (configuración, tareas programadas, copias de seguridad,
actualizaciones). Cada grupo solo aparece si puedes abrir alguna de sus entradas.

Los administradores también pueden personalizar el menú del back-office (**Personalización** ›
**Menú del back-office**); cada entrada mantiene su permiso esté donde esté, y los grupos se
pueden renombrar, mover u ocultar como cualquier entrada.

## Noticias

Barra lateral › **Noticias**:

- **Noticias** (`/backend/news`): el lector. Muestra los artículos y avisos que puedes leer;
  filtra por **Tipo**, **Categoría** y **Solo no leídas** y pulsa **Filtrar**. Las no leídas
  llevan la marca **Nueva** y las fijadas salen primero. Abre una para leerla y pulsa **Marcar
  como leída**.
- **Todas las publicaciones** (`/backend/news/posts`) y **Mis publicaciones**
  (`/backend/news/mine`): para escribir y gestionar publicaciones (la segunda solo muestra las
  tuyas). Una publicación es un **Artículo** o un **Aviso** (los avisos tienen prioridad y se
  pueden fijar; los avisos privados notifican a sus lectores cuando se publican).
  **Visibilidad**: **Pública**, **Privada** (usuarios identificados) o **Grupos**. El estado pasa
  de **Borrador** a **Programada** / **Publicada** y después a **Caducada** (pasada la **Fecha de
  caducidad**). **Publicar** y **Despublicar** lo cambian; **Lectores** muestra quién ha leído una
  publicación (con el permiso).
- **Categorías** (`/backend/news/categories`): las categorías de noticias.

Un aviso con **Fecha del evento** también aparece en el calendario, para el mismo público.

## Calendario y eventos

### El calendario

Barra lateral › **Calendario** (`/backend/calendar`) muestra todos los eventos que puedes ver.

- Vistas: mes, semana, día y lista (en el móvil se muestra primero la lista). Usa las flechas y el
  botón de hoy para moverte.
- **Calendarios**: además de **Eventos**, los módulos pueden añadir sus calendarios (por ejemplo
  los avisos de las noticias, los cumpleaños o el vencimiento de las tareas). Marca los que quieras
  y pulsa **Aplicar**; el botón del ojo muestra **Solo** ese calendario y **Mostrar todo** vuelve a
  mostrarlos todos.
- **Categoría** filtra los eventos por categoría (**Todas las categorías** para quitar el
  filtro).
- Haz clic en un evento para ver sus detalles (cuándo es, si se repite, **Lugar**, **Categoría**,
  **Organizador**, **Enlace**). Desde ahí:
  - **Avisarme** + **Guardar** te envía una notificación un rato antes de que empiece (la opción
    sin recordatorio lo quita);
  - **Añadir a mi calendario (.ics)** descarga el evento para otra aplicación de calendario;
  - **Editar** (con el permiso), o la opción de editarlo en el módulo que lo gestiona.

El calendario público de la web está en `/calendar` (solo eventos públicos).

### Gestionar eventos

Calendario › **Eventos** (`/backend/events`) y **Categorías** (`/backend/events/categories`), con
los permisos. Un evento tiene **Título**, **Inicio**, **Final** (opcional), **Todo el día**,
**Lugar**, **Categoría**, **Estado** (**Confirmado**, **Provisional**, **Cancelado**) y
**Visibilidad**: **Público** (también en la web), **Privado** (cualquier usuario identificado) o
**Restringido** (solo los **Grupos** y **Usuarios** elegidos). La **Repetición** (Diaria, Semanal,
Mensual, Anual), con **Cada**, **Los días**, **Hasta el** o **Número de veces** y las **Fechas
excluidas**, crea eventos periódicos. **Duplicar** copia un evento.

### Suscribirse desde otra aplicación de calendario

Menú de usuario › **Suscripción al calendario** (`/backend/calendar/subscription`) te da una
dirección privada (un canal iCal) a la que se pueden **suscribir** las aplicaciones de calendario.
El canal muestra todos los eventos que puedes ver aquí y se actualiza solo; es solo de lectura (los
cambios se hacen en el back-office).

1. Pulsa **Crear la dirección de mi canal** y [confirma tu identidad](#confirmar-la-identidad).
2. La página muestra **La dirección de tu canal del calendario** (tiene la forma
   `https://example.org/ical/user/<secret>.ics`). **Cópiala** ahora con **Copiar**: solo se muestra
   una vez, porque se guarda cifrada. Cualquier persona con esta dirección puede leer tu
   calendario; no la compartas.
3. Añádela a tu aplicación como calendario **suscrito por URL** (no como archivo importado):

| Aplicación | Pasos |
|---|---|
| **Google Calendar** | Desde un ordenador: Otros calendarios › + › Desde URL, pega la dirección y elige «Añadir calendario». Después también aparece en el móvil. |
| **Outlook** | Agregar calendario › Suscribirse desde la web, pega la dirección, ponle un nombre e impórtalo. |
| **Calendario de Apple (macOS, iPhone)** | Usa **Abrir en mi aplicación de calendario**, o Archivo › Nueva suscripción a calendario (Mac) / Ajustes › Calendario › Cuentas › Añadir calendario suscrito (iPhone). |
| **Synology Calendar** | Calendar › Crear › Suscribirse a un calendario, pega la dirección y elige cada cuánto se actualiza. |
| **Thunderbird** | Nuevo calendario › En la red › iCalendar (ICS), pega la dirección. |

Cada aplicación decide cada cuánto lee el canal (normalmente entre 15 minutos y un día): un cambio
puede tardar ese tiempo en aparecer.

- **Qué incluye el canal:** marca algunos **Calendarios** o **Categorías** y pulsa **Guardar**
  para limitar el canal; déjalo todo sin marcar para incluir todos tus eventos.
- **Regenerar la dirección** (restablecer el canal) crea una dirección nueva y hace que la antigua
  deje de funcionar enseguida en todas las aplicaciones que la usan: hazlo si has perdido la
  dirección o crees que otra persona la tiene, y después actualiza las aplicaciones. **Revocar**
  detiene el canal sin crear uno nuevo.
- La tarjeta muestra el **Estado**, cuándo se creó (**Creado**) y la **Última lectura de una
  aplicación**.
- Cuando el sitio publica el calendario público, la página también muestra la dirección del canal
  de **eventos públicos** (`/ical/public/all.ics`), que no necesita sesión y se puede compartir.

## Tareas

Barra lateral › **Tareas** (`/backend/tasks`), cuando el módulo está activado.

- El **tablero** muestra las tareas en las columnas **Por hacer**, **En curso** y **Hechas**.
  Arrastra una tarjeta a otra columna, o elige su estado en **Mover a** y pulsa **Mover**. Según
  tus permisos ves todas las tareas o solo las que tienes asignadas. **Nueva tarea** crea una;
  puedes filtrar por proyecto.
- **Todas las tareas** (`/backend/tasks/tasks`) es la lista completa: **Proyecto**,
  **Prioridad**, **Etiquetas**, **Fecha de vencimiento**, **Personas asignadas**, adjuntos y
  **Completar** para marcar una como hecha.
- **Proyectos** y **Solicitudes** (las que llegan del formulario público; **Aceptar** crea una
  tarea y **Rechazar** cierra la solicitud), con los permisos.
- El widget del panel **Mis tareas pendientes** muestra las tuyas.

## Mi actividad

Menú de usuario › **Mi actividad** (`/backend/activity/mine`) muestra lo que has hecho y los
eventos de seguridad de tu cuenta: inicios de sesión (también los fallidos), cambios de contraseña
y de correo, cambios de la verificación en dos pasos, sesiones cerradas, tokens creados o
revocados, registros creados, modificados o eliminados… Cada entrada tiene la **Fecha**, el tipo
(**Actividad** o **Seguridad**), la **Acción** y el **Resumen**; ábrela para ver los cambios. El
registro no se puede modificar. Si ves algo que no has hecho tú, cambia la contraseña, cierra las
demás sesiones y avisa a un administrador.

## Mis tokens de API

Menú de usuario › **Mis tokens de API** (`/backend/profile/api-tokens`), para los usuarios que
pueden crearlos (por defecto, los grupos `editor` y `admin`). Un token personal permite que un
programa o una integración usen la API en tu nombre; nunca puede hacer más de lo que puedes hacer
tú.

1. En **Token nuevo**, ponle un **Nombre** (dónde lo usarás), elige **Caduca dentro de** (7, 30,
   90 o 365 días) y **Qué puede hacer**: **Todo lo que puedo hacer yo** o **Solo estos
   permisos**.
2. Pulsa **Crear el token** y [confirma tu identidad](#confirmar-la-identidad).
3. **Tu token nuevo** solo se muestra una vez: cópialo ahora.

**Mis tokens** muestra tus tokens con su último uso; **Revocar** detiene uno al instante (los
programas que lo usan dejan de funcionar). Los permisos que pierdas más adelante también los
perderá el token. Los tokens también aparecen, y se pueden revocar, en [Mis
sesiones](#mis-sesiones).

## Referencia rápida

| Pantalla | Dónde está | Dirección |
|---|---|---|
| Inicio de sesión | — | `/login` |
| Panel | Barra lateral | `/backend` |
| Mi perfil | Menú de usuario | `/backend/profile` |
| Cambiar la contraseña | Mi perfil | `/backend/profile/password` |
| Verificación en dos pasos | Mi perfil | `/backend/profile/two-factor` |
| Mis sesiones | Menú de usuario | `/backend/profile/sessions` |
| Notificaciones | Menú de usuario, campana | `/backend/notifications` |
| Preferencias de notificaciones | Notificaciones, campana | `/backend/profile/notifications` |
| Mi actividad | Menú de usuario | `/backend/activity/mine` |
| Mis tokens de API | Menú de usuario | `/backend/profile/api-tokens` |
| Suscripción al calendario | Menú de usuario | `/backend/calendar/subscription` |
| Archivos | Barra lateral | `/backend/files` |
| Páginas | Barra lateral | `/backend/pages` |
| Menús | Barra lateral | `/backend/menus/public` |
| Noticias | Barra lateral | `/backend/news` |
| Calendario | Barra lateral | `/backend/calendar` |
| Tareas | Barra lateral | `/backend/tasks` |
