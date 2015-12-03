<?php namespace Visualplus\Cdn;

use Illuminate\Support\ServiceProvider;

class CdnServiceProvider extends ServiceProvider {

    /**
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/cdn.php' => config_path('cdn.php')
        ], 'config');
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cdn', function($app) {
            return new \Visualplus\Cdn\Cdn;
        });
    }
}