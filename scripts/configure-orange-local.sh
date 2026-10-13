#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ ! -f .env || ! -f moodle/config.php ]]; then
  echo 'Primero configura .env e instala Moodle según README.md.' >&2
  exit 1
fi
if ! rg -q '^ORANGE_DEMO_PASSWORD=' .env; then
  demo_secret=$(openssl rand -hex 16)
  printf '\nORANGE_DEMO_PASSWORD=Aa1!%s\n' "$demo_secret" >> .env
  unset demo_secret
fi
set -a
source .env
set +a
mkdir -p backups
backup_name="backups/before-orange-$(date +%Y%m%d-%H%M%S).sql"
docker compose exec -T db sh -c 'exec mysqldump --single-transaction --no-tablespaces -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > "$backup_name"
echo "Copia local creada: $backup_name"
docker compose cp assets web:/tmp/orange-assets
docker compose exec -T -u www-data web php admin/cli/upgrade.php --non-interactive
docker compose exec -T -u www-data -e ORANGE_DEMO_PASSWORD="$ORANGE_DEMO_PASSWORD" web \
  php local/orangeportal/cli/configure_demo.php --assets=/tmp/orange-assets "$@"
docker compose exec -T -u www-data web php admin/cli/purge_caches.php
echo 'Portal configurado en http://localhost:8080. Credenciales de prueba en .env.'
