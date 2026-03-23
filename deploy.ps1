<#
  Deploy automatizado al VPS via SSH.
    1) Actualiza revision automaticamente segun cambios.
    2) Ejecuta commit git obligatorio.
    3) Empaqueta y sube el codigo al VPS.
    4) Ejecuta setup remoto: Docker, contenedores, migraciones.
#>

[CmdletBinding()]
param(
    [string]$HostName = "181.13.244.35",
    [int]$Port = 5073,
    [string]$UserName = "root",
    [string]$Password = 'w(6C%QnZC7EQPZ',
    [string]$RemotePath = "/opt/mrp"
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host "[DEPLOY] $Message" -ForegroundColor Cyan
}

function Write-Warn {
    param([string]$Message)
    Write-Host "[DEPLOY][WARN] $Message" -ForegroundColor Yellow
}

function Assert-FileExists {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "No existe el archivo requerido: $Path"
    }
}

function Assert-NoLegacyCredentials {
    param([string]$RepoRoot)

    $scanFiles = @(
        (Join-Path $RepoRoot '.env')
        (Join-Path $RepoRoot '.env.example')
        (Join-Path $RepoRoot '.env.docker')
        (Join-Path $RepoRoot 'docker-compose.yml')
        (Join-Path $RepoRoot 'config/database.php')
    )

    $legacyPatterns = @(
        'ojp9Q6aYT3KHDE8sMS2u',
        'D:\\Gigliotti\\Documentos\\MartinG\\WEBS\\mrp',
        'DB_PGSQL_HOST=localhost',
        'DB_AUTH_HOST=localhost',
        'DB_TENANT_HOST=localhost',
        'DB_PGSQL_HOST=127\.0\.0\.1',
        'DB_AUTH_HOST=127\.0\.0\.1',
        'DB_TENANT_HOST=127\.0\.0\.1'
    )

    foreach ($file in $scanFiles) {
        if (-not (Test-Path -LiteralPath $file)) { continue }
        $content = Get-Content -Path $file -Raw
        foreach ($pattern in $legacyPatterns) {
            if ($content -match $pattern) {
                throw "Se detecto configuracion legacy/local en $file (patron: $pattern). Corregi antes de desplegar."
            }
        }
    }
}

function Get-LatestCommitChangedFiles {
    param([string]$RepoRoot)

    if (-not (Get-Command git -ErrorAction SilentlyContinue)) { return @() }
    $insideGit = git -C $RepoRoot rev-parse --is-inside-work-tree 2>$null
    if ($insideGit -ne 'true') { return @() }

    git -C $RepoRoot rev-parse --verify HEAD~1 *> $null
    if ($LASTEXITCODE -ne 0) { return @('ALL') }

    $changed = git -C $RepoRoot diff-tree --no-commit-id --name-only -r HEAD
    return @($changed | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
}

function Get-AutoLevel {
    param([string]$RepoRoot)

    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        return 'Z'
    }

    $insideGit = git -C $RepoRoot rev-parse --is-inside-work-tree 2>$null
    if ($insideGit -ne 'true') {
        return 'Z'
    }

    $changedFiles = New-Object System.Collections.Generic.HashSet[string]
    $unstaged = git -C $RepoRoot diff --name-only
    $staged = git -C $RepoRoot diff --name-only --cached
    $untracked = git -C $RepoRoot ls-files --others --exclude-standard

    foreach ($list in @($unstaged, $staged, $untracked)) {
        foreach ($file in $list) {
            if (-not [string]::IsNullOrWhiteSpace($file)) {
                [void]$changedFiles.Add($file)
            }
        }
    }

    if ($changedFiles.Count -eq 0) {
        return 'Z'
    }

    $majorPatterns = @(
        '^app/core/',
        '^bootstrap/',
        '^config/database\.php$',
        '^routes/'
    )

    $minorPatterns = @(
        '^app/controllers/',
        '^app/services/',
        '^app/models/',
        '^app/Repositories/',
        '^database/migrations/',
        '^public/api/',
        '^views/pages/'
    )

    foreach ($file in $changedFiles) {
        foreach ($pattern in $majorPatterns) {
            if ($file -match $pattern) {
                return 'X'
            }
        }
    }

    foreach ($file in $changedFiles) {
        foreach ($pattern in $minorPatterns) {
            if ($file -match $pattern) {
                return 'Y'
            }
        }
    }

    return 'Z'
}

