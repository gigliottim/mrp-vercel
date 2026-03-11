#!/usr/bin/env bash
set -euo pipefail

# Uso:
#   cd /opt/mrp
#   bash scripts/vps-rotate-db-password.sh 'NUEVA_CLAVE'

if [[ "${1:-}" == "" ]]; then
  echo "Uso: bash scripts/vps-rotate-db-password.sh 'NUEVA_CLAVE'"
  exit 1
fi

NEW_PASSWORD="$1"

cd /opt/mrp

if [[ ! -f .env || ! -f .env.docker ]]; then
  echo "[ERR] No se encontraron .env y .env.docker en /opt/mrp"
  exit 1
fi

DB_USER=$(grep -E '^POSTGRESQL_USERNAME=' .env.docker | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
DB_NAME=$(grep -E '^POSTGRESQL_DATABASE=' .env.docker | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
DB_OLD_PASSWORD=$(grep -E '^POSTGRESQL_PASSWORD=' .env.docker | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')

if [[ -z "$DB_USER" || -z "$DB_NAME" || -z "$DB_OLD_PASSWORD" ]]; then
  echo "[ERR] Faltan POSTGRESQL_USERNAME/POSTGRESQL_PASSWORD/POSTGRESQL_DATABASE en .env.docker"
  exit 1
fi

echo "[INFO] Rotando clave para rol PostgreSQL: $DB_USER"

docker compose exec -T postgresql sh -lc "PGPASSWORD=\"$DB_OLD_PASSWORD\" psql -U \"$DB_USER\" -d postgres -v ON_ERROR_STOP=1 -c \"ALTER ROLE \\\"$DB_USER\\\" WITH PASSWORD '$NEW_PASSWORD';\""

# Mantener archivos de entorno alineados (sin sed para evitar problemas con caracteres especiales)
awk -v pw="$NEW_PASSWORD" '
  /^POSTGRESQL_PASSWORD=/ { print "POSTGRESQL_PASSWORD=\"" pw "\""; next }
  { print }
' .env.docker > .env.docker.tmp && mv .env.docker.tmp .env.docker

awk -v pw="$NEW_PASSWORD" '
  /^DB_PGSQL_PASSWORD=/ { print "DB_PGSQL_PASSWORD=" pw; next }
  /^DB_AUTH_PASSWORD=/ { print "DB_AUTH_PASSWORD=" pw; next }
  /^DB_TENANT_PASSWORD=/ { print "DB_TENANT_PASSWORD=" pw; next }
  { print }
' .env > .env.tmp && mv .env.tmp .env

echo "[INFO] Reiniciando servicios para aplicar credenciales"
docker compose restart postgresql php-fpm nginx >/dev/null

echo "[INFO] Verificando conexion con nueva clave"
docker compose exec -T postgresql sh -lc "PGPASSWORD=\"$NEW_PASSWORD\" psql -U \"$DB_USER\" -d \"$DB_NAME\" -tAc \"SELECT current_user, current_database();\""

echo "[OK] Rotacion completada"
