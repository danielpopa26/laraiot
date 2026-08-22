<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT;

use Danpopa\LaraIoT\Console\Commands\InstallLaraIoTCommand;
use Danpopa\LaraIoT\Console\Commands\ListenMqttCommand;
use Danpopa\LaraIoT\Console\Commands\PublishMqttCommand;
use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Contracts\MqttPublisher as MqttPublisherContract;
use Danpopa\LaraIoT\Events\LogicalDeviceStateUpdated;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Observers\MqttTopicObserver;
use Danpopa\LaraIoT\Services\MqttConnectionService;
use Danpopa\LaraIoT\Services\MqttPublisher;
use Danpopa\LaraIoT\Services\PhpMqttClientFactory;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Throwable;

class LaraIoTServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laraiot.php',
            'laraiot',
        );

        $this->app->singleton(LaraIoT::class);

        $this->app->singleton(
            MqttClientFactory::class,
            PhpMqttClientFactory::class,
        );

        $this->app->singleton(
            MqttConnectionService::class,
        );

        $this->app->singleton(MqttPublisher::class);

        $this->app->singleton(
            MqttPublisherContract::class,
            MqttPublisher::class,
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            __DIR__.'/../routes/laraiot.php',
        );

        if ((bool) config(
            'laraiot.ui.enabled',
            false,
        )) {
            $this->loadRoutesFrom(
                __DIR__.'/../routes/laraiot-ui.php',
            );

            $this->shareUiProps();
        }

        $this->loadViewsFrom(
            __DIR__.'/../resources/views',
            'laraiot',
        );

        $this->loadTranslationsFrom(
            __DIR__.'/../lang',
            'laraiot',
        );

        MqttTopic::observe(
            MqttTopicObserver::class,
        );

        Broadcast::channel(
            LogicalDeviceStateUpdated::CHANNEL,
            static fn (mixed $user): bool =>
                $user !== null,
        );

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laraiot.php' =>
                config_path('laraiot.php'),
        ], [
            'laraiot',
            'laraiot-config',
        ]);

        $this->publishes([
            __DIR__.'/../resources/views' =>
                resource_path(
                    'views/vendor/laraiot',
                ),
        ], [
            'laraiot',
            'laraiot-views',
        ]);

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js'),
        ], ['laraiot', 'laraiot-ui']);

        $this->publishes([
            __DIR__.'/../lang' =>
                $this->app->langPath(
                    'vendor/laraiot',
                ),
        ], [
            'laraiot',
            'laraiot-lang',
        ]);

        $this->publishes([
            __DIR__.'/../public' =>
                public_path(
                    'vendor/laraiot',
                ),
        ], [
            'laraiot',
            'laraiot-assets',
        ]);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' =>
                database_path(
                    'migrations',
                ),
        ], [
            'laraiot',
            'laraiot-migrations',
        ]);

        $this->commands([
            InstallLaraIoTCommand::class,
            ListenMqttCommand::class,
            PublishMqttCommand::class,
        ]);
    }

    private function shareUiProps(): void
    {
        Inertia::share(
            'laraiot',
            function (): array {
                $mode = (string) config(
                    'laraiot.mode',
                    ApplicationSetting::MODE_POLLING,
                );

                $pollingInterval = max(
                    ApplicationSetting::MIN_POLLING_INTERVAL,
                    (int) config(
                        'laraiot.polling.interval',
                        10,
                    ),
                );

                try {
                    $settings =
                        ApplicationSetting::current();

                    $mode =
                        $settings->application_mode;

                    $pollingInterval =
                        $settings->polling_interval;
                } catch (Throwable) {
                    /*
                     * The UI can be enabled before the host
                     * application has run LaraIoT migrations.
                     * Keep the shared props safe in that state.
                     */
                }

                $prefix = trim(
                    (string) config(
                        'laraiot.ui.prefix',
                        'laraiot',
                    ),
                    '/',
                );

                return [
                    'baseUrl' => '/'.$prefix,
                    'mode' => $mode,
                    'pollingInterval' =>
                        $pollingInterval,
                    'mqtt' => [
                        'connected' => null,
                    ],
                    'message' => session(
                        'laraiot_message',
                    ),
                    'validation' => session(
                        'laraiot_validation',
                    ),
                ];
            },
        );
    }
}
