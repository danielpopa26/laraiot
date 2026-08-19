<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Exceptions\MqttTopicTestTimeoutException;
use Danpopa\LaraIoT\Services\MqttStateTopicTester;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

beforeEach(function () {
    config()->set('laraiot.mqtt.testing.timeout', 2);
    config()->set('laraiot.mqtt.testing.client_id_prefix', 'laraiot-test');
});

it('receives and processes a state topic payload', function () {
    $messageCallback = null;

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('subscribe')
        ->once()
        ->withArgs(function (
            string $topic,
            callable $callback,
            int $qos,
        ) use (&$messageCallback): bool {
            $messageCallback = $callback;

            return $topic === 'test/device/state'
                && $qos === 1;
        })
        ->andReturnNull();

    $client->shouldReceive('registerLoopEventHandler')
        ->once()
        ->with(Mockery::type(Closure::class))
        ->andReturn($client);

    $client->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnUsing(function () use (&$messageCallback): void {
            expect($messageCallback)->not->toBeNull();

            $messageCallback(
                'test/device/state',
                'ON',
                false,
                [],
            );
        });

    $client->shouldReceive('interrupt')
        ->once()
        ->andReturnNull();

    $client->shouldReceive('isConnected')
        ->once()
        ->andReturnTrue();

    $client->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->withArgs(function (
            string $host,
            int $port,
            ?string $clientId,
        ): bool {
            return $host === '127.0.0.1'
                && $port === 1883
                && is_string($clientId)
                && str_starts_with($clientId, 'laraiot-test-');
        })
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    $result = $this->app
        ->make(MqttStateTopicTester::class)
        ->test(
            topic: 'test/device/state',
            qos: 1,
            payloadConfiguration: [
                'format' => 'raw',
                'value_map' => [
                    'ON' => 'on',
                    'OFF' => 'off',
                ],
            ],
        );

    expect($result['topic'])->toBe('test/device/state')
        ->and($result['raw_payload'])->toBe('ON')
        ->and($result['retained'])->toBeFalse()
        ->and($result['configured_format'])->toBe('raw')
        ->and($result['detected_format'])->toBe('raw')
        ->and($result['extracted_value'])->toBe('ON')
        ->and($result['normalized_value'])->toBe('on');
});

it('times out when no state topic message is received', function () {
    $loopHandler = null;

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $client->shouldReceive('subscribe')
        ->once()
        ->with(
            'test/device/state',
            Mockery::type(Closure::class),
            0,
        )
        ->andReturnNull();

    $client->shouldReceive('registerLoopEventHandler')
        ->once()
        ->withArgs(function (Closure $callback) use (&$loopHandler): bool {
            $loopHandler = $callback;

            return true;
        })
        ->andReturn($client);

    $client->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnUsing(function () use (&$loopHandler, $client): void {
            expect($loopHandler)->not->toBeNull();

            $loopHandler($client, 2.0);
        });

    $client->shouldReceive('interrupt')
        ->once()
        ->andReturnNull();

    $client->shouldReceive('isConnected')
        ->once()
        ->andReturnTrue();

    $client->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->andReturn($client);

    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttStateTopicTester::class)
            ->test(
                topic: 'test/device/state',
                qos: 0,
                payloadConfiguration: [
                    'format' => 'raw',
                ],
            ),
    )->toThrow(
        MqttTopicTestTimeoutException::class,
        'No message was received on topic "test/device/state" within 2 seconds.',
    );
});

it('rejects an empty state topic', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');
    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttStateTopicTester::class)
            ->test(
                topic: '   ',
                qos: 0,
                payloadConfiguration: [
                    'format' => 'raw',
                ],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT topic must not be empty.',
    );
});

it('rejects wildcard state topics', function (string $topic) {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');
    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttStateTopicTester::class)
            ->test(
                topic: $topic,
                qos: 0,
                payloadConfiguration: [
                    'format' => 'raw',
                ],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT topic test requires an exact topic without wildcard characters.',
    );
})->with([
    'single-level wildcard' => 'test/+/state',
    'multi-level wildcard' => 'test/#',
]);

it('rejects an invalid state topic QoS', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');
    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttStateTopicTester::class)
            ->test(
                topic: 'test/device/state',
                qos: 3,
                payloadConfiguration: [
                    'format' => 'raw',
                ],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT QoS level must be 0, 1, or 2.',
    );
});
