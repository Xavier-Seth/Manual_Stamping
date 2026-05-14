<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

function addWindowsAbsoluteCachePathPrefixes(Application $app): void
{
    if (DIRECTORY_SEPARATOR !== '\\') {
        return;
    }

    foreach ([
        'APP_CONFIG_CACHE',
        'APP_EVENTS_CACHE',
        'APP_PACKAGES_CACHE',
        'APP_ROUTES_CACHE',
        'APP_SERVICES_CACHE',
    ] as $key) {
        $path = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: null;

        if (!is_string($path) || $path === '') {
            continue;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            $app->addAbsoluteCachePathPrefix(substr($path, 0, 2));
        }
    }
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();

$storagePath = $_ENV['LARAVEL_STORAGE_PATH']
    ?? $_SERVER['LARAVEL_STORAGE_PATH']
    ?? getenv('LARAVEL_STORAGE_PATH')
    ?: null;

if (is_string($storagePath) && $storagePath !== '') {
    $app->useStoragePath($storagePath);
}

$dbDatabase = $_ENV['DB_DATABASE']
    ?? $_SERVER['DB_DATABASE']
    ?? getenv('DB_DATABASE')
    ?: null;

if (is_string($dbDatabase) && $dbDatabase !== '') {
    $databaseDir = dirname($dbDatabase);
    $app->useDatabasePath($databaseDir);
}

addWindowsAbsoluteCachePathPrefixes($app);

return $app;