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

### CRUD de Estados e Impactos

Ejecutar `docker compose exec -T -u www-data web php local/orangeportal/cli/check_notice_options.php`. Comprueba los permisos de editor y coordinación, creación, renombrado, desactivación, eliminación de opciones sin referencias, protección de publicaciones e histórico y conservación de al menos una opción activa. Comprueba además que una opción inactiva se puede conservar en su aviso actual, pero no reasignarse ni usarse en un aviso nuevo. La prueba revierte publicaciones y ajustes del catálogo; está limitada al entorno local de demostración con los valores iniciales.

Revisión manual: con editor o coordinador, entrar en **Administración > Estados e impactos**. Crear una opción, editar su nombre, desactivarla y reactivarla; comprobar que aparece entre las opciones de nuevas incidencias solo cuando está activa. Intentar eliminar Abierta, Normal u otra opción usada: se muestra el bloqueo y se puede desactivar mediante Editar. Crear una opción sin usarla y eliminarla tras confirmar. Un agente no puede acceder a `options.php`; coordinación no recibe permiso de publicación por gestionar estos catálogos. La pantalla tiene textos en español e inglés.

### Breadcrumbs de páginas personales

Ejecutar `node scripts/test-orange-browser.mjs --breadcrumb-user-only`. Con agente y coordinador verifica Preferencias, Calificaciones personales, Calendario y Archivos privados en español e inglés. La raíz debe ser Mi cuenta / My account, enlazar al perfil y no contener «Página Principal» ni enlaces a la portada que redirige a Biblioteca. Las capturas quedan en `backups/previews/`. `--breadcrumb-only` sigue verificando las rutas de cursos, biblioteca y gestión.

### Administración en español e inglés

Ejecutar `node scripts/test-orange-browser.mjs --admin-languages-only`. Verifica con editor las pestañas de incidencias/notificaciones, sus tablas, formularios de creación/edición, las cuatro gestiones de categorías y los catálogos. Con coordinador comprueba resultados, encabezados, instrucciones e histórico. Incluye Nuevo documento con formador y los ajustes existentes del plugin y la vista de gestión de un aviso con administrador. No cambia contenido y genera capturas en `backups/previews/`. Los nombres guardados por los usuarios permanecen en su idioma original.

## Aislamiento por cohorte · 19/10/2026

Ejecutar `node scripts/test-orange-browser.mjs --isolation-only`. Comprueba acceso común, tema hijo y logo Orange, cuatro secciones, breadcrumbs personales, perfil editor y ajustes del administrador general. Con `usuario.externo` comprueba menú/tema nativos y denegación de enlaces directos al portal. Produce capturas y `backups/previews/isolation-checks.json` (ignorados por Git). No crea publicaciones ni cambia sus estados.

Ejecutar `docker compose exec -T web php local/orangeportal/cli/check_experience.php`. Verifica pertenencia y tema nativos, entrada/salida de cohorte, que el rol editorial sin cohorte no habilita el portal, que la cohorte no concede permisos y que el portal se cierra si no tiene cohorte o se desactiva. Los datos temporales se revierten y los ajustes se restauran. Limitado a la demo local. No ejecutar simultáneamente con las pruebas de navegador.

Los recorridos anteriores que asumían que el administrador general también tenía el portal Orange deben ejecutarse con una cuenta administrativa incluida expresamente en la cohorte. El administrador general de la demo queda fuera por defecto para probar la convivencia. Volver a iniciar sesión tras cambiar miembros o temas de cohortes, según el mecanismo nativo de Moodle.

## Biblioteca por columnas · 20/10/2026

Ejecutar `node scripts/test-orange-browser.mjs --library-columns-only` y `docker compose exec -T web php local/orangeportal/cli/check_library_browser.php` por separado. El primer recorrido no cambia datos; el segundo crea actividades/roles/grupos temporales en una transacción y los revierte. Comprobar RPV y CENTREX separadas, el contenido actual bajo RPV, títulos de columna por categoría, recursos nativos con botón tras seleccionar, búsqueda por nombre/descripción en toda la biblioteca y las restricciones por usuario/grupo. Los recorridos antiguos que abrían un desplegable Biblioteca quedan sustituidos por esta prueba.

## Orden y lectura de documentos · 20/10/2026

Ejecutar `docker compose exec -T web php local/orangeportal/cli/check_document_reads.php` para las pruebas reversibles del modelo y `node scripts/test-orange-browser.mjs --library-reading-only` para la interfaz. El recorrido de navegador abre un documento del agente y registra esa vista real; comprueba que el formador conserve sus propios estados, el orden por fecha/nombre, el selector compacto automático, móvil y textos en inglés.

## Flujo de Incidencias · 20/10/2026

Ejecutar `docker compose exec -T web php local/orangeportal/cli/check_incident_workflow.php` para la prueba reversible del modelo y `node scripts/test-orange-browser.mjs --incidents-workflow-only` para la interfaz. Verifica formador con funciones del editor, exclusión de cerradas para agentes, ausencia de confirmación, seguimiento de coordinador sin cambios de estado, campos de cliente/producto, filtros laterales, indicador de lectura, móvil e inglés. Los recorridos anteriores que permitían al agente actualizar estados o confirmar incidencias quedan sustituidos por esta prueba.
