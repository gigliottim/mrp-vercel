# ================================================================
# Script de Backup de Bases de Datos - MRP System
# ================================================================
# Version: 1.0
# Fecha: 11 de febrero de 2026
# Descripcion: Realiza backup de todas las bases de datos del proyecto
# ================================================================

param(
    [string]$OutputPath = "$PSScriptRoot\..\database\backups",
    [switch]$Compress
)

# Configuracion
$ErrorActionPreference = "Stop"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$backupDir = Join-Path $OutputPath $timestamp

# Colores para output
function Write-Info { Write-Host $args -ForegroundColor Cyan }
function Write-Success { Write-Host $args -ForegroundColor Green }
function Write-Warning { Write-Host $args -ForegroundColor Yellow }
function Write-Error { Write-Host $args -ForegroundColor Red }

# Banner
Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "        BACKUP DE BASES DE DATOS - MRP System          " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

# Crear directorio de backup
Write-Info "[INFO] Creando directorio de backup: $backupDir"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

# Configuracion de conexion
$pgsqlHost = "localhost"
$pgsqlPort = "5432"
$pgsqlUser = "mrp"
$pgsqlPass = "ojp9Q6aYT3KHDE8sMS2u"
$pgsqlPrefix = "mrp_"
$dockerContainer = "lemp-postgresql"

# Resolver origen de conexion PostgreSQL
$containerExists = docker ps -a --format "{{.Names}}" 2>$null | Select-String -Pattern "^$dockerContainer$"

# Detectar automaticamente todas las bases con prefijo mrp_
if ($containerExists) {
    Write-Info "[INFO] Usando contenedor Docker PostgreSQL: $dockerContainer"
    $dbListRaw = docker exec -e PGPASSWORD=$pgsqlPass $dockerContainer psql -U $pgsqlUser -d postgres -t -c "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE '$($pgsqlPrefix)%' ORDER BY datname;"
}
else {
    Write-Warning "[INFO] Contenedor PostgreSQL no encontrado, usando conexion local"
    $env:PGPASSWORD = $pgsqlPass
    $dbListRaw = psql -h $pgsqlHost -p $pgsqlPort -U $pgsqlUser -d postgres -t -c "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE '$($pgsqlPrefix)%' ORDER BY datname;"
}

$pgsqlDatabases = @($dbListRaw | ForEach-Object { $_.ToString().Trim() } | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })

if ($pgsqlDatabases.Count -eq 0) {
    Write-Error "[ERROR] No se detectaron bases de datos con prefijo '$pgsqlPrefix'"
    exit 1
}

Write-Info "[INFO] Bases detectadas para backup: $($pgsqlDatabases -join ', ')"

# Contador de exitos
$successCount = 0
$totalDatabases = $pgsqlDatabases.Count

# ================================================================
# BACKUP DE POSTGRESQL
# ================================================================
Write-Host ""
Write-Info "========================================================"
Write-Info "  BACKUP DE BASES DE DATOS POSTGRESQL"
Write-Info "========================================================"
Write-Host ""

foreach ($db in $pgsqlDatabases) {
    try {
        $outputFile = Join-Path $backupDir "$db.sql"
        Write-Info "[BACKUP] Respaldando: $db"

        if ($containerExists) {
            Write-Info "         Usando contenedor Docker: $dockerContainer"
            $env:PGPASSWORD = $pgsqlPass
            $cmd = "docker exec -e PGPASSWORD=$pgsqlPass $dockerContainer pg_dump -U $pgsqlUser $db"
            Invoke-Expression $cmd | Out-File -FilePath $outputFile -Encoding UTF8
        }
        else {
            Write-Warning "         Contenedor no encontrado, intentando conexion local"
            $env:PGPASSWORD = $pgsqlPass
            $cmd = "pg_dump -h $pgsqlHost -p $pgsqlPort -U $pgsqlUser $db"
            Invoke-Expression $cmd | Out-File -FilePath $outputFile -Encoding UTF8
        }

        $fileSizeKB = [math]::Round((Get-Item $outputFile).Length / 1KB, 2)
        Write-Success "         [OK] Completado: $outputFile (${fileSizeKB} KB)"
        $successCount++

    }
    catch {
        Write-Error "         [ERROR] Error al respaldar ${db}: $_"
    }
}

# ================================================================
# COMPRIMIR BACKUP (OPCIONAL)
# ================================================================
if ($Compress) {
    Write-Host ""
    Write-Info "========================================================"
    Write-Info "  COMPRIMIENDO BACKUPS"
    Write-Info "========================================================"
    Write-Host ""

    try {
        $zipFile = "$backupDir.zip"
        Write-Info "[ZIP] Creando archivo: $zipFile"
        Compress-Archive -Path $backupDir -DestinationPath $zipFile -Force

        $zipSizeMB = [math]::Round((Get-Item $zipFile).Length / 1MB, 2)
        Write-Success "[OK] Archivo comprimido: $zipFile (${zipSizeMB} MB)"

        # Eliminar directorio sin comprimir
        Remove-Item -Path $backupDir -Recurse -Force
        Write-Info "[CLEAN] Directorio temporal eliminado"

    }
    catch {
        Write-Error "[ERROR] Error al comprimir: $_"
    }
}

# ================================================================
# RESUMEN FINAL
# ================================================================
Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "              RESUMEN DEL BACKUP                        " -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  [*] Ubicacion:  " -NoNewline; Write-Host $backupDir -ForegroundColor Yellow
Write-Host "  [*] Exitosos:   " -NoNewline; Write-Host "$successCount/$totalDatabases" -ForegroundColor Green
Write-Host "  [*] Timestamp:  " -NoNewline; Write-Host $timestamp -ForegroundColor Cyan

if ($successCount -eq $totalDatabases) {
    Write-Host ""
    Write-Success "[SUCCESS] Backup completado exitosamente!"
}
else {
    Write-Host ""
    Write-Warning "[WARNING] Backup completado con errores ($successCount/$totalDatabases)"
}

Write-Host ""
