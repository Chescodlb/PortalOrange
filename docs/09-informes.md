# Consultar lecturas y resultados

El acceso está en **Administración > Seguimiento**. Se requiere pertenecer a Orange y la capacidad `local/orangeportal:viewreports`. Las notas requieren además `moodle/grade:viewall` en cada curso; Calidad no recibe ese permiso por acceder a lecturas.

## Selector de seguimiento

La pestaña **Seguimiento** muestra debajo un segundo nivel de navegación, igual que Gestionar categorías: **Resumen**, **Biblioteca**, **Incidencias**, **Comunicados**, **Formación** y **Usuarios**. Solo se muestra la sección seleccionada y se marca en naranja. La entrada inicial es Resumen; los enlaces existentes a un curso o publicación abren automáticamente su sección correspondiente.

Incidencias y Comunicados muestran una fila por publicación con **Publicación, Fecha de publicación/revisión, Destinatarios, Sin leer y Leídos**. Los totales corresponden a la versión actual y a los destinatarios actuales del portal Orange. Un usuario asignado por individuo, cohorte y grupo cuenta una sola vez. Las publicaciones sin destinatarios actuales solo aparecen con ceros en la consulta global sin filtro de colectivo; un responsable de equipo solo ve publicaciones asignadas a personas de su ámbito. Se incluyen publicaciones publicadas y no retiradas, también incidencias cerradas para los perfiles autorizados de seguimiento.

Seleccionar el título abre una vista de detalle por usuarios de esa publicación, con **Destinatario actual, Recibido y Leído**. Se pueden combinar los filtros de destinatario y lectura (todas, leídas o sin leer). **Volver al listado** regresa al resumen; **Limpiar filtros de este bloque** restablece los filtros del detalle manteniendo la publicación. El histórico por versión sigue disponible como un bloque colapsable dentro del detalle. Para Incidencias hay además un enlace al histórico de estados.

Formación muestra ediciones independientes, participantes activos con rol de alumno, finalizaciones y media de notas visibles. Seleccionar un curso abre **Resumen**, **Participantes y asistencia**, **Evaluaciones** y **Satisfacción**. Se mantienen los permisos nativos para consultar notas, cuestionarios y encuestas. No se conceden permisos al abrir una pestaña.

La selección usa `section=overview`, `incidents`, `notifications`, `courses` o `users`; los enlaces antiguos con `noticeid` siguen funcionando. El ámbito de equipo se aplica en el servidor a resúmenes, fichas, históricos, asistencia y CSV.

## Porcentajes, fechas y periodos

Los resúmenes por publicación y por usuario muestran **Sin leer antes de Leídos**, siempre con porcentaje y fracción: `75 % — 9/12`. Sin destinatarios/asignaciones se indica expresamente; no se inventa un 0 % o un 100 %.

En todas las secciones, el periodo permite **Últimos 7 días**, **Últimos 30 días** (predeterminado) **Rango personalizado** (ambos días incluidos en la zona horaria del usuario) y **Todo el histórico**. Son ventanas móviles de 7/30 días, no semanas o meses de calendario. El criterio es la fecha registrada de alta de la **versión actual**, mostrada en la columna Fecha de publicación/revisión, con orden descendente y desempate por ID descendente. La fecha original de creación permanece intacta. No existe todavía una fecha independiente de primera publicación: una versión preparada como borrador conserva la fecha de creación de esa versión cuando se publica. Esta columna no debe interpretarse como prueba de envío ni de primera publicación efectiva.

Un cambio de contenido/título/impacto genera versión reciente; cambiar solo estado no reinicia el periodo. La lectura se evalúa para esa versión y los destinatarios actuales. La fecha de apertura del lector no determina la entrada de la publicación en el periodo. Al seleccionar una publicación se muestran todos sus destinatarios actuales, aunque se llegue mediante un enlace antiguo fuera del periodo.

En Usuarios se añade **Críticas sin leer · histórico**, independiente del periodo: cuenta publicaciones críticas vigentes asignadas, sin incluir incidencias cerradas. Evita ocultar una crítica antigua al seleccionar la última semana. Las incidencias cerradas sí permanecen en el porcentaje histórico de lecturas y su desglose, diferenciadas por la columna Estado. No se confunden cierre y lectura.