function Update-ProjectRevision {
    param([string]$ConfigPath, [string]$Level)

    $content = Get-Content -Path $ConfigPath -Raw
    $versionRegex = "'version'\s*=>\s*'(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)'"
    $buildRegex = "'build'\s*=>\s*(?<build>\d+)"

    $versionMatch = [regex]::Match($content, $versionRegex)
    $buildMatch = [regex]::Match($content, $buildRegex)
    if (-not $versionMatch.Success -or -not $buildMatch.Success) {
        throw "No se pudo leer version/build en $ConfigPath"
    }

    $major = [int]$versionMatch.Groups['major'].Value
    $minor = [int]$versionMatch.Groups['minor'].Value
    $patch = [int]$versionMatch.Groups['patch'].Value
    $build = [int]$buildMatch.Groups['build'].Value

    switch ($Level) {
        'X' { $major++; $minor = 0; $patch = 0 }
        'Y' { $minor++; $patch = 0 }
        'Z' { $patch++ }
    }

    $build++
    $newVersion = "$major.$minor.$patch"

    $newContent = [regex]::Replace($content, $versionRegex, "'version' => '$newVersion'", 1)
    $newContent = [regex]::Replace($newContent, $buildRegex, "'build' => $build", 1)
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($ConfigPath, $newContent, $utf8NoBom)

    return @{
        Level   = $Level
        Version = $newVersion
        Build   = $build
    }
}

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $projectRoot "config/app.php"
$composeFile = Join-Path $projectRoot "docker-compose.yml"
$envDockerFile = Join-Path $projectRoot ".env.docker"
$nginxConfFile = Join-Path $projectRoot "docker/nginx/mrp.conf"

# ── 1. Validaciones previas ───────────────────────────────────────────────────

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw "Git no esta disponible. El flujo requiere commit antes de desplegar."
}
$insideGit = git -C $projectRoot rev-parse --is-inside-work-tree 2>$null
if ($insideGit -ne 'true') {
    throw "El proyecto no es un repositorio git valido."
}

Assert-FileExists -Path $composeFile
Assert-FileExists -Path $envDockerFile
Assert-FileExists -Path $nginxConfFile

Assert-NoLegacyCredentials -RepoRoot $projectRoot

# ── 2. Autoversion + commit git ───────────────────────────────────────────────

Write-Step "Actualizando revision local del proyecto..."
$autoLevel = Get-AutoLevel -RepoRoot $projectRoot
$rev = Update-ProjectRevision -ConfigPath $configPath -Level $autoLevel
Write-Step "Revision nueva: v$($rev.Version) build $($rev.Build) (nivel $($rev.Level))"

Write-Step "Creando commit git obligatorio antes del deploy..."
$statusOutput = git -C $projectRoot status --porcelain
if ([string]::IsNullOrWhiteSpace(($statusOutput -join ""))) {
    git -C $projectRoot commit --allow-empty -m "chore: deploy v$($rev.Version) build $($rev.Build)" | Out-Null
}
else {
    git -C $projectRoot add --all
    git -C $projectRoot commit -m "chore: deploy v$($rev.Version) build $($rev.Build)" | Out-Null
}

# ── 3. Determinar que incluir en el paquete ───────────────────────────────────

$releaseItems = @(
    "app", "bootstrap", "config",
    "database/migrations", "migrate_database.php",
    "public", "routes", "views",
    "vendor", "composer.json", "composer.lock", ".env"
)

$latestChangedFiles = Get-LatestCommitChangedFiles -RepoRoot $projectRoot

# -- vendor: solo sincronizar si composer cambio
$syncVendor = $true
if ($latestChangedFiles.Count -gt 0 -and -not ($latestChangedFiles -contains 'ALL')) {
    $syncVendor = ($latestChangedFiles -match '^composer\.json$|^composer\.lock$|^vendor/') -ne $null
}
if (-not $syncVendor) {
    $releaseItems = $releaseItems | Where-Object { $_ -ne 'vendor' }
    Write-Step "Optimizacion: se omite sincronizacion de vendor (composer no cambio en el ultimo commit)."
}

# -- infra: solo recrear contenedores si cambiaron archivos de configuracion Docker
# NUNCA se usan 'docker compose down' ni flags -v: los volumenes persistentes son intocables.
$infraFiles = @(
    'docker-compose.yml',
    'docker/nginx/mrp.conf',
    '.env.docker'
)
$infraChanged = $true   # primer deploy o repo sin historial: asumimos infra nueva
if ($latestChangedFiles.Count -gt 0 -and -not ($latestChangedFiles -contains 'ALL')) {
    $infraChanged = ($null -ne ($latestChangedFiles | Where-Object {
                $f = $_
                $infraFiles | Where-Object { $f -like $_ }
            } | Select-Object -First 1))
}
if ($infraChanged) {
    Write-Step "Infra Docker modificada: los contenedores afectados se recrearan (volumenes intactos)."
}
else {
    Write-Step "Solo codigo de aplicacion: los contenedores NO se recrean."
}

