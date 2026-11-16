#!/usr/bin/env bash
# Ejecutar como el usuario de Moodle, por ejemplo: sudo -u www-data bash ...
set -euo pipefail
umask 077

usage() {
  cat <<'EOF'
Uso: activar-cron-pre.sh [--moodle /ruta/moodle] [--php /usr/bin/php]
                           [--log /ruta/privada/cron.log] [--dry-run]

Instala en el crontab del usuario actual una ejecución cada minuto y habilita
el cron de Moodle. Conserva las demás entradas. Repetirlo no añade duplicados.
El directorio del log debe existir y ser escribible por el usuario de Moodle.
Detecta Moodle en el directorio actual, junto al script y en /var/www, /srv,
/opt, /usr/share/nginx/html y /home. Busca PHP CLI en PATH y rutas habituales.
Si hay varias opciones, permite elegir en una terminal; sin terminal exige
--moodle o --php. Log predeterminado: /var/log/moodle-pre/cron.log.
--dry-run muestra la entrada sin escribir ni ejecutar Moodle.
Requiere Linux, Bash, crontab y el servicio cron/crond activo.
EOF
}
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
moodle='' php='' logfile='/var/log/moodle-pre/cron.log' dryrun=0
while (($#)); do
  case "$1" in
    --moodle|--php|--log)
      (($# >= 2)) || fail "Falta el valor de $1"
      case "$1" in
        --moodle) moodle=$2 ;;
        --php) php=$2 ;;
        --log) logfile=$2 ;;
      esac
      shift 2 ;;
    --dry-run) dryrun=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; fail "Opción desconocida: $1" ;;
  esac
done
[[ $(id -u) != 0 ]] || fail 'Ejecuta con el usuario del servicio Moodle (sudo -u www-data bash ...), no como root.'
command -v crontab >/dev/null || fail 'No está instalado crontab.'

