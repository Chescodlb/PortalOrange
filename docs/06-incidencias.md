# Publicar y seguir incidencias

## Flujo vigente · 20/10/2026

Las incidencias son avisos operativos creados y asignados por el **Formador Orange**. El anterior perfil de editor/comunicaciones se integra en Formador: no se comprueba el nombre de usuario, sino capacidades nativas de Moodle. En la demo, `formador.orange` recibe publicación, gestión de categorías y catálogos, seguimiento y cambios de estado. `editor.orange` se conserva como cuenta de prueba histórica, con el mismo rol ahora llamado Formador Orange. El administrador general conserva todos sus permisos; para usar la experiencia del portal debe pertenecer también a la cohorte Orange.

| Perfil | Incidencias visibles | Acciones |
| --- | --- | --- |
| Agente | Publicadas, asignadas y en estado abierto | Consultar; la apertura registra lectura automáticamente |
| Formador | Todas, incluidas cerradas y borradores | Crear, editar, asignar, eliminar del listado, cambiar estado, cerrar/reabrir y consultar seguimiento |
| Coordinador | Todas, incluidas cerradas y borradores | Consultar y seguir destinatarios, aperturas y cambios; no publicar ni cambiar estados |

## Crear, editar y asignar

1. Con Formador, abrir **Incidencias > Nueva incidencia** o **Administración > Incidencias > Crear publicación**.
2. Completar título, categoría, contenido, impacto y estado. **Cliente afectado** y **Producto** son texto libre opcional de hasta 255 caracteres; no hay un catálogo independiente.
3. Elegir usuarios, cohortes y/o grupos de cursos. La asignación es la unión de esos destinatarios.
4. Marcar **Publicado** y guardar. Sin publicar solo lo ven los responsables autorizados.
5. Usar **Editar** para modificar datos o destinatarios. Los cambios de título, contenido, cliente o producto generan una nueva versión para la lectura individual. Cambiar solo el estado conserva la versión de lectura.

Los avisos de Notificaciones conservan su formulario y confirmación manual. Los campos y el circuito de Incidencias no se aplican a Notificaciones.

## Listado y filtros

Los filtros están a la izquierda en escritorio: búsqueda, estado, categoría con subcategorías, impacto, cliente afectado, producto, fecha de apertura desde/hasta y **Solo sin leer**. Cliente y producto muestran los valores presentes en las incidencias accesibles al usuario, sin filtrar datos de incidencias ajenas. Los días de las fechas son completos según la zona horaria del usuario. **Filtrar** combina los criterios y **Limpiar filtros** vuelve al listado inicial. No hay filtros por perfil activo ni de confirmación.

A la derecha aparece la tabla con identificación/título, apertura, cierre, impacto, estado, cliente y producto. El código `INC` se deriva del ID local de la incidencia; no es un identificador importado de un sistema externo. La categoría se muestra bajo el título con la presentación común de rutas abreviadas. El listado se ordena por apertura más reciente. En móvil los filtros se colocan arriba y la tabla se desplaza horizontalmente dentro de su contenedor.

El **! naranja** de la primera columna significa **sin leer** para el usuario y versión actuales. Desaparece al abrir el detalle. No representa impacto ni estado. No hay columna visible Lectura, botón de confirmación ni acción de confirmación manual para Incidencias; el servidor también rechaza esa acción si se intenta enviar un POST antiguo. Se conservan los registros históricos, pero no se exigen nuevas confirmaciones.

## Estados y cierre

Solo el perfil con `local/orangeportal:changestate` puede cambiar estados, tanto desde el detalle como al editar una publicación. Ese permiso se asigna a Formador y se retira del rol autenticado general; el coordinador no lo recibe. La protección se comprueba en el servidor.

Los estados siguen editables en **Administración > Estados e impactos**. Se conserva **En seguimiento** como estado abierto. El nuevo ajuste **Es un estado de cierre** distingue estados abiertos/cerrados sin depender de su nombre: crear, por ejemplo, un estado Resuelta marcado de cierre lo ocultará a agentes. Los agentes solo pueden consultar incidencias en estados no clasificados como cierre. Formador y coordinador pueden filtrar todas, abiertas, cerradas o un estado concreto.

