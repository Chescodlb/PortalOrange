# Replicar perfiles y permisos Orange paso a paso

Guía para administración y despliegues, revisada el **22 de octubre de 2026**, con Moodle **4.5.1** y el código actual del portal. Permite configurar desarrollo, preproducción y producción desde la web, aunque las personas y sus nombres de usuario sean distintos. La configuración la realiza un administrador general de Moodle; después Administración Orange mantiene sus usuarios y colectivos desde el portal.

**Para empezar:** instalar los componentes y configurar las dos raíces y la cohorte según [Despliegue a preproducción](13-despliegue-preproduccion.md). Después seguir los pasos 1 a 7 de esta guía. No ejecutar inicializadores de la demo en el campus.

## Conceptos que hay que distinguir

| Concepto | Qué significa | Ejemplo |
| --- | --- | --- |
| Cuenta | La persona que inicia sesión. | Una cuenta corporativa existente. No necesita llamarse `formador.orange`. |
| Perfil de trabajo | La responsabilidad acordada con el cliente. Puede necesitar varios roles técnicos. | Formación, Coordinación o Calidad. |
| Rol | Un conjunto de permisos guardado en Moodle. | Formador Orange o Consulta formativa Orange. |
| Permiso o capacidad | Una acción concreta que Moodle comprueba. El código entre comillas invertidas permite localizarla sin depender del idioma. | `local/orangeportal:viewreports` permite entrar a Seguimiento. |
| Lugar de asignación o contexto | Dónde se aplica el rol. Se hereda hacia abajo. | Sistema, categoría Biblioteca o un curso concreto. |
| Colectivo | Una cohorte nativa que agrupa destinatarios. No concede por sí sola permisos de edición. | Cristalia-Madrid, Centro-Valencia, OC o GP. |
| Matrícula | La relación que permite al participante acceder a un curso o colección, sujeta a visibilidad y restricciones del recurso. | Estudiante matriculado por sincronización de cohorte. |

**Ejemplo:** una persona pertenece a Usuarios Orange y a Cristalia-Madrid. Eso habilita la experiencia Orange y sus destinatarios. Si además recibe Formador Orange en Sistema, podrá gestionar publicaciones; para editar documentos y formaciones necesita los roles nativos de contenido en las categorías/cursos correspondientes.

En los ejemplos de la demo, **Responsable Comunicaciones es el nombre de una cuenta de prueba**, no un perfil independiente con permiso exclusivo para comunicados. Esa cuenta utiliza Formador Orange para las publicaciones. Los nombres de las cuentas no se utilizan para decidir sus permisos.

## Qué permiso permite ver o hacer cada cosa

Todos los accesos del plugin requieren pertenecer a la **cohorte configurada como audiencia Orange**, con el portal habilitado. Los permisos siguientes se comprueban en **Sistema**, salvo donde se indica categoría o curso.

