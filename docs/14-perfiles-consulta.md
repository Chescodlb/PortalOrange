# Perfiles de consulta de Orange

Configuración revisada el 22 de octubre de 2026. Se utilizan roles, contextos y capacidades nativos de Moodle: no se cambian las páginas del portal, el core, Moove ni el esquema de la base de datos. Las lecturas personales automáticas siguen disponibles; las restricciones afectan a la gestión de contenidos y al seguimiento de otras personas.

## Perfiles preparados

| Perfil | Consulta | Gestión y exportación |
| --- | --- | --- |
| Agente | Contenido asignado y sus propias notas/lecturas. | Sin publicación, estados ni informes de compañeros. |
| Coordinación | Lecturas e histórico Orange; notas e informes formativos solo en cursos donde se le asigne consulta. | Sin publicación, categorías, estados, impactos, matrícula de terceros, modificación de notas ni exportación por defecto. |
| Calidad | Lecturas e histórico de comunicados/incidencias Orange y contenido que reciba como participante. | Sin notas ajenas, gestión o exportación. Este es el alcance inicial conservador hasta aprobación del cliente. |
| Formación | Publicaciones, estados, impactos, recursos y seguimiento; matrícula de usuarios existentes según permisos del curso. | Conserva sus funciones. No crea cuentas ni administra globalmente el campus. Exportación del portal solo con permiso específico. |
| Administrador Orange | Usuarios, colectivos, contenidos e informes dentro de Orange. | Conserva la administración delegada; no recibe administración general. |
| Cliente | Pendiente de definición. | No se crea ni se amplía su acceso por suposición. |

Los permisos se suman. Una cuenta con Coordinación y Formación puede publicar por su rol de Formación. Los roles de consulta no usan **Prohibir** para bloquear otras funciones legítimas. Para garantizar una cuenta solo de consulta hay que retirar sus demás asignaciones de escritura en el ámbito correspondiente; ocultar botones no es suficiente.

## Repetir desde la página de Moodle

Estos pasos corresponden al administrador general, sin ejecutar scripts en el servidor:

1. Abrir **Administración del sitio > Usuarios > Permisos > Definir roles**. Crear o editar **Coordinación Orange** (`orange_seguimiento`), sin arquetipo, asignable en Sistema. Permitir únicamente las capacidades del portal `local/orangeportal:viewreports` y `local/orangeportal:viewallincidents`. Dejar sin conceder `manage`, `manageoptions`, `changestate`, `administer` y `exportreports` del componente `local/orangeportal`.
2. Crear **Calidad Orange** (`orange_calidad`) con las mismas dos capacidades del portal, sin permisos nativos para notas de terceros. Asignarlo en Sistema a sus cuentas, que también deben pertenecer a Usuarios Orange.
3. Crear **Consulta formativa Orange** (`orange_consulta_formativa`), sin arquetipo, asignable en Categoría y Curso. Permitir únicamente `moodle/course:view`, `moodle/course:viewparticipants`, `moodle/site:accessallgroups`, `moodle/grade:viewall`, `gradereport/grader:view`, `gradereport/user:view`, `mod/quiz:view`, `mod/quiz:viewreports`, `mod/feedback:view`, `mod/feedback:viewreports`, `report/completion:view` y `report/progress:view`.
4. En **Curso > Participantes > Permisos > Asignar roles**, sustituir para Coordinación el rol **Profesor sin edición** por **Consulta formativa Orange**, conservando su matrícula y sus otras pertenencias de participante. En la vista de participantes también se pueden administrar los roles de esa cuenta. No modificar la definición del Profesor sin edición estándar: afectaría a otros proyectos.
5. Asignar Consulta formativa únicamente en los cursos aprobados. No asignarlo en Sistema ni en categorías de otros proyectos. Si se utiliza una categoría Orange, recordar que se hereda en todas sus subcategorías y cursos. Calidad no recibe este rol mientras no se autorice acceso a notas.
6. Revisar **Comprobar permisos** en el curso y las asignaciones de la cuenta en sistema, categorías y cursos. Deben faltar `moodle/grade:edit`, `moodle/grade:override`, `moodle/grade:manage`, `mod/quiz:grade`, `mod/quiz:regrade`, `mod/feedback:edititems`, `moodle/course:manageactivities`, `enrol/manual:enrol` y permisos de exportación. El Profesor sin edición estándar incluye calificación; su nombre no garantiza consulta estricta.
7. Cerrar sesión y probar con cada cuenta. Desde Administración deben verse únicamente las herramientas de seguimiento autorizadas. Entrar directamente a opciones, publicaciones o exportación debe dar acceso denegado. Coordinación debe abrir el libro de notas sin campos de edición; Calidad no debe acceder a las notas de otros.

La exportación se puede conceder después, si el cliente lo aprueba, mediante `local/orangeportal:exportreports`. Los exportadores nativos de Moodle tienen permisos adicionales que se deben revisar por separado. No es necesario editar el código.

## Límites de alcance que siguen vigentes

Los paneles de lecturas e incidencias consultan Orange en general. Esta configuración no implementa una frontera por equipo de coordinador; si ese límite es obligatorio, no conceder consulta general hasta completar el desarrollo de autorización por colectivos. El acceso a notas sí depende de los cursos en los que se concede consulta. `moodle/site:accessallgroups` permite revisar los grupos de esos cursos sin matricular al coordinador en cada grupo; no se asigna globalmente. Si el seguimiento debe limitarse a un grupo, habrá que ajustar esa configuración y probar la autorización correspondiente.

La pantalla general puede mostrar el encabezado de Formación sin cursos/resultados para Calidad: no obtiene por ello acceso al libro de notas. Los agentes, Coordinación y Calidad pueden realizar las acciones personales permitidas por sus otras matrículas, como leer un comunicado o completar una actividad propia; eso no les permite modificar publicaciones o resultados de compañeros.

## Demo local y verificación

- `coordinador.orange`: consulta de lecturas/histórico y consulta formativa en los tres cursos/colecciones donde antes tenía Profesor sin edición.
- `calidad.orange`: cuenta nueva con `ORANGE_DEMO_PASSWORD` de `.env`, audiencia Orange y colectivo de demo para recibir contenido. Sin consulta de notas ajenas ni exportación.
- Formación y Administración Orange conservan sus cuentas y permisos; no se cambia la definición de los roles nativos compartidos ni los roles fuera de los cursos Orange.

`cli/configure_consultation_demo.php` reproduce la configuración mediante APIs nativas y guarda primero un respaldo privado de roles/capacidades/asignaciones en `moodledata/orange-role-backups/`. Está bloqueado fuera de localhost y no se ejecuta automáticamente al instalar o actualizar el plugin. El inicializador local de demo lo llama al terminar para no reintroducir el permiso de catálogos de Coordinación.

Para comprobar:

```bash
docker compose exec -T web php local/orangeportal/cli/check_consultation_roles.php
node scripts/test-orange-browser.mjs --consultation-roles-only
```

La prueba de permisos verifica también los rechazos reales al cambiar un estado o editar un impacto, y asigna temporalmente un segundo rol de Formación para comprobar la suma de permisos; revierte esas asignaciones al terminar. La prueba de navegador cubre los dos perfiles en español/inglés, las herramientas ocultas, los accesos directos denegados y el libro de notas sin edición.

Para replicar esta configuración con otras cuentas en cada ambiente, utilizar [Replicar perfiles y permisos Orange paso a paso](15-replicar-perfiles-y-permisos.md), con matriz de capacidades, XML nativos y pruebas de aceptación.
