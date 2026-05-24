<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override TCPDF cache path to use writable storage dir.
        // K_PATH_CACHE defaults to sys_get_temp_dir() which may
        // not be accessible in the Tauri bundled PHP environment.
        // tcpdf_autoconfig.php has a !defined() guard so this wins.
        if (!defined('K_PATH_CACHE')) {
            $storagePath = getenv('LARAVEL_STORAGE_PATH')
                        ?: storage_path();
            $tcpdfCache = rtrim($storagePath, '/\\')
                        . DIRECTORY_SEPARATOR . 'framework'
                        . DIRECTORY_SEPARATOR . 'cache'
                        . DIRECTORY_SEPARATOR . 'tcpdf'
                        . DIRECTORY_SEPARATOR;
            if (!is_dir($tcpdfCache)) {
                mkdir($tcpdfCache, 0755, true);
            }
            define('K_PATH_CACHE', $tcpdfCache);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
