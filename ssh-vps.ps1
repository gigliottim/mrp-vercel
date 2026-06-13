<#
  Conexion SSH al VPS usando usuario + clave (Posh-SSH), similar al flujo de deploy.
  Uso:
    .\ssh-vps.ps1
    .\ssh-vps.ps1 -Command "docker compose ps"
#>

[CmdletBinding()]
param(
    [string]$HostName = "181.13.244.35",
    [int]$Port = 5073,
    [string]$UserName = "root",
    [string]$Password = "",
    [string]$SshKeyPath = "$PSScriptRoot\ssh\id_ed25519",
    [string]$Command = "",
    [int]$TimeoutSeconds = 120
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host "[SSH] $Message" -ForegroundColor Cyan
}

function Ensure-PoshSsh {
    if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
        Write-Step "Posh-SSH no encontrado. Instalando en CurrentUser..."
        Install-Module -Name Posh-SSH -Scope CurrentUser -Force -AllowClobber
    }

    Import-Module Posh-SSH -ErrorAction Stop
}

function Resolve-Credential {
    param(
        [string]$UserName,
        [string]$Password
    )

    if ([string]::IsNullOrWhiteSpace($Password)) {
        $secure = Read-Host "Clave SSH para $UserName@$HostName" -AsSecureString
        return New-Object System.Management.Automation.PSCredential ($UserName, $secure)
    }

    $securePassword = ConvertTo-SecureString $Password -AsPlainText -Force
    return New-Object System.Management.Automation.PSCredential ($UserName, $securePassword)
}

function Invoke-InteractiveShell {
    param(
        [int]$SessionId,
        [int]$TimeoutSeconds
    )

    Write-Step "Sesion SSH abierta. Escribi 'exit' para cerrar."

    while ($true) {
        $line = Read-Host "vps:$HostName"
        if ($null -eq $line) {
            continue
        }

        $trimmed = $line.Trim()
        if ($trimmed -eq "") {
            continue
        }

        if ($trimmed -eq "exit" -or $trimmed -eq "quit") {
            break
        }

        $result = Invoke-SSHCommand -SessionId $SessionId -Command $line -TimeOut $TimeoutSeconds
        if ($result.Output) {
            $result.Output | ForEach-Object { Write-Host $_ }
        }

        if ($result.Error) {
            $result.Error | ForEach-Object { Write-Host $_ -ForegroundColor Red }
        }

        if ($result.ExitStatus -ne 0) {
            Write-Host "[SSH] Exit code: $($result.ExitStatus)" -ForegroundColor Yellow
        }
    }
}


Ensure-PoshSsh

if (Test-Path $SshKeyPath) {
    Write-Step "Conectando a $UserName@${HostName}:$Port usando clave SSH ($SshKeyPath)..."
    $session = New-SSHSession -ComputerName $HostName -Port $Port -Credential (New-Object System.Management.Automation.PSCredential ($UserName, (New-Object System.Security.SecureString))) -KeyFile $SshKeyPath -AcceptKey
} elseif (-not [string]::IsNullOrWhiteSpace($Password)) {
    $credential = Resolve-Credential -UserName $UserName -Password $Password
    Write-Step "Conectando a $UserName@${HostName}:$Port usando password..."
    $session = New-SSHSession -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey
} else {
    Write-Error "No se encontró la clave SSH en $SshKeyPath ni se proporcionó password."
    exit 1
}

try {
    $sessionId = $session.SessionId

    if ([string]::IsNullOrWhiteSpace($Command) -eq $false) {
        Write-Step "Ejecutando comando remoto..."
        $result = Invoke-SSHCommand -SessionId $sessionId -Command $Command -TimeOut $TimeoutSeconds
        if ($result.Output) {
            $result.Output | ForEach-Object { Write-Host $_ }
        }

        if ($result.Error) {
            $result.Error | ForEach-Object { Write-Host $_ -ForegroundColor Red }
        }

        if ($result.ExitStatus -ne 0) {
            throw "El comando remoto termino con codigo $($result.ExitStatus)."
        }

        Write-Step "Comando ejecutado correctamente."
    }
    else {
        Invoke-InteractiveShell -SessionId $sessionId -TimeoutSeconds $TimeoutSeconds
    }
}
finally {
    if ($session -and $session.SessionId -ne $null) {
        Remove-SSHSession -SessionId $session.SessionId | Out-Null
        Write-Step "Sesion SSH cerrada."
    }
}