| Quiero permitir | Permiso necesario | Qué aparece o permite | Qué no concede por sí solo |
| --- | --- | --- | --- |
| Gestionar usuarios y colectivos Orange | `local/orangeportal:administer` | Usuarios Orange y Colectivos Orange; altas y mantenimiento limitados al portal. | Administración general del campus; asignar perfiles privilegiados; editar credenciales de cuentas compartidas. |
| Gestionar publicaciones | `local/orangeportal:manage` | Gestión de Incidencias y Comunicados; publicación, edición, destinatarios y categorías de ambos tipos. | Edición de documentos/cursos nativos, cambio de estados sin su permiso adicional o seguimiento de resultados. |
| Cambiar estados de incidencias | `local/orangeportal:changestate` | Cambio del estado común y sus datos de cierre. | Publicar incidencias nuevas ni abrir herramientas de gestión sin `manage`. |
| Ver incidencias abiertas y cerradas y su seguimiento específico | `local/orangeportal:viewallincidents` | Histórico/seguimiento específico de incidencias y consulta ampliada. | Cambiar estados, publicar o consultar notas. |
| Abrir Seguimiento | `local/orangeportal:viewreports` | Selector Incidencias, Comunicados, Formación y Usuarios; porcentajes, pendientes y lecturas nominales. | Notas o intentos sin sus permisos nativos; edición; exportación. |
| Gestionar Estados e Impactos | `local/orangeportal:manageoptions` | Herramienta Estados e impactos del portal. | Publicación, notas ni configuración general de Moodle. |
| Exportar CSV del portal | `local/orangeportal:exportreports` **y** `viewreports` | Exportación de lecturas y formación; esta última exige además acceso a las notas de cada curso. Usuarios exige también `administer`. | Exportación nativa de Moodle, que tiene permisos independientes. No crea un CSV nuevo del directorio estadístico de Usuarios. |
| Gestionar categorías de Biblioteca o Formaciones | `moodle/category:manage` en la categoría correspondiente | Crear, editar y eliminar categorías vacías de esa rama desde el portal. | Crear cursos/colecciones o editar sus recursos. |
| Crear una colección o formación | `moodle/course:create` en su categoría | Crear nueva colección documental / nuevo curso. | Editar automáticamente todos los contenidos existentes. |
| Editar documentos, actividades y evaluaciones existentes | Profesor con permiso de edición o rol equivalente en curso/categoría Orange | Edición nativa de los recursos y actividades autorizados. | Administración general, altas de cuentas ni permisos globales de publicaciones. |
| Ver seguimiento formativo en la ficha de un usuario | `moodle/course:viewparticipants` **y** `report/completion:view` **o** `report/progress:view`, en un curso accesible al consultante | Matrículas activas, situación y finalización registradas en ese curso. | Notas e intentos sin los permisos siguientes. La condición es Participantes Y (Finalización O Progreso). |
| Ver notas ajenas | `moodle/grade:viewall` en el curso y permisos del informe nativo que se abra | Notas autorizadas; el panel Orange excluye notas ocultas. Para el libro de notas, usar también `gradereport/grader:view`. | Acceso a cursos de otros proyectos ni edición de notas. |
| Ver intentos en la ficha Orange | Permisos anteriores de seguimiento, `moodle/grade:viewall` y `mod/quiz:viewreports` en el cuestionario | Intentos reales, estados y fechas; acceso al informe nativo con su propia comprobación. | Cambiar notas o recalificar. |
| Consultar encuestas desde la ficha | Seguimiento del curso y `mod/feedback:viewreports` en la actividad | Cumplimentación identificable de encuestas no anónimas. | Atribuir respuestas anónimas a un usuario. |

El permiso `manage` gestiona **ambos tipos de publicación**. Hoy no existe un permiso exclusivo «solo comunicados». Esa separación requeriría ampliar el plugin. Igualmente, `viewreports` muestra lecturas de **Orange en general**: seleccionar un colectivo es un filtro, no una frontera de seguridad por equipo.

## Roles que componen cada perfil

En **Definir roles**, «Nombre completo» es la etiqueta visible y puede cambiarse. «Nombre corto» es la referencia técnica estable. Conservar los nombres cortos siguientes para reproducir la configuración y las relaciones automáticas del plugin. Los ID numéricos internos pueden ser distintos en cada ambiente.

| Rol técnico y nombre corto | Dónde se puede asignar | Uso |
| --- | --- | --- |
| Formador Orange — `orange_publicador` | Sistema | Publicaciones y seguimiento: `manage`, `changestate`, `viewallincidents`, `viewreports`, `manageoptions`, todos del componente `local/orangeportal`. Sin exportación del portal por defecto. |
| Coordinación Orange — `orange_seguimiento` | Sistema | Solo `local/orangeportal:viewreports` y `local/orangeportal:viewallincidents`. |
| Calidad Orange — `orange_calidad` | Sistema | Los mismos dos permisos de consulta. Mantenerlo separado permite cambiar después el alcance de Calidad sin modificar Coordinación. |
| Consulta formativa Orange — `orange_consulta_formativa` | Categoría o curso | Consulta nativa de resultados sin edición; lista completa más abajo. |
| Gestión de categorías Orange — `orange_gestorcategorias` | Categoría | Solo `moodle/category:manage`. |
| Creación de contenidos Orange — `orange_creadorcontenidos` | Categoría | Solo `moodle/course:create`. |
| Administrador de Orange — `orange_administrador` | Sistema | Los siete permisos `local/orangeportal:*` de la tabla anterior. Se crea al instalar el plugin. |
| Administración de contenidos Orange — `orange_admin_area` | Categoría | Rol auxiliar creado por el plugin; edición nativa basada en Profesor con edición y creación/gestión de contenido en las dos raíces. Se asigna automáticamente al administrador Orange. |
| Cuenta exclusiva Orange — `orange_cuenta` | Usuario | Marcador técnico sin permisos. Lo aplica el plugin a cuentas creadas desde Usuarios Orange. No asignarlo manualmente para dar acceso. |

