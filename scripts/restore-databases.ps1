# ================================================================
# Script de Restauración de Bases de Datos - MRP System
# ================================================================
# Version: 1.0
# Fecha: 11 de febrero de 2026
# Descripcion: Restaura backups de bases de datos del proyecto
# ================================================================

param(
    [Parameter(Mandatory = $false)]
    [string]$BackupPath,

    [Parameter(Mandatory = $false)]
    [string]$Database,

    [switch]$ListBackups,
    [switch]$Force
)

# Configuracion
$ErrorActionPreference = "Stop"
$backupsRoot = "$PSScriptRoot\..\database\backups"

# Colores para output
function Write-Info { Write-Host $args -ForegroundColor Cyan }
function Write-Success { Write-Host $args -ForegroundColor Green }
function Write-Warning { Write-Host $args -ForegroundColor Yellow }
function Write-Error { Write-Host $args -ForegroundColor Red }

# Banner
Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "     RESTAURACION DE BASES DE DATOS - MRP System       " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

# Configuracion de conexion
$mariadbHost = "localhost"
$mariadbPort = "3306"
$mariadbUser = "unik"
$mariadbPass = "ojp9Q6aYT3KHDE8sMS2u"

# ================================================================
# LISTAR BACKUPS DISPONIBLES
# ================================================================
if ($ListBackups) {
    Write-Info "Backups disponibles:"
    Write-Host ""

    Get-ChildItem $backupsRoot -Directory | Sort-Object Name -Descending | ForEach-Object {
        $backupDate = $_.Name
        $files = Get-ChildItem $_.FullName -Filter "*.sql"
        $totalSize = ($files | Measure-Object -Property Length -Sum).Sum / 1KB

        Write-Host "  [*] " -NoNewline -ForegroundColor Yellow
        Write-Host "$backupDate" -NoNewline -ForegroundColor White
        Write-Host " - $($files.Count) archivos - " -NoNewline
        Write-Host "$([math]::Round($totalSize, 2)) KB" -ForegroundColor Green

        foreach ($file in $files) {
            $size = [math]::Round($file.Length / 1KB, 2)
            Write-Host "      - $($file.BaseName): ${size} KB" -ForegroundColor Gray
        }
        Write-Host ""
    }

    Write-Host ""
    Write-Info "Uso: .\restore-databases.ps1 -BackupPath '2026-02-11_09-39-23' [-Database 'mrp_auth']"
    Write-Host ""
    exit 0
}

# ================================================================
# VALIDAR PARAMETROS
# ================================================================
if (-not $BackupPath) {
    Write-Error "[ERROR] Debe especificar -BackupPath o usar -ListBackups"
    Write-Host ""
    Write-Info "Ejemplos:"
    Write-Host "  .\restore-databases.ps1 -ListBackups"
    Write-Host "  .\restore-databases.ps1 -BackupPath '2026-02-11_09-39-23'"
    Write-Host "  .\restore-databases.ps1 -BackupPath '2026-02-11_09-39-23' -Database 'mrp_auth'"
    Write-Host ""
    exit 1
}

# Validar que el backup existe
$backupDir = Join-Path $backupsRoot $BackupPath
if (-not (Test-Path $backupDir)) {
    Write-Error "[ERROR] Backup no encontrado: $backupDir"
    Write-Host ""
    Write-Info "Use -ListBackups para ver los backups disponibles"
    Write-Host ""
    exit 1
}

# ================================================================
# CONFIRMAR RESTAURACION
# ================================================================
if (-not $Force) {
    Write-Warning "[ADVERTENCIA] Esta operacion SOBREESCRIBIRA los datos actuales!"
    Write-Host ""
    Write-Host "  Backup: " -NoNewline; Write-Host $BackupPath -ForegroundColor Yellow

    if ($Database) {
        Write-Host "  BD:     " -NoNewline; Write-Host $Database -ForegroundColor Yellow
    }
    else {
        Write-Host "  BD:     " -NoNewline; Write-Host "TODAS" -ForegroundColor Red
    }

    Write-Host ""
    $confirm = Read-Host "Desea continuar? (escriba 'SI' para confirmar)"

    if ($confirm -ne "SI") {
        Write-Info "Operacion cancelada por el usuario"
        exit 0
    }
}

# ================================================================
# RESTAURAR BASES DE DATOS
# ================================================================
Write-Host ""
Write-Info "========================================================"
Write-Info "  INICIANDO RESTAURACION"
Write-Info "========================================================"
Write-Host ""

$successCount = 0
$errorCount = 0

# Obtener archivos SQL a restaurar
if ($Database) {
    $sqlFiles = Get-ChildItem $backupDir -Filter "$Database.sql"
}
else {
    $sqlFiles = Get-ChildItem $backupDir -Filter "*.sql" | Where-Object { $_.Length -gt 0 }
}

foreach ($file in $sqlFiles) {
    $dbName = $file.BaseName

    try {
        Write-Info "[RESTORE] Restaurando: $dbName"
        Write-Info "          Archivo: $($file.Name) ($([math]::Round($file.Length/1KB,2)) KB)"

        # Verificar si el contenedor Docker existe
        $dockerContainer = "mrp-mariadb-1"
        $containerExists = docker ps -a --format "{{.Names}}" 2>$null | Select-String -Pattern $dockerContainer

        if ($containerExists) {
            Write-Info "          Usando contenedor Docker: $dockerContainer"
            Get-Content $file.FullName | docker exec -i $dockerContainer mysql -u$mariadbUser -p$mariadbPass $dbName 2>&1 | Out-Null
        }
        else {
            Write-Warning "          Contenedor no encontrado, usando conexion local"
            Get-Content $file.FullName | mysql -h $mariadbHost -P $mariadbPort -u$mariadbUser -p$mariadbPass $dbName 2>&1 | Out-Null
        }

        Write-Success "          [OK] Restauracion completada"
        $successCount++

    }
    catch {
        Write-Error "          [ERROR] Fallo al restaurar: $_"
        $errorCount++
    }

    Write-Host ""
}

# ================================================================
# RESUMEN FINAL
# ================================================================
$totalProcessed = $successCount + $errorCount

Write-Host "========================================================" -ForegroundColor Green
Write-Host "         RESUMEN DE RESTAURACION                        " -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  [*] Backup:      " -NoNewline; Write-Host $BackupPath -ForegroundColor Yellow
Write-Host "  [*] Procesadas:  " -NoNewline; Write-Host $totalProcessed -ForegroundColor Cyan
Write-Host "  [*] Exitosas:    " -NoNewline; Write-Host $successCount -ForegroundColor Green
Write-Host "  [*] Errores:     " -NoNewline; Write-Host $errorCount -ForegroundColor $(if ($errorCount -gt 0) { 'Red' } else { 'Green' })

if ($successCount -eq $totalProcessed) {
    Write-Host ""
    Write-Success "[SUCCESS] Restauracion completada exitosamente!"
}
else {
    Write-Host ""
    Write-Warning "[WARNING] Restauracion completada con errores"
}

Write-Host ""
