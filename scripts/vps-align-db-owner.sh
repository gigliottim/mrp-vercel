#!/usr/bin/env bash
set -euo pipefail

cd /opt/mrp

if [[ ! -f .env ]]; then
  echo "[ERR] No existe /opt/mrp/.env"
  exit 1
fi

TARGET_USER=$(grep -E '^DB_AUTH_USERNAME=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
TARGET_PASS=$(grep -E '^DB_AUTH_PASSWORD=' .env | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')

if [[ -z "${TARGET_USER}" || -z "${TARGET_PASS}" ]]; then
  echo "[ERR] DB_AUTH_USERNAME/DB_AUTH_PASSWORD no definidos en .env"
  exit 1
fi

echo "[INFO] Usuario objetivo: ${TARGET_USER}"

docker compose exec -T \
  -e TARGET_USER="${TARGET_USER}" \
  -e TARGET_PASS="${TARGET_PASS}" \
  postgresql sh -lc '
set -euo pipefail

PG_USER="${POSTGRESQL_USERNAME:-}"
PG_PASS="${POSTGRESQL_PASSWORD:-}"

if [[ -z "${PG_USER}" || -z "${PG_PASS}" ]]; then
  echo "[ERR] El contenedor no expone POSTGRESQL_USERNAME/POSTGRESQL_PASSWORD"
  exit 1
fi

psql_super() {
  PGPASSWORD="${PG_PASS}" psql -v ON_ERROR_STOP=1 -U "${PG_USER}" "$@"
}

# 1) Asegurar rol y atributos base
ROLE_EXISTS=$(psql_super -d postgres -Atqc "SELECT 1 FROM pg_roles WHERE rolname='${TARGET_USER}' LIMIT 1;")
if [[ "${ROLE_EXISTS}" != "1" ]]; then
  psql_super -d postgres -c "CREATE ROLE \"${TARGET_USER}\" LOGIN PASSWORD '${TARGET_PASS}' CREATEDB;"
else
  psql_super -d postgres -c "ALTER ROLE \"${TARGET_USER}\" LOGIN PASSWORD '${TARGET_PASS}' CREATEDB;"
fi

# 2) Unificar ownership y privilegios para todas las DB mrp_*
DBS=$(psql_super -d postgres -Atqc "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE 'mrp_%' ORDER BY datname;")

for DB in ${DBS}; do
  echo "[INFO] Alineando DB: ${DB}"

  # Dueño de la base
  psql_super -d postgres -c "ALTER DATABASE \"${DB}\" OWNER TO \"${TARGET_USER}\";"

  # Ownership de objetos y grants en esquema public
  psql_super -d "${DB}" <<SQL
GRANT ALL ON DATABASE \"${DB}\" TO \"${TARGET_USER}\";
GRANT USAGE, CREATE ON SCHEMA public TO \"${TARGET_USER}\";

DO \\\$\\\$
DECLARE r RECORD;
BEGIN
  FOR r IN
    SELECT tablename FROM pg_tables WHERE schemaname='"'"'public'"'"'
  LOOP
    EXECUTE format('"'"'ALTER TABLE public.%I OWNER TO %I'"'"', r.tablename, '"'"'${TARGET_USER}'"'"');
  END LOOP;

  FOR r IN
    SELECT sequencename FROM pg_sequences WHERE schemaname='"'"'public'"'"'
  LOOP
    EXECUTE format('"'"'ALTER SEQUENCE public.%I OWNER TO %I'"'"', r.sequencename, '"'"'${TARGET_USER}'"'"');
  END LOOP;

  FOR r IN
    SELECT routine_name
    FROM information_schema.routines
    WHERE routine_schema='"'"'public'"'"' AND routine_type='"'"'FUNCTION'"'"'
  LOOP
    BEGIN
      EXECUTE format('"'"'ALTER FUNCTION public.%I() OWNER TO %I'"'"', r.routine_name, '"'"'${TARGET_USER}'"'"');
    EXCEPTION WHEN others THEN
      -- Ignora firmas no vacias; ownership de funciones complejas se mantiene.
      NULL;
    END;
  END LOOP;
END
\\\$\\\$;

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO \"${TARGET_USER}\";
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO \"${TARGET_USER}\";

ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO \"${TARGET_USER}\";
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO \"${TARGET_USER}\";
SQL

done

# 3) Verificacion minima en mrp_tunna
psql_super -d mrp_tunna -c "SELECT current_database() AS db, has_table_privilege('${TARGET_USER}','public.partes','SELECT,INSERT,UPDATE,DELETE') AS partes_rw;"
'

echo "[OK] Alineacion completada"
