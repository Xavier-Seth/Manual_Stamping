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
        // Must be defined before TCPDF loads tcpdf_config.php, which has a
        // !defined() guard after our patch. Setting true makes Error() throw
        // a catchable Exception instead of calling die().
        if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
            define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
        }

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
