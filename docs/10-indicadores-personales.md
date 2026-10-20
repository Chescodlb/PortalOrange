# Indicadores personales Orange · 20/10/2026

La barra superior presenta cinco accesos directos, sin desplegables. Solo se aplica a miembros de la cohorte Orange y en el tema Orange. Sustituyen a los iconos nativos de avisos del sistema y mensajes privados; no desactivan esos subsistemas ni borran mensajes. El menú de usuario y cerrar sesión se conservan. El resto del campus mantiene su navegación nativa.

| Icono | Qué cuenta | Destino |
| --- | --- | --- |
| Campana | Incidencias abiertas asignadas y sin leer, excepto las críticas | Incidencias personales abiertas sin leer, no críticas |
| Triángulo con exclamación | Incidencias críticas abiertas asignadas y sin leer | Incidencias personales críticas abiertas sin leer |
| Libro abierto | Formaciones visibles y accesibles creadas desde la incorporación a Orange, sin apertura | Formaciones nuevas sin abrir |
| Biblioteca/libros | Recursos documentales accesibles añadidos desde la incorporación a Orange, sin apertura | Documentos nuevos sin leer |
| Burbuja de diálogo | Comunicados publicados y asignados sin leer en su versión actual | Comunicados personales sin leer |

Las incidencias críticas no se suman también en la campana. La clasificación usa el identificador estable `critical` del catálogo de Impactos; el resto de impactos, incluido Alto, se agrupa como no crítico. Cambiar el nombre de Crítico conserva su significado. No se añade un nuevo catálogo de prioridades.

Los cinco iconos muestran su cantidad, también cuando es cero, y tienen etiqueta accesible y descripción al pasar el cursor. Los números se recalculan al cargar una página: no hay sondeo en segundo plano. Las aperturas registradas por Moodle se reflejan en la siguiente carga; en el detalle de avisos, la apertura se guarda antes de dibujar la cabecera.

## Configuración con Moodle

1. La cohorte **Usuarios Orange**, configurada como audiencia del portal, establece quién pertenece al portal. La fecha nativa `cohort_members.timeadded` determina la incorporación. Quitar y volver a añadir al usuario establece una nueva fecha; no hay una fecha de alta duplicada en el plugin.
2. Publicar y asignar comunicados/incidencias mediante los formularios del portal. Solo cuentan los avisos asignados al usuario por individuo, cohorte o grupo activo. Los borradores, las vistas de gestión sin asignación y las incidencias cerradas quedan fuera de los contadores.
3. Gestionar matrículas y restricciones documentales/formativas desde Moodle. Los indicadores reutilizan esos controles y no conceden acceso adicional.
4. En Incidencias, elegir el impacto Crítico para que el pendiente aparezca en el triángulo. Los demás aparecen en la campana.
5. La apertura reduce el contador individual; no elimina publicaciones, cambia estados ni completa actividades. En Comunicados, confirmar sigue siendo una acción separada: leer reduce el contador aunque quede pendiente la confirmación.

«Nuevo» se calcula con la fecha nativa de creación de la formación (`course.timecreated`) o incorporación del recurso (`course_modules.added`), comparada con la entrada en Orange. No hay fecha independiente de publicación de recursos: hacer visible un recurso antiguo no reinicia su antigüedad. Actualizar un documento tampoco lo convierte en un documento nuevo. La evidencia de apertura formativa es `user_lastaccess`; para documentos se reutilizan las lecturas existentes del portal y de Moodle.

El listado general conserva el contenido anterior a la incorporación. Los indicadores abren un filtro de novedades; **Ver todo** o **Limpiar filtros** permite volver al listado habitual. La navegación y la búsqueda dentro del filtro documental conservan el criterio de novedades.

## Implementación aislada y verificación

Moodle no proporciona un ajuste para estos cinco contadores con esta clasificación y fechas. `classes/personal_updates.php` reúne las reglas; el renderizador del tema hijo Orange sustituye solo la salida de iconos nativos. Los filtros de listados comparten las mismas reglas. Las cadenas están en español e inglés. No se crean tablas ni se modifica core/Moove.

- `docker compose exec -T web php local/orangeportal/cli/check_personal_updates.php`: prueba con transacción revertida las asignaciones, prioridades, versiones, fechas de incorporación, aperturas y exclusión de usuarios externos.
- `node scripts/test-orange-browser.mjs --personal-updates-only`: comprueba filtros laterales, cinco indicadores, coincidencia entre cifras y listados, traducción, móvil y acceso de Formador.
