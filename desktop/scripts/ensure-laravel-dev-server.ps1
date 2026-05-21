param(
    [int]$Port = 8000,
    [int]$VitePort = 5173
)

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$hotFile = Join-Path $repoRoot 'public\hot'

$viteListener = Get-NetTCPConnection -LocalPort $VitePort -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -eq $viteListener) {
    if (Test-Path -LiteralPath $hotFile -PathType Leaf) {
        Remove-Item -LiteralPath $hotFile -Force
    }

    Write-Host "Starting Vite dev server on http://127.0.0.1:$VitePort ..."
    Start-Process -FilePath "npm.cmd" `
        -ArgumentList "run", "dev", "--", "--host", "127.0.0.1", "--port", "$VitePort" `
        -WorkingDirectory $repoRoot `
        -WindowStyle Hidden | Out-Null
} else {
    Write-Host "Port $VitePort is already in use. Reusing the existing Vite dev server."
}

$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -ne $listener) {
    Write-Host "Port $Port is already in use. Reusing the existing Laravel dev server."
    exit 0
}

Write-Host "Starting Laravel dev server on http://127.0.0.1:$Port ..."
php artisan serve --host=127.0.0.1 --port=$Port
