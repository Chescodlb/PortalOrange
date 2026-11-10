# Avisos de contenido Orange en Microsoft Teams

Disponible a partir de `local_orangeportal` **2026110602**, sin instalar plugins Microsoft ni modificar Moodle o Moove. Usa el flujo de Power Automate suministrado por el cliente. La URL firmada se configura por ambiente y no forma parte del código.

## Configurar desde la web

1. Entrar con un **administrador general de Moodle** que tenga `moodle/site:config`. El rol Administrador Orange no concede este permiso ni acceso a la URL.
2. Abrir **Administración del sitio → Portal Orange → Mensajería Teams**. Ruta directa: `/admin/settings.php?section=local_orangeportal_teams`.
3. Pegar la URL HTTPS completa del disparador de Power Automate en **URL del servicio Power Automate**. El campo se oculta y Moodle enmascara su valor en el historial de configuración. Se almacena como configuración del plugin, por lo que también hay que proteger las copias de la base de datos.
4. Elegir los tipos: Incidencias, Comunicados, Biblioteca y Formaciones. También se puede activar o desactivar por separado el aviso de alta y el de modificación.
5. Activar **Activar envíos a Teams** y guardar. Guardar la configuración no envía ningún mensaje de prueba ni recorre el contenido existente.
6. Asegurar que el servidor ejecuta el cron habitual de Moodle cada minuto. No hay un servicio adicional que instalar.

**Durante el desarrollo no se ha llamado al endpoint real de pre.** La instalación queda desactivada y con URL vacía. Activarla en pre permitirá que futuras publicaciones reales generen mensajes.

## Cuándo se avisa

| Contenido | Alta | Modificación |
| --- | --- | --- |
| Incidencia / comunicado | Guardar publicado con **Avisar por Teams al guardar** marcado | Guardar publicado con esa casilla marcada; se excluyen cambios solo de estado |
| Documento de Biblioteca | Guardar el recurso visible con la casilla Teams marcada | Editar y guardar visible con la casilla Teams marcada |
| Formación | Guardar la primera publicación con la casilla Teams marcada | Editar y guardar visible con la casilla Teams marcada |

Desde **2026111005**, las cuatro áreas tienen el mismo control en **Destinatarios**: **Visible para sus destinatarios** publica o retira el contenido, y **Avisar por Teams al guardar** solicita el aviso de ese guardado. La casilla Teams comienza desmarcada en cada edición y requiere la integración administrativa activa. Si se guarda oculto, no se envía aunque se manipule esa casilla. No es una suscripción a avisos automáticos posteriores.

Añadir o modificar actividades dentro de un comunicado o una formación no envía por sí solo un aviso: se solicita desde el formulario de la publicación o formación al guardar. Las lecturas, accesos, notas, finalizaciones, avisos personales, cambios solo de estado y eliminaciones no anuncian contenido. Otros proyectos y cursos técnicos quedan excluidos. Los trabajos pendientes cuyo contenido ya no coincide con el guardado autorizado se descartan para no anunciar silenciosamente una edición posterior.

## Petición al flujo

```json
{
  "titulo": "Nueva incidencia: Interrupción del servicio",
  "contenido": "Impacto: Crítico\nCliente afectado: Empresas\nProducto: Activación\n\nDescripción de la incidencia.",
  "url": "https://campus.example.invalid/local/orangeportal/view.php?id=123"
}
```

- Método `POST`; `Content-Type: application/json; charset=utf-8`.
- El contrato mantiene exactamente `titulo`, `contenido` y `url`.
- Títulos diferenciados: Nueva incidencia / Incidencia actualizada, Nuevo comunicado / Comunicado actualizado, Nuevo documento de Biblioteca / Documento de Biblioteca actualizado, Nueva formación / Formación actualizada.
- El extracto se convierte a texto, con un máximo de 2.000 caracteres para la descripción. No adjunta archivos, notas, listas de destinatarios ni datos del autor.
- Los enlaces usan la URL configurada en Moodle. Mantienen autenticación, permisos y seguimiento de lectura; no son enlaces públicos de descarga.
- El mensaje compartido usa español para no depender del idioma del editor. El panel administrativo está traducido a español e inglés.

**Destino:** el flujo configura el chat, persona o canal de Teams. Una cohorte, grupo o destinatario Moodle no se convierte automáticamente en un destino Teams. Debe elegirse un canal adecuado para el contenido que se enviará.

## Cola, errores y seguimiento

