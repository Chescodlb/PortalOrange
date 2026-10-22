# Publicar y gestionar comunicados

Las notificaciones del portal reúnen noticias, comunicados y alertas individuales. Los responsables autorizados las publican desde **Administración > Portal Orange > Publicar y gestionar avisos > Comunicados**.

## Categorías editables

Abrir **Portal Orange > Categorías de incidencias y notificaciones**, elegir Comunicados y crear o editar las categorías. Las iniciales son **Noticias**, **Registro** y **Comunicado**. Se pueden añadir otras y organizar subcategorías sin cambiar código.

## Eliminar una categoría

En **Administración > Gestionar categorías**, elegir la sección y pulsar **Eliminar** junto a la categoría. Revisar su nombre y confirmar; **Cancelar** vuelve al listado sin cambios. Solo pueden hacerlo administradores y usuarios con permiso de gestión del portal, como `editor.orange`.

Solo se eliminan categorías vacías. Si hay subcategorías, usar **Editar / mover** para trasladarlas; si hay publicaciones, incluidos borradores, editarlas y elegir otra categoría antes de volver a intentar la eliminación. No se borran avisos, confirmaciones ni históricos. La eliminación requiere confirmación mediante POST y la protección de sesión nativa de Moodle. Esta función pertenece al plugin; no existe un ajuste nativo para eliminar sus categorías personalizadas.

## Publicar y consultar

Crear una publicación, escribir su título y contenido, elegir la categoría y el **Impacto** (Normal, Alto o Crítico, según el catálogo configurado) y seleccionar individuos, cohortes o grupos de cursos. Marcar Publicado para hacerla visible a sus destinatarios.

La recepción en el listado y la apertura se registran automáticamente. No se solicita confirmación manual ni existe el filtro Pendientes de confirmar. Cambiar el título, contenido o impacto genera una nueva versión y conserva el histórico de la anterior.

La apertura desde un perfil con permiso de gestión también guarda la vista individual de la versión actual. Si el editor no es destinatario, el listado muestra **Visto** y el sobre abierto; no se registra recepción ni confirmación, ni se añade al usuario a los destinatarios. No se registran confirmaciones manuales nuevas.

## Consultar el listado

La sección **Comunicados** presenta una tabla con título y descripción, categoría y fecha de creación. El sobre indica la lectura individual. Pulsar el título abre el detalle donde se registra automáticamente la lectura. Los borradores se identifican para los responsables autorizados.

A la izquierda del título de cada notificación aparece un sobre cerrado naranja si la notificación está pendiente de lectura, o un sobre abierto blanco con borde naranja si el usuario ya la abrió. Se utiliza el registro de lectura del destinatario para la versión actual de la publicación; no hace falta configurar nada ni se añaden campos a la base de datos. Consultar el listado registra recepción, pero no marca la publicación como leída. El icono tiene texto alternativo y ayuda Leído/Sin leer; no existe una columna de Lectura. Abrir una publicación basta para registrarla como leída. Los iconos son informativos, no botones. Esta presentación se implementa en el plugin (`index.php`, `styles.css` y los SVG de `pix/`), sin cambios en el core ni en Moove; no existe un ajuste nativo para estos iconos del listado personalizado.

El formulario permite combinar búsqueda, categoría (incluidas subcategorías), impacto, fecha y lectura. Seleccionar una categoría en el desplegable; **Todas las categorías** elimina esa restricción. **Desde** y **Hasta** filtran por la fecha de creación mostrada en la tabla: se puede usar solo uno o ambos, e incluyen los días completos según la zona horaria del usuario. Pulsar **Buscar** aplica los filtros. Si el rango está invertido o una fecha es inválida, se muestra un mensaje para corregirla. Los enlaces del árbol lateral conservan la búsqueda, las fechas y el filtro de lectura. Solo se muestran los avisos a los que el usuario tiene acceso. En móvil la tabla se desplaza horizontalmente dentro del listado y también se puede enfocar con el teclado.

La presentación comparte el código y estilos de la tabla de incidencias en `local/orangeportal/index.php` y `styles.css`, con encabezados en español e inglés. No existe un ajuste nativo de Moodle para cambiar la presentación de este listado del plugin; no se modifica el core ni Moove.

Todos los filtros tienen etiquetas visibles, altura uniforme y alineación por su borde inferior. El separador de la cabecera ocupa todo el ancho del contenido, manteniendo limitado únicamente el ancho del texto descriptivo.

## Alertas automáticas

