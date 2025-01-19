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
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Config/ego.php' => config_path('ego.php'),
        ]);
    }
}
