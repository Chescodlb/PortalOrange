# Despliegue del portal Orange a preproducción

Guía del 22 de octubre de 2026 para añadir Orange a una **copia del campus existente**, conservando su tema, usuarios y configuración. La primera versión servirá para validación restringida; los pendientes funcionales y las entregas están en el [plan de desarrollo](12-plan-desarrollo-entregas.md).

## Versiones y dependencia de Moove

El entorno informado tiene Moodle **4.5.1 Build 20241209**, PHP **8.3.6**, MySQL **8.4.8** y las comprobaciones de entorno indicadas como correctas. Coincide con la versión base local de Moodle y el motor de base de datos. Sistemas debe confirmar también cron, HTTPS, permisos de archivos, autenticación y plugins instalados en esa copia.

Orange hereda de **Moove**, que hereda de Boost. Moove debe estar **instalado**, aunque el campus utilice otro tema. No se selecciona Moove ni Orange como tema general del campus. La dependencia probada localmente es Moove **4.5.1**, versión interna **2024100801**. Boost forma parte de Moodle. El tema Orange exige Moove; copiar solo `theme/orange` falla si falta su padre.

| Componente | Directorio destino relativo a Moodle | Versión interna de esta entrega |
| --- | --- | --- |
| Moove, dependencia sin personalizar | `theme/moove/` | `2024100801`, release 4.5.1 |
| Portal Orange | `local/orangeportal/` | `2026102003`, release 0.1.0 |
| Tema hijo Orange | `theme/orange/` | `2026101900`, release 0.2.0 |

Las releases propias siguen declaradas como alfa en el código. Son una versión de revisión, no una certificación de compatibilidad con todas las configuraciones del campus. Si Moove ya existe, comprobar su versión antes de copiar: no sobrescribir ni degradar una instalación más reciente o personalizada. En ese caso probar primero la compatibilidad del hijo con esa versión.

## Qué entregar a Sistemas

La carpeta local `backups/preproduccion-20261022/` contiene el paquete de esta revisión y queda excluida de Git porque incluye la estructura documental del cliente.

| Archivo o recurso | Uso |
| --- | --- |
| `codigo-orange.zip` | Código en rutas `theme/moove`, `theme/orange` y `local/orangeportal`. Revisar Moove existente antes de extraer. Incluye el cargador de estructura. |
| `componentes/*.zip` | Los mismos tres componentes en ZIP individuales para la alternativa del instalador web de Moodle. No instalar también otra copia del paquete combinado. |
| `catalogos-orange.json` | Árbol portable de Biblioteca/Formaciones, categorías de avisos y catálogos de estados/impactos. Los IDs del origen solo sirven para reconstruir padres. |
| `categorias-orange.csv` | Inventario legible con rutas, padres y visibilidad. Es para revisión, no un CSV que Moodle importe automáticamente. |
| `MANIFEST.sha256` | Verificar que los archivos recibidos coinciden con los preparados. |
| Copias de las guías 12, 13 y 14 | Plan de entregas, despliegue y configuración de perfiles de consulta. |
| Logo propio opcional | El tema ya incluye un logo de respaldo. Si el cliente quiere otro, cargarlo por el ajuste de logo de Orange. |

No subir `.env`, `compose.yaml`, `config.php` local, una base de datos local, `moodledata` local, contraseñas de demo ni toda la carpeta Moodle. Tampoco sustituir el tema actual del campus ni sus archivos. La estructura JSON no contiene usuarios, contraseñas, documentos, cursos, calificaciones ni lecturas.

El paquete contiene el código actual, no un commit aprobado ni un despliegue ejecutado. Conservar su manifiesto junto al número de revisión que se apruebe. Los próximos cambios necesitarán un paquete y manifiesto nuevos.

## Antes de instalar

