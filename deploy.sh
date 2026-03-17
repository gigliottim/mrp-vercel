#!/usr/bin/env bash
#
# deploy.sh — Deploy automatizado al VPS via SSH (equivalente Linux de deploy.ps1)
#   1) Actualiza revision automaticamente segun cambios.
#   2) Ejecuta commit git obligatorio.
#   3) Empaqueta y sube el codigo al VPS.
#   4) Ejecuta setup remoto: Docker, contenedores, migraciones.
#
# Requiere: git, sshpass, zip
# Uso: ./deploy.sh [--host HOST] [--port PORT] [--user USER] [--password PASS] [--remote-path PATH]
# Alternativa: definir DEPLOY_HOST, DEPLOY_PORT, DEPLOY_USER, DEPLOY_PASS, DEPLOY_REMOTE_PATH como vars de entorno
#

set -euo pipefail

# ── Parametros (sobreescribibles via variables de entorno o argumentos) ───────
HOSTNAME="${DEPLOY_HOST:-181.13.244.35}"
PORT="${DEPLOY_PORT:-5073}"
USERNAME="${DEPLOY_USER:-root}"
PASSWORD="${DEPLOY_PASS:-w(6C%QnZC7EQPZ}"
REMOTE_PATH="${DEPLOY_REMOTE_PATH:-/opt/mrp}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --host)        HOSTNAME="$2";    shift 2 ;;
    --port)        PORT="$2";        shift 2 ;;
    --user)        USERNAME="$2";    shift 2 ;;
    --password)    PASSWORD="$2";    shift 2 ;;
    --remote-path) REMOTE_PATH="$2"; shift 2 ;;
    *) echo "[DEPLOY][ERROR] Argumento desconocido: $1" >&2; exit 1 ;;
  esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_PATH="$SCRIPT_DIR/config/app.php"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"
ENV_DOCKER_FILE="$SCRIPT_DIR/.env.docker"
NGINX_CONF_FILE="$SCRIPT_DIR/docker/nginx/mrp.conf"
RELEASE_ZIP="$SCRIPT_DIR/app-release.zip"
REMOTE_SCRIPT_TMP=""

# Exportar para el helper Python (evita exponer la clave en argumentos/ps)
export _DEPLOY_SSH_HOST="$HOSTNAME"
export _DEPLOY_SSH_PORT="$PORT"
export _DEPLOY_SSH_USER="$USERNAME"
export _DEPLOY_SSH_PASS="$PASSWORD"

cleanup() {
  [[ -f "$RELEASE_ZIP" ]] && rm -f "$RELEASE_ZIP"
  [[ -n "$REMOTE_SCRIPT_TMP" && -f "$REMOTE_SCRIPT_TMP" ]] && rm -f "$REMOTE_SCRIPT_TMP"
}
trap cleanup EXIT

# ── Helpers ───────────────────────────────────────────────────────────────────
step()  { echo "[DEPLOY] $*"; }
error() { echo "[DEPLOY][ERROR] $*" >&2; exit 1; }

assert_file_exists() { [[ -e "$1" ]] || error "No existe el archivo/directorio requerido: $1"; }

# ── Backend SSH: sshpass nativo o Python/paramiko como fallback ───────────────
if command -v sshpass >/dev/null 2>&1; then
  SSH_BACKEND="sshpass"
elif python3 -c "import paramiko" 2>/dev/null; then
  SSH_BACKEND="paramiko"
  step "Backend SSH: usando Python/paramiko (sshpass no disponible)"
else
  error "Se requiere sshpass (apt install sshpass) o Python3+paramiko (pip3 install paramiko)"
fi