choose() {
  local label=$1 flag=$2 selected
  shift 2
  (($#)) || fail "No se encontró $label. Indica $flag con su ruta absoluta."
  if (($# == 1)); then
    printf '%s\n' "$1"
    return
  fi
  printf 'Se encontraron varias opciones para %s:\n' "$label" >&2
  printf '  %s\n' "$@" >&2
  [[ -t 0 ]] || fail "Sin terminal no se puede elegir. Repite con $flag RUTA."
  PS3="Selecciona $label (número): "
  select selected in "$@"; do
    if [[ -n $selected ]]; then
      printf '%s\n' "$selected"
      return
    fi
    printf 'Selecciona un número de la lista.\n' >&2
  done
  fail "No se seleccionó $label."
}

if [[ -z $moodle ]]; then
  candidates=()
  add_moodle() {
    local candidate existing
    [[ -r $1/config.php && -r $1/admin/cli/cron.php ]] || return 0
    candidate=$(cd "$1" && pwd -P)
    for existing in "${candidates[@]+"${candidates[@]}"}"; do
      [[ $existing != "$candidate" ]] || return 0
    done
    candidates+=("$candidate")
  }
  scriptdir=$(cd "$(dirname "$0")" && pwd -P)
  for nearby in "$PWD" "$PWD/moodle" "$scriptdir" "$scriptdir/../moodle"; do
    add_moodle "$nearby"
  done
  printf 'Buscando instalaciones de Moodle accesibles...\n' >&2
  for searchroot in /var/www /srv /opt /usr/share/nginx/html /home; do
    [[ -d $searchroot ]] || continue
    while IFS= read -r -d '' cronfile; do
      add_moodle "${cronfile%/admin/cli/cron.php}"
    done < <(find -H "$searchroot" -type f -path '*/admin/cli/cron.php' -print0 2>/dev/null)
  done
  moodle=$(choose Moodle --moodle "${candidates[@]+"${candidates[@]}"}")
fi
if [[ -z $php ]]; then
  php=$(command -v php || true)
  if [[ -z $php ]]; then
    candidates=()
    for candidate in /usr/bin/php[0-9]* /usr/local/bin/php /usr/local/bin/php[0-9]* /opt/remi/php*/root/usr/bin/php; do
      [[ -x $candidate && ! -d $candidate ]] || continue
      if "$candidate" -r 'exit(PHP_SAPI === "cli" ? 0 : 1);' >/dev/null 2>&1; then
        candidates+=("$candidate")
      fi
    done
    php=$(choose 'PHP CLI' --php "${candidates[@]+"${candidates[@]}"}")
  fi
fi
for value in "$moodle" "$php" "$logfile"; do
  [[ $value == /* ]] || fail 'Indica --moodle, --php y --log con rutas absolutas.'
  # El porcentaje tiene significado especial en crontab, incluso entre comillas.
  [[ $value != *$'\n'* && $value != *$'\r'* && $value != *%* ]] || fail 'Las rutas no pueden contener saltos de línea ni %.'
done
[[ -x $php ]] || fail 'PHP no es ejecutable.'
[[ -r $moodle/config.php && -r $moodle/admin/cli/cron.php ]] || fail 'No se encuentra una instalación Moodle legible en --moodle.'
moodle=$(cd "$moodle" && pwd -P)
[[ $logfile != "$moodle/"* ]] || fail 'El log debe quedar fuera del directorio web de Moodle.'
[[ -d $(dirname "$logfile") && -w $(dirname "$logfile") ]] || fail 'El directorio del log debe existir y ser escribible.'
[[ ! -L $logfile ]] || fail 'El log no puede ser un enlace simbólico.'
[[ ! -e $logfile || ( -f $logfile && -w $logfile ) ]] || fail 'El log debe ser un archivo escribible.'
"$php" -r 'exit(PHP_SAPI === "cli" ? 0 : 1);' || fail 'El ejecutable indicado no es PHP CLI.'

tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT
if ! LC_ALL=C crontab -l > "$tmpdir/current" 2> "$tmpdir/error"; then
  # No confundir falta de crontab con falta de permisos u otro error del sistema.
  if ! grep -qi 'no crontab for' "$tmpdir/error"; then
    cat "$tmpdir/error" >&2
    fail 'No se pudo leer el crontab; no se ha modificado.'
  fi
  : > "$tmpdir/current"
fi

# Una marca distinta por instalación permite conservar otros campus del usuario.
marker="orange-moodle-cron: $moodle"
awk -v marker="# $marker" 'length($0) < length(marker) || substr($0, length($0) - length(marker) + 1) != marker' \
  "$tmpdir/current" > "$tmpdir/other"
if grep -v '^[[:space:]]*#' "$tmpdir/other" | grep -F "$moodle/admin/cli/cron.php" >/dev/null; then
  fail 'Ya existe un cron para esta instalación en el crontab del usuario. Revisarlo antes de instalar otro.'
fi
# También detectar las ubicaciones habituales de cron del sistema que sean legibles.
for systemcron in /etc/crontab /etc/cron.d/*; do
  [[ -f $systemcron && -r $systemcron ]] || continue
  if grep -v '^[[:space:]]*#' "$systemcron" | grep -F "$moodle/admin/cli/cron.php" >/dev/null; then
    fail "Ya existe un cron para esta instalación en $systemcron. Revisarlo antes de instalar otro."
  fi
done
quote() {
  printf "'"
  printf '%s' "$1" | sed "s/'/'\\\\''/g"
  printf "'"
}
entry="* * * * * /bin/sh -c $(quote "exec $(quote "$php") $(quote "$moodle/admin/cli/cron.php") --keep-alive=0 >> $(quote "$logfile") 2>&1") # $marker"
printf 'Usuario: %s\nMoodle: %s\nPHP CLI: %s\nLog: %s\n%s\n' "$(id -un)" "$moodle" "$php" "$logfile" "$entry"
((dryrun == 0)) || exit 0

# Habilitar Moodle no ejecuta las tareas; estas empezarán con el siguiente cron.
"$php" "$moodle/admin/cli/cron.php" --enable
touch "$logfile"
chmod 600 "$logfile"
cp "$tmpdir/other" "$tmpdir/new"
printf '%s\n' "$entry" >> "$tmpdir/new"
crontab "$tmpdir/new"
printf 'Cron instalado cada minuto. Salida: %s\n' "$logfile"
printf 'El servicio cron/crond del servidor debe estar activo. Revisar con: crontab -l\n'
