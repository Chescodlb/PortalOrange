# Cargar actividades y recursos en Orange

Desde la versión `2026102700` del plugin, Biblioteca permite crear una actividad o recurso directamente en una categoría. Incidencias y Comunicados admiten varios recursos además de sus datos y descripción opcional. Las colecciones de Formaciones son secciones nativas del curso y conservan los bloques laterales de Moodle.

Los documentos, publicaciones, matrículas y lecturas existentes se conservan. No hay migración automática, traslado ni sustitución de contenido manual.

## Biblioteca paso a paso

1. Entrar con Formación o Administración Orange y abrir **Biblioteca**.
2. Navegar hasta la categoría deseada y pulsar **Nuevo documento**.
3. Elegir **Categoría** y **Actividad o recurso**. Ya no hay que crear ni seleccionar una colección.
4. Pulsar **Continuar al formulario de Moodle**. Allí se configuran los campos propios del tipo elegido: archivo, contenido HTML, URL, preguntas, etc.
5. Guardar. Se vuelve a Biblioteca y el elemento aparece en la categoría elegida. Si se cancela, no aparece ningún documento nuevo.
6. Seleccionar el documento y pulsar **Abrir documento** para consultarlo o **Editar actividad o recurso** para modificarlo con los permisos correspondientes.

La selección permite los módulos habilitados en Moodle. El formulario nativo vuelve a comprobar las capacidades `moodle/course:manageactivities`, `mod/TIPO:addinstance` y las restricciones de módulos del curso. Un módulo deshabilitado o prohibido no se puede guardar aunque se altere el formulario del portal.

En segundo plano se crea un curso técnico por categoría únicamente cuando se confirma la selección. Se sincroniza con la cohorte de acceso a Orange como Estudiante. Los grupos y restricciones por actividad se configuran con las herramientas nativas de ese curso: **Participantes**, **Grupos** y **Restricciones de acceso** en la actividad. No se copian automáticamente los grupos internos de otros cursos. El acceso a la categoría no concede edición al agente.

Las colecciones anteriores siguen siendo cursos nativos, con sus recursos, matrículas y restricciones originales. No se incorporan nuevos destinatarios a esas colecciones. La nueva sincronización de audiencia solo se aplica a los nuevos contenedores por categoría. Los recursos antiguos continúan apareciendo en su categoría sin tener que trasladarlos.

Se puede borrar una categoría vacía con la confirmación existente. Si solo contiene un contenedor técnico vacío, se limpia ese contenedor. Si contiene actividades, cursos anteriores, descripciones o bloques personalizados, el borrado queda bloqueado para revisar/mover el contenido primero.

## Incidencias y Comunicados paso a paso

1. Abrir **Administración > Incidencias** o **Comunicados** y crear o editar una publicación.
2. Rellenar título, categoría, destinatarios e impacto; en incidencias, también cliente afectado, producto y estado. La descripción se puede dejar vacía si el contenido estará en actividades o recursos.
3. Para preparar los recursos antes de difundirlos, mantener la publicación en **borrador**.
4. Pulsar **Guardar y gestionar actividades y recursos**. Se crea el espacio nativo de esa publicación y se abre su gestor.
5. Pulsar **Añadir actividad o recurso**, elegir el tipo y continuar al formulario nativo. Guardar para incorporarlo. Repetir para añadir varios.
6. Desde el gestor se pueden abrir los recursos, **Editar** o **Eliminar** cada uno con la confirmación nativa. El formulario nativo permite gestionar archivos, disponibilidad, visibilidad, intentos y las demás opciones de ese módulo. Su eliminación se hace con las herramientas nativas autorizadas de Moodle.
7. Volver a **Editar publicación** y activar su publicación cuando esté preparada. **Guardar publicación** conserva la opción de guardar únicamente sus datos sin abrir el gestor.
8. En la vista del destinatario aparecen las actividades disponibles y sus botones de apertura. El agente consulta; no modifica la publicación ni sus actividades.

En publicaciones anteriores también se puede usar **Actividades y recursos** desde el listado administrativo o **Gestionar actividades y recursos** desde su ficha. Si aún no tienen espacio de recursos, se prepara mediante el botón correspondiente; la descripción anterior se mantiene.

### Acceso y lecturas

Los permisos de la publicación se comprueban al preparar el acceso nativo, abrir la actividad y descargar un archivo. Cambiar los destinatarios, pasar a borrador, retirar/eliminar la publicación o cerrar una incidencia para agentes bloquea también los enlaces nativos aunque la persona hubiera accedido antes. El administrador general del sitio conserva su acceso nativo de supervisión.

El acceso nativo se prepara para el usuario al consultar los recursos de una publicación autorizada. Formación recibe un rol de autor acotado al contenedor; el destinatario recibe matrícula Estudiante para poder usar los módulos. Estas matrículas internas no aparecen como formaciones en el seguimiento ni en el CSV de cursos.

Añadir, modificar o eliminar una actividad incrementa la revisión de la publicación y deja pendiente la nueva lectura. Los registros de lecturas anteriores se conservan. Abrir una actividad también registra la lectura actual cuando el módulo emite su evento nativo de visualización. Consultar la ficha sigue registrando su lectura como antes; no certifica haber completado todas las actividades. Los cuestionarios y otras actividades conservan su finalización/calificación nativa, separada de la lectura de la publicación.

El historial de revisiones de Orange conserva los datos de la publicación; no es una copia de seguridad de cada versión del archivo o de las preguntas. La actividad, sus resultados y los registros de cambios usan las herramientas nativas de Moodle.