_py_ssh_exec() {
  # Ejecuta un comando remoto via paramiko. Si se le pasa --stdin-file <path>, envia ese archivo como stdin.
  local stdin_file=""
  if [[ "$1" == "--stdin-file" ]]; then stdin_file="$2"; shift 2; fi
  local remote_cmd="$*"
  python3 - "$remote_cmd" "$stdin_file" << 'PYEOF'
import sys, os, paramiko, threading
host     = os.environ['_DEPLOY_SSH_HOST']
port     = int(os.environ['_DEPLOY_SSH_PORT'])
user     = os.environ['_DEPLOY_SSH_USER']
password = os.environ['_DEPLOY_SSH_PASS']
remote_cmd  = sys.argv[1]
stdin_file  = sys.argv[2] if len(sys.argv) > 2 else ''
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(host, port=port, username=user, password=password, timeout=30)
stdin, stdout, stderr = client.exec_command(remote_cmd, get_pty=False)
if stdin_file:
    with open(stdin_file, 'rb') as f:
        stdin.write(f.read())
stdin.channel.shutdown_write()
def stream(src, dst):
    for line in src:
        dst.write(line if isinstance(line, bytes) else line.encode())
        dst.flush()
t = threading.Thread(target=stream, args=(stderr.channel.makefile_stderr('r'), sys.stderr.buffer), daemon=True)
t.start()
for line in stdout:
    sys.stdout.write(line if isinstance(line, str) else line.decode('utf-8', errors='replace'))
    sys.stdout.flush()
exit_code = stdout.channel.recv_exit_status()
t.join()
client.close()
sys.exit(exit_code)
PYEOF
}

_py_scp_put() {
  local src="$1" remote_dest="$2"
  python3 - "$src" "$remote_dest" << 'PYEOF'
import sys, os, paramiko
host     = os.environ['_DEPLOY_SSH_HOST']
port     = int(os.environ['_DEPLOY_SSH_PORT'])
user     = os.environ['_DEPLOY_SSH_USER']
password = os.environ['_DEPLOY_SSH_PASS']
src, dest = sys.argv[1], sys.argv[2]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(host, port=port, username=user, password=password, timeout=30)
sftp = client.open_sftp()
# Crear directorio destino si hace falta
import posixpath
dest_dir = posixpath.dirname(dest)
try: sftp.stat(dest_dir)
except IOError:
    parts = dest_dir.strip('/').split('/')
    cur = ''
    for p in parts:
        cur = '/' + p if not cur else cur + '/' + p
        try: sftp.mkdir(cur)
        except IOError: pass
sftp.put(src, dest)
size = os.path.getsize(src)
print(f'Subido: {src} -> {dest} ({size/1024:.1f} KB)')
sftp.close()
client.close()
PYEOF
}

# Interfaz publica: ssh_cmd y scp_upload delegando al backend activo
ssh_cmd() {
  if [[ "$SSH_BACKEND" == "sshpass" ]]; then
    SSHPASS="$PASSWORD" sshpass -e ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p "$PORT" "$USERNAME@$HOSTNAME" "$@"
  else
    _py_ssh_exec "$@"
  fi
}

ssh_pipe() {
  # Ejecuta comando remoto enviando un archivo local como stdin (equivalente a ssh ... 'bash -s' < file)
  local stdin_file="$1"; shift
  if [[ "$SSH_BACKEND" == "sshpass" ]]; then
    SSHPASS="$PASSWORD" sshpass -e ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p "$PORT" "$USERNAME@$HOSTNAME" "$@" < "$stdin_file"
  else
    _py_ssh_exec --stdin-file "$stdin_file" "$@"
  fi
}

scp_upload() {
  if [[ "$SSH_BACKEND" == "sshpass" ]]; then
    SSHPASS="$PASSWORD" sshpass -e scp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P "$PORT" "$1" "$USERNAME@$HOSTNAME:$2"
  else
    _py_scp_put "$1" "$2"
  fi
}

assert_no_legacy_credentials() {
  local scan_files=(
    "$SCRIPT_DIR/.env" "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.env.docker"
    "$SCRIPT_DIR/docker-compose.yml" "$SCRIPT_DIR/config/database.php"
  )
  local legacy_patterns=(
    'ojp9Q6aYT3KHDE8sMS2u'
    'D:\\Gigliotti\\Documentos\\MartinG\\WEBS\\mrp'
    'DB_PGSQL_HOST=localhost' 'DB_AUTH_HOST=localhost' 'DB_TENANT_HOST=localhost'
    'DB_PGSQL_HOST=127\.0\.0\.1' 'DB_AUTH_HOST=127\.0\.0\.1' 'DB_TENANT_HOST=127\.0\.0\.1'
  )
  for file in "${scan_files[@]}"; do
    [[ -f "$file" ]] || continue
    for pattern in "${legacy_patterns[@]}"; do
      if grep -qE "$pattern" "$file"; then
        error "Configuracion legacy/local detectada en $file (patron: $pattern). Corregi antes de desplegar."
      fi
    done
  done
}

