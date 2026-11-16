# Configuración inicial de Orange por consola

Disponible desde `local_orangeportal` **2026111007**. Permite instalar/configurar un ambiente sin alternar consola y portal para los ajustes iniciales. No sustituye el proceso de copia del código ni instala PHP o programa cron.

## Flujo continuo

Después de copiar el código completo actualizado, ejecutar como usuario del servicio, desde la raíz real de Moodle:

```bash
php admin/cli/upgrade.php --non-interactive
php local/orangeportal/cli/configure.php --file=/ruta/privada/configuracion-orange.json
php local/orangeportal/cli/configure.php --file=/ruta/privada/configuracion-orange.json --apply
php admin/cli/purge_caches.php
```

La segunda orden valida sin cambiar la configuración ni crear recursos; la tercera aplica. No se hacen llamadas al servicio Teams, no se procesan tareas pendientes y no se ejecuta cron. El portal se utiliza al final para la comprobación funcional, no como paso intermedio obligatorio para estos ajustes.

Las comprobaciones de configuración pueden leer cachés de Moodle; «sin cambios» significa que no se aplica el plan ni se crean categorías/cohortes/asignaciones.

## Preparar el archivo del ambiente

Usar [configuracion-orange.json](../../ejemplos/configuracion-orange.json) como plantilla. Sistemas debe colocarla **fuera del directorio público de Moodle**, con permisos de lectura limitados al usuario que ejecute la configuración.

- `site_url`: URL exacta de ese campus, igual a `$CFG->wwwroot`. El script rechaza otro ambiente.
- `php_cli`: ruta absoluta confirmada con `command -v php` en el servidor que ejecuta Moodle.
- `portal.library`, `portal.training`, `portal.audience`: ID numérico existente o definición con `idnumber`, `name` y `create`. Si existe el Número ID se reutiliza; si no existe, solo se crea con `create: true`. La audiencia debe ser una cohorte manual de sistema.
- `portal.enabled`, `landingsection`, `automaticalerts`: ajustes equivalentes a Configuración Orange.
- `portal.managedcohorts`: IDs de cohortes existentes que se delegan a Orange; se comprueba que sus miembros estén dentro de la audiencia elegida y que sus matrículas sincronizadas no afecten cursos externos.
- `theme.apply_to_cohort`: asignar Orange solo a esa cohorte. No cambia el tema predeterminado del campus.
- `theme.enable_cohort_themes`: habilitar el ajuste global de temas de cohortes. Tiene el mismo alcance que activarlo desde Moodle: revisar si otras cohortes ya tenían temas guardados.
- `teams.enabled`, `types`, `actions`: activación, tipos y acciones que pueden avisar. La plantilla empieza con Teams desactivado, no sobrescribir sin revisar una configuración ya activa.
- `teams.url_file`: archivo privado que contiene únicamente la URL HTTPS firmada. No se admite pegar la URL en argumentos de consola ni se imprime su valor. Si se omite o es null, se conserva la URL existente. Un archivo vacío borra esa URL; no se permite activar Teams sin una URL válida.
- `catalog_file`: ruta del JSON inicial aprobado o null. Solo sirve para un Orange vacío. Usar el inventario para revisar qué estructura se cargará.
- `administrators`: lista de nombres de usuario **ya existentes, activos y sin administración global**. Los añade a la audiencia y les asigna el rol restringido Administrador Orange. No crea cuentas ni cambia contraseñas.
- `allow_scope_change`: false por defecto. Para cambiar raíces o cohorte que ya estaban configuradas, el archivo revisado debe declarar true expresamente.

Las rutas relativas de los archivos de catálogo y URL se resuelven respecto a la carpeta del archivo principal. Todos deben estar fuera del directorio web. No guardar archivos reales con URLs o credenciales en Git. Se pueden proporcionar desde el gestor de secretos del despliegue.

Los parámetros no indicados conservan su valor. No se acepta cualquier variable de Moodle: solo los ajustes enumerados, para detectar errores y no modificar ajustes ajenos por accidente. Los valores forzados por config.php se comprueban; hay que resolver una discrepancia allí antes de aplicar.

## Primera instalación sin conocer los IDs

La plantilla define las raíces `orange_pre_library` y `orange_pre_training` y la cohorte `orange_portal_users`. Se crean con las APIs de Moodle y el script configura automáticamente sus IDs. Los identificadores que contienen pre son los que exige el importador existente, también en producción.

Para cargar el árbol y catálogos en la misma ejecución, establecer `catalog_file` a la ruta de `catalogos-orange.json`. Primero se valida el catálogo, luego se crean recursos y se importa dentro de la misma transacción. Se rechaza un Orange que ya tenga catálogos/publicaciones o raíces no vacías. No vaciar datos para forzar la carga.

Al completar la carga se guarda su huella. Repetir el mismo configurador con el mismo archivo no duplica las categorías. Si el archivo del catálogo cambia, el script rechaza convertirlo en una supuesta sincronización: hay que revisar esa migración.

Si el catálogo ya se había importado antes con el cargador anterior o de forma manual, utilizar `catalog_file: null` y las raíces existentes. El configurador no puede deducir que otro importador cargó exactamente el mismo archivo.

## Actualización de un Orange existente

No utilizar las definiciones create=true para trasladar accidentalmente el ámbito. Conservar los IDs actuales o referirse a sus Números ID existentes. Se pueden consultar por consola sin mostrar credenciales:

```bash
php admin/cli/cfg.php --component=local_orangeportal --name=librarycategory
php admin/cli/cfg.php --component=local_orangeportal --name=coursescategory
php admin/cli/cfg.php --component=local_orangeportal --name=audiencecohort
```

Ejemplo de la sección portal con IDs **ficticios**, que se deben sustituir:

```json
"portal": {
  "library": 120,
  "training": 121,
  "audience": 35
}
```

La URL de Teams se suministra en un archivo independiente si hay que cambiarla. Cambiarla no reenvía pendientes al nuevo destino: el comportamiento de las tareas sigue siendo el documentado en la integración Teams.

## Qué ejecuta además de guardar valores

Usa las APIs nativas de categorías/cohortes y configuración. Reconstruye el acceso al catálogo Orange, la asignación técnica de matrícula y los ámbitos de los administradores Orange existentes. No concede permisos a arquetipos globales del campus. Las validaciones del configurador cubren el ámbito elegido, los recursos, las opciones y los valores forzados; no es un conjunto de escrituras directas a tablas.

Esto es la diferencia frente a limitarse a ejecutar `admin/cli/cfg.php`: ese comando guarda una variable, pero no dispara las sincronizaciones específicas del portal.

## Cron y tareas del sistema

La configuración del cron pertenece al servidor o a su infraestructura de despliegue. Se puede automatizar en el mismo pase mediante su programador de tareas; no requiere abrir Moodle. Conservar el cron existente si ya ejecuta esta instalación cada minuto.

Ejemplo para el crontab del usuario de servicio, adaptando las rutas:

```text
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php
```

No es una orden para pegar en la consola. Sistemas debe configurar esa programación con su herramienta habitual. La ejecución puntual siguiente procesa tareas reales del campus y no programa ejecuciones futuras:

```bash
php admin/cli/cron.php --keep-alive=0
```

El configurador no la ejecuta por defecto, para no convertir una carga de parámetros en envío de mensajes pendientes.

## Alcance y comprobación

### Comprobación de Orange con Moove 4.5.2 — 11 de noviembre de 2026

Producción informa Moove **4.5.2 (`2024100802`)**. Se ha descargado la [rama oficial de Moodle 4.5, revisión `8b22ed5`](https://github.com/willianmano/moodle-theme_moove/tree/8b22ed5b3924e6ee2fe4bc8d04f4050f5a6057ee) y probado con el código Orange actual sobre una copia aislada de la base de datos y `moodledata` locales, con Moodle **4.5.1 (Build 20241209)** y PHP 8.3. No se ha accedido ni modificado producción. No se ha sustituido el Moove del entorno local habitual.

- El código oficial 4.5.2 difiere del Moove local 4.5.1 en `version.php` y `classes/output/core/admin_renderer.php`. Las funciones SCSS, configuración, renderer principal, plantillas y estilos que hereda Orange coinciden.
- La compilación de CSS LTR de Orange y Moove termina correctamente.
- Pasan las 22 comprobaciones de navegación del navegador: cuatro secciones, etiquetas en español/inglés, sección activa, menú móvil y navegación nativa del usuario externo.
- Se reproduce un error en **Administración del sitio → Notificaciones**, con Moove como tema del sitio: `Unknown method called against theme_moove\output\core\admin_renderer :: moodlenet_removal_warning`. Moove 4.5.2 llama a ese método y el core local 4.5.1 no lo implementa. Cumplir la dependencia mínima declarada no basta para evitar este fallo.

El verificador siguiente comprueba los contratos PHP y detecta esa incompatibilidad, sin actualizar temas, ejecutar cron o modificar ajustes. Ejecutar como usuario del servicio, cuando esté disponible el código actualizado del plugin:

```bash
php local/orangeportal/cli/check_theme_compatibility.php
```

Devuelve `0` si pasan las comprobaciones estructurales y `1` ante incompatibilidad/error. En la prueba devuelve `0` con Moove 4.5.1 y `1` con Moove 4.5.2 sobre Moodle 4.5.1. No sustituye las pruebas de navegador ni garantiza compatibilidad con personalizaciones del cliente. Las evidencias locales están en `backups/previews/moove-4.5.2-20261111/` (fuera de Git).

**Resultado:** la navegación de Orange funciona con el Moove oficial 4.5.2 probado; no se puede dar por válida la combinación completa con Moodle 4.5.1 por el fallo administrativo reproducido. Confirmar la versión exacta de Moodle de producción y comprobar allí los contratos antes de aprobar el despliegue. El instalador debe conservar Moove y detenerse con un aviso ante una incompatibilidad; no sustituirlo o parchearlo automáticamente.

- Automatiza raíces, cohorte de audiencia, IDs, tema de cohorte, PHP CLI, Teams, colectivos delegados existentes y administradores Orange existentes. Puede importar la estructura inicial del JSON en la misma operación.
- No crea cuentas, no importa perfiles XML arbitrarios ni carga documentos/cursos. Son operaciones diferentes que deben incorporarse al proceso de carga si también se quieren automatizar; no se anuncian como cubiertas por este script.
- Después del pase comprobar permisos con cuentas separadas, tema de un usuario externo, árboles de contenido, una publicación con/sin aviso y la ejecución automática de cron.

Prueba local, con cambios temporales revertidos: `php local/orangeportal/cli/check_deployment_config.php`. No ejecutar pruebas locales en producción.

Para una ejecución puntual, `--keep-alive=0` evita la espera adicional de Moodle 4.5 (tres minutos por defecto). Para operación permanente se conserva el programador o servicio del servidor. No es necesario mantener una consola personal abierta.
