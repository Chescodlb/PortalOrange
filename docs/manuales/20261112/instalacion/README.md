# Primera instalación de Portal Orange · 12 de noviembre de 2026

[Manual PDF](05_Instalacion_Portal_Orange.pdf) · [Word editable](05_Instalacion_Portal_Orange.docx) · [Comandos TXT](Comandos_instalacion_Orange.txt) · [Paquete completo](Instalacion_Orange_20261112.zip)

El manual parte de un **Moodle operativo** e instala **Portal Orange 2026111100** y **tema Orange 2026102900**. Incluye dos caminos de configuración: JSON por consola o administración web de Moodle. Después explica la cuenta delegada, usuarios y perfiles, categorías, contenidos, matrículas, cron, Teams opcional, comprobaciones y reversión.

Los comandos corresponden a Linux con Bash y PHP CLI. Las rutas y usuarios son ejemplos que Sistemas debe adaptar. El TXT está organizado por capítulos: **no es un script para ejecutarlo entero**. Hay que elegir la vía A o B y respetar las comprobaciones previas.

## Material de apoyo

- `material/componentes/orangeportal-2026111100.zip`: plugin completo, carpeta superior `orangeportal/`, destino `Moodle/local/`.
- `material/componentes/orange-2026102900.zip`: tema hijo, carpeta superior `orange/`, destino `Moodle/theme/`.
- `material/componentes/moove-4.5.1-2024100801-opcional.zip`: copia de la dependencia local de referencia para la base Moodle 4.5.1 comprobada. Si ya existe Moove, conservarlo y comprobar compatibilidad; no degradarlo ni sobrescribirlo automáticamente. El paquete conserva sus avisos y licencia.
- `material/configuracion/`: plantilla JSON sin credenciales, esquema y ejemplo del contrato Teams. La URL del campus debe sustituirse antes de aplicar.
- `material/catalogo_referencia/`: estructura e inventarios de referencia. Confirmar aprobación antes de importar. No contiene documentos, cursos, usuarios, contraseñas ni resultados.
- `material/cron/activar-cron-orange.sh`: ayuda opcional Linux. No usar si el campus ya tiene cron programado.
- `material/roles_referencia/`: seis XML para comparar o configurar ambientes anteriores. La instalación limpia 2026111100 ya crea estos roles; no duplicarlos.
- `fuentes/`: contenido editable, HTML de impresión, procedencia de capturas, inventario de componentes y validación.
- `SHA256SUMS.txt`: permite comprobar los archivos del paquete descomprimido.

La instantánea del código procede del espacio de trabajo del 12/11/2026. Los componentes Orange mantienen la madurez alfa declarada. Debe validarse esta entrega en una copia del campus antes de producción. No se ha desplegado en PRE ni producción para crear el manual.

Las capturas son reales del entorno local. Las imágenes reutilizadas del 11/11/2026 pertenecen a los manuales de usuarios; las nuevas capturas administrativas son del 12/11/2026. No presentan números de categorías locales como IDs que deban copiarse a otro campus. La URL Teams se mantiene enmascarada por el campo nativo.

## Compatibilidad relevante

La base local comprobada es Moodle 4.5.1 Build 20241209, PHP 8.3 y Moove 4.5.1 (`2024100801`). Se había reproducido una incompatibilidad de Notificaciones al combinar ese core con Moove 4.5.2 (`2024100802`). El manual exige ejecutar `local/orangeportal/cli/check_theme_compatibility.php` y resolver cualquier incompatibilidad; cumplir la versión mínima declarada no equivale a una validación completa.

## Generar y comprobar esta entrega

Desde la raíz del repositorio:

```bash
python3 scripts/generar-manual-instalacion-orange.py
node scripts/generar-manuales-pdf-orange.mjs docs/manuales/20261112/instalacion
python3 scripts/validar-manual-instalacion-orange.py
```

Estas herramientas generan documentación y el paquete local. No se ejecutan en el servidor Moodle como parte de la instalación. Los comandos operativos del servidor están en el manual y su TXT.