Debe quedar al menos un estado abierto activo para nuevas incidencias. Un estado utilizado no puede cambiar de clasificación abierto/cerrado, porque equivaldría a cerrar o reabrir publicaciones sin pasar por su histórico. Se puede renombrar, desactivar o crear otro estado. Se mantienen las protecciones de los catálogos: no borrar opciones usadas y conservar al menos una activa.

Cerrar guarda `timeclosed` y `closedby`, además del cambio en `local_orange_state_log`. Reabrir limpia el cierre actual y conserva los cambios anteriores. Las cerradas anteriores a este cambio toman la fecha/autor del último cambio de estado conocido; si no hay registro, se usa la fecha de modificación como referencia inicial y autor desconocido.

## Seguimiento de incidencias y usuarios

Formador y coordinador tienen **Seguimiento de incidencias** en la esquina derecha del listado y como pestaña de la administración del portal. El enlace abre `incident_tracking.php`, no la administración general de Moodle. Un agente no puede acceder aunque conozca la URL.

La vista general muestra incidencias, datos de cliente/producto, fechas, estado y número de destinatarios actuales y de quienes la abrieron. Pulsar una incidencia abre el detalle con destinatarios, recepción y apertura de la versión actual, responsable/fecha de cierre, histórico de estados y aperturas por versión. Las vistas personales de responsables no asignados pueden aparecer en el histórico, sin añadirlos a los destinatarios.

**Eliminar**, desde la gestión de publicaciones, pide confirmación mediante POST con sesskey. Retira la incidencia de los listados públicos mediante baja lógica (`deleted`, `deletedby`) y conserva su historial y destinatarios en Seguimiento. No permite seguir abriendo o modificando una incidencia retirada por los enlaces públicos. Las categorías con publicaciones históricas siguen protegidas frente a su eliminación.

## Categorías y permisos desde Moodle

Abrir **Administración > Gestionar categorías > Incidencias**. Crear categoría o subcategoría, editar/mover o eliminar tras confirmar. Solo se eliminan categorías sin subcategorías ni publicaciones, incluidas las conservadas para auditoría. Las tablas reutilizan `orange_category_label()` para mostrar el nombre y hasta dos superiores; las rutas profundas empiezan por `… ›` y permiten consultar la ruta completa con ratón o teclado.

Para otorgar los permisos a otro usuario: **Administración del sitio > Usuarios > Permisos > Definir roles / Asignar roles del sistema**. Formador requiere `manage`, `changestate`, `viewallincidents`, `viewreports` y `manageoptions` del componente `local_orangeportal`; coordinación requiere `viewallincidents`, `viewreports` y, si gestiona catálogos, `manageoptions`. Para categorías nativas de Biblioteca/Cursos, asignar **Gestión de categorías Orange** en sus raíces, sin conceder administración de todo Moodle.

## Almacenamiento, actualización y pruebas

Los nuevos campos pertenecen a `local_orange_notice`, en la misma base de datos Moodle. Las aperturas permanecen en `local_orange_receipt`, por usuario y versión, y los cambios de estado en `local_orange_state_log`. Los catálogos se guardan en `config_plugins`; las pertenencias y capacidades son nativas de Moodle. No se modifica el core ni Moove. La actualización habitual de plugins crea los campos, adapta los roles propios existentes y conserva los datos. Los campos personales y actores de cierre/baja se incluyen en exportación y eliminación mediante privacidad de Moodle.

Prueba de modelo reversible: `docker compose exec -T web php local/orangeportal/cli/check_incident_workflow.php`. Prueba de navegador: `node scripts/test-orange-browser.mjs --incidents-workflow-only`. El navegador consulta publicaciones existentes y registra esas aperturas reales; no cierra ni elimina contenido del usuario. La prueba de modelo revierte sus incidencias, lecturas, cambios y catálogos temporales.
