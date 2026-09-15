#!/usr/bin/env bash
# Nightly MySQL backup for the Baxela GCP demo VM: gzipped dump of the app
# database via the running mysql container, kept locally for 7 days.
# Installed as /usr/local/bin/baxela-backup by bootstrap.sh and run from
# /etc/cron.d/baxela-backup. Restore: see infrastructure/gcp/README.md.

set -euo pipefail

: "${COMPOSE_DIR:?COMPOSE_DIR must point at the repo root (set by the cron entry)}"
ENV_FILE="${COMPOSE_DIR}/.env.gcp"
BACKUP_DIR=/var/backups/baxela
KEEP=7

# shellcheck disable=SC1090
. <(grep -E '^(DB_DATABASE|DB_USERNAME|DB_PASSWORD|MYSQL_ROOT_PASSWORD)=' "$ENV_FILE")

stamp="$(date +%Y-%m-%d_%H%M%S)"
out="${BACKUP_DIR}/baxela-${stamp}.sql.gz"

docker compose --env-file "$ENV_FILE" \
    -f "${COMPOSE_DIR}/docker-compose.gcp.yml" \
    exec -T mysql mysqldump \
    --no-tablespaces \
    -uroot -p"$MYSQL_ROOT_PASSWORD" "$DB_DATABASE" \
    | gzip >"$out"

# Rotate: keep the newest $KEEP dumps.
ls -1t "${BACKUP_DIR}"/baxela-*.sql.gz | tail -n +"$((KEEP + 1))" | xargs -r rm --

echo "$(date -Is) backup written: ${out} ($(du -h "$out" | cut -f1))"