Administrador de Orange, Administración de contenidos Orange y Cuenta exclusiva Orange se crean al instalar el plugin. **El resto no se crea automáticamente en una instalación nueva:** se importa o configura desde Moodle. Estudiante y Profesor con permiso de edición son roles nativos ya existentes; no modificarlos globalmente para este proyecto.

Consulta formativa Orange permite únicamente estas capacidades en su definición:

| Permiso | Para qué se utiliza |
| --- | --- |
| `moodle/course:view` | Consultar el curso autorizado. |
| `moodle/course:viewparticipants` | Consultar participantes y habilitar la ficha de seguimiento formativo. |
| `moodle/site:accessallgroups` | Consultar todos los grupos **de los cursos donde se asigne el rol**. No asignarlo en Sistema. |
| `moodle/grade:viewall` | Consultar calificaciones de otros participantes. |
| `gradereport/grader:view` | Abrir el informe del calificador. |
| `gradereport/user:view` | Abrir el informe individual de calificaciones. |
| `mod/quiz:view` y `mod/quiz:viewreports` | Consultar cuestionarios y sus resultados. |
| `mod/feedback:view` y `mod/feedback:viewreports` | Consultar encuestas e informes. |
| `report/completion:view` y `report/progress:view` | Consultar finalización de curso y actividades. |

No utilizar Profesor sin edición como sustituto de esta consulta estricta: el rol nativo puede incluir calificación y otras acciones de escritura.

## Paso 1 Preparar las referencias del ambiente destino

1. Entrar como administrador general del Moodle destino.
2. Comprobar que están instalados `local_orangeportal`, el tema Orange y su dependencia Moove según la guía de despliegue.
3. Abrir **Administración del sitio > Plugins > Plugins locales > Portal Orange**. Seleccionar las raíces de Biblioteca y Formaciones **de ese ambiente**, y su cohorte Usuarios Orange. Habilitar el portal siguiendo la guía de despliegue.
4. Registrar las referencias en la hoja al final de esta guía. No copiar los ID numéricos de localhost.
5. Configurar el tema Orange para esa cohorte según [Navegación](02-navegacion.md), conservando el tema corporativo para los demás usuarios.

Un rol sin pertenencia a Usuarios Orange no habilita el portal. La pertenencia a Usuarios Orange sin roles de gestión conserva la vista de participante.

## Paso 2 Crear o importar las definiciones de roles

La réplica se puede realizar **sin ejecutar código**. Se incluyen [nueve XML exportados por Moodle](roles/) y una [matriz de permisos permitidos](roles/permisos-permitidos.csv). Corresponden a la configuración local revisada, no a todos los permisos efectivos de cada persona: esos dependen también de sus otras asignaciones y excepciones.

### Importar un rol que todavía no existe

1. Abrir **Administración del sitio > Usuarios > Permisos > Definir roles**.
2. Buscar primero su **nombre corto**. Si ya existe, seguir el apartado siguiente; no crear un duplicado.
3. Pulsar **Añadir un nuevo rol**. En la pantalla inicial, cargar el XML del rol en la opción de archivo de preajuste de rol y continuar. La etiqueta exacta puede variar con el idioma.
4. Comprobar nombre completo, nombre corto, contextos donde puede asignarse y permisos. Guardar.
5. Repetir con los roles necesarios. Para reproducir todos los perfiles descritos, importar los seis roles no creados por el instalador.
6. Si Moodle señala capacidades de complementos no instalados, revisar las diferencias antes de aprobar el rol. Los XML son para la versión y componentes de esta entrega; un Moodle distinto necesita su propia validación.

### Si el rol ya existe

