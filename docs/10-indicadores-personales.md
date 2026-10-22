# Indicadores personales Orange · 21/10/2026

La barra superior presenta cuatro accesos directos, sin desplegables. Solo se aplica a miembros de la cohorte Orange y en el tema Orange. Sustituyen a los iconos nativos de avisos del sistema y mensajes privados; no desactivan esos subsistemas ni borran mensajes. El menú de usuario y cerrar sesión se conservan. El resto del campus mantiene su navegación nativa.

| Icono | Qué cuenta | Destino |
| --- | --- | --- |
| Campana | Comunicados publicados, asignados y sin leer | Comunicados personales sin leer; solo críticos cuando haya alguno pendiente |
| Triángulo con exclamación | Incidencias abiertas asignadas y sin leer | Incidencias personales abiertas sin leer; solo críticas cuando haya alguna pendiente |
| Libro abierto | Formaciones visibles y accesibles creadas desde la incorporación a Orange, sin apertura | Formaciones nuevas sin abrir |
| Biblioteca/libros | Recursos documentales accesibles añadidos desde la incorporación a Orange, sin apertura | Documentos nuevos sin leer |

Campana y triángulo son naranjas y muestran el total pendiente cuando no hay críticas sin leer. Si las hay, el icono y su contador pasan a rojo, la cifra muestra **solo las críticas sin leer** y el enlace aplica los filtros personal, sin leer y crítico. El texto al pasar el cursor y la etiqueta accesible incluyen las críticas y el total pendiente. Al leer la última crítica, vuelve al naranja y al filtro general de pendientes. Alto no activa el rojo. Los otros dos iconos siempre son naranjas.

La clasificación utiliza el identificador estable `critical` del catálogo de Impactos, tanto para Comunicados como para Incidencias. Renombrarlo conserva el comportamiento. Una categoría llamada «Crítico» no activa alertas: categoría e impacto son conceptos independientes.

Los cuatro iconos muestran su cantidad, también cuando es cero, y tienen etiqueta accesible y descripción al pasar el cursor. Los números se recalculan al cargar una página: no hay sondeo en segundo plano. Las aperturas registradas por Moodle se reflejan en la siguiente carga; en el detalle de avisos, la apertura se guarda antes de dibujar la cabecera.

## Configuración con Moodle

1. La cohorte **Usuarios Orange**, configurada como audiencia del portal, establece quién pertenece al portal. La fecha nativa `cohort_members.timeadded` determina la incorporación. Quitar y volver a añadir al usuario establece una nueva fecha; no hay una fecha de alta duplicada en el plugin.
2. Publicar y asignar comunicados/incidencias mediante los formularios del portal. Solo cuentan los avisos asignados al usuario por individuo, cohorte o grupo activo. Los borradores, las vistas de gestión sin asignación y las incidencias cerradas quedan fuera de los contadores.
3. Gestionar matrículas y restricciones documentales/formativas desde Moodle. Los indicadores reutilizan esos controles y no conceden acceso adicional.
4. En **Administración Orange > Comunicados/Incidencias > Crear publicación**, elegir **Impacto > Crítico**, destinatarios y Publicado. También puede editarse una publicación existente. Los impactos se mantienen en **Estados e impactos > Impactos**; no hace falta crear una categoría Crítico. El estado de Comunicados sigue sin editarse.
5. Los filtros laterales de Comunicados incluyen Impacto, combinable con categoría, fechas, lectura y confirmación. El detalle muestra el impacto.
6. Cambiar título, contenido o impacto genera una nueva versión: solicita una nueva lectura y conserva los registros anteriores. Así, escalar un comunicado ya leído a Crítico activa la alerta personal. Cambiar solo el estado de una incidencia no solicita una nueva lectura.
7. La apertura reduce el contador individual; no elimina publicaciones, cambia estados ni completa actividades. En Comunicados, confirmar sigue siendo una acción separada: leer reduce el contador aunque quede pendiente la confirmación.

«Nuevo» se calcula con la fecha nativa de creación de la formación (`course.timecreated`) o incorporación del recurso (`course_modules.added`), comparada con la entrada en Orange. No hay fecha independiente de publicación de recursos: hacer visible un recurso antiguo no reinicia su antigüedad. Actualizar un documento tampoco lo convierte en un documento nuevo. La evidencia de apertura formativa es `user_lastaccess`; para documentos se reutilizan las lecturas existentes del portal y de Moodle.

El listado general conserva el contenido anterior a la incorporación. Los indicadores abren un filtro de novedades; **Ver todo** o **Limpiar filtros** permite volver al listado habitual. La navegación y la búsqueda dentro del filtro documental conservan el criterio de novedades.

## Implementación aislada y verificación

Moodle no proporciona un ajuste para estos cuatro contadores con esta clasificación y fechas. `classes/personal_updates.php` reúne las reglas; el renderizador del tema hijo Orange sustituye solo la salida de iconos nativos. Los filtros de listados comparten las mismas reglas. Las cadenas están en español e inglés. No se crean tablas ni se modifica core/Moove.

- `docker compose exec -T web php local/orangeportal/cli/check_personal_updates.php`: prueba con transacción revertida las asignaciones, prioridades, versiones, fechas de incorporación, aperturas y exclusión de usuarios externos.
- `node scripts/test-orange-browser.mjs --personal-updates-only`: comprueba filtros laterales, cuatro indicadores, coincidencia entre cifras y listados, traducción, móvil y acceso de Formador.