Formación filtra por **fecha de inicio de edición** y muestra la situación actual de sus matrículas activas. Las ediciones sin fecha aparecen en Todo el histórico. Evaluaciones aplica el periodo a la fecha de inicio del intento; Satisfacción a la fecha de respuesta. Las críticas pendientes mantienen todo el histórico aunque se seleccione un rango. La reconstrucción histórica de destinatarios, traslados y matrículas requiere registros adicionales; los porcentajes actuales no la simulan.

## Seguimiento → Usuarios

Disponible con el mismo permiso `local/orangeportal:viewreports`: Administración Orange, Formación, Coordinación y Calidad configurados. El agente no entra. El perfil Cliente queda pendiente de definir. Acceder no concede notas ni exportación ni escritura.

El directorio muestra Usuario/CE, porcentajes de incidencias sin leer/leídas y comunicados sin leer/leídos, críticas pendientes de todo el histórico, formaciones finalizadas/asignadas y formaciones en curso. Sin cursos autorizados se indica esa limitación, en lugar de aparentar un cero de rendimiento. Cada directorio se pagina a 25 usuarios mediante SQL después de agregar sus lecturas; ordenar por pendientes ordena todo el resultado, no solo la página visible.

Filtros: nombre completo o CE y **Colectivo / centro**, con orden predeterminado por lecturas pendientes del periodo y alternativa alfabética. **Número de ID (`user.idnumber`) es el CE**. El centro se identifica mediante pertenencia a un colectivo Orange (cohorte nativa), por ejemplo **Cristalia-Madrid** o **Centro-Valencia**. No se utiliza Institución como centro ni se deduce un centro a partir del texto del nombre.

Para configurarlo desde la web: **Administración Orange > Colectivos Orange > Crear colectivo**, introducir el nombre del centro y guardar. Después, desde **Usuarios Orange > Editar**, asignar los colectivos al usuario. Se pueden asignar varios; el filtro aplica pertenencia exacta y cada usuario aparece una sola vez. Los grupos nativos de curso siguen disponibles como destinatarios de publicaciones, pero los centros compartidos entre cursos se gestionan como cohortes. No se crean automáticamente los centros de ejemplo. La ficha individual muestra las pertenencias a colectivos. El CE se mantiene en el campo nativo Número de ID mediante administración de perfiles de Moodle.

Al seleccionar un usuario, la ficha ofrece **Resumen, Incidencias, Comunicados, Formación y Encuestas**. Los listados individuales de publicaciones incluyen fecha, categoría jerárquica, impacto, lectura y estado de incidencia, con filtro leído/sin leer. La ficha y su periodo se conservan al navegar; cambiar de periodo reinicia la página. Usuarios ajenos a Orange se rechazan también por URL.

Formación usa matrículas activas nativas en cursos donde el consultante tiene `moodle/course:viewparticipants` y `report/completion:view` o `report/progress:view`. Excluye Biblioteca y otros proyectos. Finalizada requiere `course_completions.timecompleted`. En curso requiere inicio nativo, actividad completada o un intento real sin previsualización; acceder al curso por sí solo no acredita participación, asistencia ni horas trabajadas. La ficha muestra fecha de finalización, nota total visible y resultado solo con permiso `moodle/grade:viewall`. No confunde finalización con aprobado. Sin `gradepass > 0` o sin nota visible no determina un aprobado/suspenso. Sin finalización configurada, no se deduce una finalización a partir de la nota.

Los intentos requieren además `mod/quiz:viewreports` en cada cuestionario y muestran número, estado y fechas. Se limita cada bloque a los 25 últimos intentos, con enlace al informe nativo solo para consulta global autorizada; la pestaña Evaluaciones de la edición permite paginar todos los intentos dentro del ámbito; la ficha pagina por 25 cursos. Calidad, con la configuración actual, no recibe seguimiento formativo ni notas. Coordinación solo recibe los cursos en los que dispone de los permisos nativos correspondientes. No se modifican roles por abrir esta vista.

