# Publicar y gestionar notificaciones

Las notificaciones del portal reúnen noticias, comunicados y alertas individuales. Los responsables autorizados las publican desde **Administración > Portal Orange > Publicar y gestionar avisos > Notificaciones**.

## Categorías editables

Abrir **Portal Orange > Categorías de incidencias y notificaciones**, elegir Notificaciones y crear o editar las categorías. Las iniciales son **Noticias**, **Registro** y **Comunicado**. Se pueden añadir otras y organizar subcategorías sin cambiar código.

## Eliminar una categoría

En **Administración > Gestionar categorías**, elegir la sección y pulsar **Eliminar** junto a la categoría. Revisar su nombre y confirmar; **Cancelar** vuelve al listado sin cambios. Solo pueden hacerlo administradores y usuarios con permiso de gestión del portal, como `editor.orange`.

Solo se eliminan categorías vacías. Si hay subcategorías, usar **Editar / mover** para trasladarlas; si hay publicaciones, incluidos borradores, editarlas y elegir otra categoría antes de volver a intentar la eliminación. No se borran avisos, confirmaciones ni históricos. La eliminación requiere confirmación mediante POST y la protección de sesión nativa de Moodle. Esta función pertenece al plugin; no existe un ajuste nativo para eliminar sus categorías personalizadas.

## Publicar y confirmar

Crear una publicación, escribir su título y contenido, elegir la categoría y seleccionar individuos, cohortes o grupos de cursos. Marcar Publicado para hacerla visible a sus destinatarios.

La recepción en el listado, la apertura y la confirmación explícita se registran separadamente. La opción **Pendientes de confirmar** permite localizar lo que queda por confirmar. Cambiar el texto genera una nueva versión y conserva el histórico de la anterior.

## Consultar el listado

La sección **Notificaciones** presenta una tabla con título y resumen, categoría, fecha de creación y situación individual de lectura. Pulsar el título abre el detalle donde el destinatario puede confirmar la lectura. Los borradores se identifican para los responsables autorizados.

El formulario permite combinar búsqueda, categoría (incluidas subcategorías), fecha y pendientes de confirmar. Seleccionar una categoría en el desplegable; **Todas las categorías** elimina esa restricción. **Desde** y **Hasta** filtran por la fecha de creación mostrada en la tabla: se puede usar solo uno o ambos, e incluyen los días completos según la zona horaria del usuario. Pulsar **Buscar** aplica los filtros. Si el rango está invertido o una fecha es inválida, se muestra un mensaje para corregirla. Los enlaces del árbol lateral conservan la búsqueda, las fechas y el filtro de confirmaciones. Solo se muestran los avisos a los que el usuario tiene acceso. En móvil la tabla se desplaza horizontalmente dentro del listado y también se puede enfocar con el teclado.

La presentación comparte el código y estilos de la tabla de incidencias en `local/orangeportal/index.php` y `styles.css`, con encabezados en español e inglés. No existe un ajuste nativo de Moodle para cambiar la presentación de este listado del plugin; no se modifica el core ni Moove.

Todos los filtros tienen etiquetas visibles, altura uniforme y alineación por su borde inferior. El separador de la cabecera ocupa todo el ancho del contenido, manteniendo limitado únicamente el ancho del texto descriptivo.

## Alertas automáticas

Las alertas de curso completado y calificación visible se generan individualmente en Registro. Se pueden desactivar en **Portal Orange > Configuración Orange > Alertas automáticas de formación**.

La alerta no contiene una nota oculta ni se envía a otros miembros del equipo. El usuario consulta su resultado en el informe nativo del curso.

## Mensajería y avisos nativos de Moodle

El icono de mensajes de la barra conserva la mensajería de Moodle. Sus conversaciones, preferencias y permisos se gestionan con los ajustes nativos. Las notificaciones de Moodle también conservan su icono y preferencias.

La sección Notificaciones Orange añade los comunicados categorizados y su confirmación trazable. Abrir o marcar como leído un mensaje nativo no confirma un comunicado del portal; son registros distintos.

En local no se envía correo y no hay conexión con Teams. **Recibido** significa mostrado en el portal, no entregado por correo o Microsoft Teams. Una futura integración necesitará una trazabilidad de entrega adicional por canal.

Los estilos del plugin se cargan con una versión basada en la fecha de modificación del archivo para que el navegador reciba los cambios visuales sin reutilizar una copia antigua.

En los listados de Incidencias y Notificaciones, la clase `orange-wide-listing` amplía el contenedor principal de Moove al ancho disponible de la página, conservando sus márgenes y relleno. Encabezado, descripción, separador, filtros y tabla comparten ese ancho. El ajuste se limita a estos dos listados, sin modificar archivos del tema.
