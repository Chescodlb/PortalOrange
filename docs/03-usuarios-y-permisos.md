# Gestionar usuarios y permisos

Moodle permite crear roles y asignar permisos por contexto. Una persona puede tener más de un rol y pertenecer a varias cohortes y grupos. RPV y CENTREX son categorías de Biblioteca y no representan colectivos de usuarios.

## Habilitar la experiencia Orange

Añadir el usuario a la cohorte de sistema **Usuarios Orange** y seguir [el procedimiento de navegación y tema](02-navegacion.md). La cohorte habilita el portal; los roles existentes determinan las acciones permitidas. Tener un rol Orange sin pertenecer a la cohorte no cambia la experiencia general de Moodle ni habilita el acceso directo al portal. No usar como criterio el nombre de usuario, el dominio del correo o ser administrador.

## Crear y modificar usuarios

Abrir **Administración > Usuarios > Cuentas > Agregar un usuario**. Para editar, suspender o consultar cuentas existentes, usar **Examinar lista de usuarios**. La carga por CSV también es nativa de Moodle.

## Roles y responsabilidades

Abrir **Usuarios > Permisos > Definir roles** para crear o modificar un perfil. Asignar roles globales en **Asignar roles del sistema**, y roles de formación dentro de **Curso > Participantes**.

| Perfil | Asignación inicial | Permisos |
| --- | --- | --- |
| Agente | Estudiante en sus cursos y bibliotecas | Consulta y evaluación; incidencias abiertas asignadas sin cambiar estados; confirmación manual solo en Notificaciones |
| Formador | Rol global Formador Orange; Profesor con edición y Creación de contenidos Orange en sus ámbitos formativos | Funciones del antiguo editor: publicar avisos, categorías y catálogos; CRUD de incidencias, todos los estados y seguimiento; además sus funciones de formación |
| Coordinación | Profesor sin edición en sus cursos y rol global Coordinación Orange | Consulta todas las incidencias y su seguimiento, resultados autorizados y catálogos; no cambia estados ni publica |
| Responsable documental | Profesor con edición en las colecciones documentales que gestiona | Publicación y mantenimiento de recursos; no requiere ser administrador global |
| Antiguo editor/comunicaciones | Integrado en Formador Orange | Se conserva el ID del rol para no perder asignaciones; `editor.orange` queda como cuenta histórica de pruebas de ese mismo perfil |
| Administrador | Administrador del sitio | Configuración general y todas las funciones |

Las capacidades del plugin se pueden buscar al definir un rol:

- `local/orangeportal:manage`: publicar, editar, asignar y gestionar categorías de avisos.
- `local/orangeportal:viewreports`: acceder al panel de resultados y confirmaciones.
- `local/orangeportal:manageoptions`: gestionar Estados e Impactos desde el portal; asignado a coordinación y comunicaciones. No concede administración general ni publicación de avisos.
- `local/orangeportal:changestate`: cambiar estados de incidencias; reservado al Formador, nunca al agente o coordinador.
- `local/orangeportal:viewallincidents`: consultar abiertas/cerradas y el panel específico de seguimiento; no concede edición.

Para ver notas de un curso también hace falta `moodle/grade:viewall` en ese curso. El permiso del portal por sí solo no concede acceso a notas de cursos ajenos.

## Cohortes para colectivos globales

Abrir **Usuarios > Cuentas > Cohortes**, crear una cohorte para cada equipo o centro y añadir miembros. Una cohorte puede tener ámbito de sistema o categoría. Para las asignaciones globales del portal, usar preferentemente cohortes de sistema.

En cada curso o colección documental, abrir **Participantes > Métodos de matriculación > Sincronización de cohorte**. Seleccionar la cohorte y el rol Estudiante. Así los documentos y cursos utilizan las matrículas nativas; no existe una segunda lista de permisos en el plugin.

## Grupos dentro de un curso

Abrir **Curso > Participantes > Grupos**, crear el grupo y añadir miembros. Los agrupamientos reúnen varios grupos y permiten restricciones de acceso a recursos o secciones.

Los avisos permiten asignar tanto cohortes como grupos de cursos e individuos. Cuando se usa un grupo, el portal comprueba también que su miembro conserva una matrícula activa en ese curso. Cuando se usa una cohorte, comprueba la pertenencia actual. Quitar al usuario del colectivo revoca el acceso a los avisos asignados únicamente mediante ese colectivo.

Referencia: [Grupos y cohortes en Moodle](https://docs.moodle.org/404/en/Groups_and_cohorts).

## Formador y categorías de Biblioteca y Cursos

Formador (incluida la cuenta histórica `editor.orange`) tiene además el rol **Gestión de categorías Orange**, con `moodle/category:manage`, asignado en las dos categorías raíz **Biblioteca** y **Cursos**, no en el sistema. Permite crear, editar, mover y gestionar subcategorías dentro de esas raíces, sin conceder administración general ni gestión de categorías ajenas.

Para asignarlo a otro usuario desde Moodle: abrir **Administración > Cursos > Administrar cursos y categorías**, seleccionar Biblioteca y entrar en **Permisos > Asignar roles**; asignar **Gestión de categorías Orange**. Repetir en Cursos si corresponde. Para retirar ese acceso, quitar la asignación en la raíz correspondiente. El permiso se hereda en sus subcategorías. No concede por sí solo matrícula, creación de cursos, edición de recursos ni acceso a informes.

El usuario `formador.orange` tiene Profesor con permiso de edición en las dos colecciones de Biblioteca y en el curso de ejemplo. En las tarjetas correspondientes ve **Editar documentos** o **Editar curso**, que activan la edición nativa. Un agente no ve esos botones. Además, tiene el rol **Creación de contenidos Orange**, con `moodle/course:create`, asignado únicamente en las raíces Biblioteca y Cursos. Permite crear colecciones y cursos dentro de ellas, sin administración general ni creación en categorías ajenas.

Para autorizar a otro responsable a crear contenidos, asignar **Creación de contenidos Orange** desde **Permisos > Asignar roles** en Biblioteca y/o Cursos. Moodle configura el rol del creador en un curso nuevo mediante **Usuarios > Permisos > Políticas de usuario > Rol de los creadores en nuevos cursos**; aquí es Profesor con permiso de edición. La edición de contenidos existentes sigue dependiendo del rol asignado en cada curso o colección. `editor.orange` no recibe este rol y conserva únicamente gestión de publicaciones de avisos y de categorías.
