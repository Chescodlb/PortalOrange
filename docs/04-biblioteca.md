# Gestionar la biblioteca RPV y CENTREX

La biblioteca usa categorías nativas de Moodle y cursos como colecciones documentales. El usuario navega por columnas y selecciona directamente los recursos Archivo, Carpeta, Página, Libro o URL de las colecciones, sin entrar antes en el curso documental. El cliente puede crear, mover y renombrar categorías, colecciones y recursos desde Moodle.

## Navegación por columnas · 20/10/2026

**Biblioteca** en el menú superior abre una página; ya no despliega un árbol flotante. La primera columna permite elegir RPV o CENTREX. Cada elección abre a su derecha la lista de categorías hijas, con el nombre de la categoría seleccionada como título. No se utilizan los títulos fijos Proceso, Subproceso, Detalle o Sistemas. La columna final se llama **Documentos**. Los pasos seleccionados de la ruta y el documento elegido se resaltan con fondo naranja suave y texto/borde naranja, siguiendo la referencia visual.

Ejemplo de recorrido: `RPV → ALTA → Alta con terminal → SIM Física → Documentos`. Las categorías pueden tener más o menos niveles; no se fija una profundidad. La lista final recoge los recursos accesibles de las colecciones de la rama seleccionada, incluidas sus subcategorías. Las colecciones se mantienen en Moodle para organizar y gestionar los documentos, pero no añaden un paso obligatorio al lector.

Al seleccionar un documento se muestra su ruta, título, descripción y tipo, con **Abrir documento**. El botón abre el recurso nativo de Moodle y respeta su configuración de visualización. Los perfiles con permiso para gestionar actividades ven además **Gestionar colección**, para editar sus recursos y accesos con las herramientas nativas. Antes de seleccionar no hay botón de apertura. En móvil, las columnas se desplazan horizontalmente dentro de la biblioteca; los enlaces y la búsqueda funcionan sin JavaScript. Una mejora opcional de foco muestra la siguiente columna tras navegar.

## Estructura inicial

```text
Biblioteca
├── RPV
│   └── ALTA
│       └── Alta con terminal
│           └── SIM Física
│               └── Colección documental → recursos
└── CENTREX (vacía por ahora)
```

El contenido importado de las 121 filas del Excel se mantiene inicialmente bajo RPV. RPV y CENTREX son ahora categorías separadas, reemplazando la decisión inicial de una rama única. En la demo, las ramas que estaban bajo CENTREX se han trasladado a RPV con las APIs nativas, conservando los IDs, cursos, recursos y sus permisos. El script explícito `cli/reorganize_library.php` solo admite localhost y no debe ejecutarse automáticamente contra el campus existente.

Todos los usuarios Orange pueden explorar la estructura completa, incluidas las categorías vacías. Los documentos se filtran por matrícula/acceso al curso, visibilidad de la categoría, visibilidad y disponibilidad de la actividad, restricciones nativas por grupos y capacidad de vista del recurso. Los recursos ocultos de la página del curso (incluidos los de tipo stealth) no se añaden al listado. Moodle vuelve a comprobar los permisos al abrir cada documento.

## Búsqueda global

**Buscar en toda la biblioteca** busca por nombre y descripción de los recursos accesibles, con independencia de la rama seleccionada, y muestra los resultados en la columna derecha **Documentos**. No busca en el cuerpo de los PDF, archivos adjuntos ni el contenido interno de páginas o libros. **Limpiar búsqueda** vuelve a la biblioteca sin filtro. Seleccionar otra categoría retoma la navegación por su rama. Elegir un resultado sitúa las columnas en su ruta y muestra su ficha, conservando la búsqueda global.

Las descripciones proceden del campo nativo de descripción del recurso; no se duplica el contenido en tablas del portal. No se envían al navegador datos de documentos restringidos para filtrarlos después en JavaScript.

## Crear o reorganizar categorías

1. Entrar con `editor.orange` o un perfil autorizado y abrir **Administración > Gestionar categorías > Biblioteca**. También sirve **Biblioteca > Gestionar biblioteca**.
2. Escribir el nombre de la nueva categoría.
3. En **Categoría superior**, elegir **Biblioteca · raíz de la sección** para una rama principal o una categoría existente para una subrama. Para mantener los procedimientos dentro de RPV, elegir esa rama o alguno de sus descendientes.
4. Pulsar **Guardar categoría**. Las columnas se actualizan con la estructura de Moodle.
5. Usar **Editar / mover** en el listado para renombrar o cambiar la categoría superior. El selector excluye la categoría editada y sus descendientes.

El formulario es el mismo estilo que Incidencias y Notificaciones, pero crea y actualiza categorías nativas mediante `core_course_category`. Biblioteca y Cursos tienen listados y selectores separados. No se puede seleccionar una categoría de Cursos como superior de una categoría de Biblioteca ni modificar la raíz desde este formulario.

La gestión general de Moodle sigue disponible para un administrador en **Administración del sitio > Cursos > Administrar cursos y categorías**; no es la entrada habitual del portal.

## Crear una colección y cargar recursos

