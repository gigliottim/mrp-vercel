#!/usr/bin/env bash
set -euo pipefail

cd /opt/mrp

TARGET_USER=$(grep -E '^DB_AUTH_USERNAME=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
TARGET_PASS=$(grep -E '^DB_AUTH_PASSWORD=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')

if [[ -z "${TARGET_USER}" || -z "${TARGET_PASS}" ]]; then
  echo "[ERR] DB_AUTH_USERNAME/DB_AUTH_PASSWORD no definidos en .env"
  exit 1
fi

echo "[INFO] Usuario objetivo: ${TARGET_USER}"

docker compose exec -T --user postgres postgresql psql -d postgres -v ON_ERROR_STOP=1 -c "DO \
\$\$ BEGIN \
IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='${TARGET_USER}') THEN \
  EXECUTE format('CREATE ROLE %I LOGIN PASSWORD %L CREATEDB', '${TARGET_USER}', '${TARGET_PASS}'); \
ELSE \
  EXECUTE format('ALTER ROLE %I LOGIN PASSWORD %L CREATEDB', '${TARGET_USER}', '${TARGET_PASS}'); \
END IF; \
END \$\$;"

DBS=$(docker compose exec -T --user postgres postgresql psql -d postgres -Atc "SELECT datname FROM pg_database WHERE datistemplate=false AND datname LIKE 'mrp_%' ORDER BY datname;")

for DB in ${DBS}; do
  echo "[INFO] Alineando DB: ${DB}"

  docker compose exec -T --user postgres postgresql psql -d postgres -v ON_ERROR_STOP=1 -c "ALTER DATABASE \"${DB}\" OWNER TO \"${TARGET_USER}\";"

  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "DO \
  \$\$ BEGIN \
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='mrp') THEN \
    EXECUTE format('REASSIGN OWNED BY %I TO %I', 'mrp', '${TARGET_USER}'); \
  END IF; \
  END \$\$;"

  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT ALL ON DATABASE \"${DB}\" TO \"${TARGET_USER}\";"
  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT USAGE, CREATE ON SCHEMA public TO \"${TARGET_USER}\";"
  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO \"${TARGET_USER}\";"
  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO \"${TARGET_USER}\";"
  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO \"${TARGET_USER}\";"
  docker compose exec -T --user postgres postgresql psql -d "${DB}" -v ON_ERROR_STOP=1 -c "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO \"${TARGET_USER}\";"
done

docker compose exec -T --user postgres postgresql psql -d mrp_tunna -v ON_ERROR_STOP=1 -c "SELECT current_database() AS db, has_table_privilege('${TARGET_USER}','public.partes','SELECT,INSERT,UPDATE,DELETE') AS partes_rw;"

echo "[OK] Ajuste completado"
