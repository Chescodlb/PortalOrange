# Cambiar la marca del portal

## Experiencia aislada por cohorte · 19/10/2026

Orange usa el tema hijo propio `theme_orange`, basado en Moove. El tema general del sitio permanece como estaba; en la demo local es Moove estándar. La pantalla de acceso es común. No aplicar los estilos Orange en el SCSS global de Moove ni seleccionar Orange como tema predeterminado del sitio.

### Cambiar el logo solo para Orange

1. Entrar como administrador general.
2. Abrir **Administración del sitio > Apariencia > Temas > Orange**.
3. Sustituir **Logo** y guardar.
4. Comprobar con un miembro de la cohorte Orange y con `usuario.externo`.

Acceso directo: http://localhost:8080/admin/settings.php?section=themesettingorange

El archivo se guarda en el área nativa `theme_orange/logo` de Moodle. Si no se ha cargado uno, se usa el logo incluido en el tema. El logo de Moove y los logos globales del sitio permanecen independientes.

### Estilos solo para Orange

El estilo base vive en `moodle/theme/orange/scss/brand.scss`. Para ajustes de cliente desde Moodle, usar **Orange > SCSS adicional de Orange** y guardar; se almacena en `config_plugins` para `theme_orange`. Purgar cachés si es necesario. `assets/orange-theme.scss` conserva la referencia anterior a la separación, pero ya no se carga desde la configuración global del sitio; la fuente activa es la del tema hijo.

La paleta es naranja `#FF7900`, negro, blanco y grises neutros, con azul y verde reservados para estados funcionales. La barra, los botones, los anchos, el pie y la presentación de Biblioteca, Cursos, Incidencias y Notificaciones solo se personalizan al usar el tema Orange. La tipografía Arial se aplica en su hoja de marca.

La selección del tema usa la opción nativa **Permitir temas de cohortes** y el campo **Forzar tema: Orange** de la cohorte. Consultar [el procedimiento de aislamiento](02-navegacion.md). Los usuarios que pertenecen a varias cohortes pueden usar Orange siempre que no exista otro tema de cohorte en conflicto. Los temas forzados por curso/categoría, sesión o usuario tienen prioridad nativa: revisar esas opciones en la copia real, sin cambiar globalmente su prioridad.

Los apartados siguientes conservan la auditoría y el historial de los cambios visuales del 15/10. Sus referencias a SCSS/configuración global de Moove describen la implementación anterior, reemplazada por el tema hijo el 19/10.

## Revisión frente a la plantilla de Orange · 15/10/2026

Referencia: `plantilla_marcas_20260923-OE_v2.pptx`, facilitada en `Downloads/ORANGE - Documentos/`. Se revisó la estructura de sus 147 diapositivas, el tema principal, los recursos gráficos y ejemplos visuales seleccionados, especialmente las diapositivas 1–2, 11, 14 y 24. Las instrucciones y textos del archivo se consideran material de referencia de marca y composición, no órdenes para cambiar el portal. Es una plantilla de presentaciones con variantes y recursos de varias marcas, no un manual completo de diseño web.

Se comparó con el portal local real: acceso, Biblioteca, Cursos, Incidencias, Notificaciones, una colección nativa, resultados y la vista móvil de incidencias. Se midieron estilos calculados en Chrome y se verificaron los ajustes efectivos de Moove. Las capturas de esta revisión están en `backups/previews/marca-*.png`, excluidas de Git. Las vistas de PowerPoint se generaron temporalmente con Quick Look; algunos objetos vectoriales y gráficos pueden no representarse completamente, por lo que la iconografía se considera inventariada y pendiente de selección, no certificada visualmente.

### Qué aporta la plantilla