## Colecciones dentro de una formación

1. Abrir **Formaciones > Colecciones de formación**, o seleccionar una formación y pulsar ese mismo botón en su ficha.
2. Elegir la formación. Se muestran sus secciones actuales, incluidas las creadas antes del cambio.
3. Para crear otra, escribir **Nombre de la colección** y pulsar **Crear colección**. Se crea una sección en el curso seleccionado, sin duplicar cursos ni documentos.
4. En cada colección, pulsar **Añadir actividad o recurso**. El formulario nativo guarda el elemento en esa sección.
5. Usar **Editar** junto a la colección para cambiar su nombre, descripción y disponibilidad con el editor de secciones de Moodle.
6. Usar **Editar formación y bloques laterales** para abrir el curso con edición activada. El panel **Agregar un bloque** permite elegir los bloques instalados y autorizados. El orden y visibilidad de los bloques siguen siendo nativos.

Los agentes ven las colecciones y actividades que les permite Moodle. Las matrículas, intentos, calificaciones, finalización y encuestas siguen perteneciendo al curso nativo. Crear colecciones no concede acceso a usuarios nuevos ni cambia sus roles.

## Permisos y almacenamiento

- Crear documentos por categoría requiere pertenecer a Orange y `moodle/course:create` en esa categoría. El rol técnico **Autor de recursos Orange** se asigna solo al curso contenedor permitido y habilita la edición nativa de actividades. No se asigna al sistema.
- Gestionar recursos de publicaciones requiere `local/orangeportal:manage`. Coordinación y Calidad no lo reciben por consultar seguimiento. Una incidencia eliminada no se puede editar.
- Gestionar colecciones y bloques de una formación usa las capacidades nativas del curso; los agentes no reciben edición.
- El rol técnico es un rol nativo editable desde **Administración del sitio > Usuarios > Permisos > Definir roles**. No debe concederse manualmente a agentes. El callback comprueba que el autor conserva su ámbito de edición y retira sus asignaciones automáticas si pierde ese permiso.

`local_orange_container` guarda únicamente la relación entre curso técnico y categoría/publicación. Los archivos, HTML, preguntas, intentos, secciones, bloques, matrículas y roles se guardan en las entidades nativas y en `moodledata`. La categoría técnica de publicaciones se crea oculta fuera de Biblioteca y Formaciones; su ID queda en `local_orangeportal/resourcescategory`. No cambiar ese ID a una categoría compartida ni eliminar o mover sus cursos manualmente.

Todas las personalizaciones están en `local/orangeportal`; no se modifica Moodle, Moove ni el tema Orange.

## Desplegar esta actualización

1. Hacer copia de base de datos, código y `moodledata` según el procedimiento del ambiente.
2. Subir **todo `local/orangeportal`**, incluido `lib.php`, las clases y `db/`.
3. Ejecutar la actualización habitual de Moodle desde **Administración del sitio > Notificaciones**, o en la raíz del servidor:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

4. Confirmar que el plugin figura como `2026102700`. La actualización crea la tabla de relaciones y el rol técnico. No crea ni migra recursos de usuarios automáticamente.
5. Mantener habilitados **Matrícula manual** y **Sincronización de cohortes**. Comprobar las dos categorías raíz y la cohorte de acceso a Orange ya configuradas.
6. Probar con Formación: documento por categoría, recurso de una publicación y colección formativa. Probar con Agente: lectura sin edición. Retirar un destinatario y comprobar que también se bloquea el enlace al archivo.

Para volver a la versión anterior después de cargar actividades nuevas se requiere una restauración coordinada de código, base de datos y archivos. No basta con borrar la tabla de relaciones: se perdería la comprobación de destinatarios sobre los enlaces nativos de publicaciones.

## Pruebas locales

`cli/check_native_content.php` verifica transaccionalmente creación, módulos, permisos, cambios de revisión, destinatarios, borradores, incidencias cerradas y conservación del contenido. No ejecutar contra el campus real.

`cli/native_content_browser_fixture.php create` prepara ejemplos independientes para el navegador; sus IDs se guardan en `backups/previews/native-content-fixture.json`. `node scripts/test-orange-browser.mjs --native-content-only` comprueba formularios ES/EN, cancelación, guardado sin descripción, colecciones, bloques y descarga nativa. Después, `withdraw TOKEN`, `--native-content-withdrawn` y `cleanup TOKEN` permiten verificar la retirada y eliminar únicamente los ejemplos identificados por ese token. Estas herramientas solo admiten localhost.

## Continuación tras guardar y destinatarios — 10/11/2026

Formaciones incorpora **Guardar y continuar**, que abre las colecciones y recursos del curso recién creado. **Editar** abre directamente la configuración nativa, con accesos al contenido, participantes y copia de edición, y con publicación y aviso opcional a Teams dentro de Destinatarios. Los enlaces antiguos a `training_setup.php` redirigen a ese formulario. No hace falta regresar al listado ni activar edición para encontrar esos accesos. La página de participantes del portal usa matrícula manual nativa y restringe la búsqueda a usuarios Orange.

Los documentos de Biblioteca incorporan el selector de colectivos en el formulario nativo del recurso. Tras guardar se vuelve a Biblioteca con el documento seleccionado. Las publicaciones sin destinatarios explícitos pasan a dirigirse a toda la cohorte Orange, conservando la comprobación de pertenencia al proyecto.
