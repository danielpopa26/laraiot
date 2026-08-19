<?php

declare(strict_types=1);

use Danpopa\LaraIoT\LaraIoT;
use Danpopa\LaraIoT\LaraIoTServiceProvider;
use Danpopa\LaraIoT\Services\MqttPublisher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;

it('resolves the singleton', function () {
    expect(app(LaraIoT::class))->toBeInstanceOf(LaraIoT::class);
});

it('returns the same instance from the container', function () {
    expect(app(LaraIoT::class))->toBe(app(LaraIoT::class));
});

it('registers the MQTT publisher as a singleton', function () {
    $publisher = app(MqttPublisher::class);

    expect($publisher)
        ->toBeInstanceOf(MqttPublisher::class)
        ->and($publisher)
        ->toBe(app(MqttPublisher::class));
});

it('merges the package configuration', function () {
    expect(config('laraiot.mode'))->toBe('polling')
        ->and(config('laraiot.ui.enabled'))->toBeFalse()
        ->and(config('laraiot.ui.prefix'))->toBe('laraiot')
        ->and(config('laraiot.api.enabled'))->toBeTrue()
        ->and(config('laraiot.api.prefix'))->toBe('api/laraiot')
        ->and(config('laraiot.polling.interval'))->toBe(10)
        ->and(config('laraiot.mqtt.port'))->toBe(1883);
});

it('registers the package publish groups', function () {
    $packageRoot = dirname(__DIR__, 2);

    expect(ServiceProvider::pathsToPublish(
        LaraIoTServiceProvider::class,
        'laraiot-config',
    ))->toBe([
        $packageRoot.'/src/../config/laraiot.php' => config_path('laraiot.php'),
    ])->and(ServiceProvider::pathsToPublish(
        LaraIoTServiceProvider::class,
        'laraiot-migrations',
    ))->toBe([
        $packageRoot.'/src/../database/migrations' => database_path('migrations'),
    ]);
});

it('contains the publishable package resources', function () {
    $packageRoot = dirname(__DIR__, 2);
    $migrationFiles = glob(
        $packageRoot.'/database/migrations/*.php',
    ) ?: [];

    expect(is_file($packageRoot.'/config/laraiot.php'))->toBeTrue()
        ->and(is_dir($packageRoot.'/database/migrations'))->toBeTrue()
        ->and($migrationFiles)->not->toBeEmpty();
});

it('registers the installation command and its force option', function () {
    $commands = app(Kernel::class)->all();

    expect($commands)->toHaveKey('laraiot:install')
        ->and(
            $commands['laraiot:install']
                ->getDefinition()
                ->hasOption('force'),
        )->toBeTrue();
});

it('loads the package translations', function () {
    expect(trans('laraiot::messages.placeholder'))->toBe('LaraIoT placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('laraiot::placeholder'))->toBeTrue();
});

it('registers and executes the installation command', function () {
    $this->artisan('laraiot:install', ['--force' => true])
        ->expectsOutputToContain('LaraIoT installed successfully.')
        ->expectsOutputToContain('php artisan migrate')
        ->assertSuccessful();
})->group('install');
