#!/usr/bin/env bash
set -euo pipefail

REMOTE_RESTORE_PATH="${1:-/opt/mrp/restore/2026-03-10_17-22-40}"
CONTAINER_NAME="${2:-lepp-postgresql}"
PSQL_BIN="/opt/bitnami/postgresql/bin/psql"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER_NAME"; then
  echo "[ERROR] No existe el contenedor $CONTAINER_NAME en ejecucion"
  exit 1
fi

# Create role expected by dump ownership statements.
docker exec -u 0 "$CONTAINER_NAME" su postgres -c "$PSQL_BIN -d postgres -v ON_ERROR_STOP=1 -c \"DO \\\$\\\$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mrp') THEN CREATE ROLE mrp; END IF; END \\\$\\\$;\""

for db in mrp_auth mrp_demo mrp_tunna; do
  echo "==> Restaurando $db"

  docker exec -u 0 "$CONTAINER_NAME" su postgres -c "$PSQL_BIN -d postgres -v ON_ERROR_STOP=1 -c \"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$db' AND pid <> pg_backend_pid();\"" >/dev/null 2>&1 || true
  docker exec -u 0 "$CONTAINER_NAME" su postgres -c "$PSQL_BIN -d postgres -v ON_ERROR_STOP=1 -c \"DROP DATABASE IF EXISTS \\\"$db\\\";\""
  docker exec -u 0 "$CONTAINER_NAME" su postgres -c "$PSQL_BIN -d postgres -v ON_ERROR_STOP=1 -c \"CREATE DATABASE \\\"$db\\\" OWNER mrp;\""

  if [ ! -f "$REMOTE_RESTORE_PATH/$db.sql" ]; then
    echo "[ERROR] Falta archivo: $REMOTE_RESTORE_PATH/$db.sql"
    exit 1
  fi

  docker cp "$REMOTE_RESTORE_PATH/$db.sql" "$CONTAINER_NAME:/tmp/$db.sql"
  docker exec -u 0 "$CONTAINER_NAME" su postgres -c "$PSQL_BIN -d $db -v ON_ERROR_STOP=1 -f /tmp/$db.sql"
  docker exec -u 0 "$CONTAINER_NAME" rm -f "/tmp/$db.sql"
  docker exec -u 0 "$CONTAINER_NAME" su postgres -c "$PSQL_BIN -d $db -c \"SELECT current_database();\""
done

echo "[SUCCESS] Restauracion completada"
