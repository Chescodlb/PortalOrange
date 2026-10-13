# Implementación del portal Orange

La primera versión mantiene Moodle 4.5.1 y Moove 4.5.1 como dependencias sin modificar. La configuración de marca vive en los ajustes del tema y las funciones adicionales se concentran en `moodle/local/orangeportal/`.

## Configuración nativa antes que código

| Requisito | Solución | Motivo |
| --- | --- | --- |
| Logo editable | Archivo de logo en Moove | Existe en sus ajustes General |
| Naranja, negro, Arial y estilos | Ajustes de color y SCSS de Moove | Existe una interfaz administrativa para personalizar el tema |
| Página inicial configurable | Ajuste de navegación de Moodle | El plugin registra las secciones como opciones; el cliente decide desde Moodle |
| Roles y permisos | Capacidades y roles de Moodle | No se crea un modelo de identidad paralelo |
| Colectivos | Cohortes y grupos de Moodle | Reutiliza pertenencia y matriculación nativas |
| Árbol de biblioteca | Categorías nativas | Editable desde Administrar cursos y categorías |
| Documentos | Cursos documentales y recursos Archivo, Carpeta, Página, Libro y URL | Moodle protege y sirve sus contenidos y archivos |
| Formación, encuestas y notas | Cuestionario, Feedback, finalización y calificaciones nativos | No se reemplazan evaluación ni seguimiento |
| Menú exacto de cuatro secciones | Hook de navegación del plugin | Moove no ofrece un ajuste para sustituir así su menú principal |
| Árbol con contadores por destinatario | Vista del plugin | Debe contar exactamente los avisos accesibles a cada persona |
| Avisos globales categorizados y asignables | Plugin integrado en administración | Las actividades nativas no reúnen este flujo fuera de los cursos |
| Recibido, leído y confirmado por versión | Tablas del plugin | El registro de actividad nativo no es una confirmación explícita del texto de una versión |
| Estado compartido con histórico | Plugin | Las fichas nativas no incluyen este registro de transición y permisos por destinatario |
| Panel de resultados | Resumen y enlaces a informes nativos | Integra la revisión por curso y la trazabilidad de avisos en un punto de entrada |

## Biblioteca

Se ha interpretado el Excel como una sola rama **RPV/CENTREX**, con procesos, subprocesos y productos debajo. La carga inicial crea categorías mediante las APIs de Moodle y no altera el core. Después el cliente mantiene la estructura desde Moodle; el archivo JSON extraído no se usa para renderizar el árbol.

Los agentes acceden a colecciones mediante matrícula y restricciones de recurso. Los administradores ven también los nodos sin contenido. Los nombres del Excel se han conservado, incluidos sus errores tipográficos; se pueden corregir desde administración.

## Incidencias y notificaciones

Se guardan seis tipos de registro: categorías, publicaciones, asignaciones, recepciones por versión, transiciones de estado y textos de versiones. La asignación admite individuos, cohortes y grupos de cursos. Todas las vistas, contadores y mutaciones usan el mismo control de acceso.

Los cambios de estado requieren una petición POST con sesión válida. El estado es común, mientras que las lecturas y confirmaciones son individuales. Las operaciones de lectura y edición usan bloqueos por publicación para evitar confirmar una versión mientras se modifica su texto.

Las consultas de gestión no se atribuyen al destinatario si el gestor no está asignado. Retirar una publicación conserva su histórico. La interfaz incluye filtros de categoría, texto, estado y confirmación pendiente.

## Formación y alertas

El curso de ejemplo se crea con recursos Página, un Cuestionario de una pregunta y una actividad Feedback. Las preguntas y notas usan el banco de preguntas y el motor nativo.

El plugin observa los eventos nativos de finalización del curso y actualización de calificación. Crea avisos individuales sin exponer calificaciones ocultas. El correo local sigue desactivado y no hay integración con Teams.

## Límites de esta entrega

La búsqueda de Biblioteca localiza nombres y descripciones de las colecciones; todavía no busca dentro de PDF ni en los textos de todos los recursos. Moodle tiene búsqueda global configurable si el cliente la necesita, y ese trabajo debe revisarse con el volumen real de contenidos.

La biblioteca muestra metadatos y versiones mediante contenido nativo editable. No tiene un motor adicional de aprobación de documentos o versionado automático de archivos. Las publicaciones del portal sí conservan versiones de texto y confirmaciones.

Las publicaciones no incluyen adjuntos en esta versión. El panel de resultados no es un cuadro de mando de calidad o IA. Las consultas de avisos están pensadas para la demostración local; con el volumen real conviene añadir paginación y optimizar las consultas de destinatarios antes de producción.

## Archivos y mantenimiento

El plugin está separado de Moodle y Moove. `assets/orange-theme.scss` se copia al ajuste nativo de SCSS del tema; el CSS propio del plugin afecta a sus componentes. `scripts/configure-orange-local.sh` respalda la base y configura ejemplos mediante APIs nativas; es un inicializador local, no una herramienta de gestión cotidiana.

La matriz original y su extracción se mantienen fuera de Git, igual que `.env`, los datos y los respaldos. Los ajustes efectivos de Moodle están en la base de datos: los archivos de Git no sustituyen una copia de seguridad de esa base y de `moodledata`.

La gestión de categorías de Biblioteca y Cursos se presenta en el formulario del portal, con la misma estructura visual que los avisos. Utiliza `core_course_category::create()` y `update()` y valida raíz, permisos, categoría superior y ciclos. La interfaz general de Moodle sigue disponible, pero no es el destino de Gestionar biblioteca/cursos porque mezcla raíces y navegación secundaria.
