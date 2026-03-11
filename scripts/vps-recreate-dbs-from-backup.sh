#!/usr/bin/env bash
set -euo pipefail

cd /opt/mrp

BACKUP_DIR="database/backups/2026-03-10_17-22-40"
DBS=("mrp_auth" "mrp_demo" "mrp_tunna")

DB_AUTH_USER=$(grep -E '^DB_AUTH_USERNAME=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
DB_AUTH_PASS=$(grep -E '^DB_AUTH_PASSWORD=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')

if [[ -z "$DB_AUTH_USER" || -z "$DB_AUTH_PASS" ]]; then
  echo "[ERR] DB_AUTH_USERNAME/DB_AUTH_PASSWORD no definidos en .env"
  exit 1
fi

for db in "${DBS[@]}"; do
  if [[ ! -f "$BACKUP_DIR/$db.sql" ]]; then
    echo "[ERR] Falta dump: $BACKUP_DIR/$db.sql"
    exit 1
  fi
done

echo "[INFO] Usuario objetivo: $DB_AUTH_USER"
echo "[INFO] Backup origen: $BACKUP_DIR"

# 1) Habilitar trust temporal via SIGHUP (sin restart, para que Bitnami no regenere pg_hba)

echo "[INFO] Aplicando trust mode via reload (sin restart)..."
docker compose exec -T postgresql sh -c '
set -e
PG_HBA=/opt/bitnami/postgresql/conf/pg_hba.conf
cp "$PG_HBA" "$PG_HBA.bak.recreate"
sed -i "s/scram-sha-256/trust/g; s/md5/trust/g" "$PG_HBA"
echo "[INFO] pg_hba trust aplicado:"
grep -v "^#" "$PG_HBA" | grep -v "^$" || true
PGPID=$(pgrep -o postgres)
echo "[INFO] Enviando SIGHUP a postgres PID=$PGPID"
kill -HUP "$PGPID"
sleep 3
echo "[INFO] Reload completado"
'

# 2) Asegurar rol de aplicacion y capacidades del wizard

docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER ROLE postgres WITH PASSWORD '${DB_AUTH_PASS}';"

ROLE_EXISTS=$(docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -Atqc "SELECT 1 FROM pg_roles WHERE rolname='${DB_AUTH_USER}' LIMIT 1;")
if [[ "$ROLE_EXISTS" != "1" ]]; then
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "CREATE ROLE \"${DB_AUTH_USER}\" LOGIN PASSWORD '${DB_AUTH_PASS}' CREATEDB;"
else
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER ROLE \"${DB_AUTH_USER}\" LOGIN PASSWORD '${DB_AUTH_PASS}' CREATEDB;"
fi

# 3) Drop/Create limpio de las 3 BD y restore de dumps
for db in "${DBS[@]}"; do
  echo "[INFO] Recreando $db"
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${db}' AND pid <> pg_backend_pid();"
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"${db}\";"
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"${db}\" OWNER \"${DB_AUTH_USER}\" ENCODING 'UTF8' LOCALE_PROVIDER 'icu' ICU_LOCALE 'es-ES' TEMPLATE template0;"

  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d "$db" -v ON_ERROR_STOP=1 < "$BACKUP_DIR/$db.sql"

  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER DATABASE \"${db}\" OWNER TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d "$db" -v ON_ERROR_STOP=1 -c "GRANT USAGE, CREATE ON SCHEMA public TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d "$db" -v ON_ERROR_STOP=1 -c "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d "$db" -v ON_ERROR_STOP=1 -c "GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO \"${DB_AUTH_USER}\";"
done

# 4) Garantizar tenant mapping para login runtime

docker compose exec -T postgresql psql -h 127.0.0.1 -U postgres -d mrp_auth -v ON_ERROR_STOP=1 -c "UPDATE company_databases SET host='postgresql', port='5432', username='${DB_AUTH_USER}', password_encrypted='${DB_AUTH_PASS}', updated_at=NOW();"

# 5) Volver a md5 via SIGHUP (sin restart)

echo "[INFO] Restaurando md5 via reload (sin restart)..."
docker compose exec -T postgresql sh -c '
set -e
PG_HBA=/opt/bitnami/postgresql/conf/pg_hba.conf
mv "$PG_HBA.bak.recreate" "$PG_HBA"
echo "[INFO] pg_hba md5 restaurado"
PGPID=$(pgrep -o postgres)
echo "[INFO] Enviando SIGHUP a postgres PID=$PGPID"
kill -HUP "$PGPID"
sleep 3
echo "[INFO] Reload a md5 completado"
'

# 6) Verificaciones finales

docker compose exec -T -e PGPASSWORD="$DB_AUTH_PASS" postgresql psql -h 127.0.0.1 -U "$DB_AUTH_USER" -d postgres -Atqc "SELECT rolname, rolcreatedb FROM pg_roles WHERE rolname='${DB_AUTH_USER}';"
echo "[INFO] Verificando permiso en partes..."
docker compose exec -T -e PGPASSWORD="$DB_AUTH_PASS" postgresql psql -h 127.0.0.1 -U "$DB_AUTH_USER" -d mrp_tunna -Atqc "SELECT has_table_privilege('${DB_AUTH_USER}','public.partes','SELECT,INSERT,UPDATE,DELETE');"
echo "[INFO] Verificando permiso en mrp_demo..."
docker compose exec -T -e PGPASSWORD="$DB_AUTH_PASS" postgresql psql -h 127.0.0.1 -U "$DB_AUTH_USER" -d mrp_demo -Atqc "SELECT has_table_privilege('${DB_AUTH_USER}','public.partes','SELECT,INSERT,UPDATE,DELETE');" 2>/dev/null || echo "(mrp_demo sin tabla partes - OK)"

echo "[OK] Bases recreadas y restauradas con owner ${DB_AUTH_USER}"
