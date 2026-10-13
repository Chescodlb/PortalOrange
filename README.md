# Orange · entorno local Moodle

Base local para Moodle 4.5.1 con Moove. El portal real es `https://campusabai.abaigroup.com/`. Esta instalación limpia permite empezar con el tema, pero solo será una réplica cuando se incorporen el código y los datos facilitados por sistemas.

Repositorio: [Chescodlb/PortalOrange](https://github.com/Chescodlb/PortalOrange). Consulta [la guía de Git](docs/git.md) para conocer qué archivos se versionan y cómo reconstruir el entorno. Las personalizaciones Orange están en desarrollo y todavía no se han instalado ni activado.

## Requisitos

- Docker Desktop iniciado y `docker compose` disponible.
- Espacio suficiente para la base de datos y `moodledata` reales.

## Preparación

1. Copia `.env.example` como `.env` y cambia las contraseñas locales. `.env` está excluido de Git. En este proyecto ya se ha generado un `.env` local con contraseñas aleatorias.
2. El proyecto ya incluye Moodle 4.5.1 en `moodle/`, descargado de `https://packaging.moodle.org/stable405/moodle-4.5.1.tgz`.
3. También incluye Moove 4.5.1 (revisión `41279a0be68c465c12ae3caa74f494246aece3e9`) en `moodle/theme/moove/`. Es provisional; cuando sepamos la versión del portal, usaremos esa misma.
4. Ejecuta `bash scripts/init-fresh.sh`. Después abre `http://localhost:8080` (o el puerto indicado en `.env`). El usuario y la contraseña locales están en `.env`.

Para parar el entorno: `docker compose down`. Para arrancarlo de nuevo: `docker compose up -d`. `down` conserva la base de datos. **No uses `docker compose down -v`** si deseas conservarla.

El cron queda desactivado por defecto. Cuando se hayan revisado las integraciones y sea necesario probar tareas programadas, se puede activar con `docker compose --profile jobs up -d`.

## Cuando llegue la copia del portal

Solicita un paquete del **mismo momento** con: árbol completo del código Moodle (incluyendo tema, plugins y `config.php`), volcado anonimizado de MySQL y contenido de `moodledata`. El `config.php` recibido sirve como referencia; sus credenciales y rutas de producción no deben usarse en local.

La restauración se hará en una base de datos local vacía, sustituyendo el código provisional por el recibido y copiando `moodledata`. Después se adaptará `config.php` a la URL local y se ejecutará la herramienta de Moodle de búsqueda y reemplazo de URLs **solo en local**: `/admin/tool/replace/index.php`. Antes de restaurar se revisarán los plugins, la versión real de Moove y el motor de búsqueda. Si hay Solr, se añadirá como servicio local y se reconstruirá el índice.

La instalación limpia tiene el envío de correo desactivado. Al restaurar una base de datos del portal, hay que volver a comprobar esa configuración antes de arrancar cron o probar integraciones.

Referencias: [migración de Moodle 4.5](https://docs.moodle.org/405/en/Moodle_migration) · [Moove](https://moodle.org/plugins/theme_moove/versions) · [Moodle 4.5.1](https://packaging.moodle.org/stable405/)
