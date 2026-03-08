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
        $packageConfig = require __DIR__.'/../config/ego.php';
        $appConfig = $this->app['config']->get('ego', []);
        $this->app['config']->set('ego', array_replace_recursive($packageConfig, $appConfig));
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
