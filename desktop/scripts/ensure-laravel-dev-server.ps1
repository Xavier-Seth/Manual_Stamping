param(
    [int]$Port = 8000
)

$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -ne $listener) {
    Write-Host "Port $Port is already in use. Reusing the existing Laravel dev server."
    exit 0
}

Write-Host "Starting Laravel dev server on http://127.0.0.1:$Port ..."
php artisan serve --host=127.0.0.1 --port=$Port
