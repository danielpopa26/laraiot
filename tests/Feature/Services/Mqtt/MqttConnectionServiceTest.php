<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Exceptions\MqttConnectionException;
use Danpopa\LaraIoT\Services\MqttConnectionService;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

it('registers the MQTT connection service as a singleton', function () {
    $firstInstance = $this->app->make(MqttConnectionService::class);
    $secondInstance = $this->app->make(MqttConnectionService::class);

    expect($firstInstance)->toBe($secondInstance);
});

it('rejects an empty MQTT broker host', function () {
    config()->set('laraiot.mqtt.host', '   ');

    expect(
        fn () => $this->app
            ->make(MqttConnectionService::class)
            ->connect(),
    )->toThrow(
        MqttConnectionException::class,
        'The MQTT broker host is not configured.',
    );
});

it('rejects an invalid MQTT broker port', function () {
    config()->set('laraiot.mqtt.port', 70000);

    expect(
        fn () => $this->app
            ->make(MqttConnectionService::class)
            ->connect(),
    )->toThrow(
        MqttConnectionException::class,
        'The MQTT broker port must be between 1 and 65535.',
    );
});

it('requires a client ID for a persistent MQTT session', function () {
    config()->set('laraiot.mqtt.clean_session', false);
    config()->set('laraiot.mqtt.client_id', null);

    expect(
        fn () => $this->app
            ->make(MqttConnectionService::class)
            ->connect(),
    )->toThrow(
        MqttConnectionException::class,
        'A client ID is required when clean session is disabled.',
    );
});

it('creates and connects an MQTT client using the configured values', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1884);
    config()->set('laraiot.mqtt.client_id', 'listener-test');
    config()->set('laraiot.mqtt.clean_session', false);

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            false,
        )
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1884,
            'listener-test',
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    $result = $this->app
        ->make(MqttConnectionService::class)
        ->connect();

    expect($result)->toBe($client);
});

it('wraps client creation errors in an MQTT connection exception', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1883);

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->andThrow(new InvalidArgumentException(
            'Invalid MQTT client parameters.',
        ));

    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttConnectionService::class)
            ->connect(),
    )->toThrow(
        MqttConnectionException::class,
        'Unable to connect to the MQTT broker at mqtt.example.test:1883.',
    );
});