Abrir su definición y comparar con la matriz/XML y las tablas anteriores. Ajustar desde **Editar** solo lo acordado para ese rol Orange. Antes de cambiarlo, usar **Exportar** para guardar su definición anterior. No borrar el rol para recrearlo: las personas ya pueden tenerlo asignado.

En los roles exclusivos Orange de consulta/gestión pequeña, permitir únicamente los permisos enumerados y dejar los demás sin conceder. **No establecido/No definido** no equivale a prohibir: otros roles pueden conceder ese mismo permiso. No usar **Prohibir** como forma habitual de configurar perfiles de consulta.

Los tres roles automáticos del administrador ya deben existir. Utilizar sus XML como referencia; no importarlos como nuevos ni sustituir sus nombres cortos. `orange_admin_area` tiene muchos permisos nativos y su XML/CSV contiene la definición completa. La definición excluye concesiones `enrol/*` y `moodle/role:*`; la gestión delegada de colectivos/matrículas usa las pantallas limitadas del plugin.

**El XML transporta la definición, no las personas:** no incluye cuentas, contraseñas, asignaciones en cursos, excepciones locales, cohortes, matrículas ni configuración del portal. Los siguientes pasos son necesarios aunque la importación termine correctamente.

## Paso 3 Dar acceso Orange y asignar colectivos

1. Crear la cuenta desde Moodle o utilizar una existente. Su nombre de usuario y correo pueden ser diferentes en cada ambiente.
2. Incorporarla a la cohorte configurada como Usuarios Orange desde **Administración del sitio > Usuarios > Cuentas > Cohortes**.
3. Administración Orange puede crear **Cristalia-Madrid**, **Centro-Valencia** u otros desde **Colectivos Orange > Crear colectivo**.
4. Desde **Usuarios Orange > Editar**, seleccionar los colectivos de la persona. Puede pertenecer a varios. Para colectivos preexistentes, el administrador general debe delegarlos antes en la configuración del portal y cumplir las restricciones de ámbito.
5. Para acceso documental/formativo, configurar **Curso o colección > Participantes > Métodos de matriculación > Sincronización de cohorte**, con el colectivo y rol Estudiante. También puede utilizarse matrícula manual de usuarios existentes.

Los centros son colectivos/cohortes; no se utiliza Institución como centro. El CE se guarda en el campo nativo **Número de ID** de la cuenta. Ninguno de esos datos decide por sí solo si la persona es Formador, Calidad o Administrador.

## Paso 4 Asignar el perfil de trabajo a cada persona

| Perfil que necesita la persona | Qué asignar en Sistema | Qué asignar en categorías o cursos |
| --- | --- | --- |
| Agente | Ningún rol de gestión Orange. | Estudiante en sus cursos/colecciones, mediante matrícula manual o cohorte. |
| Formación | Formador Orange. | Gestión de categorías Orange y Creación de contenidos Orange en las raíces autorizadas; Profesor con permiso de edición en los cursos/colecciones que gestionará. |
| Coordinación | Coordinación Orange. | Consulta formativa Orange **solo en sus cursos autorizados**. No asignar gestión de categorías, creación ni Profesor con edición. |
| Calidad | Calidad Orange. | Estudiante si recibe contenido. No asignar Consulta formativa Orange ni permisos de notas ajenas con el alcance actual. |
| Administrador de Orange | Administrador de Orange, con ese nombre corto exacto. | El plugin asigna automáticamente Administración de contenidos Orange en las dos raíces configuradas. |
| Cliente | Pendiente de aprobación de su alcance. | No deducir permisos solo de su denominación ni darle Profesor sin edición automáticamente. |

### Asignación en Sistema

1. Abrir **Administración del sitio > Usuarios > Permisos > Asignar roles del sistema**.
2. Seleccionar el rol correspondiente, buscar la cuenta real de ese ambiente y añadirla.
3. Revisar que no se le haya concedido Gestor global ni se haya añadido a Administradores del sitio para resolver un problema de acceso.

### Asignación en una categoría