1. Sistemas confirma la URL y rutas reales de Moodle y `moodledata`, usuario de ejecución de PHP, versión instalada de cada plugin y tema general actual. Esta guía no presupone Docker en el servidor.
2. Hacer un respaldo consistente de **base de datos, código y moodledata**, y registrar la configuración de tema/portada/autenticación/cohortes. Es el punto de reversión de la copia del campus.
3. En pre, revisar correo, tareas programadas e integraciones heredadas para que las pruebas no envíen mensajes de producción. Conservar el cron que Moodle necesita para matrículas y finalización, adaptado al entorno de pre.
4. Probar con una cuenta del campus ajena a Orange y guardar una captura de su navegación y tema antes de instalar.
5. Revisar si ya existe `local_orangeportal`, si tiene datos o si sus raíces se comparten con otros proyectos. El cargador incluido es para una **primera carga en raíces vacías**; no sirve para fusionar instalaciones existentes.

## Instalar el código

Con el procedimiento habitual de Sistemas, habilitar mantenimiento durante la instalación y ejecutar los comandos como el usuario del servicio web, desde la raíz Moodle. Si se prefiere la interfaz, instalar Moove, después el plugin local y finalmente Orange mediante el instalador de plugins y **Administración del sitio > Notificaciones**, revisando dependencias.

```bash
php admin/cli/maintenance.php --enable
# Copiar los tres directorios revisados al código de esta copia de pre.
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

Si la actualización falla, mantener mantenimiento mientras se resuelve o restaura el respaldo. No desinstalar el plugin como método de reversión: la desinstalación puede eliminar sus tablas.

El código y dependencias se instalan en el servidor; no hace falta trasladar la instalación limpia local. No ejecutar `configure_demo.php`, `isolate_demo.php`, `configure_admin_demo.php`, `configure_consultation_demo.php`, `configure-orange-local.sh`, `init-fresh.sh` ni los inicializadores de ejemplo sobre el campus. Están pensados para localhost y algunos cambian configuración general.

## Preparar las raíces y cargar estructura

### Crear los dos contenedores desde Moodle

Como administrador general, abrir **Administración del sitio > Cursos > Administrar cursos y categorías > Crear nueva categoría**:

| Nombre | Categoría superior | Número ID requerido por el cargador |
| --- | --- | --- |
| Biblioteca Orange | Una ubicación exclusiva y acordada del campus | `orange_pre_library` |
| Formaciones Orange | Una ubicación exclusiva y acordada del campus | `orange_pre_training` |

Ambas deben estar vacías y fuera de las categorías de otros proyectos. Anotar sus IDs numéricos desde la URL al abrirlas. Los IDs de local (2 y 4) **no deben copiarse** como configuración de pre. El nombre del contenedor puede adaptarse al cliente sin cambiar su finalidad.

### Ejecutar una única carga inicial

Guardar `catalogos-orange.json` **fuera del directorio web**, en una ruta legible por el usuario del servicio. Sustituir los marcadores por los IDs reales anteriores. Ejecutar primero sin `--apply`:

```bash
php local/orangeportal/cli/import_preproduction.php \
  --file=/ruta/privada/catalogos-orange.json \
  --library-root-id=ID_BIBLIOTECA \
  --training-root-id=ID_FORMACIONES
