# Orange · entorno local Moodle

Base local para Moodle 4.5.1 con Moove. El portal real es `https://campusabai.abaigroup.com/`. Esta instalación limpia permite empezar con el tema, pero solo será una réplica cuando se incorporen el código y los datos facilitados por sistemas.

Repositorio: [Chescodlb/PortalOrange](https://github.com/Chescodlb/PortalOrange). Consulta [la guía de Git](docs/git.md) para conocer qué archivos se versionan y cómo reconstruir el entorno. La primera versión Orange está instalada en local y pendiente de revisión. No se harán nuevos commits sin aprobación ni push automático.

## Portal Orange local

La cohorte **Usuarios Orange** recibe Biblioteca, Cursos, Incidencias y Notificaciones, con Administración para los perfiles autorizados, y el tema hijo propio `theme_orange`. El resto conserva la experiencia nativa del sitio. El acceso es común. La configuración se explica en [la guía de aislamiento](docs/02-navegacion.md). La biblioteca usa categorías y recursos nativos de Moodle; las evaluaciones y encuestas usan sus actividades estándar. Los avisos y sus confirmaciones se gestionan desde **Administración > Portal Orange** mediante el plugin propio `local_orangeportal`, sin modificar el core ni Moove.

Consulta las [guías de administración por caso](docs/README.md), la [implementación y sus límites](docs/implementacion.md) y el [recorrido de prueba](docs/08-pruebas-y-revision.md). Los perfiles ficticios usan la clave `ORANGE_DEMO_PASSWORD` de `.env`.

La revisión del 22 de octubre está en el [plan de desarrollo y entregas](docs/12-plan-desarrollo-entregas.md). Para instalar sobre una copia del campus existente, usar la [guía de preproducción](docs/13-despliegue-preproduccion.md); incluye la dependencia Moove, carga de categorías/catálogos y creación de cuentas y perfiles desde Moodle.

Para repetir la carga inicial en una instalación local, instalar primero español desde **Administración > Idioma > Paquetes de idioma** y extraer la matriz:

```bash
python3 scripts/extract-library-matrix.py '/ruta/Matriz de procesos v.12 - ORANGE.xlsx'
bash scripts/configure-orange-local.sh
```

El inicializador crea un respaldo SQL, instala el plugin y configura ejemplos. Si se vuelve a ejecutar, conserva los contenidos existentes pero reaplica marca y navegación; no usarlo para el mantenimiento diario del cliente.

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

## Comprobar las dos experiencias

- `agente.orange` y los demás perfiles Orange: portal y diseño Orange.
- `usuario.externo`: navegación y diseño Moove estándar de la demo. Usa `ORANGE_DEMO_PASSWORD` de `.env`.
- Administrador general: administración completa de Moodle, sin diseño Orange mientras no pertenezca a la cohorte.

No ejecutar los inicializadores de demostración en el campus existente. El despliegue debe conservar su tema y configuración general y asignar Orange únicamente a su cohorte.