Los guardados encolan tareas `\local_orangeportal\task\send_teams` en la tabla nativa `task_adhoc`. No hay tablas nuevas ni copia de credenciales o contenido en la cola. La espera inicial es de un minuto, más el tiempo hasta que el cron recoja la tarea. Los avisos idénticos pendientes se agrupan; antes de enviar se consulta de nuevo el contenido vigente.

El cliente HTTPS respeta proxy y restricciones HTTP de Moodle, verifica certificados, no sigue redirecciones y limita conexión a 5 segundos y petición a 15 segundos. Cualquier respuesta 2xx se considera **aceptada por el flujo**, no confirmación de entrega final en Teams.

Los fallos conservan la tarea y Moodle reintenta con espera creciente, hasta **cinco intentos**. El panel muestra último intento, resultado HTTP y última aceptación, además de enlaces a la cola y registros de tareas. Si se agotan los intentos, revisar el motivo y usar las herramientas nativas de tareas de Moodle para reprogramar la tarea detenida. Los registros de la integración excluyen la URL firmada y el cuerpo de la respuesta del proveedor.

Antes de cada intento se vuelve a comprobar activación, tipo de aviso, visibilidad y existencia del contenido. Si se retira o elimina el contenido, la tarea no envía. Al cambiar la URL, las tareas destinadas a la URL anterior se descartan; no se trasladan al nuevo destino. Desactivar la integración impide nuevos envíos y descarta las tareas pendientes cuando el cron las procesa; no hay reenvío histórico automático al reactivarla.

Un timeout puede ocurrir después de que el flujo haya aceptado el mensaje. La cabecera `X-Orange-Event-Id` permanece estable durante los reintentos de una tarea y permite deduplicar en Power Automate si se añade esa lógica al flujo. Sin deduplicación en el receptor, no se garantiza una única entrega ante un fallo ambiguo.

## Desplegar esta actualización

1. Copiar la versión completa y actual de `local/orangeportal/`, comprobando que `version.php` contiene `2026110602`. Esta integración no requiere cambios en el tema.
2. Desde la raíz de Moodle ejecutar:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

3. Configurar el panel anterior con la URL específica del ambiente. No copiar la base de datos de desarrollo ni reutilizar automáticamente la URL de pre en producción.
4. Confirmar que el cron está funcionando. Con la integración activa, una publicación real nueva debe dejar una tarea y, después de ejecutarse, una aceptación HTTP en el panel.

## Prueba local reproducible

Con Docker local y Postman CLI instalados:

```bash
node scripts/test-orange-teams.mjs
node scripts/test-orange-browser.mjs --teams-settings-only
```

La primera prueba ejecuta el código real de Moodle (guardados, eventos, cola, cliente HTTPS y reintentos) contra la simulación local `postman/simulations/orange-teams.sim.yaml`. Genera un certificado temporal para el simulador; no desactiva la verificación TLS del cliente ni cambia la política HTTP persistida del sitio. Al terminar limpia datos ficticios y restaura los ajustes iniciales.

La segunda comprueba el panel en ambos idiomas y su acceso restringido. Requiere que la instalación local conserve el estado inicial: integración desactivada y URL vacía.

El contrato local procede de los tres campos y método aportados por el cliente; las respuestas de error son escenarios de prueba, no una afirmación sobre respuestas concretas del flujo real. No se ha buscado un mock remoto del propietario: no hace falta conectar ni publicar recursos en Postman para ejecutar estas pruebas locales. El funcionamiento final del flujo y su entrega en Teams deben validarse en pre cuando se autorice enviar.

## Publicación desde Destinatarios — 2026111005

Las nuevas formaciones y documentos empiezan con **Visible para sus destinatarios** desmarcado. Se puede publicar en el mismo guardado marcándolo; no se exige una pantalla posterior. **Editar** en Formaciones abre directamente el formulario nativo, con accesos a contenido, participantes y copia de edición. Guardar una formación nueva continúa a su contenido; guardar su edición vuelve al listado.

Desmarcar Visible y guardar retira la publicación sin borrar matrículas, intentos ni contenidos. La primera publicación conserva su fecha propia para los indicadores. La primera publicación de una formación se anuncia como `created`; las posteriores, como `updated`. En todos los casos se exige marcar Teams para ese guardado. Se invalidan los avisos pendientes antiguos al retirar una formación. Los mensajes ya aceptados por el flujo no pueden retirarse desde Moodle.

Los enlaces antiguos a `training_setup.php` redirigen al formulario de edición; ya no muestran una pantalla intermedia.
