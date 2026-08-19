<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Exceptions\MqttTopicTestTimeoutException;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Services\MqttCommandTopicTester;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

beforeEach(function () {
    config()->set('laraiot.mqtt.testing.timeout', 2);
    config()->set('laraiot.mqtt.testing.client_id_prefix', 'laraiot-test');
});

function makeValidatedStateTopic(array $overrides = []): MqttTopic
{
    $topic = (new MqttTopic)->forceFill(array_merge([
        'logical_device_id' => 1,
        'purpose' => 'state',
        'topic' => 'test/device/state',
        'payload_mapping' => [
            'format' => 'raw',
            'value_map' => [
                'ON' => 'on',
                'OFF' => 'off',
            ],
        ],
        'qos' => 1,
        'retain' => false,
        'is_enabled' => true,
        'validated_at' => now(),
    ], $overrides));

    $topic->exists = true;

    return $topic;
}

function makeCommandTestClient(
    string $expectedCommandPayload,
    string $statePayload,
    int $commandQos,
    bool $retain,
): MqttClientContract {
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

    $client->shouldReceive('publish')
        ->once()
        ->with(
            'test/device/command',
            $expectedCommandPayload,
            $commandQos,
            $retain,
        )
        ->andReturnNull();

    $client->shouldReceive('registerLoopEventHandler')
        ->once()
        ->with(Mockery::type(Closure::class))
        ->andReturn($client);

    $client->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnUsing(function () use (
            &$messageCallback,
            $statePayload,
        ): void {
            expect($messageCallback)->not->toBeNull();

            $messageCallback(
                'test/device/state',
                $statePayload,
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

    return $client;
}

it('validates both on and off commands against the state topic', function () {
    $stateTopic = makeValidatedStateTopic();

    $onClient = makeCommandTestClient(
        expectedCommandPayload: 'ON',
        statePayload: 'ON',
        commandQos: 1,
        retain: true,
    );

    $offClient = makeCommandTestClient(
        expectedCommandPayload: 'OFF',
        statePayload: 'OFF',
        commandQos: 1,
        retain: true,
    );

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->withArgs(fn (string $host, int $port, ?string $clientId): bool => $host === '127.0.0.1'
            && $port === 1883
            && is_string($clientId)
            && str_contains($clientId, '-command-on-'),
        )
        ->andReturn($onClient)
        ->ordered();

    $factory->shouldReceive('create')
        ->once()
        ->withArgs(fn (string $host, int $port, ?string $clientId): bool => $host === '127.0.0.1'
            && $port === 1883
            && is_string($clientId)
            && str_contains($clientId, '-command-off-'),
        )
        ->andReturn($offClient)
        ->ordered();

    $this->app->instance(MqttClientFactory::class, $factory);

    $result = $this->app
        ->make(MqttCommandTopicTester::class)
        ->test(
            stateTopic: $stateTopic,
            commandTopic: 'test/device/command',
            commandPayloads: [
                'on' => 'ON',
                'off' => 'OFF',
            ],
            qos: 1,
            retain: true,
        );

    expect($result['on']['command_payload'])->toBe('ON')
        ->and($result['on']['state_topic'])->toBe('test/device/state')
        ->and($result['on']['state_raw_payload'])->toBe('ON')
        ->and($result['on']['normalized_value'])->toBe('on')
        ->and($result['off']['command_payload'])->toBe('OFF')
        ->and($result['off']['state_raw_payload'])->toBe('OFF')
        ->and($result['off']['normalized_value'])->toBe('off');
});

it('ignores a retained state before accepting a fresh command confirmation', function () {
    $stateTopic = makeValidatedStateTopic();

    $onCallback = null;
    $onClient = Mockery::mock(MqttClientContract::class);

    $onClient->shouldReceive('connect')->once()->andReturnNull();
    $onClient->shouldReceive('subscribe')
        ->once()
        ->withArgs(function (string $topic, callable $callback, int $qos) use (&$onCallback): bool {
            $onCallback = $callback;

            return $topic === 'test/device/state' && $qos === 1;
        })
        ->andReturnNull();
    $onClient->shouldReceive('publish')
        ->once()
        ->with('test/device/command', 'ON', 0, false)
        ->andReturnNull();
    $onClient->shouldReceive('registerLoopEventHandler')->once()->andReturn($onClient);
    $onClient->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnUsing(function () use (&$onCallback): void {
            expect($onCallback)->not->toBeNull();
            $onCallback('test/device/state', 'ON', true, []);
            $onCallback('test/device/state', 'ON', false, []);
        });
    $onClient->shouldReceive('interrupt')->once()->andReturnNull();
    $onClient->shouldReceive('isConnected')->once()->andReturnTrue();
    $onClient->shouldReceive('disconnect')->once()->andReturnNull();

    $offClient = makeCommandTestClient(
        expectedCommandPayload: 'OFF',
        statePayload: 'OFF',
        commandQos: 0,
        retain: false,
    );

    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldReceive('create')->once()->andReturn($onClient)->ordered();
    $factory->shouldReceive('create')->once()->andReturn($offClient)->ordered();
    $this->app->instance(MqttClientFactory::class, $factory);

    $result = $this->app
        ->make(MqttCommandTopicTester::class)
        ->test(
            stateTopic: $stateTopic,
            commandTopic: 'test/device/command',
            commandPayloads: [
                'on' => 'ON',
                'off' => 'OFF',
            ],
        );

    expect($result['on']['normalized_value'])->toBe('on')
        ->and($result['off']['normalized_value'])->toBe('off');
});

it('times out when the expected command state is not observed', function () {
    $stateTopic = makeValidatedStateTopic();

    $messageCallback = null;
    $loopHandler = null;

    $client = Mockery::mock(MqttClientContract::class);

    $client->shouldReceive('connect')->once()->andReturnNull();
    $client->shouldReceive('subscribe')
        ->once()
        ->withArgs(function (string $topic, callable $callback, int $qos) use (&$messageCallback): bool {
            $messageCallback = $callback;

            return $topic === 'test/device/state' && $qos === 1;
        })
        ->andReturnNull();
    $client->shouldReceive('publish')
        ->once()
        ->with('test/device/command', 'ON', 0, false)
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
        ->andReturnUsing(function () use (&$messageCallback, &$loopHandler, $client): void {
            expect($messageCallback)->not->toBeNull()
                ->and($loopHandler)->not->toBeNull();

            $messageCallback('test/device/state', 'OFF', false, []);
            $loopHandler($client, 2.0);
        });
    $client->shouldReceive('interrupt')->once()->andReturnNull();
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once()->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldReceive('create')->once()->andReturn($client);
    $this->app->instance(MqttClientFactory::class, $factory);

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: [
                    'on' => 'ON',
                    'off' => 'OFF',
                ],
            ),
    )->toThrow(
        MqttTopicTestTimeoutException::class,
        'The MQTT command "on" was not confirmed on state topic "test/device/state" within 2 seconds. Last normalized state: off.',
    );
});

it('rejects an unsaved state topic', function () {
    $stateTopic = makeValidatedStateTopic();
    $stateTopic->exists = false;

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The state MQTT topic must be saved before testing a command topic.',
    );
});

