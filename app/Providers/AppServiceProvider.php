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
        // Force TCPDF to throw \Exception on Error() instead of calling die().
        // tcpdf_autoconfig.php uses !defined() so this wins when set before first TCPDF load.
        if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
            define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
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
