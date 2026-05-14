Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$stageRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\runtime\laravel-app'))

function Assert-SafePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    $resolvedPath = [System.IO.Path]::GetFullPath($Path)

    if (-not $resolvedPath.StartsWith($repoRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to operate outside the repository root: $resolvedPath"
    }
}

function Ensure-Directory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    Assert-SafePath -Path $Path

    if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Copy-RelativeDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $sourcePath = Join-Path $repoRoot $RelativePath
    $destinationPath = Join-Path $stageRoot $RelativePath

    if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
        throw "Required directory not found: $sourcePath"
    }

    Ensure-Directory -Path (Split-Path -Parent $destinationPath)
    Copy-Item -LiteralPath $sourcePath -Destination $destinationPath -Recurse -Force
}

function Copy-RelativeFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $sourcePath = Join-Path $repoRoot $RelativePath
    $destinationPath = Join-Path $stageRoot $RelativePath

    if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
        throw "Required file not found: $sourcePath"
    }

    Ensure-Directory -Path (Split-Path -Parent $destinationPath)
    Copy-Item -LiteralPath $sourcePath -Destination $destinationPath -Force
}

function Copy-PublicDirectory {
    $sourcePath = Join-Path $repoRoot 'public'
    $destinationPath = Join-Path $stageRoot 'public'

    Ensure-Directory -Path $destinationPath

    foreach ($item in Get-ChildItem -LiteralPath $sourcePath -Force) {
        $isPublicStorageLink = $item.Name -eq 'storage' -and
            (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0)

        if ($isPublicStorageLink) {
            Write-Host "Skipping public/storage symlink or junction"
            continue
        }

        $itemDestination = Join-Path $destinationPath $item.Name
        Copy-Item -LiteralPath $item.FullName -Destination $itemDestination -Recurse -Force
    }
}

function Clear-StagedDirectoryContents {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $targetPath = Join-Path $stageRoot $RelativePath

    Ensure-Directory -Path $targetPath

    foreach ($child in Get-ChildItem -LiteralPath $targetPath -Force) {
        if ($child.Name -eq '.gitignore') {
            continue
        }

        Remove-Item -LiteralPath $child.FullName -Recurse -Force
    }
}

function Remove-StagedFileIfPresent {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $targetPath = Join-Path $stageRoot $RelativePath

    if (Test-Path -LiteralPath $targetPath -PathType Leaf) {
        Remove-Item -LiteralPath $targetPath -Force
    }
}

function Remove-StagedBootstrapCacheFiles {
    $cachePath = Join-Path $stageRoot 'bootstrap\cache'

    if (-not (Test-Path -LiteralPath $cachePath -PathType Container)) {
        return
    }

    $pathsToRemove = @(
        (Join-Path $cachePath 'config.php')
    )

    foreach ($path in $pathsToRemove) {
        if (Test-Path -LiteralPath $path -PathType Leaf) {
            Remove-Item -LiteralPath $path -Force
        }
    }

    foreach ($routeCacheFile in Get-ChildItem -LiteralPath $cachePath -Filter 'routes*.php' -File) {
        Remove-Item -LiteralPath $routeCacheFile.FullName -Force
    }
}

function Copy-StorageDirectory {
    Copy-RelativeDirectory -RelativePath 'storage'

    $pathsToClear = @(
        'storage\logs',
        'storage\framework\cache',
        'storage\framework\sessions',
        'storage\framework\testing',
        'storage\framework\views',
        'storage\app\manual-stamping\generated'
    )

    $privateGeneratedPath = Join-Path $stageRoot 'storage\app\private\manual-stamping\generated'
    if (Test-Path -LiteralPath $privateGeneratedPath -PathType Container) {
        $pathsToClear += 'storage\app\private\manual-stamping\generated'
    }

    foreach ($relativePath in $pathsToClear) {
        Clear-StagedDirectoryContents -RelativePath $relativePath
    }
}

Write-Host "Preparing staged Laravel runtime at $stageRoot"

Assert-SafePath -Path $stageRoot

if (Test-Path -LiteralPath $stageRoot) {
    Write-Host "Removing previous staged runtime"
    Remove-Item -LiteralPath $stageRoot -Recurse -Force
}

Ensure-Directory -Path $stageRoot

$directoriesToCopy = @(
    'app',
    'bootstrap',
    'config',
    'resources\views',
    'routes',
    'vendor'
)

foreach ($relativePath in $directoriesToCopy) {
    Write-Host "Copying $relativePath"
    Copy-RelativeDirectory -RelativePath $relativePath
}

Write-Host 'Copying public'
Copy-PublicDirectory
Remove-StagedFileIfPresent -RelativePath 'public\hot'

Write-Host 'Copying storage'
Copy-StorageDirectory

$filesToCopy = @(
    'artisan',
    '.env',
    'database\database.sqlite'
)

foreach ($relativePath in $filesToCopy) {
    Write-Host "Copying $relativePath"
    Copy-RelativeFile -RelativePath $relativePath
}

Write-Host 'Removing staged config and route caches'
Remove-StagedBootstrapCacheFiles

Write-Host 'Laravel runtime staging complete.'

# ─── Run migrations on bundled SQLite ────────────────────────────────────────
# The bundled database.sqlite must have all migrations applied before tauri
# build, because lib.rs copies it to app_local_data_dir on first launch.
# Migration files must be staged alongside the SQLite so artisan can find them.
$phpExe = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\runtime\php\php.exe'))

Write-Host ''
Write-Host '=== Running migrations ==='

Write-Host 'Copying database\migrations'
Copy-RelativeDirectory -RelativePath 'database\migrations'

Push-Location $stageRoot
& $phpExe artisan migrate --force
$migrationExitCode = $LASTEXITCODE
Pop-Location

if ($migrationExitCode -ne 0) {
    Write-Host "Migration failed with exit code $migrationExitCode. Aborting." -ForegroundColor Red
    exit 1
}

Write-Host '=== Staging complete ==='
