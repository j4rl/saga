$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$php = $env:PHP_BIN
if (-not $php) {
    $php = 'C:\xampp\php\php.exe'
}

if (-not (Test-Path -LiteralPath $php)) {
    throw "PHP hittades inte. Satt PHP_BIN eller installera PHP pa $php."
}

Get-ChildItem -Path $repoRoot -Recurse -Filter *.php |
    ForEach-Object {
        & $php -l $_.FullName | Out-Host
        if ($LASTEXITCODE -ne 0) {
            throw "PHP-syntaxkontroll misslyckades for $($_.FullName)."
        }
    }

& $php (Join-Path $repoRoot 'tests\security_checks.php') | Out-Host
if ($LASTEXITCODE -ne 0) {
    throw 'Sakerhetskontroller misslyckades.'
}

Write-Host 'Verifiering klar.'
