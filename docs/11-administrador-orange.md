# Administrador delegado de Orange · 20/10/2026

Administración mantiene usuarios y colectivos; Formación publica contenido y matricula usuarios existentes. Crear usuarios no se concede al Formador. El perfil **Administrador de Orange** no es administrador del sitio ni Gestor global de Moodle.

## Por qué hay una pantalla propia

Los roles y contextos nativos permiten gestionar las dos categorías raíz de contenido. Sin embargo, `moodle/user:create` y `moodle/user:update` se comprueban globalmente; concederlos no limita las cuentas por cohorte. Por eso el portal presenta **Administración > Usuarios Orange / Colectivos Orange**, con capacidad propia y comprobaciones en cada operación. Usa APIs de Moodle para las cuentas, roles, cohortes y matrículas; no replica esas entidades.

## Activar el perfil desde Moodle

1. Instalar/actualizar `local_orangeportal` a `2026102003` o posterior mediante **Administración del sitio > Notificaciones** o el procedimiento habitual de actualización. No hay cambios en tablas del core.
2. Verificar que Biblioteca, Formaciones y la cohorte que habilita Orange están correctamente configuradas en **Plugins locales > Portal Orange**. Las categorías deben ser exclusivas de Orange, no raíces compartidas de varios proyectos.
3. Desde administración general, incorporar la cuenta a **Usuarios Orange**.
4. En **Usuarios > Permisos > Asignar roles del sistema**, asignar **Administrador de Orange**. No asignar Administrador del sitio ni Gestor global. El observador del plugin asigna automáticamente **Administración de contenidos Orange** en las dos raíces configuradas. Retirar el rol o la pertenencia al portal retira esas asignaciones automáticas.
5. Si se cambian las raíces en el futuro, retirar y volver a asignar el rol para reconstruir sus dos ámbitos. Las asignaciones manuales de roles ajenas al plugin no se eliminan.
6. Para reutilizar colectivos nativos ya existentes, el administrador general los selecciona en **Colectivos existentes delegados a Orange**. Solo se habilitan si no pertenecen a un componente externo, todos sus miembros pertenecen a Orange y sus sincronizaciones no apuntan a cursos de otros proyectos. No se reconocen por nombre ni por prefijo.

Los permisos se suman con los demás roles de Moodle: asignar este perfil a un administrador general existente no reduce sus privilegios. Usar una cuenta separada para probar la delegación.

## Crear y mantener usuarios

**Administración > Usuarios Orange > Crear usuario Orange** permite introducir usuario, nombre, apellidos, correo, contraseña inicial y colectivos. El nombre de usuario y correo deben estar disponibles; la contraseña cumple la política nativa. La nueva cuenta utiliza autenticación manual y debe cambiar la contraseña al iniciar sesión por primera vez. No se envía correo automático con la contraseña.

La cuenta se añade a Usuarios Orange. Pertenecer al portal y pertenecer a sus colectivos son decisiones distintas; las matrículas dependen de los métodos de cada formación/colección. Los nuevos usuarios no reciben permisos de Formador ni de Administración. Los perfiles privilegiados siguen asignándose por administración general desde Moodle.

En el listado se pueden buscar usuarios y abrir **Editar** para cambiar colectivos. Solo las cuentas creadas desde este flujo, y que no se hayan reutilizado fuera de Orange, permiten actualizar nombre, apellidos y correo desde el portal. Las cuentas preexistentes/compartidas conservan sus datos y credenciales globales. La contraseña se recupera por los mecanismos nativos o desde administración general; el portal no cambia autenticación ni nombres de usuario de cuentas existentes.

**Retirar acceso Orange** exige confirmación y POST con `sesskey`; quita la pertenencia al portal y a sus colectivos gestionables. No borra ni suspende la cuenta global ni altera sus pertenencias a otros proyectos. Las matrículas por cohortes se actualizan mediante los observadores nativos. Las matrículas manuales independientes siguen siendo responsabilidad del curso. Restaurar una cuenta retirada se hace desde administración general, añadiéndola a Usuarios Orange y comprobando sus colectivos/matrículas.

