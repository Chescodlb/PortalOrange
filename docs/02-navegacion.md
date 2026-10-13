# Gestionar la navegación y la página de entrada

Los usuarios autenticados ven **Biblioteca, Cursos, Incidencias y Notificaciones** en la barra superior. Los usuarios con permisos administrativos del portal ven además **Administración**. El menú de cuenta y la opción de cerrar sesión siguen siendo los de Moodle.

## Elegir dónde entra el usuario

1. Abrir **Administración del sitio > Apariencia > Navegación**.
2. En **Página de inicio por defecto para los usuarios**, elegir **Biblioteca Orange**, **Cursos Orange**, **Incidencias Orange** o **Notificaciones Orange**.
3. Guardar y comprobar con un nuevo inicio de sesión.

Acceso directo: http://localhost:8080/admin/settings.php?section=navigation

La opción actual es **Biblioteca Orange** y el cliente puede cambiarla. Las cuatro opciones se registran mediante el mecanismo de extensiones de Moodle; no son enlaces introducidos en el core.

## Activar o desactivar la navegación Orange

Abrir **Administración > Portal Orange > Configuración Orange** y cambiar **Navegación Orange**. Desactivarla recupera la navegación habitual del tema. Para recuperar también la entrada habitual, cambiar la página por defecto a **Inicio del sitio** y activar de nuevo el Área personal si se necesita.

La configuración nativa permite desactivar el Área personal y añadir enlaces personalizados, pero no permite sustituir exactamente la navegación principal de Moove por estas cuatro secciones con permisos y sección activa. Esa parte usa el hook de navegación del plugin. No se ocultan enlaces con CSS como mecanismo de autorización.

## Comportamiento móvil y administración

En pantallas pequeñas, el menú se despliega con el botón de la barra superior. En las páginas de un curso o recurso se resalta Biblioteca o Cursos según la categoría nativa a la que pertenezca.

**Administración > Portal Orange** incluye publicaciones, categorías e informes. Un publicador delegado accede solo a sus funciones, aunque no sea administrador completo del sitio. El enlace superior Administración lleva a la administración general si el usuario tiene permiso de configuración del sitio; para un publicador delegado lleva a gestionar avisos, y para Coordinación a Resultados y confirmaciones. La visibilidad del enlace no concede permisos: cada página comprueba su capacidad de Moodle.

## Árboles desplegables en la barra superior

Pulsar **Biblioteca**, **Cursos**, **Incidencias** o **Notificaciones** abre su árbol. **Ver todo** entra a la sección completa; cada categoría abre esa sección filtrada. Las flechas del árbol despliegan sus subcategorías. Un clic fuera o Escape cierra el desplegable. Con teclado, flecha abajo abre el panel y lleva a su primer enlace; Tab recorre enlaces y ramas. También funciona desde el menú móvil.

No hay que mantener una segunda lista de enlaces:

- Biblioteca y Cursos toman sus categorías de **Administración del sitio > Cursos > Administrar cursos y categorías**. Biblioteca conserva **RPV/CENTREX** como una sola rama. Todos los usuarios ven el árbol completo, incluidas ramas vacías o sin contenido asignado. Los permisos por matrícula, grupos y visibilidad se aplican a los contenidos, no al árbol.
- Incidencias y Notificaciones toman sus categorías de **Administración del sitio > Portal Orange > Categorías**. Los números suman únicamente las publicaciones visibles para el usuario, incluyendo subcategorías.

Los menús personalizados configurables de Moove son estáticos. En esta instalación su plantilla de desplegable representa un nivel de enlaces y no genera estos árboles con permisos y contadores. Por ello el comportamiento dinámico se añade en `local/orangeportal`, mediante un hook de salida de Moodle, `navigation.js` y estilos del plugin, sin modificar Moodle ni Moove. Si JavaScript no está disponible, los enlaces superiores siguen abriendo las secciones.

## Ocultar las categorías del lateral

Las cuatro secciones muestran las categorías únicamente en los desplegables superiores. El lateral izquierdo está oculto y el contenido ocupa todo el ancho. Se configura en **Moove > Avanzado > SCSS en bruto**, con las reglas `.orange-sidebar { display: none; }` y `.orange-layout { display: block; }`, limitadas a las páginas del portal. Para recuperar el lateral, retirar esas dos reglas y guardar. No se modifica código de Moodle ni del plugin para este ajuste.

## Sección de gestión activa

La opción activa tiene fondo naranja suave, texto en negrita y subrayado naranja. En Categorías, el segundo selector indica si se editan incidencias o notificaciones. El menú compartido muestra únicamente las opciones autorizadas por las capacidades del usuario.

## Categorías de Biblioteca y Cursos

**Gestionar categorías** contiene cuatro opciones: **Biblioteca, Cursos, Incidencias y Notificaciones**, según permisos. Todas abren un formulario dentro del portal con Nombre, Categoría superior y Guardar categoría. Cada opción muestra únicamente su listado y sus posibles categorías superiores. La opción activa se remarca en naranja. No se añade la barra secundaria de gestión general de Moodle.

Biblioteca y Cursos conservan sus categorías nativas: el formulario usa las APIs de Moodle para crear, renombrar y mover, y exige `moodle/category:manage` en la raíz correspondiente. Este formulario adaptado es necesario porque la gestión nativa completa muestra varias raíces y herramientas de cursos juntas; ese diseño no permite la experiencia separada solicitada. No se duplica la estructura en tablas del plugin.