get_latest_commit_changed_files() {
  command -v git >/dev/null 2>&1 || { echo ""; return; }
  git -C "$SCRIPT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1 || { echo ""; return; }
  if ! git -C "$SCRIPT_DIR" rev-parse --verify HEAD~1 >/dev/null 2>&1; then echo "ALL"; return; fi
  git -C "$SCRIPT_DIR" diff-tree --no-commit-id --name-only -r HEAD
}

get_auto_level() {
  command -v git >/dev/null 2>&1 || { echo "Z"; return; }
  git -C "$SCRIPT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1 || { echo "Z"; return; }
  local changed_files
  changed_files=$(
    { git -C "$SCRIPT_DIR" diff --name-only
      git -C "$SCRIPT_DIR" diff --name-only --cached
      git -C "$SCRIPT_DIR" ls-files --others --exclude-standard
    } | sort -u | grep -v '^$' || true
  )
  [[ -z "$changed_files" ]] && { echo "Z"; return; }
  while IFS= read -r f; do
    if echo "$f" | grep -qE '^app/core/|^bootstrap/|^config/database\.php$|^routes/'; then echo "X"; return; fi
  done <<< "$changed_files"
  while IFS= read -r f; do
    if echo "$f" | grep -qE '^app/controllers/|^app/services/|^app/models/|^app/Repositories/|^database/migrations/|^public/api/|^views/pages/'; then echo "Y"; return; fi
  done <<< "$changed_files"
  echo "Z"
}

update_project_revision() {
  local level="$1" current_version current_build major minor patch
  current_version=$(grep -oP "'version'\s*=>\s*'\K[0-9]+\.[0-9]+\.[0-9]+" "$CONFIG_PATH") \
    || error "No se pudo leer 'version' en $CONFIG_PATH"
  current_build=$(grep -oP "'build'\s*=>\s*\K[0-9]+" "$CONFIG_PATH") \
    || error "No se pudo leer 'build' en $CONFIG_PATH"
  IFS='.' read -r major minor patch <<< "$current_version"
  local build=$current_build
  case "$level" in
    X) major=$((major+1)); minor=0; patch=0 ;;
    Y) minor=$((minor+1)); patch=0 ;;
    Z) patch=$((patch+1)) ;;
  esac
  build=$((build+1))
  local new_version="$major.$minor.$patch"
  sed -i -E "s/'version'\s*=>\s*'[0-9]+\.[0-9]+\.[0-9]+'/'version' => '$new_version'/" "$CONFIG_PATH"
  sed -i -E "s/'build'\s*=>\s*[0-9]+/'build' => $build/"                               "$CONFIG_PATH"
  echo "$level|$new_version|$build"
}

# ── 1. Validaciones previas ───────────────────────────────────────────────────

command -v git >/dev/null 2>&1 || error "git no esta disponible."
command -v zip >/dev/null 2>&1 || error "zip no esta instalado. Ejecuta: sudo apt install zip"

git -C "$SCRIPT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1 \
  || error "El proyecto no es un repositorio git valido."

assert_file_exists "$COMPOSE_FILE"
assert_file_exists "$ENV_DOCKER_FILE"
assert_file_exists "$NGINX_CONF_FILE"
assert_no_legacy_credentials

# ── 2. Autoversion + commit git ───────────────────────────────────────────────

step "Actualizando revision local del proyecto..."
AUTO_LEVEL=$(get_auto_level)
rev_info=$(update_project_revision "$AUTO_LEVEL")
REV_LEVEL="${rev_info%%|*}"; _tmp="${rev_info#*|}"; REV_VERSION="${_tmp%%|*}"; REV_BUILD="${_tmp##*|}"
step "Revision nueva: v$REV_VERSION build $REV_BUILD (nivel $REV_LEVEL)"