1. Abrir **Administración del sitio > Cursos > Administrar cursos y categorías**.
2. Seleccionar la raíz Biblioteca o Formaciones correspondiente.
3. Entrar en **Permisos > Asignar roles** y añadir la cuenta al rol técnico requerido.
4. Repetir en la otra raíz solo si también debe gestionarla. Una asignación en raíz alcanza a todos sus descendientes.

### Asignación y matrícula en un curso o colección

1. Abrir el curso o colección y entrar en **Participantes**.
2. Para Formación, matricular la cuenta existente como Profesor con permiso de edición en ese ámbito.
3. Para Coordinación, asignar Consulta formativa Orange desde **Participantes > Permisos > Asignar roles** o mediante la gestión de roles de la matrícula, según la navegación disponible. Conservar las matrículas que ya deba tener; no modificar el rol estándar Profesor sin edición.
4. No asignar Consulta formativa en una raíz completa si solo está autorizado un curso. No dar permisos de contenido en Sistema.

Al crear contenido nuevo, comprobar **Administración del sitio > Usuarios > Permisos > Políticas de usuario > Rol de los creadores en nuevos cursos**. La demo utiliza Profesor con permiso de edición. Ese ajuste es global: si el campus tiene otra política, no cambiarla sin revisar el efecto sobre los demás proyectos; asignar el rol necesario en el curso nuevo.

### Comprobar la asignación automática de Administración Orange

Configurar primero las raíces y la cohorte; añadir a la persona a Usuarios Orange y después asignar Administrador de Orange en Sistema. Verificar que aparece Administración de contenidos Orange en ambas raíces. Si se cambiaron las raíces después, retirar y volver a asignar el rol del sistema para reconstruir esos ámbitos. No asignar manualmente Cuenta exclusiva Orange ni modificar su marcador para ampliar permisos sobre cuentas compartidas.

## Paso 5 Comprobar los permisos efectivos

Una persona puede tener varios roles; Moodle combina los permisos. **Calidad + Formación permite gestionar por Formación.** Para una cuenta de consulta, revisar sus asignaciones de Sistema, categorías y cursos, además de las excepciones de permisos. Retirar la asignación de escritura que sobra; no basta con añadir Calidad.

1. En el curso, abrir **Participantes > Permisos > Comprobar permisos** y seleccionar la persona.
2. Para las capacidades globales, usar **Administración del sitio > Usuarios > Permisos > Comprobar permisos del sistema**.
3. Buscar el identificador técnico del permiso de la tabla. Comprobar si está permitido y qué roles/contextos contribuyen a ello.
4. Para Coordinación y Calidad, comprobar que faltan publicación, cambio de estados, gestión de opciones y administración Orange.
5. Para consulta formativa estricta, comprobar que no se conceden `moodle/grade:edit`, `moodle/grade:override`, `moodle/grade:manage`, `mod/quiz:grade`, `mod/quiz:regrade`, `mod/feedback:edititems`, `moodle/course:manageactivities` ni `enrol/manual:enrol`.
6. Comprobar aparte las exportaciones nativas: retirar el permiso del CSV Orange no retira exportaciones concedidas por Profesor con edición u otros roles de Moodle.

La lectura automática de publicaciones no necesita un permiso de publicación y no modifica el estado común de una incidencia.

## Paso 6 Probar con cuentas separadas

Cerrar sesión y entrar con cada cuenta. No validar la delegación usando un administrador del sitio: ese acceso ocultaría permisos que faltan o sobran.

| Prueba | Resultado esperado |
| --- | --- |
| Agente | Ve contenido asignado; no publica ni cambia estados; no accede al Seguimiento de compañeros. |
| Formación | Publica incidencias/comunicados, cambia estados, edita contenido autorizado y matricula usuarios existentes según permisos nativos; no crea cuentas ni administra otros proyectos. |
| Coordinación | Consulta lecturas Orange y resultados de cursos autorizados; no modifica publicaciones, estados, categorías, notas ni matrículas de terceros. |
| Calidad | Consulta lecturas e histórico; no obtiene notas ni intentos por abrir Usuarios > Formación. |
| Administrador Orange | Mantiene usuarios/colectivos Orange y contenidos de sus raíces; no administra el campus ni cuentas de otros proyectos. |
| Usuario externo a Usuarios Orange | Conserva el campus corporativo y no entra por URL directa al portal Orange. |
| Perfil de consulta abre directamente una URL de gestión | Acceso denegado, aunque conozca la dirección. |
| Coordinador abre notas de un curso no autorizado | Sin acceso a esos resultados. |
| Usuario de Cristalia-Madrid | Aparece al filtrar su colectivo y no en Centro-Valencia, salvo que pertenezca también a ese centro. |

