# Gestionar la navegación y la página de entrada

Solo los miembros de la cohorte Orange configurada ven **Biblioteca, Comunicados, Incidencias y Formaciones**, en ese orden. Sus roles determinan si aparece **Administración**. El resto conserva la navegación, portada, breadcrumbs y apariencia nativos del sitio. El acceso de inicio de sesión es común.

## Preparar el aislamiento desde Moodle

1. Instalar `local_orangeportal` y el tema propio `theme_orange`, manteniendo instalado Moove como dependencia. Ejecutar la actualización habitual de Moodle. No cambiar el tema predeterminado del sitio.
2. Abrir **Administración del sitio > Apariencia > Temas > Ajustes de temas** y habilitar **Permitir temas de cohortes** (`allowcohortthemes`).
3. Abrir **Usuarios > Cuentas > Cohortes** y crear o editar una cohorte de sistema, por ejemplo **Usuarios Orange**. Elegir **Forzar tema: Orange**.
4. En **Asignar miembros**, añadir únicamente las cuentas Orange. Los roles de editor/coordinador no añaden automáticamente a una persona: pertenencia y permisos son independientes.
5. Abrir **Administración > Portal Orange > Configuración Orange**, activar el portal y seleccionar esa misma **Cohorte de usuarios Orange**.
6. Elegir **Página de entrada Orange**: Biblioteca, Formaciones, Incidencias o Comunicados. Solo cambia la redirección de los miembros de la cohorte; conservar la página de inicio general de Moodle como estaba.
7. Cerrar sesión e iniciar de nuevo para que Moodle recalcule el tema nativo de la cohorte. Hacerlo también tras cambiar miembros o el tema de una cohorte.
8. Comprobar en dos sesiones separadas un miembro Orange y un usuario ajeno.

Sin cohorte seleccionada, el plugin no aplica la experiencia a ningún usuario. El administrador general tampoco recibe diseño Orange automáticamente: puede configurar Moodle sin pertenecer a la cohorte. Si necesita revisar el portal Orange, puede entrar con un perfil de pruebas o añadirse a la cohorte conservando todos sus permisos.

La pertenencia se lee de `cohort_members`; la cohorte y la sección de entrada se guardan en `config_plugins` del plugin. No se crea una tabla de identidades ni un modelo de roles alternativo. La selección de tema es nativa de Moodle; el código del plugin limita sus hooks y sus páginas a esa misma cohorte. El acceso directo por URL no evita la restricción. Las cohortes/grupos usados para asignar avisos siguen siendo independientes de la cohorte que habilita el portal.

## Desactivar o retirar un usuario

Quitar al usuario de **Usuarios Orange** o desactivar el portal en su configuración restaura la navegación nativa y bloquea sus páginas. Para retirar también la apariencia Orange, quitar su pertenencia a la cohorte o retirar **Forzar tema** de ella y volver a iniciar sesión. Desactivar el plugin no modifica por sí mismo la asignación nativa del tema de la cohorte.

No seleccionar una sección Orange como página de inicio global: afectaría al resto del sitio. Las opciones de entrada Orange se gestionan ahora en la configuración del plugin.

## Demo local y despliegue

En la demo, `agente.orange`, `agente.otro`, `editor.orange`, `coordinador.orange` y `formador.orange` pertenecen a **Usuarios Orange**. `usuario.externo` y el administrador general están fuera. `usuario.externo` usa `ORANGE_DEMO_PASSWORD` de `.env`, igual que las demás cuentas de prueba.

`cli/isolate_demo.php` es una migración exclusiva de esta demo: respalda la configuración, traslada el logo y retira los antiguos ajustes globales Orange de Moove, habilita los temas de cohortes y crea la cuenta externa. Su ejecución requiere la variable `ORANGE_DEMO_PASSWORD` y está bloqueada fuera de localhost. **No ejecutarlo ni ejecutar `configure_demo.php` contra el portal existente.** Allí instalar los dos plugins y seguir los pasos anteriores, conservando los ajustes actuales del sitio y de su tema.

La prueba local compara con Moove estándar, no con una copia del campus existente. Antes de desplegar, verificar su versión real de Moodle/Moove, prioridades de temas y posibles cohortes con otros temas forzados. Moodle resuelve conflictos entre temas de cohortes volviendo al tema del sitio; no debe haber dos temas distintos forzados para un mismo usuario.

## Comportamiento móvil y administración

En pantallas pequeñas, el menú se despliega con el botón de la barra superior. En las páginas de un curso o recurso se resalta Biblioteca o Formaciones según la categoría nativa a la que pertenezca.

**Administración > Portal Orange** incluye publicaciones, categorías e informes. Un publicador delegado accede solo a sus funciones, aunque no sea administrador completo del sitio. El enlace superior Administración lleva a la administración general si el usuario tiene permiso de configuración del sitio; para un publicador delegado lleva a gestionar avisos, y para Coordinación a Resultados y confirmaciones. La visibilidad del enlace no concede permisos: cada página comprueba su capacidad de Moodle.

## Acceso directo desde el menú principal · 20/10/2026

**Biblioteca**, **Comunicados**, **Incidencias** y **Formaciones** abren directamente sus páginas. Ninguno despliega un árbol flotante. En móvil se conserva el botón nativo para abrir el menú principal; cada opción navega a su sección.

