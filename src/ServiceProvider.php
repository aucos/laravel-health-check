<?php

namespace Aucos\HealthCheck;

use Aucos\HealthCheck\Console\HealthCheck;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/health-check.php', 'health-check'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/health-check.php' => config_path('health-check.php'),
        ]);

        $this->commands([
            HealthCheck::class,
        ]);
    }
}
