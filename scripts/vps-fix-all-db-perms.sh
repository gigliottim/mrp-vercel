#!/usr/bin/env bash
set -euo pipefail

cd /opt/mrp

DB_AUTH_USER=$(grep -E '^DB_AUTH_USERNAME=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
DB_AUTH_PASS=$(grep -E '^DB_AUTH_PASSWORD=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')

if [[ -z "${DB_AUTH_USER}" || -z "${DB_AUTH_PASS}" ]]; then
  echo "[ERR] DB_AUTH_USERNAME/DB_AUTH_PASSWORD no definidos en .env"
  exit 1
fi

echo "[INFO] Usuario objetivo: ${DB_AUTH_USER}"

PG_HBA_PATH="/opt/bitnami/postgresql/conf/pg_hba.conf"
if [[ -f "/bitnami/postgresql/conf/pg_hba.conf" ]]; then
  PG_HBA_PATH="/bitnami/postgresql/conf/pg_hba.conf"
fi

# 1) Habilitar trust temporalmente para recuperar acceso admin postgres
cp "$PG_HBA_PATH" "$PG_HBA_PATH.bak"
sed -i 's/^\([^#]*\)md5/\1trust/g' "$PG_HBA_PATH"

docker compose restart postgresql >/dev/null

# Esperar PostgreSQL
for i in {1..30}; do
  if docker compose exec -T postgresql psql -U postgres -d postgres -Atqc "SELECT 1" >/dev/null 2>&1; then
    break
  fi
  sleep 1
  if [[ "$i" == "30" ]]; then
    echo "[ERR] PostgreSQL no levanto luego de habilitar trust"
    exit 1
  fi
done

# 2) Dejar password de postgres conocida (igual a DB_AUTH_PASS para simplificar soporte)
docker compose exec -T postgresql psql -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER ROLE postgres WITH PASSWORD '${DB_AUTH_PASS}';"

# Asegurar rol objetivo
ROLE_EXISTS=$(docker compose exec -T postgresql psql -U postgres -d postgres -Atqc "SELECT 1 FROM pg_roles WHERE rolname='${DB_AUTH_USER}' LIMIT 1;")
if [[ "${ROLE_EXISTS}" != "1" ]]; then
  docker compose exec -T postgresql psql -U postgres -d postgres -v ON_ERROR_STOP=1 -c "CREATE ROLE \"${DB_AUTH_USER}\" LOGIN PASSWORD '${DB_AUTH_PASS}' CREATEDB;"
else
  docker compose exec -T postgresql psql -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER ROLE \"${DB_AUTH_USER}\" LOGIN PASSWORD '${DB_AUTH_PASS}' CREATEDB;"
fi

# 3) Alinear ownership + grants en TODAS las bases mrp_*
DBS=$(docker compose exec -T postgresql psql -U postgres -d postgres -Atqc "SELECT datname FROM pg_database WHERE datistemplate=false AND datname LIKE 'mrp_%' ORDER BY datname;")

for DB in ${DBS}; do
  echo "[INFO] Alineando DB: ${DB}"

  docker compose exec -T postgresql psql -U postgres -d postgres -v ON_ERROR_STOP=1 -c "ALTER DATABASE \"${DB}\" OWNER TO \"${DB_AUTH_USER}\";"

  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "DO \
  \$\$ BEGIN \
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='mrp') THEN \
    EXECUTE format('REASSIGN OWNED BY %I TO %I', 'mrp', '${DB_AUTH_USER}'); \
  END IF; \
  END \$\$;"

  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT ALL ON DATABASE \"${DB}\" TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT USAGE, CREATE ON SCHEMA public TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO \"${DB_AUTH_USER}\";"

  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "ALTER DEFAULT PRIVILEGES FOR ROLE \"${DB_AUTH_USER}\" IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO \"${DB_AUTH_USER}\";"
  docker compose exec -T postgresql psql -U postgres -d "${DB}" -v ON_ERROR_STOP=1 -c "ALTER DEFAULT PRIVILEGES FOR ROLE \"${DB_AUTH_USER}\" IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO \"${DB_AUTH_USER}\";"
done

# 4) Volver a md5
mv "$PG_HBA_PATH.bak" "$PG_HBA_PATH"
docker compose restart postgresql >/dev/null

for i in {1..30}; do
  if docker compose exec -T postgresql sh -lc "PGPASSWORD='${DB_AUTH_PASS}' psql -U '${DB_AUTH_USER}' -d postgres -Atqc 'SELECT 1'" >/dev/null 2>&1; then
    break
  fi
  sleep 1
  if [[ "$i" == "30" ]]; then
    echo "[ERR] PostgreSQL no levanto luego de restaurar md5"
    exit 1
  fi
done

# 5) Verificaciones clave

docker compose exec -T postgresql sh -lc "PGPASSWORD='${DB_AUTH_PASS}' psql -U '${DB_AUTH_USER}' -d mrp_tunna -Atqc \"SELECT has_table_privilege('${DB_AUTH_USER}','public.partes','SELECT,INSERT,UPDATE,DELETE');\""
docker compose exec -T postgresql sh -lc "PGPASSWORD='${DB_AUTH_PASS}' psql -U '${DB_AUTH_USER}' -d mrp_tunna -Atqc \"SELECT tableowner, count(*) FROM pg_tables WHERE schemaname='public' GROUP BY tableowner ORDER BY count(*) DESC;\""

echo "[OK] Ownership y permisos alineados"