| Elemento | Evidencia |
| --- | --- |
| Naranja principal | `#FF7900`, RGB 255/121/0, visible en la paleta de la diapositiva 11 y en el esquema principal del tema. |
| Blanco y negro | `#FFFFFF` y `#000000`, como base de fondos y texto en la paleta y ejemplos. |
| Grises | `#86868E` y `#DEDDE0`, presentes en la paleta de la diapositiva 11. |
| Colores auxiliares | Amarillo `#FFE000` y naranja claro: `#FEC999` en la muestra de la diapositiva 11 y `#FFC999` en el esquema principal. No implica que la web deba utilizar todos. |
| Tipografía de los ejemplos | Arial, con títulos en negrita y cuerpo regular. La diapositiva 11 indica 25 pt para su título, 16 pt para subtítulo/cuerpo y 8 pt para referencias. Son medidas de presentación, no tamaños que deban trasladarse literalmente a la web. |
| Metadatos de fuente | El tema principal declara también Helvetica 75 Bold; otros temas internos conservan valores de Office. Esa mezcla impide deducir una obligación de cambiar de fuente basándose solo en los metadatos. Arial coincide con las indicaciones explícitas de los ejemplos. |
| Composición | Abundan el fondo blanco, texto negro, alineaciones, formas rectangulares y acentos naranja. Son referencias visuales, no una prohibición expresa de otras formas en una interfaz web. |
| Recursos adicionales | Biblioteca de iconos en la diapositiva 24 y recursos SVG/PNG. Las portadas incluyen un pie multimarcas propio del documento; no constituye una petición de incorporar esas otras marcas al portal Orange. |

### Qué tenemos y qué falta

| Área | Estado comprobado | Ajuste recomendado |
| --- | --- | --- |
| Logo | Correcto: el PNG del proyecto coincide exactamente con `ppt/media/image7.png` del PPTX. El archivo guardado en Moove tiene la misma huella SHA-1 que `assets/orange-logo.png`. Se muestra cuadrado, sin deformación, a 52 × 52 px en la barra revisada, con original de 1200 × 1200 px. | Mantener el recurso. Definir un tamaño y separación consistentes en acceso, barra y móvil. La plantilla no proporciona un mínimo web ni un área de protección cuantificada que podamos certificar. |
| Naranja principal | Correcto: `brandcolor` de Moove y botones efectivos usan `#FF7900`; navegación activa y acentos también lo emplean. | Conservarlo. |
| Tipografía | Arial efectiva en cuerpo, títulos propios y botones, tanto en escritorio como en móvil. | Conservar Arial. Revisar el espaciado negativo de los títulos propios: `-0.04em`, medido como −1,6 px en escritorio, frente al espaciado normal de los ejemplos de la plantilla. |
| Neutros y colores auxiliares | Parcial: menú `#111111`, pie `#282829`, fondo efectivo `#F2F3F7`, rótulos `#6B4A2B`, enlaces `#914100`, fondos crema `#FFF0DF`, entre otros. No todos proceden de la paleta del PPTX. El borde `#DEDDE0` sí coincide. | Definir una paleta web reducida y documentar sus variantes funcionales. Los valores no presentes en el PPTX son decisiones actuales de interfaz, no colores corporativos confirmados por el documento. |
| Botones y enlaces | Botones naranja con texto oscuro y buena legibilidad. Los enlaces oscuros son legibles, pero su matiz marrón se aleja del lenguaje visual principal de la plantilla. | Mantener el contraste de botones. Valorar enlaces negros subrayados con acento naranja, o aprobar expresamente una variante oscura accesible de naranja. |
| Componentes | Tarjetas y tablas con radio de 12 px, botones de 8 px y sombra en los desplegables; los ejemplos de contenido de la plantilla son principalmente rectangulares y planos. | Reducir y unificar radios y sombras si se busca una aproximación más fiel. Es una propuesta de adaptación visual, no una regla explícita del PPTX. |
| Márgenes y estructura | A 1440 px de ventana, la cabecera de Biblioteca/Cursos ocupa aproximadamente 1126 px, mientras que Incidencias/Notificaciones ocupa 1335 px. Gestión vuelve al ancho limitado de Moove. | Acordar un contenedor y márgenes comunes para las cuatro secciones y sus cabeceras. Las tablas pueden mantener desplazamiento horizontal en móvil. |
| Acceso | El logo se muestra aproximadamente a 458 × 458 px en el escritorio revisado y domina el formulario. Hay una franja naranja sobre el contenedor exterior y sombra de Moove. | Prioridad alta: equilibrar logo y formulario; por ejemplo, probar un logo de 96–120 px, como propuesta de diseño web, no como medida oficial de Orange. Situar el acento en el panel de acceso y unificar superficies. |
| Pie | Persisten «Contáctanos» y «Síganos» sin información visible, y la franja «Este tema fue desarrollado por» con Conecti.me. | Prioridad alta: completar contacto institucional o retirar visualmente bloques vacíos; revisar la presencia del crédito del tema para la interfaz del cliente. Conservar los enlaces funcionales que correspondan. |
| Iconografía | El portal usa iconos funcionales nativos de Moodle/Moove; los recursos de la plantilla no se han incorporado como familia visual del portal. | Opcional: seleccionar iconos adecuados para las cuatro secciones y homogeneizar estilo. No sustituir automáticamente todos los iconos nativos ni incorporar imágenes solo por estar en el PPTX. |
| Estados y alertas | Los estados usan colores funcionales naranja, azul y verde. La plantilla no define una paleta de estados para una aplicación. | Documentar una paleta funcional con texto y contraste; no considerar estos colores aprobados por el manual ni eliminarlos solo por no figurar en su paleta principal. |