$migrationsDir = Join-Path $projectRoot 'database/migrations'
$hasLocalSqlMigrations = $false
if (Test-Path -LiteralPath $migrationsDir) {
    $sqlFiles = Get-ChildItem -Path $migrationsDir -Filter '*.sql' -File -ErrorAction SilentlyContinue
    $hasLocalSqlMigrations = $sqlFiles.Count -gt 0
}

foreach ($item in $releaseItems) {
    Assert-FileExists -Path (Join-Path $projectRoot $item)
}

# ── 4. SSH / SCP ──────────────────────────────────────────────────────────────

Write-Step "Validando modulo Posh-SSH..."
if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
    Write-Step "Posh-SSH no encontrado. Instalando en CurrentUser..."
    Install-Module -Name Posh-SSH -Scope CurrentUser -Force -AllowClobber
}
Import-Module Posh-SSH -ErrorAction Stop

$releaseZipPath = Join-Path $projectRoot "app-release.zip"
if (Test-Path -LiteralPath $releaseZipPath) { Remove-Item -LiteralPath $releaseZipPath -Force }

Write-Step "Empaquetando codigo de la aplicacion para el VPS..."
$pathsToZip = foreach ($item in $releaseItems) { Join-Path $projectRoot $item }
Compress-Archive -Path $pathsToZip -DestinationPath $releaseZipPath -Force

Write-Step "Creando sesion SSH a $UserName@${HostName}:$Port ..."
$securePassword = ConvertTo-SecureString $Password -AsPlainText -Force
$credential = New-Object System.Management.Automation.PSCredential ($UserName, $securePassword)
$session = New-SSHSession -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey

try {
    $sessionId = $session.SessionId

    Write-Step "Preparando directorios remotos en $RemotePath ..."
    $prepareCmd = @"
mkdir -p $RemotePath
mkdir -p $RemotePath/docker/nginx
mkdir -p $RemotePath/docker/logs/nginx
mkdir -p $RemotePath/docker/logs/php-fpm
mkdir -p $RemotePath/docker/logs/postgresql
mkdir -p $RemotePath/docker/logs/pgadmin
chmod -R 0777 $RemotePath/docker/logs
"@
    Invoke-SSHCommand -SessionId $sessionId -Command $prepareCmd | Out-Null

    Write-Step "Subiendo docker-compose.yml ..."
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $composeFile -Destination "$RemotePath" -NewName "docker-compose.yml"

    Write-Step "Subiendo .env.docker ..."
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $envDockerFile -Destination "$RemotePath" -NewName ".env.docker"

    Write-Step "Subiendo configuracion Nginx ..."
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $nginxConfFile -Destination "$RemotePath/docker/nginx" -NewName "mrp.conf"

    Write-Step "Subiendo paquete de aplicacion ..."
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $releaseZipPath -Destination "$RemotePath" -NewName "app-release.zip"

    # ── 5. Despliegue remoto ──────────────────────────────────────────────────

    $syncVendorFlag = if ($syncVendor) { '1' } else { '0' }
    $hasMigrationsFlag = if ($hasLocalSqlMigrations) { '1' } else { '0' }    $infraChangedFlag = if ($infraChanged) { '1' } else { '0' }
    $remoteDeployCmd = @'
set -e

echo "==> Verificando Docker"
if ! command -v docker >/dev/null 2>&1; then
  echo "[ERROR] Docker no esta instalado en el VPS"
  exit 1
fi
docker --version

if command -v systemctl >/dev/null 2>&1; then
  systemctl enable docker >/dev/null 2>&1 || true
fi

echo "==> Verificando Docker Compose plugin"
if ! docker compose version >/dev/null 2>&1; then
  echo "[WARN] Docker Compose plugin no encontrado. Intentando instalar..."
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update -y && apt-get install -y docker-compose-plugin
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y docker-compose-plugin || dnf install -y docker-compose
  elif command -v yum >/dev/null 2>&1; then
    yum install -y docker-compose-plugin || yum install -y docker-compose
  elif command -v apk >/dev/null 2>&1; then
    apk add --no-cache docker-cli-compose
  else
    echo "[ERROR] No se pudo detectar gestor de paquetes para instalar Compose"
    exit 1
  fi
fi
docker compose version

# Leer credenciales Docker Hub desde .env.docker
DOCKERHUB_USERNAME=""
DOCKERHUB_PASSWORD=""
if [ -f "__REMOTE_PATH__/.env.docker" ]; then
  DOCKERHUB_USERNAME=$(grep -E '^DOCKERHUB_USERNAME=' "__REMOTE_PATH__/.env.docker" | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
  DOCKERHUB_PASSWORD=$(grep -E '^DOCKERHUB_PASSWORD=' "__REMOTE_PATH__/.env.docker" | head -n1 | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
fi
if [ -n "$DOCKERHUB_USERNAME" ] && [ -n "$DOCKERHUB_PASSWORD" ]; then
  echo "==> Login en Docker Hub"
  echo "$DOCKERHUB_PASSWORD" | docker login -u "$DOCKERHUB_USERNAME" --password-stdin
else
  echo "==> Sin credenciales Docker Hub en .env.docker (modo anonimo)"
fi

echo "==> Desplegando stack LEPP"
cd __REMOTE_PATH__

if ! command -v unzip >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then apt-get update -y && apt-get install -y unzip
  elif command -v dnf >/dev/null 2>&1; then dnf install -y unzip
  elif command -v yum >/dev/null 2>&1; then yum install -y unzip
  elif command -v apk >/dev/null 2>&1; then apk add --no-cache unzip
  else echo "[ERROR] No se pudo instalar unzip"; exit 1
  fi
fi

if [ -f app-release.zip ]; then
  rm -rf app bootstrap config database public routes views composer.json composer.lock .env migrate_database.php
  if [ "__SYNC_VENDOR__" = "1" ]; then rm -rf vendor; fi
  unzip -o app-release.zip >/dev/null || true
  rm -f app-release.zip
fi

if [ -d migrations ] && [ ! -d database/migrations ]; then
  mkdir -p database && mv migrations database/migrations
fi
mkdir -p database/migrations

chmod -R 0777 __REMOTE_PATH__/docker/logs || true
if [ "__SYNC_VENDOR__" = "1" ]; then
  find __REMOTE_PATH__/vendor -type d -exec chmod 755 {} +
  find __REMOTE_PATH__/vendor -type f -exec chmod 644 {} +
fi
find __REMOTE_PATH__/app      -type d -exec chmod 755 {} +; find __REMOTE_PATH__/app      -type f -exec chmod 644 {} +
find __REMOTE_PATH__/bootstrap -type d -exec chmod 755 {} +; find __REMOTE_PATH__/bootstrap -type f -exec chmod 644 {} +
find __REMOTE_PATH__/config   -type d -exec chmod 755 {} +; find __REMOTE_PATH__/config   -type f -exec chmod 644 {} +
find __REMOTE_PATH__/public   -type d -exec chmod 755 {} +; find __REMOTE_PATH__/public   -type f -exec chmod 644 {} +
find __REMOTE_PATH__/routes   -type d -exec chmod 755 {} +; find __REMOTE_PATH__/routes   -type f -exec chmod 644 {} +
find __REMOTE_PATH__/views    -type d -exec chmod 755 {} +; find __REMOTE_PATH__/views    -type f -exec chmod 644 {} +
mkdir -p __REMOTE_PATH__/storage/logs __REMOTE_PATH__/storage/cache
chmod -R 775 __REMOTE_PATH__/storage || true
chmod -R 777 __REMOTE_PATH__/storage/logs __REMOTE_PATH__/storage/cache || true

# IMPORTANTE: nunca usar 'docker compose down' ni 'docker compose down -v'.
# Los volumenes nombrados (postgresql_data, pgadmin_data) son persistentes y no deben borrarse.
if [ "__INFRA_CHANGED__" = "1" ]; then
  echo "==> Infra Docker modificada: aplicando docker compose up -d ..."
  echo "    (Solo se recrean los contenedores cuya config cambio; los volumenes de datos son intocables)"
  docker compose up -d
else
  echo "==> Solo codigo de aplicacion actualizado. Asegurando que los contenedores esten corriendo ..."
  # 'docker compose start' levanta contenedores detenidos sin recrearlos ni tocar volumenes
  docker compose start 2>/dev/null || docker compose up -d
fi

echo "==> Verificando driver PDO PostgreSQL en php-fpm"
if ! docker compose exec -T php-fpm php -m | grep -q '^pdo_pgsql$'; then
  echo "[WARN] pdo_pgsql no habilitado. Activando extensiones pgsql/pdo_pgsql..."
  docker compose exec -T php-fpm sh -lc "cat > /opt/bitnami/php/etc/conf.d/zz-pgsql.ini <<'EOF'
extension=pgsql
extension=pdo_pgsql
EOF"
  docker compose restart php-fpm
  sleep 3
  if ! docker compose exec -T php-fpm php -m | grep -q '^pdo_pgsql$'; then
    echo "[ERROR] No se pudo habilitar pdo_pgsql en php-fpm"
    exit 1
  fi
  echo "[OK] pdo_pgsql habilitado en php-fpm"
else
  echo "[OK] pdo_pgsql ya estaba habilitado"
fi

echo "==> Validando extensiones PHP requeridas (PDF/XLSX/ZIP)"
MISSING_EXTENSIONS=""
for EXT in zip gd mbstring dom xml xmlwriter xmlreader fileinfo intl zlib; do
  if ! docker compose exec -T php-fpm php -m | grep -qi "^${EXT}$"; then
    MISSING_EXTENSIONS="$MISSING_EXTENSIONS $EXT"
  fi
done
if [ -n "$MISSING_EXTENSIONS" ]; then
  echo "[ERROR] Faltan extensiones PHP requeridas:$MISSING_EXTENSIONS"
  exit 1
fi
echo "[OK] Extensiones PHP requeridas presentes"

echo "==> Validando requisitos de plataforma Composer"
if ! docker compose exec -T php-fpm sh -lc "cd /app && php /opt/bitnami/php/bin/composer check-platform-reqs --no-dev"; then
  echo "[ERROR] Composer detecto requisitos de plataforma faltantes"
  exit 1
fi

echo "==> Ejecutando migraciones SQL (single connection default)"
if [ "__HAS_MIGRATIONS__" != "1" ]; then
  echo "[WARN] Sin archivos .sql locales. Se omite migracion."
else
  MIGRATIONS_PATH="/app/database/migrations"
  if docker compose exec -T php-fpm sh -lc "test -d /app/migrations"; then
    MIGRATIONS_PATH="/app/migrations"
  fi
  if docker compose exec -T php-fpm php /app/migrate_database.php --path="$MIGRATIONS_PATH" --skip-existing; then
    echo "[OK] Migraciones default completadas"
  else
    MIGRATION_FILES_COUNT=$(docker compose exec -T php-fpm sh -lc "ls -1 $MIGRATIONS_PATH/*.sql 2>/dev/null | wc -l" | tr -d '\r')
    if [ "${MIGRATION_FILES_COUNT:-0}" = "0" ]; then
      echo "[WARN] Sin archivos .sql en $MIGRATIONS_PATH. Se omite migracion."
    else
      echo "[ERROR] Fallaron las migraciones default"; exit 1
    fi
  fi

  echo "==> Ejecutando migraciones en todas las BDs empresa (all-tenants)"
  if docker compose exec -T php-fpm php /app/migrate_database.php --path="$MIGRATIONS_PATH" --skip-existing --all-tenants; then
    echo "[OK] Migraciones all-tenants completadas"
  else
    echo "[ERROR] Fallaron las migraciones en alguna BD empresa"; exit 1
  fi
fi

docker compose ps
'@
    $remoteDeployCmd = $remoteDeployCmd.Replace("__REMOTE_PATH__", $RemotePath)
    $remoteDeployCmd = $remoteDeployCmd.Replace("__SYNC_VENDOR__", $syncVendorFlag)
    $remoteDeployCmd = $remoteDeployCmd.Replace("__HAS_MIGRATIONS__", $hasMigrationsFlag)
    $remoteDeployCmd = $remoteDeployCmd.Replace("__INFRA_CHANGED__", $infraChangedFlag)

    $deployResult = Invoke-SSHCommand -SessionId $sessionId -Command $remoteDeployCmd
    $deployOutput = ($deployResult.Output -join [Environment]::NewLine)
    Write-Host $deployOutput

    if ($deployResult.ExitStatus -ne 0) {
        throw "El despliegue remoto fallo con codigo $($deployResult.ExitStatus)."
    }

    Write-Host "`n[SUCCESS] Deploy completado en $HostName" -ForegroundColor Green
}
finally {
    if (Test-Path -LiteralPath $releaseZipPath) {
        Remove-Item -LiteralPath $releaseZipPath -Force
    }
    if ($session) {
        Remove-SSHSession -SessionId $session.SessionId | Out-Null
    }
}
