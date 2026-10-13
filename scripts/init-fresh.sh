#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
  echo 'Copia .env.example a .env y cambia las contraseñas locales.' >&2
  exit 1
fi
if [[ ! -f moodle/version.php || ! -f moodle/theme/moove/version.php ]]; then
  echo 'Falta Moodle o Moove. Consulta el README para instalar el código.' >&2
  exit 1
fi
if [[ -f moodle/config.php ]]; then
  echo 'Ya existe moodle/config.php. No se sobrescribirá una instalación.' >&2
  exit 1
fi

set -a
source .env
set +a

admin_user=${MOODLE_ADMIN_USER:-admin}
admin_password=${MOODLE_ADMIN_PASSWORD:-}
if [[ -z "$admin_password" ]]; then
  echo 'Define MOODLE_ADMIN_PASSWORD en .env.' >&2
  exit 1
fi

docker compose up -d --build
docker compose exec -T -u www-data web php admin/cli/install.php \
  --non-interactive \
  --agree-license \
  --lang=en \
  --wwwroot="http://localhost:${MOODLE_PORT:-8080}" \
  --dataroot=/var/moodledata \
  --dbtype=mysqli \
  --dbhost=db \
  --dbname="$MYSQL_DATABASE" \
  --dbuser="$MYSQL_USER" \
  --dbpass="$MYSQL_PASSWORD" \
  --fullname='Orange Moodle local' \
  --shortname='Orange local' \
  --adminuser="$admin_user" \
  --adminpass="$admin_password" \
  --adminemail='admin@localhost.invalid'

echo "Moodle disponible en http://localhost:${MOODLE_PORT:-8080}"
