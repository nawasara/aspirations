<?php

namespace Nawasara\Aspirations;

use Illuminate\Support\ServiceProvider;

class AspirationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/nawasara-aspirations.php',
            'nawasara-aspirations'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerCitizenRoutes();

        $this->publishes([
            __DIR__.'/../config/nawasara-aspirations.php' => config_path('nawasara-aspirations.php'),
        ], 'nawasara-aspirations-config');
    }

    /**
     * Rute aplikasi warga — di belakang `api.citizen` (JWT realm warga),
     * BUKAN `api.auth`.
     *
     * Prefix mengikuti config nawasara/api supaya seluruh endpoint warga
     * berada di bawah satu awalan yang sama; aplikasi cukup tahu satu alamat
     * dasar.
     *
     * `throttle:nawasara-citizen` memakai limiter yang sudah didaftarkan
     * nawasara/api dan dikunci pada `sub`, bukan IP — puluhan ribu ponsel
     * warga berbagi IP operator, sehingga pembatasan per-IP akan menghukum
     * warga yang tidak melakukan apa-apa.
     */
    protected function registerCitizenRoutes(): void
    {
        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/aspirations';

        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware(['api', 'api.citizen', 'throttle:nawasara-citizen'])
            ->name('nawasara-aspirations.citizen.')
            ->group(__DIR__.'/../routes/citizen.php');
    }
}
