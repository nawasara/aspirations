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

        $this->publishes([
            __DIR__.'/../config/nawasara-aspirations.php' => config_path('nawasara-aspirations.php'),
        ], 'nawasara-aspirations-config');
    }
}
