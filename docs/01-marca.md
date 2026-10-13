# Cambiar la marca del portal

Moodle y Moove permiten cargar y sustituir el logo desde la administración. El logo inicial se ha extraído de la plantilla corporativa facilitada por el usuario y se ha cargado en el área de archivos de Moove.

## Cambiar el logo

1. Iniciar sesión como administrador.
2. Abrir **Administración del sitio > Apariencia > Temas > Moove**. En algunas traducciones aparece como **Configuración de Moove**.
3. En **General**, sustituir el archivo de **Logo** y guardar los cambios.
4. Comprobar la barra superior y la pantalla de acceso.

Acceso directo local: http://localhost:8080/admin/settings.php?section=themesettingmoove

Moodle dispone además de ajustes globales en **Apariencia > Logos**. En este portal el ajuste usado en la barra es el de Moove; mantener ambos coherentes si se configura un logo global para otras salidas.

## Colores y tipografía

En **Moove > General**, el color de marca es `#FF7900` y el del menú secundario es `#111111`. El ajuste de fuente usa **Moodle Font** para evitar cargar una fuente de Google; Arial se aplica en el campo de SCSS de Moove.

En **Moove > Avanzado > SCSS en bruto**, el contenido inicial es el de [orange-theme.scss](../assets/orange-theme.scss). Se puede editar allí sin modificar los archivos del tema. El repositorio conserva una copia reproducible del estilo, pero Moodle guarda el ajuste efectivo en su base de datos.

El naranja se usa como fondo y acento. Los enlaces llevan un tono más oscuro y los botones naranja usan texto negro para mantener la legibilidad. La barra superior es negra y el contenido usa blanco y grises.

El menú superior conserva el fondo oscuro al seleccionar, enfocar o desplegar una sección. El texto y el subrayado se remarcan en naranja `#FF7900`; el foco de teclado tiene además un contorno naranja. Estas reglas están en el mismo campo **SCSS en bruto** de Moove.

Una sección distinta de la página actual mantiene también el remarcado mientras su árbol esté abierto, incluso al pasar el cursor o el foco a sus categorías. El plugin marca explícitamente ese estado abierto y lo elimina al cerrar el desplegable.

Si un cambio no se refleja, abrir **Administración > Desarrollo > Purgar cachés**. Comprobar también el menú móvil: el icono de apertura debe verse sobre el fondo negro.