Encuestas requiere permiso nativo de consulta en el curso/actividad. Solo las respuestas identificadas en encuestas no anónimas se asocian al usuario. Las anónimas se etiquetan como tales y no se intenta deducir su autor. Satisfacción agrega por encuesta las preguntas numéricas o de elección con puntuación: número de respuestas, media en la escala configurada y distribución. Los comentarios se muestran aparte. Las respuestas anónimas solo se agregan cuando el consultante abarca a todos los alumnos de la edición: nunca se desglosan por colectivo ni se atribuyen a personas. La valoración del formador se obtiene de las preguntas configuradas en cada encuesta; no se inventa una media entre escalas o preguntas distintas. No hay importación automática del histórico de Excel.

La ficha de edición incorpora código, edición, tipo, modalidad, duración prevista y real, formadores, centro, territorio, cartera, objetivo, origen, materia y referencia de KPI mediante campos personalizados nativos. Son datos manuales; los textos de KPI no calculan rendimiento. La asistencia manual utiliza una tabla propia, separada de las matrículas, notas, intentos y finalización nativos. Usuarios permite exportar estadísticas con los mismos filtros si se concede exportación.

## Paginación

Cada tabla muestra **25 filas por página**, con paginación nativa de Moodle, tanto en el resumen por publicaciones como en el detalle por usuarios. Los controles aparecen cuando hay más resultados; no se muestran si todo cabe en una página. Cambiar de página mantiene filtros y selección. Una página fuera de rango se ajusta a la última disponible.

Los resúmenes agregados y las tablas de detalle de Incidencias y Comunicados aplican conteo, filtros y límite en la base de datos; no generan toda la tabla para ocultar filas en el navegador. Se utiliza un predicado de audiencia compartido con las comprobaciones de acceso del portal, evitando duplicados cuando alguien recibe una publicación por individuo, cohorte y grupo simultáneamente. Los grupos requieren una matrícula activa, igual que el acceso personal.

Formación pagina por cursos autorizados. Al seleccionar una publicación, su histórico de lecturas tiene también 25 filas por página. Los textos de versiones se consultan de uno en uno mediante el selector de versión, sin imprimir todo el contenido histórico a la vez.

## Lectura automática e histórico

**Recibido** significa que la publicación se mostró en el listado; **Leído** significa que el usuario abrió su detalle. No acredita entrega por correo o Microsoft Teams ni lectura completa del texto. No hay botón ni columna de confirmación manual, tampoco en Comunicados. Las solicitudes antiguas de confirmación se rechazan y no escriben registros nuevos.

Cambiar título, contenido o impacto genera una nueva versión y solicita otra lectura, conservando las anteriores. Leer no cambia el estado común de una incidencia. Las aperturas de responsables sin asignación se registran como vistas personales, pero no los añaden como destinatarios actuales.

Seleccionar una publicación permite desplegar **Histórico de lecturas por versión**. El histórico conserva las lecturas efectivas anteriores, pero su visibilidad se limita a los usuarios que pertenecen actualmente al ámbito del consultante. No reconstruye equipos anteriores. El filtro de destinatario también se aplica a ese histórico; Leído/Sin leer filtra la versión actual, no reinterpreta las versiones anteriores. Las confirmaciones históricas se conservan internamente; si fueran la única evidencia antigua de lectura, se reutiliza su fecha para mostrar Leído.

El listado actual excluye publicaciones retiradas y borradores. El seguimiento específico de Incidencias mantiene su consulta de retiradas y cambios de estado para los autorizados; no se borra su histórico al retirarlas del listado público.

## Exportación

Solo aparece con `local/orangeportal:exportreports`, además de los permisos de consulta. En el detalle por usuarios, Incidencias y Comunicados exportan únicamente el tipo, publicación, destinatario y lectura seleccionados. Formación conserva su botón propio. El CSV incluye **todas las páginas** que coinciden con los filtros, no solo las 25 filas visibles, y no contiene Confirmación. Formación respeta curso, búsqueda, modalidad, periodo y colectivo; incluye los datos manuales de la edición. Asistencia, evaluaciones y satisfacción tienen exportaciones propias. El CSV de satisfacción contiene agregados, sin identidades de respuestas anónimas. Los permisos de exportación nativa de Moodle son independientes.

