<#
  Entry point de despliegue para VPS.
    1) Actualiza revision automaticamente segun cambios.
    2) Ejecuta commit git obligatorio.
    3) Ejecuta deployvps.ps1 con los parametros remotos.
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
$deployVpsPath = Join-Path $projectRoot "deployvps.ps1"
$configPath = Join-Path $projectRoot "config/app.php"

if (-not (Test-Path -LiteralPath $deployVpsPath)) {
    throw "No se encontro el script de despliegue VPS: $deployVpsPath"
}

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw "Git no esta disponible. El flujo requiere commit antes de desplegar."
}

$insideGit = git -C $projectRoot rev-parse --is-inside-work-tree 2>$null
if ($insideGit -ne 'true') {
    throw "El proyecto no es un repositorio git valido."
}

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

Write-Host "[DEPLOY] Ejecutando deploy remoto a $UserName@${HostName}:$Port ..." -ForegroundColor Cyan

& $deployVpsPath `
    -HostName $HostName `
    -Port $Port `
    -UserName $UserName `
    -Password $Password `
    -RemotePath $RemotePath

if ($LASTEXITCODE -ne 0) {
    throw "El despliegue VPS finalizo con codigo $LASTEXITCODE"
}
