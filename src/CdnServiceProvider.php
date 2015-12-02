<?php namespace Visualplus\Cdn;

use Illuminate\Support\ServiceProvider;

class CdnServiceProvider extends ServiceProvider {

    /**
     * @return void
     */
    public function boot()
    {

    }

    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cdn', function($app) {
            return new \Visualplus\cdn\FileUploader;
        });
    }
}