Biblioteca mantiene su explorador por columnas; Incidencias conserva los filtros laterales y Comunicados sus filtros en la página. La retirada de los desplegables no cambia categorías, documentos ni permisos.

Las etiquetas proceden de las cadenas del plugin: en español Formaciones y Comunicados; en inglés Training y Announcements. Los identificadores internos `courses` y `notifications`, las URLs y los nombres de categorías guardados se conservan. Los cursos individuales siguen usando la terminología nativa de Moodle.

Se retiran el hook que generaba los paneles y el script `navigation.js` que interceptaba los enlaces. No se modifica Moodle ni Moove. Verificación: `node scripts/test-orange-browser.mjs --navigation-only`.

## Sección de gestión activa

La opción activa tiene fondo naranja suave, texto en negrita y subrayado naranja. En Categorías, el segundo selector indica si se editan incidencias o notificaciones. El menú compartido muestra únicamente las opciones autorizadas por las capacidades del usuario.

## Categorías de Biblioteca y Formaciones

**Gestionar categorías** contiene cuatro opciones: **Biblioteca, Formaciones, Incidencias y Comunicados**, según permisos. Todas abren un formulario dentro del portal con Nombre, Categoría superior y Guardar categoría. Cada opción muestra únicamente su listado y sus posibles categorías superiores. La opción activa se remarca en naranja. No se añade la barra secundaria de gestión general de Moodle.

Biblioteca y Formaciones conservan sus categorías nativas: el formulario usa las APIs de Moodle para crear, renombrar y mover, y exige `moodle/category:manage` en la raíz correspondiente. Este formulario adaptado es necesario porque la gestión nativa completa muestra varias raíces y herramientas de cursos juntas; ese diseño no permite la experiencia separada solicitada. No se duplica la estructura en tablas del plugin.

## Rutas de navegación del portal

Los breadcrumbs de cursos, secciones, actividades y ajustes de formación empiezan en **Formaciones**; los de colecciones y recursos documentales empiezan en **Biblioteca**. En inglés usan **Training** y **Library**. Se conserva el recorrido nativo posterior, incluido el curso, la sección y la actividad cuando Moodle y el tema los muestran.

El plugin determina la sección por la categoría raíz y adapta el nodo raíz de navegación de la página. El primer enlace abre la sección correspondiente, en lugar de «Página Principal» y su redirección a Biblioteca. Las páginas propias de documentos, publicaciones y gestión de categorías usan la misma regla. Las páginas generales de administración ajenas a esas secciones conservan la navegación de Moodle. No se modifica el core ni el tema.

### Páginas personales del usuario

Preferencias, la vista personal de Calificaciones, Calendario personal y Archivos privados usan **Mi cuenta** como raíz del breadcrumb, enlazada al perfil del usuario. Por ejemplo: **Mi cuenta > Preferencias**. En inglés: **My account > Preferences**. Se eliminan las raíces «Página Principal» y los duplicados que llevaban a Biblioteca. Los nodos de mes o día del calendario se conservan cuando Moodle los añade. En la propia página del perfil, la raíz es Mi cuenta; Moove puede ocultar una ruta con un único elemento.

Si la página corresponde a un curso o una colección de las raíces del portal, conserva la ruta de Formaciones o Biblioteca. Cuando un responsable consulta las preferencias o calificaciones de otra persona en contexto de usuario, la raíz muestra el nombre de esa persona y apunta a su perfil; no altera permisos ni datos. El ajuste se implementa en `classes/breadcrumb.php`, mediante los nodos nativos de navegación, sin modificar Moodle ni Moove.

## Idioma de la administración del portal

Las pestañas de gestión, formularios de publicaciones y categorías, tablas, informes, ayudas, validaciones y mensajes utilizan `lang/es` y `lang/en` del plugin y cadenas estándar de Moodle. Los ajustes generales existentes del plugin también tienen traducciones. Cambiar el idioma desde el menú de usuario o las preferencias aplica la traducción de la interfaz; el idioma predeterminado del sitio se configura con los ajustes nativos de Moodle. No hay un segundo ajuste de idioma propio del portal.

Los títulos y cuerpos de publicaciones, nombres de categorías, cursos, colectivos y opciones configurables de Estados e Impactos son contenido guardado y mantienen el texto introducido por el responsable. No se renombran ni se traducen automáticamente al cambiar el idioma. El detalle de un aviso y el formulario Nuevo documento traducen sus instrucciones y controles.

## Biblioteca por columnas · 20/10/2026

El enlace superior **Biblioteca** abre directamente su página de exploración; no tiene desplegable. Las categorías se recorren allí en columnas y los recursos aparecen en **Documentos**, con búsqueda global. Las otras tres secciones también abren directamente sus páginas. Ver [gestión y recorrido de Biblioteca](04-biblioteca.md).


## Cinco indicadores personales · 20/10/2026

El lado derecho de la barra muestra campana, triángulo de incidencia crítica, libro abierto, biblioteca y burbuja de comunicados. Sustituyen los iconos nativos de avisos y mensajería exclusivamente dentro del tema Orange para sus miembros. Cada cifra cuenta pendientes personales y abre la sección filtrada; no abre desplegables. Ver [reglas, fechas y configuración](10-indicadores-personales.md).

Las portadas de sección muestran directamente su título y descripción, sin el rótulo «ORANGE · PORTAL DE AGENTES» ni variantes según el rol.
