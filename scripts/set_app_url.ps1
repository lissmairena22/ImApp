param(
    [Parameter(Mandatory=$true)]
    [string]$url
)

$envFile = Join-Path $PSScriptRoot '..\.env'
if (-not (Test-Path $envFile)) {
    Write-Error "No se encontró el archivo .env en $envFile"
    exit 1
}

# Replace or add APP_URL line
$content = Get-Content $envFile -Raw
if ($content -match "(?m)^APP_URL=") {
    $new = $content -replace "(?m)^APP_URL=.*", "APP_URL=$url"
} else {
    $new = $content + "`nAPP_URL=$url`n"
}

Set-Content -Path $envFile -Value $new -Encoding UTF8
Write-Output "Actualizado .env: APP_URL=$url"
Write-Output "Ejecuta los comandos: php artisan config:clear && php artisan cache:clear && php artisan config:cache"