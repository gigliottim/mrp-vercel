# ================================================================
# Script de Deploy - Revision automatica
# ================================================================
# Uso:
#   .\deploy.ps1 -Level X   # Major
#   .\deploy.ps1 -Level Y   # Minor
#   .\deploy.ps1 -Level Z   # Patch
#   .\deploy.ps1            # Auto-detecta nivel segun cambios desde ultimo deploy
#
# Reglas:
# - X incrementa major y reinicia minor/patch
# - Y incrementa minor y reinicia patch
# - Z incrementa patch
# - Siempre incrementa build en +1
# - Si es repositorio Git: hace commit de TODOS los cambios y crea tag vX.Y.Z-buildN
# - No pide confirmaciones interactivas
# ================================================================

param(
    [Parameter(Mandatory = $false)]
    [ValidateSet('X', 'Y', 'Z')]
    [string]$Level
)

$ErrorActionPreference = 'Stop'

function Write-Info { Write-Host $args -ForegroundColor Cyan }
function Write-Success { Write-Host $args -ForegroundColor Green }
function Write-Warning { Write-Host $args -ForegroundColor Yellow }
function Write-ErrorLine { Write-Host $args -ForegroundColor Red }

function Get-AutoLevel {
    param(
        [string]$RepoRoot
    )

    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        return 'Z'
    }

    $insideGit = git -C $RepoRoot rev-parse --is-inside-work-tree 2>$null
    if ($insideGit -ne 'true') {
        return 'Z'
    }

    $lastDeployTag = ''
    try {
        $lastDeployTagResult = git -C $RepoRoot describe --tags --match "v*-build*" --abbrev=0 2>$null
        if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($lastDeployTagResult)) {
            $lastDeployTag = $lastDeployTagResult
        }
    }
    catch {
        $lastDeployTag = ''
    }
    $changedFiles = New-Object System.Collections.Generic.HashSet[string]

    if (-not [string]::IsNullOrWhiteSpace($lastDeployTag)) {
        $committedChanges = git -C $RepoRoot diff --name-only "$lastDeployTag..HEAD"
        foreach ($file in $committedChanges) {
            if (-not [string]::IsNullOrWhiteSpace($file)) {
                [void]$changedFiles.Add($file)
            }
        }
    }

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

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $projectRoot 'config\app.php'

if (-not (Test-Path $configPath)) {
    throw "No se encontro el archivo de configuracion: $configPath"
}

if ([string]::IsNullOrWhiteSpace($Level)) {
    $Level = Get-AutoLevel -RepoRoot $projectRoot
}

$content = Get-Content -Path $configPath -Raw

$versionRegex = "'version'\s*=>\s*'(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)'"
$buildRegex = "'build'\s*=>\s*(?<build>\d+)"

$versionMatch = [regex]::Match($content, $versionRegex)
$buildMatch = [regex]::Match($content, $buildRegex)

if (-not $versionMatch.Success) {
    throw "No se pudo leer 'app.version' en $configPath"
}

if (-not $buildMatch.Success) {
    throw "No se pudo leer 'app.build' en $configPath"
}

$major = [int]$versionMatch.Groups['major'].Value
$minor = [int]$versionMatch.Groups['minor'].Value
$patch = [int]$versionMatch.Groups['patch'].Value
$build = [int]$buildMatch.Groups['build'].Value

$oldVersion = "$major.$minor.$patch"
$oldBuild = $build

switch ($Level) {
    'X' {
        $major++
        $minor = 0
        $patch = 0
    }
    'Y' {
        $minor++
        $patch = 0
    }
    'Z' {
        $patch++
    }
}

$build++
$newVersion = "$major.$minor.$patch"
$newBuild = $build

# Reemplazos puntuales en config/app.php
$newContent = [regex]::Replace($content, $versionRegex, "'version' => '$newVersion'", 1)
$newContent = [regex]::Replace($newContent, $buildRegex, "'build' => $newBuild", 1)

Set-Content -Path $configPath -Value $newContent -Encoding UTF8

Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "             DEPLOY - REVISION ACTUALIZADA              " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""
Write-Info "[INFO] Nivel aplicado: $Level"
Write-Info "[INFO] Revision anterior: v$oldVersion build $oldBuild"
Write-Success "[OK] Revision nueva:    v$newVersion build $newBuild"
Write-Info "[INFO] Archivo actualizado: $configPath"

# Si es repo git, realiza commit y tag en forma automatica
if (Get-Command git -ErrorAction SilentlyContinue) {
    try {
        $insideGit = git -C $projectRoot rev-parse --is-inside-work-tree 2>$null
        if ($insideGit -eq 'true') {
            $tagName = "v$newVersion-build$newBuild"
            $commitMessage = "chore: bump version to v$newVersion build $newBuild"

            git -C $projectRoot add --all
            git -C $projectRoot commit -m $commitMessage | Out-Null

            $existingTag = git -C $projectRoot tag --list $tagName
            if ([string]::IsNullOrWhiteSpace($existingTag)) {
                git -C $projectRoot tag -a $tagName -m "Release $tagName" | Out-Null
                Write-Success "[OK] Tag creado: $tagName"
            }
            else {
                Write-Warning "[WARN] El tag ya existe y no se sobreescribe: $tagName"
            }

            Write-Host ""
            Write-Info "[INFO] Commit creado: $commitMessage"
            Write-Info "[NEXT] Sugerido: git push && git push origin $tagName"
        }
    }
    catch {
        Write-Warning "[WARN] No se pudo completar commit/tag automatico: $($_.Exception.Message)"
    }
}
