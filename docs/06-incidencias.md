# Publicar y seguir incidencias

Las incidencias son avisos operativos publicados por responsables autorizados. No son tickets de soporte creados por cualquier usuario. Se crean y asignan desde **Administración > Portal Orange > Publicar y gestionar avisos**.

## Crear categorías

1. Abrir **Portal Orange > Categorías de incidencias y notificaciones**.
2. Elegir Incidencias.
3. Escribir el nombre y seleccionar la categoría superior; guardar.

Se pueden crear subcategorías, renombrarlas y moverlas. El árbol impide mover un nodo dentro de sus propios descendientes. Los ejemplos iniciales son Sistemas > Centrex y Red móvil.

## Eliminar una categoría

En **Administración > Gestionar categorías**, elegir la sección y pulsar **Eliminar** junto a la categoría. Revisar su nombre y confirmar; **Cancelar** vuelve al listado sin cambios. Solo pueden hacerlo administradores y usuarios con permiso de gestión del portal, como `editor.orange`.

Solo se eliminan categorías vacías. Si hay subcategorías, usar **Editar / mover** para trasladarlas; si hay publicaciones, incluidos borradores, editarlas y elegir otra categoría antes de volver a intentar la eliminación. No se borran avisos, confirmaciones ni históricos. La eliminación requiere confirmación mediante POST y la protección de sesión nativa de Moodle. Esta función pertenece al plugin; no existe un ajuste nativo para eliminar sus categorías personalizadas.

## Publicar y asignar

1. Abrir **Publicar y gestionar avisos > Incidencias > Crear publicación**.
2. Completar título, categoría, descripción, impacto y estado.
3. Elegir usuarios individuales, cohortes globales y/o grupos de cursos.
4. Marcar Publicado y guardar. Sin esa marca, permanece como borrador.

La asignación es la unión de los destinatarios: basta pertenecer a uno de los grupos o cohortes seleccionados, o estar asignado individualmente. No se permite publicar sin destinatarios. El usuario solo ve sus incidencias y los contadores calculan esas mismas incidencias, incluidas las de las subcategorías.

## Consultar el listado

La sección **Incidencias** muestra una tabla con título y resumen, categoría, fecha de creación, impacto, estado compartido y situación individual de lectura. Pulsar el título abre el detalle para consultar el aviso, confirmar su lectura o cambiar su estado si se tienen permisos. Los borradores se identifican en el título y solo aparecen para los responsables autorizados.

Se mantienen los filtros de categoría (incluidas subcategorías), búsqueda, estado y pendientes de confirmar, además de los contadores del menú. El filtro **Impacto** permite elegir Normal, Alto o Crítico y combinarlo con los demás filtros; **Todos los impactos** elimina esa restricción. Elegir el impacto y pulsar **Buscar** aplica la selección. **Desde** y **Hasta** filtran por la fecha de creación que se muestra en la tabla: se puede indicar una sola fecha o ambas, incluyendo los días completos según la zona horaria del usuario. Se combinan con categoría, búsqueda, estado, impacto y confirmaciones. Un rango invertido o una fecha inválida muestra un mensaje para corregirlo; los enlaces del árbol lateral conservan los filtros. En pantallas pequeñas la tabla se desplaza horizontalmente dentro del listado; también se puede enfocar con el teclado. Notificaciones utiliza el mismo formato de tabla, con sus columnas de título, categoría, fecha y lectura.

Esta presentación se implementa en `local/orangeportal/index.php` y `styles.css`, con encabezados traducidos en los archivos de idioma del plugin. No existe un ajuste nativo de Moodle para cambiar el listado de este plugin de tarjetas a tabla; no se modifica el core ni el tema Moove.

## Estado compartido

Los estados iniciales son Abierta, En seguimiento y Cerrada. Un destinatario con la capacidad de cambiar estado puede actualizarlo desde el detalle. El cambio se aplica a la incidencia completa y queda registrado con usuario y fecha. Los destinatarios restantes ven el nuevo estado.

## Recibido, leído y confirmado

**Recibido** se registra al mostrar el aviso en el listado del portal. **Leído** se registra al abrirlo. **Confirmado** exige pulsar **Confirmar que he leído este aviso**. Estos estados son individuales y no se confunden con el estado compartido de la incidencia.

Si un responsable cambia el título o el contenido, aumenta la versión y se exige una nueva confirmación. Se conservan el texto y las confirmaciones anteriores, consultables desde los informes. Cambiar únicamente el estado no invalida la confirmación de lectura.

Un responsable que abre una vista de gestión sin ser destinatario no se cuenta como receptor ni lector. Un borrador tampoco registra lecturas de destinatarios.

## Modificar o retirar

En el listado de gestión, elegir Editar. Para retirar un aviso sin borrar su histórico, desmarcar Publicado. Cambiar los destinatarios modifica el acceso de acuerdo con la pertenencia actual a sus colectivos.

La presentación y este flujo utilizan el plugin local. La actividad Base de datos de Moodle permite almacenar fichas y controlar permisos, pero no proporciona este circuito global de destinatarios mixtos, estado compartido auditado y tres marcas de lectura por versión.

Los filtros del listado tienen etiquetas visibles y controles de altura uniforme. El separador bajo la descripción ocupa todo el ancho del contenido; los estilos incluyen una versión para invalidar la caché del navegador al modificarlos.

En los listados de Incidencias y Notificaciones, la clase `orange-wide-listing` amplía el contenedor principal de Moove al ancho disponible de la página, conservando sus márgenes y relleno. Encabezado, descripción, separador, filtros y tabla comparten ese ancho. El ajuste se limita a estos dos listados, sin modificar archivos del tema.
