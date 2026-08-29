<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT;

use Danpopa\LaraIoT\Console\Commands\InstallLaraIoTCommand;
use Danpopa\LaraIoT\Console\Commands\ListenMqttCommand;
use Danpopa\LaraIoT\Console\Commands\PublishMqttCommand;
use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Contracts\MqttPublisher as MqttPublisherContract;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Observers\MqttTopicObserver;
use Danpopa\LaraIoT\Services\MqttConnectionService;
use Danpopa\LaraIoT\Services\MqttHealthMonitor;
use Danpopa\LaraIoT\Services\MqttPublisher;
use Danpopa\LaraIoT\Services\PhpMqttClientFactory;
use Danpopa\LaraIoT\Support\Reverb\ReverbHealthMonitor;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Throwable;

class LaraIoTServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configPath = __DIR__.'/../config/laraiot.php';

        $this->mergeConfigFrom(
            $configPath,
            'laraiot',
        );

        $this->mergeMqttHealthConfig($configPath);
        $this->mergeMqttTestingConfig($configPath);
        $this->mergeWebsocketHealthConfig($configPath);

        $this->app->singleton(LaraIoT::class);

        $this->app->singleton(
            MqttClientFactory::class,
            PhpMqttClientFactory::class,
        );

        $this->app->singleton(
            MqttConnectionService::class,
        );

        $this->app->singleton(MqttHealthMonitor::class);

        $this->app->singleton(ReverbHealthMonitor::class);

        $this->app->singleton(MqttPublisher::class);

        $this->app->singleton(
            MqttPublisherContract::class,
            MqttPublisher::class,
        );
    }

    private function mergeMqttHealthConfig(string $configPath): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $packageConfig = require $configPath;
        $mqttDefaults = is_array($packageConfig)
            ? ($packageConfig['mqtt'] ?? [])
            : [];
        $healthDefaults = is_array($mqttDefaults)
            ? ($mqttDefaults['health'] ?? [])
            : [];
        $configured = $config->get(
            'laraiot.mqtt.health',
            [],
        );

        $config->set(
            'laraiot.mqtt.health',
            array_replace(
                is_array($healthDefaults) ? $healthDefaults : [],
                is_array($configured) ? $configured : [],
            ),
        );
    }

    private function mergeWebsocketHealthConfig(string $configPath): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $packageConfig = require $configPath;
        $websocketDefaults = is_array($packageConfig)
            ? ($packageConfig['websocket'] ?? [])
            : [];
        $healthDefaults = is_array($websocketDefaults)
            ? ($websocketDefaults['health'] ?? [])
            : [];
        $configured = $config->get(
            'laraiot.websocket.health',
            [],
        );

        $config->set(
            'laraiot.websocket.health',
            array_replace(
                is_array($healthDefaults) ? $healthDefaults : [],
                is_array($configured) ? $configured : [],
            ),
        );
    }

    private function mergeMqttTestingConfig(string $configPath): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $packageConfig = require $configPath;
        $mqttDefaults = is_array($packageConfig)
            ? ($packageConfig['mqtt'] ?? [])
            : [];
        $testingDefaults = is_array($mqttDefaults)
            ? ($mqttDefaults['testing'] ?? [])
            : [];
        $configured = $config->get(
            'laraiot.mqtt.testing',
            [],
        );

        $config->set(
            'laraiot.mqtt.testing',
            array_replace(
                is_array($testingDefaults) ? $testingDefaults : [],
                is_array($configured) ? $configured : [],
            ),
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

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laraiot.php' => config_path('laraiot.php'),
        ], [
            'laraiot',
            'laraiot-config',
        ]);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path(
                'views/vendor/laraiot',
            ),
        ], [
            'laraiot',
            'laraiot-views',
        ]);

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js'),
            __DIR__.'/../resources/css/laraiot.css' => resource_path('css/laraiot.css'),
        ], ['laraiot', 'laraiot-ui']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath(
                'vendor/laraiot',
            ),
        ], [
            'laraiot',
            'laraiot-lang',
        ]);

        $this->publishes([
            __DIR__.'/../public' => public_path(
                'vendor/laraiot',
            ),
        ], [
            'laraiot',
            'laraiot-assets',
        ]);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path(
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
                $requestedMode = (string) config(
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
                    $settings = ApplicationSetting::current();

                    $requestedMode = $settings->application_mode;

                    $pollingInterval = $settings->polling_interval;
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

                $websocket = $this->app
                    ->make(ReverbHealthMonitor::class)
                    ->snapshot();
                $websocketLive = $websocket['live'] === true;
                $mode = $requestedMode === ApplicationSetting::MODE_WEBSOCKET
                    && $websocketLive
                        ? ApplicationSetting::MODE_WEBSOCKET
                        : ApplicationSetting::MODE_POLLING;
                $fallbackActive = $requestedMode
                    === ApplicationSetting::MODE_WEBSOCKET
                    && $mode === ApplicationSetting::MODE_POLLING;

                return [
                    'baseUrl' => '/'.$prefix,
                    'mode' => $mode,
                    'requestedMode' => $requestedMode,
                    'fallbackActive' => $fallbackActive,
                    'pollingInterval' => $pollingInterval,
                    'websocket' => $websocket,
                    'mqtt' => $this->app
                        ->make(MqttHealthMonitor::class)
                        ->snapshot(),
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