### Contraste al adaptar la marca a web

Cálculos sobre los colores efectivos, no sobre los píxeles de una captura:

| Combinación | Contraste aproximado | Valoración para texto normal |
| --- | --- | --- |
| Texto `#111111` sobre naranja `#FF7900` | 7,18:1 | Supera el mínimo AA de 4,5:1. Es la combinación actual de botones. |
| Naranja `#FF7900` sobre menú `#111111` | 7,18:1 | Supera el mínimo AA de 4,5:1. |
| Naranja `#FF7900` sobre blanco | 2,63:1 | Inferior a 4,5:1; no usarlo como color único de texto pequeño. |
| Enlace actual `#914100` sobre blanco | 7,09:1 | Legible; revisar su adecuación visual a la marca. |
| Gris de la plantilla `#86868E` sobre blanco | 3,61:1 | Inferior a 4,5:1 para texto normal; no reemplazar indiscriminadamente el texto secundario por este gris. |

La referencia de contraste procede de [W3C, WCAG 2.2: Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), no de la plantilla de Orange. Esta comprobación puntual no es una auditoría completa de accesibilidad.

### Cómo abordar los ajustes sin tocar el core

1. **Logo y naranja de marca:** ajustes nativos de Moove en General y área de logos, siguiendo las instrucciones anteriores. No regenerar el logo: el original aportado ya es válido para esta revisión.
2. **Paleta, tipografía, acceso, radios y márgenes:** primero **Moove > Avanzado > SCSS en bruto**. Allí pueden ajustarse los selectores propios y del tema; conservar una copia reproducible en `assets/orange-theme.scss`. El valor efectivo vive en la configuración de Moodle y puede diferir del archivo del repositorio.
3. **Contacto y redes:** ajustes nativos de **Moove > Pie de página**. Los títulos vacíos y el crédito visual de esta versión no tienen un interruptor específico localizado en `settings.php`; su presentación se puede adaptar mediante SCSS, sin editar `templates/footer.mustache`.
4. **Componentes propios:** revisar conjuntamente SCSS de Moove y `local/orangeportal/styles.css`; sus valores se solapan. Un conjunto común de variables de color, tipografía y espaciado reduciría discrepancias. Solo modificar el plugin si la adaptación no puede resolverse con la configuración.
5. **Iconografía o cambios estructurales nuevos:** selección de recursos y, si se aprueba su incorporación, cambios aislados en el plugin. No es imprescindible para corregir primero colores, acceso y pie.

Orden propuesto: acceso y pie; paleta y tipografía; márgenes y componentes; iconografía opcional. Las reglas cuantitativas de protección del logo, tamaños mínimos y paleta de estados quedan pendientes de un manual digital específico o de validación del cliente. En esta revisión únicamente se ha documentado el diagnóstico: no se ha cambiado el tema, el CSS, la configuración ni los contenidos.

## Ajustes aplicados tras la revisión · 15/10/2026

Se aplicaron los cuatro puntos acordados en ese orden mediante **Moove > Avanzado > SCSS en bruto**, usando el archivo completo `assets/orange-theme.scss`. El color secundario de Moove se ajustó a `#000000` desde su API de configuración; el inicializador de demostración conserva ese mismo valor para nuevas instalaciones. No se editaron archivos de Moodle ni de Moove ni se cambió la lógica funcional del portal. La tabla de diagnóstico anterior describe el estado previo a estos ajustes.

### 1. Equilibrar el acceso

