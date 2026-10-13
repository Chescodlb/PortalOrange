# Avisos de incidencias en Microsoft Teams

La integración debe avisar a personas, chats grupales o canales cuando se publique una incidencia. Moodle seguirá gestionando destinatarios, acceso, estado compartido y confirmaciones de lectura. La evaluación se realiza en la rama `feature/teams-notifications`; todavía no hay envío real ni plugins Microsoft instalados.

## Plugins oficiales de Moodle

Se ha revisado el código oficial de Microsoft 365 para Moodle, etiqueta `v4.5.8`, commit `d2dbddcd95a8bf2476367ee6ff036f0ab03c0687`. Tanto `local_o365` como `auth_oidc` requieren Moodle `2024100700`, compatible con la instalación 4.5.1. `local_o365` depende de `auth_oidc` versión `2024100740`.

La compatibilidad declarada no sustituye una prueba de instalación. La descarga para revisión permanece en una carpeta temporal, fuera del código servido por Moodle.

La actualización `2023100904` de `local_o365` elimina expresamente la integración del bot: borra sus ajustes y la tabla `local_o365_notif`. Esa eliminación sigue presente en 4.5.8. No se puede utilizar la documentación antigua del bot como guía de instalación actual. Los plugins mantienen otras funciones de identidad y sincronización de cursos con Teams, pero no ofrecen aquel mecanismo de avisos.

Fuentes: [versión oficial](https://github.com/microsoft/o365-moodle/blob/v4.5.8/local/o365/version.php), [eliminación del bot](https://github.com/microsoft/o365-moodle/blob/v4.5.8/local/o365/db/upgrade.php).

## Primera alternativa a validar

Teams Workflows y Power Automate permiten configurar la acción «Post a message in a chat or channel». Como Flow bot admite canal, chat grupal y mensaje directo a una persona. La propuesta es reutilizar ese envío y desarrollar solo la conexión de las incidencias del portal con el flujo. La disponibilidad, licencias, políticas y autenticación del disparador deben validarse en el entorno del cliente.

Si esta alternativa no está disponible, se evaluará una aplicación de Teams con mensajes proactivos. Requiere instalar la aplicación en los ámbitos destinatarios y configurar su acceso. No usar permisos de migración de Microsoft Graph para enviar avisos ordinarios.

Fuentes: [envíos mediante Power Automate](https://learn.microsoft.com/en-us/power-automate/teams/send-a-message-in-teams), [Workflows y webhooks](https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using), [mensajes proactivos](https://learn.microsoft.com/en-us/microsoftteams/platform/bots/how-to/conversations/send-proactive-messages).

## Configuración para la prueba en Microsoft

1. Confirmar que Workflows o Power Automate están habilitados y designar un responsable y un sustituto del flujo.
2. Preparar una persona, un chat grupal existente y un canal de pruebas. No es necesario crear o sincronizar equipos para todos los cursos.
3. Crear un flujo con un disparador HTTPS adecuado para recibir el aviso del portal. Elegir su autenticación y comprobar las licencias antes de conectar Moodle.
4. Añadir la acción de Teams con destino de prueba y un mensaje fijo. Verificar cada destino por separado.
5. Sustituir el texto fijo por los campos acordados y devolver o registrar el resultado de la publicación. La aceptación de una petición por el flujo no demuestra que el mensaje ya se haya publicado.

Los flujos tienen propietarios y conexiones que requieren mantenimiento. Una URL que autorice el envío debe tratarse como credencial y permanecer fuera de Git y de los registros públicos.

## Adaptación prevista en Moodle

La publicación y asignación están centralizadas en `classes/service.php`. Actualmente no generan un mensaje nativo mediante `message_send()` ni un evento propio de publicación. Instalar un conector no añade automáticamente el aviso de incidencia.

La adaptación debe usar configuración administrativa y tareas de Moodle para mapear destinos, guardar envíos pendientes junto con la publicación y procesarlos después de confirmar la transacción. Debe registrar errores, reintentar de forma controlada y distinguir pendiente, aceptado por el flujo y publicación confirmada. Los reintentos ambiguos necesitan deduplicación también en el receptor.

El disparador inicial propuesto es la primera publicación de una incidencia; borradores no generan avisos. Nuevas versiones, reasignaciones y cambios de estado quedan pendientes de definición. Antes de enviar se comprobará que la publicación siga vigente y que su asignación corresponda al destino.

Una cohorte o grupo Moodle no equivale automáticamente a los miembros de un chat o canal. La primera prueba utilizará un aviso genérico con enlace, sin contenido de la incidencia ni datos personales. El acceso al enlace seguirá sujeto a los permisos de Moodle. Un usuario sin destino configurado debe quedar identificado en gestión, sin impedir guardar la incidencia.

## Acceso y verificación

Una conexión saliente al flujo puede probarse desde Docker sin publicar Moodle en Internet. Para que otros usuarios abran la incidencia hace falta una URL del portal accesible para ellos; `localhost:8080` apunta al equipo de cada destinatario. Una aplicación con callbacks de Microsoft necesitaría además un endpoint HTTPS accesible.

La prueba funcional debe cubrir los tres destinos, borradores, falta de configuración, fallos de red, reintentos, retirada de la incidencia y acceso denegado de usuarios no asignados. Teams no modificará las fechas de recibido, leído o confirmado del portal.

El siguiente paso depende de confirmar la disponibilidad del flujo y su autenticación. Hasta entonces no se instalará la suite Microsoft 365 solo para intentar recuperar el bot eliminado ni se enviarán avisos reales.
