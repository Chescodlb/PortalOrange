# Probar y revisar el portal local

Abrir http://localhost:8080 para revisar la primera versión. La entrada por defecto es Biblioteca. No se ha publicado el portal ni se ha hecho push al repositorio.

## Usuarios ficticios

| Usuario | Uso |
| --- | --- |
| Usuario de `MOODLE_ADMIN_USER` en `.env` | Administración completa y árbol de biblioteca completo |
| `agente.orange` | Dos colecciones documentales, formación y avisos asignados |
| `agente.otro` | Equipo sin acceso a esas colecciones ni avisos |
| `editor.orange` | Publicación y categorías de avisos; gestión de categorías dentro de Biblioteca y Cursos; no obtiene informes globales |
| `coordinador.orange` | Resultados de sus cursos y confirmaciones de publicaciones |
| `formador.orange` | Documentos y colecciones de Biblioteca; creación, edición y evaluación de sus cursos |

La contraseña del administrador está en `MOODLE_ADMIN_PASSWORD`. Los cinco perfiles ficticios usan `ORANGE_DEMO_PASSWORD`, también en `.env`. No copiar esas claves a documentación o Git.

## Recorrido de revisión

1. Entrar como administrador, comprobar logo y las cuatro secciones, y desplegar RPV/CENTREX.
2. Entrar como agente Orange: consultar documentos, abrir el curso y probar evaluación y encuesta.
3. Abrir una incidencia, comprobar el registro de lectura, confirmarla y cambiar su estado. El estado se comparte con los demás destinatarios.
4. Abrir y confirmar una notificación. Comprobar el filtro Pendientes de confirmar.
5. Entrar como editor y crear un borrador. Seleccionar destinatarios y publicarlo; después editar el texto para comprobar que se pide otra confirmación.
6. Entrar como coordinador y consultar la trazabilidad y los informes nativos de formación.
7. Entrar como agente de otro equipo y comprobar que no tiene contenido asignado ni acceso directo a los avisos del equipo Orange.
8. Probar una pantalla estrecha: logo, apertura del menú y árbol desplegable.

## Comprobaciones automáticas

Se han comprobado en navegador el menú, las secciones, el logo, el acceso delegado a administración, asignaciones, confirmaciones, estado compartido, denegación de accesos y anchura móvil. Las capturas están en `backups/previews/`, excluidas de Git.

```bash
node scripts/test-orange-browser.mjs
docker compose exec -T -u www-data web php local/orangeportal/cli/check_access.php
docker compose exec -T -u www-data web php local/orangeportal/cli/check_training.php
```

La prueba de navegador usa Chrome instalado en macOS y deja ejemplos de lecturas y cambios de estado en los usuarios ficticios. También crea un comunicado de ejemplo desde el formulario administrativo. Las pruebas PHP de acceso y formación revierten sus cambios al terminar.

La prueba de acceso verifica permisos, retirada de pertenencia a cohorte, borradores, idempotencia, transiciones auditadas y confirmaciones de cada versión. La prueba de formación simula un intento mediante las APIs nativas: comprueba nota 100/100, finalización por aprobado, alerta individual y exclusión de notas ocultas. El intento simulado se revierte y no queda como una nota del agente.

## Git y aprobación

El commit inicial es `805cf77`. Los cambios posteriores se dejan en el árbol de trabajo para revisión. No se crean nuevos commits sin mostrar los cambios al usuario y recibir su aprobación. El usuario realizará el push cuando decida publicar.

Para revisar el código y la documentación:

```bash
git status --short
git diff HEAD --stat
git diff HEAD
```

Los archivos nuevos no aparecen en `git diff` hasta prepararlos; se pueden leer directamente desde `docs/`, `scripts/` y `moodle/local/orangeportal/`. Esta revisión no requiere preparar ni commitear archivos.

## Respaldo y recuperación local

Antes de instalar se creó una copia SQL en `backups/before-orange-20261013-095418.sql`. Los intentos posteriores del inicializador también generan respaldos con fecha en ese directorio. Todos permanecen locales.

Para desactivar la navegación personalizada sin restaurar, usar Portal Orange > Configuración Orange y cambiar la página por defecto en Navegación. Una restauración completa requiere también el respaldo de archivos si se han modificado documentos.

### Revisar los desplegables de la barra superior

Ejecutar `node scripts/test-orange-browser.mjs --navigation-only` para comprobar los cuatro menús, selección de categoría, cierre con Escape, versión móvil, árbol completo para todos y contenido/contadores limitados por permisos. Los resultados y capturas quedan en `backups/previews/`, excluido de Git. También se puede comparar manualmente `agente.orange` con `agente.otro`: deben ver el mismo árbol de Biblioteca, pero solo el primero ve las colecciones de ejemplo.

### Categorías de Biblioteca y Cursos dentro del portal

Con `editor.orange`, entrar en **Administración > Gestionar categorías** y alternar Biblioteca, Cursos, Incidencias y Notificaciones. Todas usan un formulario del portal, la opción seleccionada se remarca y el selector de categoría superior contiene únicamente la sección elegida. Biblioteca incluye RPV/CENTREX; Cursos no la incluye. Un agente no puede abrir esta gestión.

Ejecutar `docker compose exec -T -u www-data web php local/orangeportal/cli/check_categories.php` para verificar creación nativa, subcategorías, renombrado, movimiento, rechazo de ciclos, protección de raíces y aislamiento entre secciones. Todas las categorías de prueba se revierten mediante una transacción.

### Guardado de notificaciones

Ejecutar `docker compose exec -T -u www-data web php local/orangeportal/cli/check_notice_form.php`. Reproduce los datos enviados por el formulario Moodle con `editor.orange`, comprueba publicación individual, borrador con cohorte y edición con versión nueva, tanto para notificaciones como para incidencias. Incluye la validación de publicaciones sin destinatarios y revierte todos los datos de prueba. Los campos bloqueados de notificaciones tienen valores por defecto válidos para evitar el error de parámetro no válido.

La prueba del formulario incluye el título «Pruebas nueva Notificación» en todas las categorías de Notificaciones y simula campos internos vacíos de formularios abiertos antes de la corrección. Estado e impacto de notificaciones son constantes del formulario y prevalecen sobre esos valores enviados; en incidencias siguen siendo editables.

### Publicar contenidos con el formador

`formador.orange` ve **Nuevo documento** y **Nueva colección documental** en Biblioteca, **Nuevo curso** en Cursos y **Nueva evaluación** en cada curso editable. Nuevo documento pide una colección autorizada y abre el formulario nativo Archivo; no guarda contenidos hasta que se complete ese formulario. Colecciones y cursos usan el formulario nativo de creación de cursos y evaluaciones el de Cuestionario. Un editor de categorías o un agente no debe ver esos accesos.