it('rejects a state topic with the wrong purpose', function () {
    $stateTopic = makeValidatedStateTopic([
        'purpose' => 'command',
    ]);

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'Command topic testing requires a state MQTT topic.',
    );
});

it('rejects a disabled state topic', function () {
    $stateTopic = makeValidatedStateTopic([
        'is_enabled' => false,
    ]);

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'Command topic testing requires an enabled state MQTT topic.',
    );
});

it('rejects an unvalidated state topic', function () {
    $stateTopic = makeValidatedStateTopic([
        'validated_at' => null,
    ]);

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'Command topic testing requires a validated state MQTT topic.',
    );
});

it('rejects a state topic without payload mapping', function () {
    $stateTopic = makeValidatedStateTopic([
        'payload_mapping' => null,
    ]);

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The state MQTT topic payload mapping is not configured.',
    );
});

it('rejects an empty command topic', function () {
    $stateTopic = makeValidatedStateTopic();

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: '   ',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT command topic must not be empty.',
    );
});

it('rejects wildcard command topics', function (string $topic) {
    $stateTopic = makeValidatedStateTopic();

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: $topic,
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT command topic must not contain wildcard characters.',
    );
})->with([
    'single-level wildcard' => 'test/+/command',
    'multi-level wildcard' => 'test/#',
]);

it('rejects an invalid command topic QoS', function () {
    $stateTopic = makeValidatedStateTopic();

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: ['on' => 'ON', 'off' => 'OFF'],
                qos: 3,
            ),
    )->toThrow(
        InvalidArgumentException::class,
        'The MQTT QoS level must be 0, 1, or 2.',
    );
});

it('requires both on and off command payloads', function (array $payloads, string $missingCommand) {
    $stateTopic = makeValidatedStateTopic();

    expect(
        fn () => $this->app
            ->make(MqttCommandTopicTester::class)
            ->test(
                stateTopic: $stateTopic,
                commandTopic: 'test/device/command',
                commandPayloads: $payloads,
            ),
    )->toThrow(
        InvalidArgumentException::class,
        sprintf(
            'The MQTT payload for command "%s" is not configured.',
            $missingCommand,
        ),
    );
})->with([
    'missing on' => [
        ['off' => 'OFF'],
        'on',
    ],
    'missing off' => [
        ['on' => 'ON'],
        'off',
    ],
]);