Con `formador.orange`, Biblioteca muestra **Nuevo documento** y **Nueva colección documental**.

1. Para un archivo nuevo, pulsar **Nuevo documento**, elegir una colección editable y pulsar **Añadir documento**. Se abre el formulario nativo Archivo: indicar el nombre, subir el PDF o documento y guardar.
2. Para una nueva colección, pulsar **Nueva colección documental**. El formulario nativo de Moodle crea un curso documental en Biblioteca. Si se ha seleccionado una categoría del árbol, se propone esa categoría. Elegir **Guardar y mostrar** para entrar en la colección nueva y activar edición.
3. Añadir recursos Archivo, Carpeta, Página, Libro o URL desde la colección. Las evaluaciones se reservan normalmente a Cursos.
4. En Participantes, matricular los usuarios o colectivos destinatarios y asignar los responsables. Una colección nueva no hereda automáticamente los destinatarios de otra.
5. Probar con un agente matriculado.

El selector Nuevo documento solo contiene colecciones de Biblioteca donde se pueden gestionar actividades y añadir archivos. La subida, almacenamiento y permisos de archivos son los del módulo nativo de Moodle. La nueva colección queda bajo la responsabilidad de su creador con el rol nativo Profesor con permiso de edición, según el ajuste de Moodle para creadores de cursos.

## Restringir documentos y secciones

La matrícula limita el acceso a la colección completa. Dentro de ella se puede usar **Editar recurso > Restricciones de acceso > Grupo o Agrupamiento**. Cerrar el icono del ojo si también se quiere ocultar el recurso a quienes no cumplen la restricción.

Para restringir una parte extensa del árbol, situar sus colecciones en esa rama y matricular solo las cohortes autorizadas en ellas. Una categoría por sí sola no matricula usuarios ni concede acceso a los documentos. Los nombres de categorías y cursos pueden aparecer en listados nativos de Moodle; la protección del contenido depende de matrículas y restricciones.

## Versionado y mantenimiento

Indicar versión, última actualización, responsable e histórico de cambios en la descripción de la colección o en la portada del recurso. Son campos editables del contenido nativo; esta entrega no añade un sistema automático de aprobación o versionado de archivos.

Para sustituir un PDF, editar su recurso Archivo, reemplazarlo y actualizar la versión visible. Conservar las versiones anteriores en una carpeta restringida a responsables si el cliente necesita su archivo histórico. Programar la revisión mensual de contenidos como proceso de trabajo del equipo.

## Lectura y descarga frente a edición

Tener acceso a la colección permite consultar y descargar sus documentos. Crear o modificar documentos exige un rol de profesor con edición o permisos equivalentes. El agente no obtiene edición por pertenecer a un grupo.

## Menú al abrir una colección documental

Una colección se almacena como curso nativo de Moodle. Su menú contextual se adapta dentro de Biblioteca a **Documentos**, **Configuración** y **Accesos**, mostrando las opciones que Moodle permite al usuario. Documentos abre sus recursos; Configuración permite modificar la colección y Accesos abre Participantes para gestionar destinatarios, matrículas y grupos. Se retiran de esta barra las herramientas académicas como calificaciones e informes; los cursos de formación conservan su navegación normal. El plugin adapta los nodos mediante el hook `secondary_extend`, sin alterar roles, permisos ni archivos del core.

## Formulario de nueva colección

«Nueva colección documental» reutiliza el formulario nativo de Moodle con nombres de colección para el título, los campos y la ruta de navegación. El selector se limita a Biblioteca y sus subcategorías. En los accesos de creación del portal se oculta la navegación secundaria «Categoría / Más» mediante la API de página de Moodle, sin modificar el tema ni el core.

El formulario de nueva colección muestra nombre, código corto, categoría, visibilidad, identificador opcional, descripción e imagen de portada, además de idioma, archivos, grupos y etiquetas. Las ayudas también usan terminología de Biblioteca. La imagen de portada admite imágenes; no es el lugar para cargar documentos.

La ruta de navegación es **Biblioteca > Crear una nueva colección documental**. El primer enlace abre la raíz de Biblioteca. En esta pantalla el plugin adapta el nodo raíz que Moodle añade automáticamente para evitar un enlace «Página Principal» que redirija a otra sección.

Los títulos, campos, ayudas, botones de creación y errores de categoría usan cadenas de idioma nativas de Moodle, con archivos `lang/es` y `lang/en` del plugin. En inglés la ruta es **Library > Create a new document collection**. El idioma se elige desde el perfil del usuario; el idioma predeterminado se gestiona en **Administración del sitio > Idioma > Ajustes de idioma**. Los nombres de categorías y los documentos son contenido y no se traducen automáticamente. La administración del portal (publicaciones, categorías, informes, estados e impactos) y el formulario de nuevo documento también utilizan cadenas de idioma. Algunos textos de los listados públicos siguen pendientes de una revisión independiente.

