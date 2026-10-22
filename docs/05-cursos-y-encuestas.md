# Gestionar cursos y encuestas

Los cursos, evaluaciones, calificaciones y encuestas utilizan Moodle estándar. El portal muestra los cursos de la categoría **Cursos** a los que el usuario está matriculado o tiene permiso de acceso. No se ha activado un catálogo de automatriculación.

## Crear un curso

1. Con `formador.orange`, entrar en **Formaciones > Nuevo curso**. Se abre el formulario nativo de Moodle con la categoría actual de Cursos propuesta.
2. Crear el curso e indicar nombre, descripción y formato.
3. Activar el seguimiento de finalización en sus ajustes.
4. En **Participantes > Métodos de matriculación**, añadir cohortes o matricular usuarios individualmente.
5. Asignar el rol de formador a los responsables del curso.

## Contenidos y evaluación

Con `formador.orange`, pulsar **Editar curso** en la ficha del curso de ejemplo, después de seleccionarlo en el listado. Ese botón activa la edición nativa de Moodle; solo aparece si se tiene permiso para gestionar actividades en ese curso. Con edición activada, añadir recursos y actividades. Para una evaluación, pulsar **Nueva evaluación** en la ficha del curso o, dentro del curso, elegir **Cuestionario**, indicar intentos, nota máxima, nota para aprobar y opciones de revisión. Añadir preguntas desde **Banco de preguntas** o crearlas dentro del cuestionario.

El ejemplo usa una pregunta Verdadero/Falso, dos intentos, nota sobre 100 y aprobado con 70. El cálculo de notas y el registro de intentos son los del módulo Cuestionario de Moodle.

Los resultados se consultan en **Curso > Calificaciones** y en **Cuestionario > Resultados**. Configurar las opciones de revisión para decidir cuándo el agente puede ver las respuestas y su nota.

## Encuestas

Añadir la actividad **Encuesta** del módulo `feedback`, también llamada **Retroalimentación** en algunas traducciones. Elegir respuesta anónima o identificada, permitir o limitar envíos y añadir las preguntas en **Editar preguntas**.

El ejemplo es identificado y no publica sus estadísticas a estudiantes. Los responsables autorizados pueden consultar el análisis y las respuestas desde la actividad.

Una encuesta independiente puede ubicarse en un curso dedicado a encuestas, dentro de Cursos, sin exigir otra formación. La asignación sigue usando las matrículas de ese curso.

## Finalización y alertas

Configurar las condiciones en **Actividad > Finalización de actividad** y **Curso > Finalización del curso**. En el ejemplo hay finalización por visualizar el contenido, aprobar el test y enviar la encuesta.

El portal genera una notificación individual de categoría Registro cuando Moodle emite el evento de curso completado o actualiza una calificación visible de actividad. No incluye notas ocultas en el aviso. Las finalizaciones de curso se consolidan mediante las tareas programadas de Moodle, por lo que requieren cron.

La instalación local mantiene el correo desactivado. Para procesar tareas locales se puede activar el servicio cron indicado en README; Teams pertenece a una fase posterior.

## Categorías al crear desde el portal

Al pulsar «Nuevo curso» desde Formaciones, el selector del formulario nativo muestra la raíz Cursos y sus subcategorías, respetando el permiso `moodle/course:create`. «Nueva colección documental» desde Biblioteca limita el selector a Biblioteca. No es necesario retirar al formador sus permisos de la otra sección.

La ruta de navegación al crear un curso es **Formaciones > Crear un nuevo curso**; el primer enlace vuelve a la raíz de Cursos. No muestra «Página Principal» ni lleva a Biblioteca.

Moodle ofrece un selector conjunto de las categorías donde se tiene permiso; no dispone de un ajuste para separar estos dos accesos manteniendo ambos permisos. El plugin usa los hooks nativos del formulario y valida la categoría al guardar, sin modificar el core. La creación desde la administración general y la edición de cursos existentes conservan su comportamiento nativo.

## Eliminar categorías vacías

En **Administración > Gestionar categorías > Formaciones**, pulsar **Eliminar** junto a una categoría y confirmar su nombre. **Cancelar** vuelve al listado sin cambios. La operación requiere permiso nativo `moodle/category:manage` en la raíz, la categoría y su padre; está disponible para el editor autorizado.

