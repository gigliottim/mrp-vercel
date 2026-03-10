<#
  Deploy automatizado de docker-compose.yml al VPS via SSH.
  Requisitos locales: PowerShell + modulo Posh-SSH.
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

function Assert-FileExists {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "No existe el archivo requerido: $Path"
    }
}

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$composeFile = Join-Path $projectRoot "docker-compose.yml"
$envDockerFile = Join-Path $projectRoot ".env.docker"
$nginxConfFile = Join-Path $projectRoot "docker/nginx/mrp.conf"

Assert-FileExists -Path $composeFile
Assert-FileExists -Path $envDockerFile
Assert-FileExists -Path $nginxConfFile

Write-Step "Validando modulo Posh-SSH..."
if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
    Write-Step "Posh-SSH no encontrado. Instalando en CurrentUser..."
    Install-Module -Name Posh-SSH -Scope CurrentUser -Force -AllowClobber
}

Import-Module Posh-SSH -ErrorAction Stop

Write-Step "Creando sesion SSH a $UserName@$HostName:$Port ..."
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
"@
    Invoke-SSHCommand -SessionId $sessionId -Command $prepareCmd | Out-Null

    Write-Step "Subiendo docker-compose.yml ..."
    Set-SCPItem -SessionId $sessionId -Path $composeFile -Destination "$RemotePath/docker-compose.yml"

    Write-Step "Subiendo .env.docker ..."
    Set-SCPItem -SessionId $sessionId -Path $envDockerFile -Destination "$RemotePath/.env.docker"

    Write-Step "Subiendo configuracion Nginx ..."
    Set-SCPItem -SessionId $sessionId -Path $nginxConfFile -Destination "$RemotePath/docker/nginx/mrp.conf"

    Write-Step "Verificando Docker y plugin Compose en VPS..."
    $remoteDeployCmd = @"
set -e

echo "==> Verificando Docker"
if ! command -v docker >/dev/null 2>&1; then
  echo "[ERROR] Docker no esta instalado en el VPS"
  exit 1
fi

docker --version

echo "==> Verificando Docker Compose plugin"
if ! docker compose version >/dev/null 2>&1; then
  echo "[WARN] Docker Compose plugin no encontrado. Intentando instalar..."

  if command -v apt-get >/dev/null 2>&1; then
    apt-get update -y
    apt-get install -y docker-compose-plugin
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

echo "==> Desplegando stack LEPP"
cd $RemotePath
docker compose pull
docker compose up -d
docker compose ps
"@

    $deployResult = Invoke-SSHCommand -SessionId $sessionId -Command $remoteDeployCmd
    $deployOutput = ($deployResult.Output -join [Environment]::NewLine)
    Write-Host $deployOutput

    if ($deployResult.ExitStatus -ne 0) {
        throw "El despliegue remoto fallo con codigo $($deployResult.ExitStatus)."
    }

    Write-Host "`n[SUCCESS] Deploy completado en $HostName" -ForegroundColor Green
}
finally {
    if ($session) {
        Remove-SSHSession -SessionId $session.SessionId | Out-Null
    }
}