step "Creando commit git obligatorio antes del deploy..."
status_output=$(git -C "$SCRIPT_DIR" status --porcelain)
if [[ -z "$status_output" ]]; then
  git -C "$SCRIPT_DIR" commit --allow-empty -m "chore: deploy v$REV_VERSION build $REV_BUILD"
else
  git -C "$SCRIPT_DIR" add --all
  git -C "$SCRIPT_DIR" commit -m "chore: deploy v$REV_VERSION build $REV_BUILD"
fi

# ── 3. Determinar que incluir en el paquete ───────────────────────────────────

RELEASE_ITEMS=(app bootstrap config database/migrations migrate_database.php public routes views vendor composer.json composer.lock .env)

LATEST_CHANGED=$(get_latest_commit_changed_files)
SYNC_VENDOR=1
if [[ -n "$LATEST_CHANGED" && "$LATEST_CHANGED" != "ALL" ]]; then
  echo "$LATEST_CHANGED" | grep -qE '^composer\.json$|^composer\.lock$|^vendor/' || SYNC_VENDOR=0
fi
if [[ $SYNC_VENDOR -eq 0 ]]; then
  RELEASE_ITEMS=("${RELEASE_ITEMS[@]/vendor}")
  step "Optimizacion: se omite sincronizacion de vendor (composer no cambio en el ultimo commit)."
fi

INFRA_CHANGED=1
if [[ -n "$LATEST_CHANGED" && "$LATEST_CHANGED" != "ALL" ]]; then
  echo "$LATEST_CHANGED" | grep -qE '^docker-compose\.yml$|^docker/nginx/mrp\.conf$|^\.env\.docker$' || INFRA_CHANGED=0
fi
if [[ $INFRA_CHANGED -eq 1 ]]; then
  step "Infra Docker modificada: contenedores afectados se recrearan (volumenes intactos)."
else
  step "Solo codigo de aplicacion: los contenedores NO se recrean."
fi

HAS_LOCAL_SQL_MIGRATIONS=0
if [[ -d "$SCRIPT_DIR/database/migrations" ]]; then
  sql_count=$(find "$SCRIPT_DIR/database/migrations" -maxdepth 1 -name '*.sql' | wc -l)
  [[ $sql_count -gt 0 ]] && HAS_LOCAL_SQL_MIGRATIONS=1
fi

for item in "${RELEASE_ITEMS[@]}"; do
  [[ -z "$item" ]] && continue
  assert_file_exists "$SCRIPT_DIR/$item"
done

# ── 4. Empaquetar y subir ─────────────────────────────────────────────────────

step "Empaquetando codigo de la aplicacion para el VPS..."
zip_items=()
for item in "${RELEASE_ITEMS[@]}"; do [[ -n "$item" ]] && zip_items+=("$item"); done
(cd "$SCRIPT_DIR" && zip -r "$RELEASE_ZIP" "${zip_items[@]}")

step "Preparando directorios remotos en $REMOTE_PATH ..."
ssh_cmd "mkdir -p $REMOTE_PATH/docker/nginx $REMOTE_PATH/docker/logs/nginx $REMOTE_PATH/docker/logs/php-fpm $REMOTE_PATH/docker/logs/postgresql $REMOTE_PATH/docker/logs/pgadmin && chmod -R 0777 $REMOTE_PATH/docker/logs"

step "Subiendo docker-compose.yml ..."  && scp_upload "$COMPOSE_FILE"    "$REMOTE_PATH/docker-compose.yml"
step "Subiendo .env.docker ..."         && scp_upload "$ENV_DOCKER_FILE" "$REMOTE_PATH/.env.docker"
step "Subiendo configuracion Nginx ..." && scp_upload "$NGINX_CONF_FILE" "$REMOTE_PATH/docker/nginx/mrp.conf"
step "Subiendo paquete de aplicacion ..." && scp_upload "$RELEASE_ZIP"   "$REMOTE_PATH/app-release.zip"