La categoría debe estar vacía: no puede contener subcategorías ni cursos, incluidos los ocultos. Moverlos primero mediante las herramientas nativas de Moodle. La raíz **Cursos** está protegida y no ofrece eliminación; tampoco se pueden eliminar categorías de otra sección manipulando la URL.

El portal utiliza `core_course_category::delete_move()` hacia la categoría superior después de comprobar que no existen cursos ni subcategorías. Moodle gestiona contextos, cachés y eventos, y conserva otros contenidos del contexto mediante su traslado nativo. Se exige confirmación mediante POST con `sesskey` y se comprueba de nuevo que la categoría esté vacía. No se modifica el core ni se borran cursos o documentos.


## Formaciones por columnas · 21/10/2026

La sección **Formaciones** reutiliza el menú horizontal de Biblioteca: la primera columna contiene **Formación inicial** y **Formación continua**, las columnas siguientes toman sus títulos de la categoría que las origina y el último panel se llama **Cursos**. No se selecciona una rama ni un curso ficticio al entrar. Los nombres de categorías son contenido nativo gestionable por el cliente, no títulos fijos en el código.

El listado muestra títulos con un indicador personal de apertura. Seleccionar un curso muestra su ruta, nombre, resumen y **Abrir formación**, que lleva al curso nativo de Moodle. Abrir la ficha del portal no registra apertura del curso ni acredita finalización; la consulta real usa la evidencia nativa de acceso. Los perfiles con permiso conservan **Editar curso** y **Nueva evaluación** en la ficha, además de **Nuevo curso** y el enlace a resultados y seguimiento.

La búsqueda consulta nombre y resumen en todas las formaciones accesibles, con independencia de la rama. El selector permite **Más recientes** (fecha de creación) o **Nombre**, con aplicación automática. El contador personal de formaciones abre el mismo explorador con el filtro de cursos nuevos desde el alta en Orange y sin abrir; **Ver todo** vuelve al catálogo accesible.

Las categorías utilizan las mismas columnas flexibles, selección naranja y adaptación móvil que Biblioteca. Si hay muchos niveles, se desplazan horizontalmente dentro del explorador. Los cursos se filtran por matrícula o capacidad de acceso y por visibilidad nativa; no existe un catálogo de libre acceso ni se mezclan colecciones documentales.

### Gestionar las ramas desde Moodle

1. Abrir **Administración > Gestionar categorías > Formaciones** con permiso para gestionar sus categorías.
2. Crear **Formación inicial** y **Formación continua**, indicando como superior la raíz de la sección.
3. Añadir subcategorías si hacen falta; el menú las recoge automáticamente.
4. Seleccionar una rama del explorador y pulsar **Nuevo curso** para proponerla como categoría del formulario nativo.
5. Para mover cursos existentes, un responsable autorizado utiliza la gestión nativa de cursos/categorías. Cambiar su categoría conserva IDs, actividades y matrículas.

En la demo local, `cli/configure_training_branches.php` crea las dos categorías solo si no existen y mueve únicamente el curso conocido `ORANGE-FORMACION-INICIAL` desde la raíz a Formación inicial. Formación continua queda inicialmente vacía. No mueve otros cursos ni debe ejecutarse automáticamente en el campus real. Copia local previa: `backups/before-training-columns-20261021.sql`.

### Implementación y verificación

`classes/training_browser.php` consulta cursos con `orange_native_courses()` y reutiliza `library_browser::categories()`, `trail()` y `column()`. Los estilos y el avance de columnas/orden automático son compartidos. La ruta se mantiene en `section=courses`; el parámetro `course` selecciona una ficha, sin dar acceso adicional. El detalle solo se genera si el curso está dentro del conjunto accesible y del filtro actual. No se modifica Moodle, Moove ni el almacenamiento de cursos.

- `docker compose exec -T web php local/orangeportal/cli/check_training_browser.php`: ramas, búsquedas globales, IDs manipulados, separación documental y visibilidad por rol; revierte cambios temporales.
- `node scripts/test-orange-browser.mjs --training-columns-only`: recorrido, fichas, orden, idioma, móvil y herramientas del Formador.
