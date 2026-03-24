param(
    [string]$HostName = "181.13.244.35",
    [int]   $Port = 5073,
    [string]$UserName = "root",
    [string]$Password = "w(6C%QnZC7EQPZ"
)

$ErrorActionPreference = "Stop"
if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
    Install-Module -Name Posh-SSH -Scope CurrentUser -Force -AllowClobber
}
Import-Module Posh-SSH -Force

$secPassword = ConvertTo-SecureString $Password -AsPlainText -Force
$credential = New-Object System.Management.Automation.PSCredential($UserName, $secPassword)
$session = New-SSHSession -ComputerName $HostName -Port $Port -Credential $credential -AcceptKey -Force

try {
    $checkCmd = 'cd /opt/mrp && docker compose exec -T postgresql psql -h 127.0.0.1 -U mrp_unik_2026 -d mrp_auth -Atc "SELECT u.email, r.id, r.name FROM users u JOIN user_company uc ON uc.user_id=u.id JOIN roles r ON r.id=uc.role_id JOIN companies c ON c.id=uc.company_id WHERE u.email=chr(117)||chr(115)||chr(117)||chr(97)||chr(114)||chr(105)||chr(111)||chr(64)||chr(109)||chr(105)||chr(109)||chr(114)||chr(112)||chr(46)||chr(99)||chr(111)||chr(109)||chr(46)||chr(97)||chr(114) AND c.slug=chr(109)||chr(114)||chr(112)||chr(95)||chr(116)||chr(117)||chr(110)||chr(110)||chr(97)"'
    $r1 = Invoke-SSHCommand -SessionId $session.SessionId -Command $checkCmd -TimeoutSeconds 30
    Write-Host "=== ESTADO ACTUAL ===" -ForegroundColor Cyan
    Write-Host ($r1.Output -join "`n")

    $updateCmd = 'cd /opt/mrp && docker compose exec -T postgresql psql -h 127.0.0.1 -U mrp_unik_2026 -d mrp_auth -Atc "UPDATE user_company SET role_id=4 WHERE user_id=(SELECT id FROM users WHERE email=chr(117)||chr(115)||chr(117)||chr(97)||chr(114)||chr(105)||chr(111)||chr(64)||chr(109)||chr(105)||chr(109)||chr(114)||chr(112)||chr(46)||chr(99)||chr(111)||chr(109)||chr(46)||chr(97)||chr(114)) AND company_id=(SELECT id FROM companies WHERE slug=chr(109)||chr(114)||chr(112)||chr(95)||chr(116)||chr(117)||chr(110)||chr(110)||chr(97))"'
    $r2 = Invoke-SSHCommand -SessionId $session.SessionId -Command $updateCmd -TimeoutSeconds 30
    Write-Host "=== UPDATE RESULTADO ===" -ForegroundColor Green
    Write-Host ($r2.Output -join "`n")

    $verifyCmd = 'cd /opt/mrp && docker compose exec -T postgresql psql -h 127.0.0.1 -U mrp_unik_2026 -d mrp_auth -Atc "SELECT u.email, r.id, r.name FROM users u JOIN user_company uc ON uc.user_id=u.id JOIN roles r ON r.id=uc.role_id JOIN companies c ON c.id=uc.company_id WHERE u.email=chr(117)||chr(115)||chr(117)||chr(97)||chr(114)||chr(105)||chr(111)||chr(64)||chr(109)||chr(105)||chr(109)||chr(114)||chr(112)||chr(46)||chr(99)||chr(111)||chr(109)||chr(46)||chr(97)||chr(114) AND c.slug=chr(109)||chr(114)||chr(112)||chr(95)||chr(116)||chr(117)||chr(110)||chr(110)||chr(97)"'
    $r3 = Invoke-SSHCommand -SessionId $session.SessionId -Command $verifyCmd -TimeoutSeconds 30
    Write-Host "=== VERIFICACION FINAL ===" -ForegroundColor Yellow
    Write-Host ($r3.Output -join "`n")
}
finally {
    Remove-SSHSession -SessionId $session.SessionId | Out-Null
}
