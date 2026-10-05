$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectPath '.env.docker'
if (Test-Path -LiteralPath $envPath) {
    Write-Output '.env.docker já existe; configurações preservadas.'
    exit 0
}
function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return [Convert]::ToBase64String($bytes)
}
$appKey = New-Secret
$dbSecret = New-Secret
$rootSecret = New-Secret
$lines = @('HTTP_PORT=8080', "APP_KEY=base64:$appKey", "DB_PASSWORD=$dbSecret", "MYSQL_ROOT_PASSWORD=$rootSecret")
[System.IO.File]::WriteAllLines($envPath, $lines, (New-Object System.Text.UTF8Encoding($false)))
Write-Output '.env.docker criado com chave e senhas aleatórias. Não versione esse arquivo.'
