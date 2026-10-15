# Gestionar la biblioteca RPV/CENTREX

La biblioteca usa categorías de Moodle para el árbol y cursos como colecciones documentales. Dentro de cada colección se añaden recursos nativos. El cliente puede crear, mover y renombrar categorías sin editar código.

## Estructura inicial

```text
Biblioteca
└── RPV/CENTREX
    └── Proceso
        └── Subproceso
            └── Producto cuando corresponda
                └── Colección documental
```

Se han importado las 121 filas de contenido del Excel facilitado. RPV/CENTREX es una sola rama, conforme a la aclaración del usuario. Las celdas fusionadas de proceso y subproceso se propagan a sus productos. Hay procesos o subprocesos sin producto y se conservan como nodos finales válidos.

Todos los usuarios ven el árbol completo, incluidas categorías vacías o sin contenido asignado, en los desplegables de la barra superior. Cada usuario solo ve las colecciones y los recursos para los que tiene permiso; un recurso restringido sigue protegido por Moodle aunque alguien conozca su URL.

## Crear o reorganizar categorías

1. Entrar con `editor.orange` o un perfil autorizado y abrir **Administración > Gestionar categorías > Biblioteca**. También sirve **Biblioteca > Gestionar biblioteca**.
2. Escribir el nombre de la nueva categoría.
3. En **Categoría superior**, elegir **Biblioteca · raíz de la sección** para una rama principal o una categoría existente para una subrama. Para mantener los procedimientos dentro de RPV/CENTREX, elegir esa rama o alguno de sus descendientes.
4. Pulsar **Guardar categoría**. El árbol superior se actualiza con la estructura de Moodle.
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
