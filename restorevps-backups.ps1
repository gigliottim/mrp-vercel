<#
  Restaura backups SQL en el PostgreSQL del VPS (contenedor lepp-postgresql).
#>

[CmdletBinding()]
param(
    [string]$HostName = "181.13.244.35",
    [int]$Port = 5073,
    [string]$UserName = "root",
    [string]$Password = 'w(6C%QnZC7EQPZ',
    [string]$RemotePath = "/opt/mrp",
    [string]$BackupFolder = "2026-03-10_17-22-40"
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host "[RESTORE] $Message" -ForegroundColor Cyan
}

function Assert-FileExists {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "No existe el archivo requerido: $Path"
    }
}

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$localBackupPath = Join-Path $projectRoot "database/backups/$BackupFolder"
$authSql = Join-Path $localBackupPath "mrp_auth.sql"
$demoSql = Join-Path $localBackupPath "mrp_demo.sql"
$tunnaSql = Join-Path $localBackupPath "mrp_tunna.sql"

Assert-FileExists -Path $authSql
Assert-FileExists -Path $demoSql
Assert-FileExists -Path $tunnaSql

if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
    Write-Step "Posh-SSH no encontrado. Instalando en CurrentUser..."
    Install-Module -Name Posh-SSH -Scope CurrentUser -Force -AllowClobber
}
Import-Module Posh-SSH -ErrorAction Stop

$securePassword = ConvertTo-SecureString $Password -AsPlainText -Force
$credential = New-Object System.Management.Automation.PSCredential ($UserName, $securePassword)

Write-Step "Conectando a $UserName@${HostName}:$Port ..."
$session = New-SSHSession -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey

try {
    $sessionId = $session.SessionId
    $remoteRestorePath = "$RemotePath/restore/$BackupFolder"

    Write-Step "Preparando carpeta remota: $remoteRestorePath"
    Invoke-SSHCommand -SessionId $sessionId -Command "mkdir -p $remoteRestorePath" | Out-Null

    Write-Step "Subiendo archivos SQL al VPS..."
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $authSql -Destination $remoteRestorePath -NewName "mrp_auth.sql"
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $demoSql -Destination $remoteRestorePath -NewName "mrp_demo.sql"
    Set-SCPItem -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Path $tunnaSql -Destination $remoteRestorePath -NewName "mrp_tunna.sql"

    Write-Step "Restaurando bases en contenedor lepp-postgresql..."
    $remoteCmdTemplate = @'
set -e

REMOTE_RESTORE="__REMOTE_RESTORE__"

# Asegurar role "mrp" para sentencias ALTER OWNER del dump.
docker exec -u 0 lepp-postgresql su postgres -c "psql -d postgres -v ON_ERROR_STOP=1 -c \"DO \\\$\\\$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mrp') THEN CREATE ROLE mrp; END IF; END \\\$\\\$;\""

for DB in mrp_auth mrp_demo mrp_tunna; do
  echo "==> Restaurando $DB"
  docker exec -u 0 lepp-postgresql su postgres -c "psql -d postgres -v ON_ERROR_STOP=1 -c \"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB' AND pid <> pg_backend_pid();\"" >/dev/null 2>&1 || true
  docker exec -u 0 lepp-postgresql su postgres -c "psql -d postgres -v ON_ERROR_STOP=1 -c \"DROP DATABASE IF EXISTS \\\"$DB\\\";\""
  docker exec -u 0 lepp-postgresql su postgres -c "psql -d postgres -v ON_ERROR_STOP=1 -c \"CREATE DATABASE \\\"$DB\\\" OWNER mrp;\""
  docker cp "$REMOTE_RESTORE/$DB.sql" lepp-postgresql:/tmp/$DB.sql
  docker exec -u 0 lepp-postgresql su postgres -c "psql -d $DB -v ON_ERROR_STOP=1 -f /tmp/$DB.sql"
  docker exec -u 0 lepp-postgresql rm -f /tmp/$DB.sql
  docker exec -u 0 lepp-postgresql su postgres -c "psql -d $DB -c \"SELECT current_database();\""
done
'@
    $remoteCmd = $remoteCmdTemplate.Replace("__REMOTE_RESTORE__", $remoteRestorePath)

    $result = Invoke-SSHCommand -SessionId $sessionId -Command $remoteCmd
    $output = ($result.Output -join [Environment]::NewLine)
    Write-Host $output

    if ($result.ExitStatus -ne 0) {
        throw "La restauracion fallo con codigo $($result.ExitStatus)."
    }

    Write-Host "`n[SUCCESS] Restauracion completada en $HostName" -ForegroundColor Green
}
finally {
    if ($session) {
        Remove-SSHSession -SessionId $session.SessionId | Out-Null
    }
}