Los administradores Orange y del sitio están protegidos: su cuenta y su acceso se mantienen desde administración general, evitando cambios sobre otro administrador o dejarse sin acceso desde esta pantalla.

## Colectivos y matrículas

**Administración > Colectivos Orange > Crear colectivo** crea una cohorte nativa identificada con `component=local_orangeportal`. Los miembros se mantienen desde Usuarios Orange. También permite renombrar colectivos, consultar sus miembros y borrar los vacíos. No se permite eliminar si tiene miembros, publicaciones asignadas o sincronizaciones de matrícula.

Al crear/editar un colectivo se pueden **añadir** sincronizaciones en colecciones y formaciones Orange con rol Estudiante. La selección no retira sincronizaciones anteriores. Para retirar una, administración general usa **Curso > Participantes > Métodos de matrícula**, revisando el efecto sobre sus miembros. El administrador delegado no recibe capacidades nativas generales de cohortes ni de matrícula manual; mantiene las pertenencias y altas de sincronización mediante estos controles limitados.

Los grupos internos de cursos siguen siendo objetos nativos de Moodle y dependen del contexto de cada curso. Los destinatarios de publicaciones ofrecen usuarios Orange, la cohorte general, los colectivos delegados válidos y grupos de las dos raíces. El backend rechaza IDs externos aunque se manipule el formulario.

## Contenidos e informes

El perfil puede sustituir a Formación: crear colecciones/formaciones, editar recursos en ambas raíces, gestionar categorías, publicar comunicados e incidencias, elegir destinatarios, cambiar estados y consultar seguimiento. El menú y la configuración general del tema no son editables desde este perfil.

Las capacidades de contenido se basan en Profesor con edición, asignadas únicamente en las dos categorías Orange. No se asignan al sistema. Las capacidades propias del portal (`manage`, `viewreports`, `viewallincidents`, `changestate`, `manageoptions`) no conceden administración global.

Puede exportar CSV de usuarios Orange, lecturas de publicaciones y formación. Se requiere `local/orangeportal:exportreports`, además de los permisos de consulta correspondientes. Las notas se limitan a los cursos Orange donde puede consultar calificaciones, matrículas activas y resultados visibles; no se exportan contraseñas ni datos de otras cuentas. Los historiales de publicaciones mantienen los destinatarios y responsables que participaron en Orange, aunque después dejen el colectivo.

La exportación del portal no sustituye los informes definitivos aún pendientes con el cliente; es un formato básico de consulta con usuario, curso, nota total y finalización, o publicación y fechas de recepción/lectura/confirmación.

## Almacenamiento y actualizaciones

No se añade ninguna tabla. Se utilizan `user`, `user_preferences`, `context`, `role`, `role_capabilities`, `role_assignments`, `cohort`, `cohort_members` y las tablas nativas de matrícula. Las cuentas propias se identifican mediante un rol sin permisos, **Cuenta exclusiva Orange**, asignado únicamente al contexto personal por el componente del plugin; no se confía en un nombre de usuario o una preferencia editable. El resto de configuraciones está en `config_plugins`.

Los informes reutilizan las tablas de notas/finalización y los registros de lectura existentes. Moodle gestiona los eventos y las herramientas de privacidad de las entidades nativas. El tema Orange y el plugin local conservan las personalizaciones fuera del core y de Moove.

## Demo y pruebas

- Cuenta local: **admin.orange**, con `ORANGE_DEMO_PASSWORD` de `.env`. Es una cuenta nueva; las otras conservan sus perfiles.
- `cli/configure_admin_demo.php` requiere esa variable de entorno y solo admite localhost. Prepara la cuenta y delega explícitamente la cohorte nativa de demo `ORANGE-EQUIPO-DEMO`. No ejecutarlo en el campus real.
- `docker compose exec -T web php local/orangeportal/cli/check_orange_admin.php`: prueba reversible de alta nativa, protección de cuentas, aislamiento de IDs, colectivos, matrícula y permisos de Formador/externos.
- `node scripts/test-orange-browser.mjs --orange-admin-only`: navegación, formularios, acceso denegado a administración general, CSV, idioma y móvil.
- Copia local previa: `backups/before-orange-admin-20261020.sql`, excluida de Git.