Las alertas de curso completado y calificación visible se generan individualmente en Registro. Se pueden desactivar en **Portal Orange > Configuración Orange > Alertas automáticas de formación**.

La alerta no contiene una nota oculta ni se envía a otros miembros del equipo. El usuario consulta su resultado en el informe nativo del curso.

## Mensajería y avisos nativos de Moodle

En la experiencia Orange, los cuatro indicadores personales sustituyen los iconos nativos de mensajes y avisos; los subsistemas de Moodle permanecen activos y los demás temas conservan sus accesos nativos.

La sección Comunicados Orange añade los comunicados categorizados y su lectura trazable. Leer un mensaje nativo no registra la apertura de un comunicado del portal; son registros distintos.

En local no se envía correo y no hay conexión con Teams. **Recibido** significa mostrado en el portal, no entregado por correo o Microsoft Teams. Una futura integración necesitará una trazabilidad de entrega adicional por canal.

Los estilos del plugin se cargan con una versión basada en la fecha de modificación del archivo para que el navegador reciba los cambios visuales sin reutilizar una copia antigua.

En los listados de Incidencias y Comunicados, la clase `orange-wide-listing` amplía el contenedor principal de Moove al ancho disponible de la página, conservando sus márgenes y relleno. Encabezado, descripción, separador, filtros y tabla comparten ese ancho. El ajuste se limita a estos dos listados, sin modificar archivos del tema.

## Categorías en las tablas del portal y administración

En las tablas de Incidencias y Comunicados del portal, y en los listados de publicaciones y de gestión de categorías, se muestra el nombre sin guiones y, debajo, la ruta de sus categorías superiores en texto pequeño. Se muestran como máximo las dos superiores más cercanas: `Centrex` con `Sistemas › Telefonía` debajo. Si existen más niveles, la ruta comienza por `… ›`; al pasar el cursor o enfocar la ruta con el teclado aparece la ruta superior completa. Las categorías raíz no muestran una segunda línea. Se mantiene el orden jerárquico y los selectores de los formularios conservan su indentación. Es una presentación del plugin, sin cambios en los nombres guardados ni en Moodle o Moove.

La función compartida `orange_category_label()` de `local/orangeportal/locallib.php` genera esta presentación para `index.php`, `manage.php` y `categories.php`, con los mismos estilos de `styles.css`. Las categorías se consultan una vez por listado; no se añade una consulta por fila.


## Filtros laterales · 20/10/2026

Comunicados usa el mismo diseño de filtros a la izquierda y tabla a la derecha que Incidencias. Se conservan búsqueda, categoría, impacto, fecha desde/hasta y lectura. Se añade Lectura (todas/sin leer),  para el acceso desde el contador personal. Limpiar filtros vuelve al listado completo accesible. En móvil, los filtros se sitúan encima de la tabla y esta se desplaza dentro de su contenedor.

Se reutilizan la consulta y los permisos existentes del listado; no se requiere configurar Moodle ni se modifica su core. Ver [indicadores personales](10-indicadores-personales.md).

La tabla de Comunicados tiene tres columnas: **Título y descripción**, **Categoría** y **Fecha**. El sobre permanece a la izquierda del título. Retirar la columna de Lectura no modifica los registros, los filtros ni el registro de apertura del detalle.

## Impacto y alerta crítica · 21/10/2026

La categoría organiza el comunicado; **Impacto** indica su gravedad. En el formulario de publicación, seleccionar Crítico para activar la campana roja de los destinatarios que aún no lo han leído. No crear una categoría especial para ello. La campana cuenta solo las críticas sin leer y abre ese listado; el total pendiente se consulta al pasar el cursor. Después de leer la última crítica vuelve a naranja y muestra todos los pendientes. El detalle muestra el impacto y el filtro lateral permite seleccionarlo manualmente.

Se reutiliza el campo `local_orange_notice.impact` y el catálogo compartido de Impactos. No hay tablas nuevas ni migración de publicaciones existentes. Cambiar el impacto solicita una nueva lectura de la versión actual, conservando los registros anteriores. Este formulario y los indicadores pertenecen al plugin y no pueden configurarse desde un ajuste estándar de Moodle; no se modifica core/Moove ni los otros temas. Ver [indicadores personales](10-indicadores-personales.md).

## Informes separados

**Administración > Seguimiento** separa Comunicados e Incidencias en un selector por tipo, resúmenes de leídos/sin leer por publicación y detalle por destinatario, con paginación de 25 filas. Se retiró la confirmación manual del detalle, de los filtros y del CSV; se conservan las evidencias históricas. Ver [informes](09-informes.md).