Coordinación y Calidad no exportan por defecto. El agente no tiene acceso al informe ni a sus CSV. Sin `local/orangeportal:viewallreports`, se limita la consulta a las personas de los colectivos Orange del consultante; seleccionar otro ID no amplía permisos. Ver [Perfiles de consulta](14-perfiles-consulta.md).

## Informes nativos

- **Curso > Calificaciones**: notas por alumno y actividad.
- **Cuestionario > Resultados**: intentos, notas y análisis de preguntas.
- **Encuesta > Análisis / Respuestas**: valoración de la formación.
- **Curso > Informes > Finalización del curso**: consolidada mediante cron.
- **Administración > Informes > Informes personalizados**: informes adicionales con filtros y audiencias nativos cuando se configuren.

Los informes nativos generales no garantizan el límite por colectivo: para los responsables con ámbito de equipo se bloquean esos accesos y se utiliza Seguimiento Orange. Las calificaciones personales siguen disponibles. El panel Orange reúne resumen, lecturas y datos nativos; no sustituye los motores de informes de las actividades. En **Incidencias > Seguimiento de incidencias** se consultan destinatarios, aperturas, cierre, responsable y cambios de estado con `local/orangeportal:viewallincidents`. Formación modifica; Coordinación y Calidad consultan según permisos.

## Implementación y configuración

Los permisos se mantienen desde los roles nativos de Moodle. El selector, los resúmenes y los filtros de esta página pertenecen al plugin y no tienen un ajuste equivalente en la administración estándar: se implementan en `reports.php`, `report_users.php`, `classes/reading_report.php`, `classes/user_report.php` y `export.php`, reutilizando el predicado de audiencia de `classes/service.php`. El histórico colapsable usa HTML nativo `details` y la paginación utiliza el renderizador de Moodle. La versión `2026110601` crea `local_orange_attendance` (una fila por curso y usuario) y los campos personalizados `orange_*` en las tablas nativas `customfield_*`. La asistencia registra estado, minutos, autor, fecha y nombres de colectivos al guardar; actualiza el registro vigente, no un historial de cada corrección. Se integra en exportación y borrado de privacidad. No se cambia core/Moove ni se duplican notas, intentos o matrículas. Las cadenas propias están en español e inglés.

Pruebas locales: `cli/check_user_reports.php` valida periodos, coherencia de porcentajes, búsqueda y pertenencia a colectivos de centro, orden global, ámbito y permisos con reversión. `node scripts/test-orange-browser.mjs --user-reports-only` comprueba Usuarios por perfil, ES/EN y móvil. `cli/check_reading_reports.php` verifica paginación con 30 publicaciones temporales y revierte los cambios; `node scripts/test-orange-browser.mjs --reading-reports-only` verifica separación, filtros, CSV, móvil y ausencia de confirmación manual.


## Configurar el ámbito de cada responsable

1. Crear los centros/equipos en **Administración Orange > Colectivos Orange**.
2. Añadir a cada agente a sus colectivos y a la cohorte base de acceso Orange.
3. Añadir también al coordinador a cada colectivo de personas que deba supervisar. La cohorte base de acceso no concede seguimiento de todos los usuarios.
4. Conceder `local/orangeportal:viewreports`. Para ver formación, asignar además Consulta formativa Orange solo en cursos/categorías autorizados. Calidad conserva únicamente lecturas mientras no se apruebe otro acceso.
5. En Seguimiento, elegir un colectivo o **Todos mis colectivos**. Una persona que pertenece a varios aparece una sola vez. Sin colectivos, un responsable sin consulta global no ve destinatarios.
6. Reservar `local/orangeportal:viewallreports` al Administrador Orange u otros responsables expresamente autorizados. No depende de nombres de usuario ni etiquetas del rol. La actualización lo concede al rol técnico `orange_administrador`.
7. Probar con dos equipos distintos también mediante URL y CSV. El filtro se basa en pertenencias actuales, no en el equipo histórico de una fecha pasada.