Las nuevas colecciones usan formato Temas con una sección documental, sin fechas de formación, anuncios, libro de calificaciones ni seguimiento de finalización. Estos valores se fijan en el servidor mediante el formulario nativo. Los documentos se añaden después como recursos Archivo, Carpeta, Página o Libro. Los cursos de formación conservan todos sus ajustes académicos. La edición de colecciones existentes mantiene por ahora el formulario nativo completo.

Los marcos de competencias son una función académica nativa para organizar habilidades y relacionarlas con cursos y planes de aprendizaje. No son categorías documentales. La instalación local no contiene marcos ni competencias. Si se decide desactivar esta función en todo Moodle, se hace desde **Administración del sitio > Características avanzadas > Habilitar competencias**; no es necesario desactivarla globalmente para limpiar la creación de colecciones.

## Eliminar categorías vacías

En **Administración > Gestionar categorías > Biblioteca**, pulsar **Eliminar** junto a una categoría y confirmar su nombre. **Cancelar** vuelve al listado sin cambios. La operación requiere permiso nativo `moodle/category:manage` en la raíz, la categoría y su padre; está disponible para el editor autorizado.

La categoría debe estar vacía: no puede contener subcategorías ni colecciones documentales, incluidos los ocultos. Moverlos primero mediante las herramientas nativas de Moodle. La raíz **Biblioteca** está protegida y no ofrece eliminación; tampoco se pueden eliminar categorías de otra sección manipulando la URL.

El portal utiliza `core_course_category::delete_move()` hacia la categoría superior después de comprobar que no existen cursos ni subcategorías. Moodle gestiona contextos, cachés y eventos, y conserva otros contenidos del contexto mediante su traslado nativo. Se exige confirmación mediante POST con `sesskey` y se comprueba de nuevo que la categoría esté vacía. No se modifica el core ni se borran cursos o documentos.

## Implementación y comprobación

La generación de columnas, la consulta de recursos permitidos y la búsqueda se reúnen en `classes/library_browser.php`, reutilizando `orange_native_courses()` y `get_fast_modinfo()`. Las categorías siguen gestionadas con las APIs nativas; no se crean tablas ni se modifica el core o Moove. La presentación por columnas requiere código porque no existe un ajuste nativo para ese recorrido dentro del portal. Los textos nuevos están en español e inglés y el diseño sigue limitado a la cohorte Orange.

`node scripts/test-orange-browser.mjs --library-columns-only` comprueba el recorrido, selección, apertura, búsqueda, móvil, traducciones y que Formaciones abra directamente su página. `php local/orangeportal/cli/check_library_browser.php` dentro del contenedor comprueba búsquedas globales y permisos, incluidos recursos ocultos, restricciones por grupo y capacidades de módulo; revierte todas las actividades y roles temporales.

## Orden y lectura individual · 20/10/2026

El listado se abre por defecto con **Más recientes**, usando la fecha de última actualización del recurso nativo. El selector compacto está a la derecha de **Documentos** en la misma línea. Permite cambiar a **Nombre** y aplica el orden automáticamente, sin botón; esta mejora usa JavaScript. La elección se conserva al navegar por las categorías o buscar, mediante el parámetro de URL `order`. No modifica un orden compartido para otros usuarios.

Cada documento se presenta en una fila: indicador naranja a la izquierda, centrado verticalmente, y a su lado nombre, descripción y breadcrumb en tres líneas. El check indica **Leído**; el punto y el título destacado indican **Sin leer**. Los indicadores tienen etiquetas accesibles y texto al pasar el cursor. Las líneas largas se abrevian visualmente con puntos suspensivos y conservan el texto completo al pasar el cursor. La fecha no aparece en la fila; se utiliza para ordenar. La marca es individual y se registra al abrir realmente el recurso nativo; seleccionar su ficha no lo marca. Se mantiene al volver a entrar. No se exige activar finalización académica ni se concede acceso adicional a contenidos.

El plugin observa el evento nativo `course_module_viewed` para recursos de Biblioteca y miembros Orange. Guarda únicamente usuario, ID del módulo y primera fecha de apertura en `local_orange_doc_read`, con una clave única por usuario/recurso. También aprovecha registros existentes de la tabla nativa `course_modules_viewed` cuando los hay; no depende de registros de actividad que puedan purgarse y no reconstruye aperturas antiguas sin evidencia. La marca indica una apertura del recurso, no una confirmación explícita de lectura ni el versionado de sus archivos. Editar un documento cambia su fecha y posición entre los recientes, pero no reinicia esta marca de lectura.

Los registros nuevos se incluyen en las herramientas de privacidad de Moodle para exportación y eliminación. Al eliminar un módulo, se limpian sus marcas. Actualizar el plugin desde Administración > Notificaciones o con `admin/cli/upgrade.php --non-interactive` crea la tabla sin modificar tablas nativas ni Moove. La apariencia de los usuarios externos no cambia.

Prueba reversible: `docker compose exec -T web php local/orangeportal/cli/check_document_reads.php`. Comprueba fechas frente a lectura, orden alfabético, ficha sin apertura, lectura por usuario, reapertura sin duplicados, vistas nativas previas, exclusión de usuarios externos y limpieza de registros y privacidad.