# ── 5. Despliegue remoto ──────────────────────────────────────────────────────
# El script remoto usa placeholders (__VAR__) que se sustituyen con sed antes del envio,
# igual que el .Replace() del deploy.ps1. Las variables del servidor usan $ normal.

REMOTE_SCRIPT_TMP=$(mktemp /tmp/mrp-deploy-XXXXXX.sh)

cat > "$REMOTE_SCRIPT_TMP" << 'REMOTE_EOF'
set -e
echo "==> Verificando Docker"
command -v docker >/dev/null 2>&1 || { echo "[ERROR] Docker no instalado en el VPS"; exit 1; }
docker --version
command -v systemctl >/dev/null 2>&1 && systemctl enable docker >/dev/null 2>&1 || true

echo "==> Verificando Docker Compose plugin"
if ! docker compose version >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then apt-get update -y && apt-get install -y docker-compose-plugin
  elif command -v dnf >/dev/null 2>&1; then dnf install -y docker-compose-plugin || dnf install -y docker-compose
  elif command -v yum >/dev/null 2>&1; then yum install -y docker-compose-plugin || yum install -y docker-compose
  elif command -v apk >/dev/null 2>&1; then apk add --no-cache docker-cli-compose
  else echo "[ERROR] No se pudo instalar Docker Compose"; exit 1
  fi
fi
docker compose version