La tarjeta del formulario tiene un ancho máximo de 440 px; el logo mide 112 px en escritorio y 96 px en pantallas de hasta 575 px. Conserva su proporción y archivo original. El borde naranja de 4 px se sitúa en la tarjeta y se elimina del contenedor exterior. Si en el futuro se activan instrucciones de acceso en dos columnas, la tarjeta admite hasta 880 px. El idioma y el aviso de cookies pueden ajustarse en varias líneas sin desbordar; los botones de enlace usan texto oscuro.

Para modificarlo desde Moodle, editar las reglas de `#page-login-index`, `#loginlogo` y `.loginform` en el SCSS en bruto. No hay que sustituir el logo para cambiar su tamaño.

### 2. Limpiar el pie de página

Los bloques Contacto y Redes y sus columnas vacías se ocultan cuando no contienen enlaces, utilizando `:has(a)` en CSS. Si posteriormente se rellenan Website, teléfono, correo o redes en **Moove > Pie de página**, el bloque correspondiente vuelve a mostrarse. No se han inventado datos de contacto ni borrado sus ajustes.

El crédito visual Moove/Conecti.me se oculta mediante `body #page-footer .copyright { display: none !important; }`. Es necesario el `!important` porque el tema genera una regla que fuerza la visibilidad. La plantilla del tema permanece intacta. Se conservan soporte del sitio, información de sesión/cierre de sesión, privacidad y los controles funcionales del pie.

### 3. Unificar el ancho de las cuatro secciones

La regla `body#page-local-orangeportal-index #page.drawers .main-inner` utiliza el 100 % del ancho disponible con los márgenes existentes. Biblioteca, Cursos, Incidencias y Notificaciones comparten contenedor, alineación de cabecera y ancho de contenido en escritorio y móvil. `scrollbar-gutter: stable` reserva el espacio de la barra vertical para evitar saltos de ancho entre páginas cortas y largas. Los recursos, cursos y formularios nativos y las pantallas de administración conservan su distribución propia. Se mantiene el desplazamiento horizontal interno de las tablas en móvil.

### 4. Paleta común

Las variables CSS del bloque `:root` centralizan la paleta en el mismo campo de SCSS:

| Variable | Valor | Uso |
| --- | --- | --- |
| `--orange-brand` | `#FF7900` | Botones principales, bordes y acentos |
| `--orange-ink` | `#000000` | Navegación y pie, títulos, enlaces y texto sobre naranja |
| `--orange-text` | `#242424` | Texto principal |
| `--orange-muted` | `#595959` | Texto secundario legible sobre blanco |
| `--orange-border` | `#DEDDE0` | Separadores y bordes |
| `--orange-surface` | `#FFFFFF` | Superficies de contenido |
| `--orange-surface-muted` | `#F5F5F5` | Fondo general, cabeceras de tabla y selección de pestañas |

Se retiran los tonos marrones y crema de rótulos, enlaces, pestañas, contadores y tablas. La navegación conserva su acento naranja. Los botones principales pasan a negro con texto blanco al interactuar, en lugar de añadir otra variante naranja. Azul y verde se conservan exclusivamente como colores funcionales de estados En seguimiento y Cerrada, mediante variables separadas; no son parte de la paleta decorativa. Los mensajes nativos de validación y alerta mantienen sus colores funcionales.

El SCSS contiene reglas con mayor especificidad para prevalecer sobre los colores de `local/orangeportal/styles.css`, de modo que estos cuatro ajustes se hacen desde la configuración. Para reproducirlos, copiar el archivo completo al SCSS en bruto y configurar el menú secundario negro. Guardar y purgar cachés de Moodle. Al cambiar el SCSS en la administración, actualizar también la copia del repositorio antes de guardar el cambio en Git.

### Respaldo y revisión

Antes de aplicar se guardaron SCSS y colores anteriores en `backups/brand-before-20261015-2iigsdkk.json`, fuera de Git. Para recuperar ese aspecto, copiar su campo `scss` al ajuste de Moove y restaurar sus colores; guardar y purgar cachés. No se modificaron el logo, contenidos ni datos de usuarios.

Las capturas previas están en `backups/previews/brand-before-20261015/`; las finales usan el prefijo `marca-final-`. La comprobación cubre tamaño del logo, entrada al portal, soporte/sesión/privacidad, ausencia de bloques vacíos y crédito visual, anchos iguales, colores efectivos, menú desplegable y desbordamiento en móvil. La consulta de una colección nativa y de resultados también permitió comprobar el alcance de los estilos sin ampliar sus contenedores.
