<?php

namespace beck\S3Uploader;

use beck\S3Uploader\Services\S3Service;
use Illuminate\Support\ServiceProvider;

class S3UploaderServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(S3Service::class, function ($app) {
            return new S3Service();
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/s3Uploader.php' => config_path('s3Uploader.php'),
        ], 'config');
    }
}
