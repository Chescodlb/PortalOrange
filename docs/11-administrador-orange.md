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

## Importar agentes desde CSV

Disponible en **Administración > Usuarios Orange > Importar agentes desde CSV**, exclusivamente para Administración Orange. Usa las APIs nativas de cuentas, cohortes y matrículas. El importador general de Moodle necesita permisos globales, por lo que este flujo aplica las restricciones del portal en el servidor.

1. Crea primero los colectivos de centro o equipo en **Colectivos Orange**, por ejemplo `Cristalia-Madrid` y `OC`.
2. Pulsa **Descargar plantilla CSV**. El icono circular **?** junto a Archivo explica los campos y límites. La fila de ejemplo tiene la contraseña vacía: sustituye todos sus datos antes de importar.
3. Guarda como **CSV UTF-8**. Cabeceras obligatorias: `username;firstname;lastname;email;password`. Opcionales: `idnumber;cohorts;courses`. No se admiten otras columnas.
4. Usa usuarios en minúsculas y correos únicos. Introduce una contraseña inicial por persona que cumpla la política de Moodle. `idnumber` es el CE o identificador interno, opcional.
5. En `cohorts`, escribe el nombre exacto o identificador de colectivos Orange existentes; separa varios con `|`, por ejemplo `Cristalia-Madrid|OC`. No escribas aquí roles ni la cohorte general de acceso: **Usuarios Orange se añade automáticamente**. Deja vacío si no requiere colectivos.
6. En `courses`, indica los **nombres cortos exactos** de las formaciones separados por `|`, por ejemplo `ORANGE-FORMACION-INICIAL|ORANGE-FORMACION-CONTINUA`. Puedes copiarlos del desplegable **Consultar nombres cortos de formaciones disponibles**, debajo de la plantilla. Deben existir en Formaciones Orange y tener **Matrícula manual** habilitada. Se matricula como Estudiante, con la duración predeterminada de esa instancia de matrícula; no utiliza el rol predeterminado si fuera distinto de Estudiante. Deja vacío si no quieres matrícula directa.
7. Sube el archivo, selecciona coma o punto y coma y pulsa **Validar y ver vista previa**. Límite: **1.000 personas y 2 MB**. Se valida todo el archivo y se muestran hasta 25 filas; si hay errores se muestran hasta 25 filas afectadas. En esta fase no se crea ninguna cuenta.
8. Corrige los errores en el CSV y vuelve a subirlo. Si está correcto, pulsa **Confirmar creación de agentes**. Se crean en lotes de 20 con un indicador de progreso. No vuelvas a subir el mismo archivo si se interrumpe: recarga para comprobar y continuar.
9. Comprueba una cuenta y su pertenencia a Orange/colectivos. Cada persona deberá cambiar su contraseña al entrar; no se envía correo automático. Las matrículas pueden provenir de las sincronizaciones de colectivos o de la columna `courses`, siempre como Estudiante dentro de Orange. Las matrículas directas son independientes: quitar un colectivo no elimina la matrícula manual. Para retirarla, usa Participantes de la formación con un usuario autorizado.

Archivo de ejemplo: [agentes-orange-ejemplo.csv](ejemplos/agentes-orange-ejemplo.csv). Incluye una formación, varias formaciones y ninguna matrícula. Los nombres, correos, contraseñas, colectivos y nombres cortos son ejemplos: sustitúyelos por los del ambiente destino. La formación continua del ejemplo debe existir para importarla; no se crean cursos ni colectivos desde este archivo.

Solo crea cuentas nuevas de agentes: no modifica usuarios existentes ni asigna roles de Formador, Coordinación, Calidad o Administración. Si un usuario/correo ya existe, lo rechaza. Los roles de gestión siguen asignándose desde Moodle general, y los permisos formativos en sus categorías/cursos. Los colectivos no conceden roles de gestión.

**Cancelación y privacidad:** la vista previa caduca a los 30 minutos de inactividad. Cancelar antes de iniciar no crea nada; después conserva los lotes completados. Cada lote se confirma o revierte completo. Si caduca después de una carga parcial, prepara otro archivo solo con las cuentas restantes. Las contraseñas no se muestran ni se incluyen en resultados o registros propios del importador: el CSV temporal se retira del área de borradores después de validarlo y los datos pendientes quedan cifrados en la sesión nativa hasta finalizar/cancelar/caducar. Protege el archivo original y los accesos al servidor. El cifrado utiliza las claves nativas de Moodle en `moodledata/secret`, compartidas entre nodos cuando corresponda; no se añaden tablas ni configuración de roles.

Para llevar esta funcionalidad a otro ambiente, actualiza **todo `local/orangeportal`** y purga las cachés nativas. No requiere cambios de esquema, importación de roles ni modificación del tema/core. Este procedimiento no instala el resto del portal desde cero: para eso sigue la guía de despliegue.

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

- `cli/check_agent_import.php`: prueba reversible local de CSV, límites, duplicados, permisos, colectivos, cifrado, lotes y caducidad.
- `node scripts/test-orange-browser.mjs --agent-import-only`: formulario, ayuda, idiomas, subida, vista previa y cancelación.
