<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT;

use Danpopa\LaraIoT\Console\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;

class LaraIoTServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraiot.php', 'laraiot');

        $this->app->singleton(LaraIoT::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/laraiot.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laraiot');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laraiot');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laraiot.php' => config_path('laraiot.php'),
        ], ['laraiot', 'laraiot-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laraiot'),
        ], ['laraiot', 'laraiot-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laraiot'),
        ], ['laraiot', 'laraiot-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/laraiot'),
        ], ['laraiot', 'laraiot-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['laraiot', 'laraiot-migrations']);

        $this->commands([
            InstallCommand::class,
        ]);
    }
}
