<?php

namespace App\Providers;

use App\Console\Commands\LicenseKeygen;
use App\Http\Middleware\CheckRoleMiddleware;
use App\Http\Middleware\EnsureApiStore;
use App\Http\Middleware\EnsureStoreWritable;
use App\Http\Middleware\ResolveDevice;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Alias middleware didaftarkan di sini supaya tidak perlu mengubah
         * bootstrap/app.php. Bila bootstrap/app.php Anda sudah mendaftarkan
         * alias 'role', baris ini menimpanya dengan versi baru (aman).
         */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', CheckRoleMiddleware::class);
        $router->aliasMiddleware('store.writable', EnsureStoreWritable::class);
        $router->aliasMiddleware('api.store', EnsureApiStore::class);
        $router->aliasMiddleware('device', ResolveDevice::class);
        $router->aliasMiddleware('api.admin', \App\Http\Middleware\EnsureAdminApp::class);

        if ($this->app->runningInConsole()) {
            $this->commands([LicenseKeygen::class]);
        }
    }
}
