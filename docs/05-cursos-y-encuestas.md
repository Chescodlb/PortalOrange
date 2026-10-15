# Gestionar cursos y encuestas

Los cursos, evaluaciones, calificaciones y encuestas utilizan Moodle estándar. El portal muestra los cursos de la categoría **Cursos** a los que el usuario está matriculado o tiene permiso de acceso. No se ha activado un catálogo de automatriculación.

## Crear un curso

1. Con `formador.orange`, entrar en **Cursos > Nuevo curso**. Se abre el formulario nativo de Moodle con la categoría actual de Cursos propuesta.
2. Crear el curso e indicar nombre, descripción y formato.
3. Activar el seguimiento de finalización en sus ajustes.
4. En **Participantes > Métodos de matriculación**, añadir cohortes o matricular usuarios individualmente.
5. Asignar el rol de formador a los responsables del curso.

## Contenidos y evaluación

Con `formador.orange`, pulsar **Editar curso** en la tarjeta del curso de ejemplo. Ese botón activa la edición nativa de Moodle; solo aparece si se tiene permiso para gestionar actividades en ese curso. Con edición activada, añadir recursos y actividades. Para una evaluación, pulsar **Nueva evaluación** en la tarjeta del curso o, dentro del curso, elegir **Cuestionario**, indicar intentos, nota máxima, nota para aprobar y opciones de revisión. Añadir preguntas desde **Banco de preguntas** o crearlas dentro del cuestionario.

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

Al pulsar «Nuevo curso» desde Cursos, el selector del formulario nativo muestra la raíz Cursos y sus subcategorías, respetando el permiso `moodle/course:create`. «Nueva colección documental» desde Biblioteca limita el selector a Biblioteca. No es necesario retirar al formador sus permisos de la otra sección.

La ruta de navegación al crear un curso es **Cursos > Crear un nuevo curso**; el primer enlace vuelve a la raíz de Cursos. No muestra «Página Principal» ni lleva a Biblioteca.

Moodle ofrece un selector conjunto de las categorías donde se tiene permiso; no dispone de un ajuste para separar estos dos accesos manteniendo ambos permisos. El plugin usa los hooks nativos del formulario y valida la categoría al guardar, sin modificar el core. La creación desde la administración general y la edición de cursos existentes conservan su comportamiento nativo.

## Eliminar categorías vacías

En **Administración > Gestionar categorías > Cursos**, pulsar **Eliminar** junto a una categoría y confirmar su nombre. **Cancelar** vuelve al listado sin cambios. La operación requiere permiso nativo `moodle/category:manage` en la raíz, la categoría y su padre; está disponible para el editor autorizado.

La categoría debe estar vacía: no puede contener subcategorías ni cursos, incluidos los ocultos. Moverlos primero mediante las herramientas nativas de Moodle. La raíz **Cursos** está protegida y no ofrece eliminación; tampoco se pueden eliminar categorías de otra sección manipulando la URL.

El portal utiliza `core_course_category::delete_move()` hacia la categoría superior después de comprobar que no existen cursos ni subcategorías. Moodle gestiona contextos, cachés y eventos, y conserva otros contenidos del contexto mediante su traslado nativo. Se exige confirmación mediante POST con `sesskey` y se comprueba de nuevo que la categoría esté vacía. No se modifica el core ni se borran cursos o documentos.