## Crear una edición y registrar su asistencia

1. Abrir una formación con permisos de edición. Pulsar **Crear nueva edición**, disponible solo si Moodle permite copiar cursos. También existe la opción nativa **Más > Reutilización del curso > Copiar curso**.
2. Copiar **sin datos de usuarios** y revisar las matrículas/cohortes del destino. No copiar notas ni intentos. El cron de Moodle debe procesar la copia.
3. Nombrar, por ejemplo, «Operativa Orange · noviembre 2026» y actualizar nombre corto, fechas, código y edición. La edición anterior conserva su histórico; no borrarla ni reiniciarla para reutilizarla.
4. En **Configuración del curso > Orange**, completar los campos de la edición. La duración se expresa en minutos. Al copiar, revisar los campos heredados de la plantilla.
5. Matricular a los participantes de la nueva edición con rol de alumno y a los formadores con su rol de edición. Los profesores sin rol de alumno no forman parte del denominador de finalización.
6. Abrir **Administración Orange > Seguimiento > Formación > [edición] > Participantes y asistencia**. Formación puede registrar Sin registrar, Asistió, No asistió o Parcial y los minutos reales conocidos. Coordinación consulta, sin modificar.
7. Revisar Evaluaciones y Satisfacción. Una matrícula no acredita asistencia; asistencia no acredita finalización ni aprobado. La nota final del cuestionario sigue el método nativo y puede incluir intentos fuera del rango; su columna lo indica. La duración del intento incluye posibles pausas y no acredita horas activas de estudio.

Pruebas de esta ampliación: `cli/check_report_scope.php` (datos temporales con reversión, dos ediciones, colectivos, rangos, asistencia, evaluaciones y anonimato) y `node scripts/test-orange-browser.mjs --edition-reports-only` (ES/EN, móvil y accesos por perfil). Los Excel se han usado como referencia de campos; no se han importado sus registros reales.


## Seguimiento de Biblioteca

En **Administración > Seguimiento > Biblioteca**, cada recurso publicado muestra fecha de alta, categoría, usuarios con acceso, Sin leer y Leídos (porcentaje y cantidad). Seleccionar el título abre el detalle por usuario, CE, colectivos, estado de lectura y primera fecha registrada disponible. Se puede buscar por nombre o CE y filtrar Leído/Sin leer. Los listados tienen 25 filas por página.

Se mantienen los filtros de colectivo y periodo: en Biblioteca, el periodo se aplica a la fecha de alta de la actividad/recurso, no a su fecha de apertura. El denominador incluye a las personas del ámbito del consultante que pueden acceder actualmente al recurso, teniendo en cuenta matrícula o permisos de acceso, visibilidad y restricciones nativas. No se cuentan como pendientes los documentos ocultos o inaccesibles. El informe muestra los recursos actuales, no reconstruye accesos ni destinatarios históricos.

Las fechas proceden de `local_orange_doc_read` y, cuando existe, `course_modules_viewed`; se conserva la primera evidencia disponible. Abrir acredita una vista, no lectura completa. Reabrir no sobrescribe la primera fecha; modificar el contenido de un mismo recurso no genera versiones de lectura. No se inventan fechas para aperturas anteriores sin registros. Las áreas de texto y medios abiertas desde Biblioteca también registran su vista, aunque no tengan finalización configurada.

Se requiere `local/orangeportal:viewreports`; Coordinación y Calidad conservan su ámbito de colectivos. El CSV requiere además `local/orangeportal:exportreports` y exporta todas las filas que coinciden con los filtros, con fecha de lectura y sin columnas de notas. No se crean tablas nuevas para este informe ni se modifican permisos de cursos.

Comprobaciones: `cli/check_library_reports.php` cubre visibilidad, restricciones, lecturas, fechas, filtros y permisos con datos temporales revertidos; `cli/check_document_reads.php` cubre las lecturas personales y privacidad.
