<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Illuminate\Support\Facades\Artisan;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

it('registers the MQTT listener command', function () {
    expect(Artisan::all())
        ->toHaveKey('laraiot:mqtt-listen');
});

it('runs the MQTT listener command successfully', function () {
    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('registerLoopEventHandler')
        ->once()
        ->with(Mockery::type(Closure::class))
        ->andReturn($client);

    $client->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnNull();

    $client->shouldReceive('isConnected')
        ->once()
        ->andReturnFalse();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            '127.0.0.1',
            1883,
            'laraiot-listener',
        )
        ->andReturn($client);

    $this->app->instance(
        MqttClientFactory::class,
        $factory,
    );

    $this->artisan('laraiot:mqtt-listen')
        ->expectsOutputToContain(
            'Starting LaraIoT MQTT listener.',
        )
        ->expectsOutputToContain(
            'LaraIoT MQTT listener stopped.',
        )
        ->assertSuccessful();
});

it('returns a failure exit code when the MQTT connection fails', function () {
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->andThrow(
            new InvalidArgumentException(
                'Invalid MQTT client configuration.',
            ),
        );

    $this->app->instance(
        MqttClientFactory::class,
        $factory,
    );

    $this->artisan('laraiot:mqtt-listen')
        ->expectsOutputToContain(
            'Starting LaraIoT MQTT listener.',
        )
        ->expectsOutputToContain(
            'Unable to connect to the MQTT broker at 127.0.0.1:1883.',
        )
        ->assertFailed();
});
