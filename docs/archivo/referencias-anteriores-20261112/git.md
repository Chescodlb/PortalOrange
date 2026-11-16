# Repositorio del proyecto

Repositorio: https://github.com/Chescodlb/PortalOrange

La rama principal es `main`. El repositorio remoto es público y aún no se ha hecho push. Se versionan Docker, scripts, documentación, estilos y los componentes propios `moodle/local/orangeportal/` y `moodle/theme/orange/`. Moodle y Moove se mantienen como dependencias descargables; sus archivos originales no se modifican.

## Archivos que permanecen locales

- `.env`: credenciales. `.env.example` contiene solo nombres y ejemplos.
- `moodle/config.php`: configuración generada de Moodle.
- `moodledata/`, volúmenes Docker y `backups/`: documentos, sesiones y bases de datos.
- Presentaciones y Excel del cliente, y sus extracciones `library-matrix-rows.json`: material de trabajo no publicado.

Los ajustes guardados desde la administración de Moodle viven en la base de datos. Documentar cada cambio y guardar sus estilos o scripts reproducibles en Git; un commit no respalda la base de datos.

## Trabajo habitual

```bash
git status
git diff
git add <archivos concretos>
git commit -m "Descripción del cambio"
git push origin main
```

El usuario debe revisar y aprobar los cambios antes de cada nuevo commit. El usuario hará el push. Revisar siempre los archivos incluidos antes de subirlos. Para nuevas funcionalidades se pueden usar ramas y pull requests.

## Estado del primer commit

El commit inicial `805cf77` guardó el entorno y un esqueleto del plugin todavía sin instalar. Los cambios posteriores completan una primera versión instalada localmente y están sin commitear para su revisión. No se ha hecho push.

## Recuperación en otra máquina

1. Clonar el repositorio y copiar `.env.example` a `.env` con contraseñas locales propias.
2. Descargar Moodle 4.5.1 y Moove 4.5.1 en sus rutas del README, conservando `moodle/local/orangeportal/` y `moodle/theme/orange/` del repositorio.
3. Ejecutar `bash scripts/init-fresh.sh` para una instalación limpia.
4. Los documentos y datos existentes requieren una restauración independiente; no están en Git.

Una instalación nueva detecta el plugin durante el instalador de Moodle, pero la navegación Orange está desactivada por defecto hasta configurarla. Para reproducir únicamente la base original, mover temporalmente `moodle/local/orangeportal/` fuera de `moodle/local/` antes de instalar.
