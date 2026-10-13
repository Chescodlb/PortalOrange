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

1. Dentro de la categoría adecuada, crear un curso con un nombre documental, por ejemplo **Manual de portabilidad entrante**.
2. Añadir los colectivos mediante sincronización de cohortes o matriculación manual.
3. Activar edición y añadir **Archivo** para PDF o documentos, **Carpeta** para varios archivos, **Página** o **Libro** para contenido consultable, y **URL** para material externo.
4. Para grabaciones, añadir un archivo multimedia o un enlace autorizado.
5. Probar con un agente matriculado.

Las dos colecciones iniciales son ejemplos de Alta con terminal e Incidencias Centrex. Su contenido no es un procedimiento operativo aprobado.

## Restringir documentos y secciones

La matrícula limita el acceso a la colección completa. Dentro de ella se puede usar **Editar recurso > Restricciones de acceso > Grupo o Agrupamiento**. Cerrar el icono del ojo si también se quiere ocultar el recurso a quienes no cumplen la restricción.

Para restringir una parte extensa del árbol, situar sus colecciones en esa rama y matricular solo las cohortes autorizadas en ellas. Una categoría por sí sola no matricula usuarios ni concede acceso a los documentos. Los nombres de categorías y cursos pueden aparecer en listados nativos de Moodle; la protección del contenido depende de matrículas y restricciones.

## Versionado y mantenimiento

Indicar versión, última actualización, responsable e histórico de cambios en la descripción de la colección o en la portada del recurso. Son campos editables del contenido nativo; esta entrega no añade un sistema automático de aprobación o versionado de archivos.

Para sustituir un PDF, editar su recurso Archivo, reemplazarlo y actualizar la versión visible. Conservar las versiones anteriores en una carpeta restringida a responsables si el cliente necesita su archivo histórico. Programar la revisión mensual de contenidos como proceso de trabajo del equipo.

## Lectura y descarga frente a edición

Tener acceso a la colección permite consultar y descargar sus documentos. Crear o modificar documentos exige un rol de profesor con edición o permisos equivalentes. El agente no obtiene edición por pertenecer a un grupo.
