<?php

namespace Emmy\Ego\Provider;

use Illuminate\Support\ServiceProvider;

class EgoProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ego.php', 'ego');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ego.php' => config_path('ego.php'),
        ]);
    }
}