DOCKERHUB_USERNAME=""; DOCKERHUB_PASSWORD=""
if [ -f "__REMOTE_PATH__/.env.docker" ]; then
  DOCKERHUB_USERNAME=$(grep -E '^DOCKERHUB_USERNAME=' "__REMOTE_PATH__/.env.docker" | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
  DOCKERHUB_PASSWORD=$(grep -E '^DOCKERHUB_PASSWORD=' "__REMOTE_PATH__/.env.docker" | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
fi
if [ -n "$DOCKERHUB_USERNAME" ] && [ -n "$DOCKERHUB_PASSWORD" ]; then
  echo "==> Login en Docker Hub"
  echo "$DOCKERHUB_PASSWORD" | docker login -u "$DOCKERHUB_USERNAME" --password-stdin
else
  echo "==> Sin credenciales Docker Hub (modo anonimo)"
fi

echo "==> Desplegando stack LEPP"
cd __REMOTE_PATH__

command -v unzip >/dev/null 2>&1 || {
  if command -v apt-get >/dev/null 2>&1; then apt-get update -y && apt-get install -y unzip
  elif command -v dnf  >/dev/null 2>&1; then dnf install -y unzip
  elif command -v yum  >/dev/null 2>&1; then yum install -y unzip
  elif command -v apk  >/dev/null 2>&1; then apk add --no-cache unzip
  else echo "[ERROR] No se pudo instalar unzip"; exit 1
  fi
}

if [ -f app-release.zip ]; then
  rm -rf app bootstrap config database public routes views composer.json composer.lock .env migrate_database.php
  [ "__SYNC_VENDOR__" = "1" ] && rm -rf vendor
  unzip -o app-release.zip >/dev/null || true
  rm -f app-release.zip
fi

[ -d migrations ] && [ ! -d database/migrations ] && mkdir -p database && mv migrations database/migrations
mkdir -p database/migrations

chmod -R 0777 __REMOTE_PATH__/docker/logs || true
if [ "__SYNC_VENDOR__" = "1" ]; then
  find __REMOTE_PATH__/vendor -type d -exec chmod 755 {} +
  find __REMOTE_PATH__/vendor -type f -exec chmod 644 {} +
fi
for dir in app bootstrap config public routes views; do
  find __REMOTE_PATH__/$dir -type d -exec chmod 755 {} +
  find __REMOTE_PATH__/$dir -type f -exec chmod 644 {} +
done
mkdir -p __REMOTE_PATH__/storage/logs __REMOTE_PATH__/storage/cache
chmod -R 775 __REMOTE_PATH__/storage || true
chmod -R 777 __REMOTE_PATH__/storage/logs __REMOTE_PATH__/storage/cache || true

# IMPORTANTE: nunca usar 'docker compose down' ni flags -v: los volumenes son persistentes.
if [ "__INFRA_CHANGED__" = "1" ]; then
  echo "==> Infra Docker modificada: aplicando docker compose up -d ... (volumenes intactos)"
  docker compose up -d
else
  echo "==> Solo codigo actualizado: asegurando contenedores activos ..."
  docker compose start 2>/dev/null || docker compose up -d
fi

echo "==> Verificando driver PDO PostgreSQL en php-fpm"
if ! docker compose exec -T php-fpm php -m | grep -q '^pdo_pgsql$'; then
  echo "[WARN] pdo_pgsql no habilitado. Activando..."
  docker compose exec -T php-fpm sh -lc "printf 'extension=pgsql\nextension=pdo_pgsql\n' > /opt/bitnami/php/etc/conf.d/zz-pgsql.ini"
  docker compose restart php-fpm && sleep 3
  docker compose exec -T php-fpm php -m | grep -q '^pdo_pgsql$' \
    || { echo "[ERROR] No se pudo habilitar pdo_pgsql"; exit 1; }
  echo "[OK] pdo_pgsql habilitado"
else
  echo "[OK] pdo_pgsql ya estaba habilitado"
fi

echo "==> Validando extensiones PHP requeridas (PDF/XLSX/ZIP)"
MISSING_EXTENSIONS=""
for EXT in zip gd mbstring dom xml xmlwriter xmlreader fileinfo intl zlib; do
  docker compose exec -T php-fpm php -m | grep -qi "^${EXT}$" || MISSING_EXTENSIONS="$MISSING_EXTENSIONS $EXT"
done
[ -n "$MISSING_EXTENSIONS" ] && { echo "[ERROR] Faltan extensiones PHP:$MISSING_EXTENSIONS"; exit 1; }
echo "[OK] Extensiones PHP requeridas presentes"

echo "==> Validando requisitos de plataforma Composer"
docker compose exec -T php-fpm sh -lc 'cd /app && php /opt/bitnami/php/bin/composer check-platform-reqs --no-dev' \
  || { echo "[ERROR] Composer detecto requisitos faltantes"; exit 1; }

echo "==> Ejecutando migraciones SQL"
if [ "__HAS_MIGRATIONS__" != "1" ]; then
  echo "[WARN] Sin archivos .sql locales. Se omite migracion."
else
  MIGRATIONS_PATH="/app/database/migrations"
  docker compose exec -T php-fpm sh -lc "test -d /app/migrations" 2>/dev/null && MIGRATIONS_PATH="/app/migrations"
  if docker compose exec -T php-fpm php /app/migrate_database.php --path="$MIGRATIONS_PATH" --skip-existing; then
    echo "[OK] Migraciones completadas"
  else
    MIGRATION_FILES_COUNT=$(docker compose exec -T php-fpm sh -lc "ls -1 $MIGRATIONS_PATH/*.sql 2>/dev/null | wc -l" | tr -d '\r')
    if [ "${MIGRATION_FILES_COUNT:-0}" = "0" ]; then
      echo "[WARN] Sin archivos .sql en $MIGRATIONS_PATH. Se omite migracion."
    else
      echo "[ERROR] Fallaron las migraciones"; exit 1
    fi
  fi
fi

docker compose ps
REMOTE_EOF

# Sustituir placeholders con valores locales (igual que el .Replace() del PowerShell)
sed -i "s|__REMOTE_PATH__|$REMOTE_PATH|g"       "$REMOTE_SCRIPT_TMP"
sed -i "s|__SYNC_VENDOR__|$SYNC_VENDOR|g"       "$REMOTE_SCRIPT_TMP"
sed -i "s|__HAS_MIGRATIONS__|$HAS_LOCAL_SQL_MIGRATIONS|g" "$REMOTE_SCRIPT_TMP"
sed -i "s|__INFRA_CHANGED__|$INFRA_CHANGED|g"   "$REMOTE_SCRIPT_TMP"

step "Ejecutando despliegue remoto en $HOSTNAME ..."
if ssh_pipe "$REMOTE_SCRIPT_TMP" 'bash -s'; then
  echo ""
  echo "[SUCCESS] Deploy completado en $HOSTNAME"
else
  error "El despliegue remoto fallo."
fi
