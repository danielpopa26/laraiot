<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Services\MqttPublisher;
use Danpopa\LaraIoT\Exceptions\MqttPublishException;
use Danpopa\LaraIoT\Exceptions\MqttConnectionException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Exceptions\MqttClientException;

it('publishes an MQTT message using the default options', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1883);
    config()->set('laraiot.mqtt.client_id', null);
    config()->set('laraiot.mqtt.clean_session', true);

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('publish')
        ->once()
        ->with(
            'laraiot/device/state',
            'online',
            0,
            false,
        )
        ->andReturnNull();

    $client->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1883,
            null,
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    $this->app
        ->make(MqttPublisher::class)
        ->publish(
            'laraiot/device/state',
            'online',
        );
});

it('publishes an MQTT message with QoS retain and a custom client ID', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1884);
    config()->set('laraiot.mqtt.clean_session', false);

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            false,
        )
        ->andReturnNull();

    $client->shouldReceive('publish')
        ->once()
        ->with(
            'laraiot/device/command',
            '{"state":"ON"}',
            2,
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1884,
            'publisher-test',
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    $this->app
        ->make(MqttPublisher::class)
        ->publish(
            topic: 'laraiot/device/command',
            payload: '{"state":"ON"}',
            qos: 2,
            retain: true,
            clientId: 'publisher-test',
        );
});

it('rejects invalid MQTT publish topics', function (
    string $topic,
    string $expectedMessage,
) {
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldNotReceive('create');

    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttPublisher::class)
            ->publish($topic, 'online'),
    )->toThrow(
        InvalidArgumentException::class,
        $expectedMessage,
    );
})->with([
    'empty topic' => [
        '',
        'The MQTT topic must not be empty.',
    ],
    'whitespace-only topic' => [
        '   ',
        'The MQTT topic must not be empty.',
    ],
    'leading whitespace' => [
        ' laraiot/device/state',
        'The MQTT topic must not contain leading or trailing whitespace.',
    ],
    'trailing whitespace' => [
        'laraiot/device/state ',
        'The MQTT topic must not contain leading or trailing whitespace.',
    ],
    'single-level wildcard' => [
        'laraiot/+/state',
        'The MQTT publish topic must not contain wildcard characters.',
    ],
    'multi-level wildcard' => [
        'laraiot/device/#',
        'The MQTT publish topic must not contain wildcard characters.',
    ],
]);

it('rejects invalid MQTT QoS levels', function (int $qos) {
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldNotReceive('create');

    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttPublisher::class)
            ->publish(
                topic: 'laraiot/device/state',
                payload: 'online',
                qos: $qos,
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT QoS level must be 0, 1, or 2.',
    );
})->with([
    'negative QoS' => -1,
    'QoS above maximum' => 3,
]);

it('disconnects and wraps MQTT client exceptions when publishing fails', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1883);
    config()->set('laraiot.mqtt.client_id', null);
    config()->set('laraiot.mqtt.clean_session', true);

    $publishFailure = new MqttClientException('Publish failed.');

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('publish')
        ->once()
        ->with(
            'laraiot/device/state',
            'online',
            0,
            false,
        )
        ->andThrow($publishFailure);

    $client->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1883,
            null,
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    try {
        $this->app
            ->make(MqttPublisher::class)
            ->publish(
                'laraiot/device/state',
                'online',
            );

        $this->fail('An MqttPublishException was expected.');
    } catch (MqttPublishException $exception) {
        expect($exception->getMessage())
            ->toBe(
                'Unable to publish the MQTT message to topic "laraiot/device/state".',
            )
            ->and($exception->getPrevious())
            ->toBe($publishFailure);
    }
});

it('wraps a disconnect failure after a successful publication', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1883);
    config()->set('laraiot.mqtt.client_id', null);
    config()->set('laraiot.mqtt.clean_session', true);

    $disconnectFailure = new MqttClientException('Disconnect failed.');

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('publish')
        ->once()
        ->with(
            'laraiot/device/state',
            'online',
            0,
            false,
        )
        ->andReturnNull();

    $client->shouldReceive('disconnect')
        ->once()
        ->andThrow($disconnectFailure);

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1883,
            null,
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    try {
        $this->app
            ->make(MqttPublisher::class)
            ->publish(
                'laraiot/device/state',
                'online',
            );

        $this->fail('An MqttPublishException was expected.');
    } catch (MqttPublishException $exception) {
        expect($exception->getMessage())
            ->toBe(
                'The MQTT message was published to topic "laraiot/device/state", but the client could not disconnect safely.',
            )
            ->and($exception->getPrevious())
            ->toBe($disconnectFailure);
    }
});

it('preserves the publication failure when disconnect also fails', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1883);
    config()->set('laraiot.mqtt.client_id', null);
    config()->set('laraiot.mqtt.clean_session', true);

    $publishFailure = new MqttClientException('Publish failed.');
    $disconnectFailure = new MqttClientException('Disconnect failed.');

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('publish')
        ->once()
        ->andThrow($publishFailure);

    $client->shouldReceive('disconnect')
        ->once()
        ->andThrow($disconnectFailure);

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1883,
            null,
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    try {
        $this->app
            ->make(MqttPublisher::class)
            ->publish(
                'laraiot/device/state',
                'online',
            );

        $this->fail('An MqttPublishException was expected.');
    } catch (MqttPublishException $exception) {
        expect($exception->getMessage())
            ->toBe(
                'Unable to publish the MQTT message to topic "laraiot/device/state".',
            )
            ->and($exception->getPrevious())
            ->toBe($publishFailure);
    }
});

it('preserves the MQTT connection exception when connecting fails', function () {
    config()->set('laraiot.mqtt.host', 'mqtt.example.test');
    config()->set('laraiot.mqtt.port', 1883);
    config()->set('laraiot.mqtt.client_id', null);
    config()->set('laraiot.mqtt.clean_session', true);

    $connectionFailure = new MqttClientException('Connection failed.');

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andThrow($connectionFailure);

    $client->shouldNotReceive('publish');
    $client->shouldNotReceive('disconnect');

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            'mqtt.example.test',
            1883,
            null,
        )
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    try {
        $this->app
            ->make(MqttPublisher::class)
            ->publish(
                'laraiot/device/state',
                'online',
            );

        $this->fail('An MqttConnectionException was expected.');
    } catch (MqttConnectionException $exception) {
        expect($exception)
            ->not->toBeInstanceOf(MqttPublishException::class)
            ->and($exception->getPrevious())
            ->toBe($connectionFailure);
    }
});