```

La simulación comprueba raíces vacías y sus Números ID, jerarquías, colisiones de identificadores y ausencia de categorías/publicaciones/catálogos ya configurados del plugin. No escribe datos. Su resultado esperado para esta copia es:

- 167 subcategorías nuevas dentro de Biblioteca; sumando el contenedor, 168 categorías.
- Dos subcategorías de Formaciones: Formación inicial y Formación continua.
- Cinco categorías de Incidencias y una de Comunicados, conservando sus jerarquías.
- Estados e impactos con sus identificadores, nombres y activación actuales.

Tras revisar el resultado, repetir **el mismo comando** añadiendo `--apply`. Utiliza la API nativa de categorías de Moodle, remapea IDs y guarda los catálogos en la configuración del plugin. No crea cuentas, no activa el portal, no asigna temas, no matricula ni toca cursos/documentos. La creación sigue el orden exportado dentro de cada padre.

La carga escribe en una transacción y rechaza una segunda ejecución sobre estructura no vacía. Conserva los Números ID existentes en el origen; si alguno estaba vacío, asigna `orange_pre_category_` seguido de su ID de origen, únicamente como identificador técnico del nuevo nodo. No es una herramienta de sincronización ni actualiza categorías existentes. Si la instalación ya contiene datos Orange, detenerse y preparar una migración específica; no vaciar datos para forzar la carga. Tras cargar, purgar cachés y verificar el inventario en la web.

### Revisar los catálogos desde el portal

La copia conserva **Abierta**, **En seguimiento** y **Cerrada**. En seguimiento está **inactivo** en el origen. Antes de aceptar la versión, el responsable puede activarlo en **Administración Orange > Estados e impactos > Estados > Editar**, manteniendo su código. Cerrada debe conservar su clasificación de cierre; no sustituirla por un estado nuevo con nombre parecido.

Los impactos actuales son **Normal, Alto, Crítico y Bajo**. Confirmar si se mantiene Bajo. El código `critical` es el que activa campana/triángulo rojos; cambiar su nombre no cambia el comportamiento. Crear otro impacto o categoría llamado Crítico no reproduce ese identificador.

La rama CENTREX solo contiene su nodo inicial. El paquete no inventa sus procesos. Las categorías actuales de Incidencias son Red móvil, Sistemas, Sistemas > Centrex > Alta > Sin Terminal; Comunicados contiene Comunicado. Se pueden editar y ampliar desde **Gestionar categorías**. Eliminar requiere que estén vacías; no se eliminan categorías con contenido o subcategorías.

## Configurar el aislamiento Orange desde la página

1. Mantener el tema y la página de inicio generales del campus. **No seleccionar Orange como tema predeterminado del sitio.**
2. En **Apariencia > Temas > Ajustes de temas**, habilitar **Permitir temas de cohortes**. Antes revisar si existen cohortes con temas guardados que estuviesen deshabilitados globalmente, porque habilitar esta opción también las activa. Un mismo usuario con temas de cohorte distintos puede no recibir el esperado.
3. En **Usuarios > Cuentas > Cohortes**, crear una cohorte de **sistema** llamada Usuarios Orange, con identificador `orange_portal_users`. Seleccionar **Forzar tema: Orange**. Al principio no añadir usuarios del campus en bloque.
4. Abrir **Administración > Portal Orange > Configuración Orange** y seleccionar esa cohorte como audiencia; configurar los IDs reales de Biblioteca y Formaciones; elegir Biblioteca como entrada. Los dos contenedores deben ser diferentes y exclusivos.
5. Revisar **Alertas automáticas de formación**. Activarlas si se van a validar notas/finalización; generan Comunicados personales en Registro, sin integración con Teams.
6. Habilitar el portal. Con la cohorte vacía no se cambia la experiencia de ningún usuario ordinario.
7. En **Apariencia > Temas > Orange**, cargar el logo si se quiere sustituir el de respaldo. Los ajustes de Orange son independientes de los del tema general.
8. Cerrar sesión y volver a entrar después de modificar temas/pertenencias. Verificar una sesión Orange y otra ajena en navegadores separados.

Las raíces, audiencia, entrada y alertas se guardan en `config_plugins`; la pertenencia y tema forzado se guardan con las cohortes nativas. La apariencia no depende del prefijo del usuario ni de su correo. Tener un rol Orange no sustituye pertenecer a la cohorte.

## Crear el primer administrador Orange por la web

Estos pasos los realiza el administrador general del campus. No se incluye una contraseña reutilizable en el paquete.

1. En **Usuarios > Cuentas > Agregar un usuario**, crear una cuenta exclusiva, por ejemplo `org.admin.pre`, con nombre, apellidos, correo de validación y autenticación manual o el mecanismo aprobado por Sistemas. Establecer contraseña individual y cambio obligatorio inicial si corresponde.
2. Añadirla a **Usuarios Orange**. Debe haberse configurado y habilitado el portal antes de asignar su rol para que se sincronicen los ámbitos de contenido.
3. En **Usuarios > Permisos > Asignar roles del sistema**, elegir **Administrador de Orange** y asignar la cuenta. La instalación del plugin crea este rol, **Administración de contenidos Orange** y **Cuenta exclusiva Orange (sin permisos)**.
4. No asignar Administrador del sitio ni Gestor global. El observador del plugin asigna Administración de contenidos Orange únicamente en las dos raíces configuradas.
5. Iniciar sesión con esta cuenta y verificar **Administración > Usuarios Orange / Colectivos Orange**, gestión de contenidos e informes. El acceso a la administración general debe estar denegado.
6. Si se cambian las raíces después, retirar y volver a asignar el rol para reconstruir los ámbitos automáticos. No asignar manualmente el rol técnico Cuenta exclusiva Orange: lo usa el flujo de altas del plugin.

Los permisos se suman: una cuenta que ya sea administradora global no se vuelve restringida por añadirle este rol. Usar una cuenta nueva para validar la delegación.

## Crear colectivos y agentes desde el portal

Con el administrador Orange:

1. En **Colectivos Orange > Crear colectivo**, crear OC, GP y los colectivos de coordinación/centro/localidad que se acuerden. Son cohortes nativas gestionables por el portal, no categorías de Biblioteca.
2. En **Usuarios Orange > Crear usuario Orange**, introducir usuario, contraseña inicial, nombre, apellidos, correo y sus colectivos. Las cuentas creadas por este flujo tienen autenticación manual y cambio de contraseña obligatorio al primer acceso; no se envía automáticamente la contraseña por correo.
3. La cuenta se incorpora a Usuarios Orange, pero no recibe un perfil privilegiado ni acceso automático a todos los cursos/documentos. Para ello configurar las matrículas y restricciones.
4. Crear primero las colecciones/cursos de validación. En cada colectivo, seleccionar las colecciones/formaciones donde debe existir sincronización con rol Estudiante. Alternativamente usar **Participantes > Métodos de matrícula > Sincronización de cohorte** desde el curso con un responsable autorizado.
5. Para una excepción individual, Formación usa **Curso > Participantes > Matricular usuarios**, con una cuenta existente y rol Estudiante. No crea cuentas desde ese diálogo.
6. Para cambiar de colectivo, editar pertenencias en Usuarios Orange. Comprobar el efecto sobre matrículas sincronizadas y conservar los demás colectivos necesarios. Una matrícula manual independiente no se retira automáticamente por quitar una cohorte.

Las cuentas preexistentes del campus se incorporan inicialmente a Usuarios Orange por administración general. Sus datos y credenciales compartidos no se pueden editar libremente desde el delegado. Para reutilizar cohortes existentes, administración general debe seleccionarlas en **Colectivos existentes delegados a Orange**; solo se permiten si todos sus miembros son Orange y no sincronizan cursos de otros proyectos. No delegar cohortes corporativas por su nombre o prefijo.

## Crear los demás perfiles desde Moodle

La instalación inicial **no crea todos los roles de la demo**. Configurarlos en **Usuarios > Permisos > Definir roles > Agregar un nuevo rol**. El administrador general crea/asigna los perfiles privilegiados; el administrador Orange mantiene cuentas y colectivos, pero no reparte roles globales.

### Capacidades globales del portal

Crear roles sin arquetipo o a partir de un rol vacío. Habilitar contexto Sistema. Buscar las capacidades siguientes por su identificador y marcar **Permitir** solo donde se indica. El resto queda sin conceder; no utilizar Prohibir como sustituto de una revisión de roles acumulados.

| Capacidad | Formación | Coordinación | Calidad | Cliente |
| --- | --- | --- | --- | --- |
| `local/orangeportal:manage` | Sí | No | No | No |
| `local/orangeportal:changestate` | Sí | No | No | No |
| `local/orangeportal:viewallincidents` | Sí | Sí, con alcance aprobado | Sí, con alcance aprobado | Solo si se autoriza |
| `local/orangeportal:viewreports` | Sí | Sí, con alcance aprobado | Solo si se aprueba el acceso del panel | Solo si se aprueba |
| `local/orangeportal:manageoptions` | Sí | No | No | No |
| `local/orangeportal:exportreports` | Según autorización | Según autorización | No por defecto | No por defecto |
| `local/orangeportal:administer` | No | No | No | No |

Usar nombres como **Formador Orange** (`orange_publicador`) y **Coordinación Orange** (`orange_seguimiento`). Crear **Calidad Orange** (`orange_calidad`) con el alcance inicial de consulta de la guía 14; mantener Cliente pendiente de aprobación. Asignar estos roles en **Asignar roles del sistema** a cuentas ya pertenecientes a Usuarios Orange. El agente no necesita un rol global propio del plugin.

El demo actual de Coordinación ya no tiene `manageoptions` ni Profesor sin edición. Si se recupera una copia antigua, corregir ambos accesos. Si se reutiliza un rol existente, revisar su definición y todas las asignaciones de la cuenta. Un usuario con Coordinación más Formación conserva escritura por su rol de Formación.

### Permisos formativos y documentales en sus categorías

Los permisos globales anteriores no permiten por sí solos crear cursos, editar documentos o consultar notas. Para Formación, crear un rol de **contenidos Orange** basado en Profesor con edición, con asignación permitida en Categoría, y añadir `moodle/course:create` y `moodle/category:manage`. Asignarlo en las dos raíces Orange desde **Administrar cursos y categorías > seleccionar raíz > Permisos > Asignar roles**, nunca en el Sistema. Si solo debe gestionar algunos cursos, asignarlo dentro de esos cursos y reservar creación/categorías a los responsables necesarios.

Revisar las capacidades de matrícula manual del rol (`enrol/manual:enrol`, y las necesarias para mantener sus matrículas), `moodle/role:assign` y la matriz **Permitir asignación de roles** para que pueda matricular como Estudiante, sin asignar perfiles administrativos. Habilitar el método Matrícula manual en esos cursos. Validar que el selector de participantes del campus no amplíe indebidamente el alcance a cuentas ajenas a Orange; el portal restringe sus destinatarios, pero no sustituye todas las pantallas nativas de matrícula del campus.

Ver [los pasos y la lista exacta del rol de consulta](14-perfiles-consulta.md). Para Coordinación, Calidad y Cliente no basta con asignar el Profesor sin edición estándar: puede incluir calificación y otras escrituras. Crear un rol propio **Consulta formativa Orange** sin permisos de escritura, asignado solo en los cursos aprobados, con los permisos de consulta que correspondan: `moodle/course:view`, `moodle/grade:viewall`, `gradereport/grader:view`, `report/completion:view`, `mod/quiz:viewreports` y `mod/feedback:viewreports`. No conceder notas/encuestas a Calidad o Cliente hasta definir su alcance. Usar **Comprobar permisos** en el contexto del curso y pruebas con la cuenta real antes de habilitarlo.

Actualmente el seguimiento del plugin permite consulta general Orange, no una frontera completa de equipo por coordinador. Si el requisito es ver solo el propio equipo, no conceder todavía los permisos generales de informes a ese perfil: completar E3 del plan y validar la autorización antes. La exportación del portal requiere capacidad explícita además de consulta; los informes/exportadores nativos de Moodle tienen sus propios permisos y también deben revisarse.

## Publicar contenidos y validar la versión

Formación crea una colección en Biblioteca y añade Archivo, Página, Libro, Carpeta o URL; crea una formación dentro de Inicial/Continua y añade Cuestionario y Encuesta/Feedback. Para HTML interactivo, usar Página y comprobar que el editor conserva el iframe de Genially sin `sandbox` vacío. Repetir la comprobación en pre con HTTPS y las políticas de seguridad reales. No trasladar los recursos de la demo como contenido definitivo.

Las restricciones de Biblioteca y cursos dependen de matrícula, visibilidad y restricciones de acceso nativas; ver [Biblioteca](04-biblioteca.md) y [Formación](05-cursos-y-encuestas.md). Abrir un recurso indica lectura, no demuestra que se haya leído hasta el final ni que se haya completado una formación.

| Prueba de aceptación de E1 | Resultado requerido |
| --- | --- |
| Cuenta externa, antes/después | Mismo tema, navegación y portada del campus; sin acceso directo al portal Orange. |
| Agente Orange | Entrada a Biblioteca; cuatro enlaces principales y cuatro indicadores; Calendario/Archivos privados ocultos en su menú. |
| Árbol y búsqueda | RPV con sus niveles, CENTREX como rama todavía vacía, ruta y selección naranja; solo recursos accesibles. |
| Comunicado normal y crítico | Asignación a colectivo/individuo; campana roja solo con críticas personales sin leer; cifra crítica y total en ayuda; filtro correcto. |
| Incidencia normal y crítica | Triángulo y filtros equivalentes; lectura no cambia estado; agente no puede modificarlo ni por URL/POST. |
| Cambio de versión | Cambiar título/contenido/impacto vuelve a solicitar lectura; historial anterior conservado. |
| Formación y resultados | Test/encuesta asignados; agente solo ve sus notas; responsable consulta únicamente lo autorizado. |
| Administrador Orange | Gestiona cuentas/colectivos/contenido Orange y no administración global ni usuarios externos. |
| Coordinación y Calidad | Sin botones ni permisos de escritura, tampoco al acceder directamente; acceso a informes según alcance aceptado. |
| Móvil, ES y EN | Menú utilizable; columnas/listados accesibles; sin etiquetas `[[...]]`; registrar textos fijos aún pendientes. |
| Genially | Contenido termina de cargar y conserva interacción en pre; comprobar tamaño, móvil y políticas del navegador. |

Crear usuarios de validación con contraseñas distintas y un usuario externo real de prueba. No ejecutar automáticamente los `cli/check_*` de localhost sobre el campus: varios esperan IDs, datos o usuarios de la demo. En local se puede comprobar la carga inicial con datos temporales revertidos. Guardar resultados y capturas de pre con los perfiles utilizados y los límites aceptados.

## Operación, datos y reversión

Biblioteca/Formaciones usan categorías, cursos, recursos, usuarios, cohortes y matrículas nativos; los archivos se guardan mediante Moodle en `moodledata`. El plugin guarda publicaciones, categorías de avisos, destinatarios, versiones, lecturas y cambios de estado en sus tablas `local_orange_*`. Los estados e impactos editables están en `config_plugins` del componente `local_orangeportal`, claves `notice_states` y `notice_impacts`. Instalar código no transporta automáticamente ninguno de esos datos.

Para desactivar el acceso Orange sin borrar datos, deshabilitar el portal en su configuración. Para retirar también el aspecto Orange, quitar el tema forzado de su cohorte o retirar la pertenencia correspondiente y volver a iniciar sesión. Esto no devuelve la base de datos al estado previo ni desinstala componentes.

Si hace falta revertir instalación/esquema, poner mantenimiento y restaurar **juntos** base de datos, código y moodledata del punto previo bajo control de Sistemas; revisar integraciones y purgar cachés antes de reabrir. No basta con borrar el directorio del plugin después de ejecutar una actualización. Los contenidos creados desde el respaldo se perderían en una restauración completa: acordar la ventana de pruebas y el punto de recuperación.

Las actualizaciones futuras se realizan con nuevas versiones de `local_orangeportal` y `theme_orange`, más la dependencia Moove cuando corresponda, siguiendo respaldo, actualización y pruebas. Los datos se conservan en la base del servidor. No volver a ejecutar el cargador de estructura ni los inicializadores de demo para actualizar.

La versión revisada separa **Seguimiento** mediante un selector de Incidencias, Comunicados, Formación y Usuarios, con porcentajes, periodos de lectura de 7/30 días/histórico, fechas de versión descendentes, ficha individual y 25 filas por página. La nueva vista conserva los permisos de informes actuales y los permisos formativos nativos. No requiere migración de esquema. El CE se toma de Número de ID y los centros se configuran como colectivos/cohortes Orange; la consulta filtra por pertenencia, sin usar Institución. Se retira la confirmación manual; se conservan sus datos históricos. Los CSV de lecturas respetan los filtros de su bloque. Ver [guía de informes](09-informes.md).

Los paquetes ZIP generados antes de esta ampliación de Seguimiento no la incluyen. Antes de entregar código a Sistemas, regenerar el paquete desde el estado revisado del repositorio y actualizar su manifiesto de integridad. No mezclar archivos de versiones de la página de informes.

Para replicar esta configuración con otras cuentas en cada ambiente, utilizar [Replicar perfiles y permisos Orange paso a paso](15-replicar-perfiles-y-permisos.md), con matriz de capacidades, XML nativos y pruebas de aceptación.