El seguimiento de lecturas es Orange general, sin restricción por equipo del coordinador. Si se exige consulta exclusiva del equipo propio, debe desarrollarse esa autorización antes de dar acceso general. La selección de centro no sustituye ese desarrollo.

## Paso 7 Guardar la configuración del ambiente

Completar esta hoja con las referencias reales y conservarla con el despliegue. No escribir contraseñas.

| Dato que debe registrarse | Desarrollo | Preproducción | Producción |
| --- | --- | --- | --- |
| URL y versión Moodle/plugin/tema | | | |
| Categoría raíz Biblioteca | | | |
| Categoría raíz Formaciones | | | |
| Cohorte que habilita Orange | | | |
| Colectivos de centro y otros delegados | | | |
| Personas con Administración Orange | | | |
| Personas con Formación y categorías/cursos asignados | | | |
| Personas con Coordinación y cursos autorizados | | | |
| Personas con Calidad | | | |
| Exportación autorizada y permisos nativos revisados | | | |
| Fecha, responsable y resultado de las pruebas | | | |
| Versión de los XML utilizados y diferencias aprobadas | | | |

Para actualizar permisos en el futuro: editar el rol Orange desde Definir roles, revisar el ámbito, probar con cuentas separadas y exportar de nuevo su XML desde la vista de definición. Guardar la nueva referencia y sus diferencias. No copiar tablas de usuarios/roles entre bases de datos ni reutilizar sus ID numéricos.

## Si algo no funciona

| Síntoma | Qué revisar primero |
| --- | --- |
| Tiene rol Orange, pero no entra al portal | Portal habilitado y pertenencia a la cohorte de audiencia configurada en ese ambiente. |
| Entra a Orange, pero no aparece Administración | Tiene solo acceso de participante; revisar las capacidades de gestión/seguimiento asignadas en Sistema. |
| Ve Seguimiento, pero no notas o cursos | Revisar permisos nativos en el curso, acceso al curso y matrícula activa de la persona observada. Calidad no tiene esas notas por defecto. |
| Puede crear categorías, pero no documentos o cursos | Gestión de categorías no concede creación ni edición; revisar los dos roles de contenido y su ámbito. |
| Un usuario de consulta puede editar | Conserva otro rol o una excepción que concede escritura. Revisar permisos efectivos, no solo el nombre del perfil nuevo. |
| Administrador Orange no edita contenidos | Revisar raíces y asignación automática de `orange_admin_area`. |
| Un centro no aparece en el selector | Crear/delegar su cohorte desde Colectivos Orange; una cohorte de otro proyecto o con miembros externos no se ofrece como colectivo delegado válido. |
| Importar un XML no dio acceso a nadie | El XML solo crea la definición; faltan asignación de roles, audiencia y matrículas. |

## Archivos y alcance de esta referencia

- [Definiciones XML nativas](roles/) de los nueve roles Orange.
- [CSV de permisos explícitamente permitidos](roles/permisos-permitidos.csv), con nombre corto, capacidad y contextos asignables. Es una referencia de definiciones, no el resultado efectivo por persona.
- [Manifiesto de integridad](roles/SHA256SUMS.txt) de esos archivos.
- [Perfiles de consulta](14-perfiles-consulta.md), [Administración Orange](11-administrador-orange.md) y [Seguimiento](09-informes.md) amplían los procedimientos concretos.

Los permisos y asignaciones viven en la base de datos de cada Moodle; subir carpetas de código no replica las asignaciones de personas. El código define las capacidades y las comprobaciones, mientras Moodle conserva roles, contextos, cohortes y matrículas. Instalar el plugin crea sus tres roles auxiliares/delegados; los inicializadores `configure_demo.php` y `configure_consultation_demo.php` son solo para localhost y pueden reaplicar permisos de demo. No utilizarlos para mantener perfiles reales.
