<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Exceptions\MqttTopicTestTimeoutException;
use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Services\MqttCommandTopicTester;
use Danpopa\LaraIoT\Services\MqttConnectionService;
use Danpopa\LaraIoT\Services\MqttPayloadProcessor;
use Danpopa\LaraIoT\Services\MqttStateTopicTester;
use Danpopa\LaraIoT\Services\MqttTopicValidationService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Mockery\MockInterface;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

beforeEach(function () {
    config()->set('laraiot.mqtt.testing.timeout', 1);
    config()->set(
        'laraiot.mqtt.testing.client_id_prefix',
        'laraiot-test',
    );

    $deviceType = DeviceType::query()->create([
        'identifier' => 'validation-test-relay',
        'name' => 'Validation test relay',
        'is_enabled' => true,
    ]);

    $this->physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'validation-test-controller',
        'name' => 'Validation test controller',
    ]);

    $this->logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $this->physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'validation-test-logical-device',
        'name' => 'Validation test logical device',
        'is_enabled' => true,
    ]);

    $this->otherLogicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $this->physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'validation-test-other-logical-device',
        'name' => 'Validation test other logical device',
        'is_enabled' => true,
    ]);

    $this->makeValidationService = function (
        MqttClientFactory $factory,
    ): MqttTopicValidationService {
        $connectionService = new MqttConnectionService(
            $this->app->make(ConfigRepository::class),
            $factory,
        );

        $payloadProcessor = $this->app->make(
            MqttPayloadProcessor::class,
        );

        return new MqttTopicValidationService(
            new MqttStateTopicTester(
                $connectionService,
                $payloadProcessor,
            ),
            new MqttCommandTopicTester(
                $connectionService,
                $payloadProcessor,
            ),
        );
    };

    $this->makeStateTopic = function (
        ?LogicalDevice $logicalDevice = null,
    ): MqttTopic {
        $logicalDevice ??= $this->logicalDevice;

        return MqttTopic::query()->create([
            'logical_device_id' => $logicalDevice->getKey(),
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
        ]);
    };

    $this->makeCommandTopic = function (
        ?LogicalDevice $logicalDevice = null,
    ): MqttTopic {
        $logicalDevice ??= $this->logicalDevice;

        return MqttTopic::query()->create([
            'logical_device_id' => $logicalDevice->getKey(),
            'purpose' => 'command',
            'topic' => 'test/device/command',
            'payload_mapping' => [
                'on' => 'ON',
                'off' => 'OFF',
            ],
            'qos' => 1,
            'retain' => true,
            'is_enabled' => true,
        ]);
    };

    $this->makeStateClient = function (
        string $receivedPayload,
    ): MqttClientContract&MockInterface {
        $messageCallback = null;

        /** @var MqttClientContract&MockInterface $client */
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

        $client->shouldReceive('interrupt')
            ->once()
            ->andReturnNull();

        $client->shouldReceive('loop')
            ->once()
            ->with(true)
            ->andReturnUsing(function () use (
                &$messageCallback,
                $receivedPayload,
            ): void {
                expect($messageCallback)->not->toBeNull();

                $messageCallback(
                    'test/device/state',
                    $receivedPayload,
                    false,
                    [],
                );
            });

        $client->shouldReceive('isConnected')
            ->once()
            ->andReturnTrue();

        $client->shouldReceive('disconnect')
            ->once()
            ->andReturnNull();

        return $client;
    };

    $this->makeSilentClient = function (): MqttClientContract&MockInterface {
        /** @var MqttClientContract&MockInterface $client */
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
            ->andReturnTrue();

        $client->shouldReceive('disconnect')
            ->once()
            ->andReturnNull();

        return $client;
    };

    $this->makeCommandClient = function (
        string $commandPayload,
        string $statePayload,
    ): MqttClientContract&MockInterface {
        $messageCallback = null;

        /** @var MqttClientContract&MockInterface $client */
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
                $commandPayload,
                1,
                true,
            )
            ->andReturnNull();

        $client->shouldReceive('registerLoopEventHandler')
            ->once()
            ->with(Mockery::type(Closure::class))
            ->andReturn($client);

        $client->shouldReceive('interrupt')
            ->once()
            ->andReturnNull();

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

        $client->shouldReceive('isConnected')
            ->once()
            ->andReturnTrue();

        $client->shouldReceive('disconnect')
            ->once()
            ->andReturnNull();

        return $client;
    };
});

it('validates a state topic after receiving a processable MQTT message', function () {
    $stateTopic = ($this->makeStateTopic)();
    $client = ($this->makeStateClient)('ON');

    /** @var MqttClientFactory&MockInterface $factory */
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            '127.0.0.1',
            1883,
            Mockery::pattern('/^laraiot-test-[a-f0-9]{12}$/'),
        )
        ->andReturn($client);

    $service = ($this->makeValidationService)($factory);

    $result = $service->validateStateTopic($stateTopic);

    $stateTopic->refresh();

    expect($result['normalized_value'])->toBe('on')
        ->and($stateTopic->validated_at)->not->toBeNull()
        ->and($stateTopic->isValidated())->toBeTrue()
        ->and($stateTopic->isUsable())->toBeTrue();
});

it('leaves a state topic unvalidated when the MQTT test fails', function () {
    $stateTopic = ($this->makeStateTopic)();
    $stateTopic->markAsValidated();

    $client = ($this->makeSilentClient)();

    /** @var MqttClientFactory&MockInterface $factory */
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->andReturn($client);

    $service = ($this->makeValidationService)($factory);

    expect(
        fn () => $service->validateStateTopic($stateTopic),
    )->toThrow(MqttTopicTestTimeoutException::class);

    expect($stateTopic->refresh()->validated_at)->toBeNull();
});

it('rejects invalid state topic configuration before connecting to MQTT', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');

    $service = ($this->makeValidationService)($factory);

    $commandTopic = ($this->makeCommandTopic)();

    expect(
        fn () => $service->validateStateTopic($commandTopic),
    )->toThrow(
        \InvalidArgumentException::class,
        'Only MQTT state topics can be validated as state topics.',
    );

    $disabledStateTopic = ($this->makeStateTopic)();
    $disabledStateTopic->update(['is_enabled' => false]);

    expect(
        fn () => $service->validateStateTopic($disabledStateTopic),
    )->toThrow(
        \InvalidArgumentException::class,
        'A disabled MQTT state topic cannot be validated.',
    );

    $missingMappingTopic = ($this->makeStateTopic)();
    $missingMappingTopic->update(['payload_mapping' => null]);

    expect(
        fn () => $service->validateStateTopic($missingMappingTopic),
    )->toThrow(
        \InvalidArgumentException::class,
        'The MQTT state topic payload mapping is not configured.',
    );
});

it('rejects unsaved topics before validation', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');

    $service = ($this->makeValidationService)($factory);

    $stateTopic = (new MqttTopic)->forceFill([
        'purpose' => 'state',
        'topic' => 'test/device/state',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    expect(
        fn () => $service->validateStateTopic($stateTopic),
    )->toThrow(
        \InvalidArgumentException::class,
        'The MQTT topic must be saved before it can be validated.',
    );
});

it('validates a command topic only after both on and off are confirmed', function () {
    $stateTopic = ($this->makeStateTopic)();
    $stateTopic->markAsValidated();

    $commandTopic = ($this->makeCommandTopic)();

    $onClient = ($this->makeCommandClient)(
        'ON',
        'ON',
    );

    $offClient = ($this->makeCommandClient)(
        'OFF',
        'OFF',
    );

    /** @var MqttClientFactory&MockInterface $factory */
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            '127.0.0.1',
            1883,
            Mockery::pattern('/^laraiot-test-command-on-[a-f0-9]{12}$/'),
        )
        ->andReturn($onClient);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            '127.0.0.1',
            1883,
            Mockery::pattern('/^laraiot-test-command-off-[a-f0-9]{12}$/'),
        )
        ->andReturn($offClient);

    $service = ($this->makeValidationService)($factory);

    $result = $service->validateCommandTopic(
        $commandTopic,
        $stateTopic,
    );

    $commandTopic->refresh();

    expect($result['on']['normalized_value'])->toBe('on')
        ->and($result['off']['normalized_value'])->toBe('off')
        ->and($commandTopic->validated_at)->not->toBeNull()
        ->and($commandTopic->isUsable())->toBeTrue();
});

it('keeps a command topic unvalidated when command confirmation fails', function () {
    $stateTopic = ($this->makeStateTopic)();
    $stateTopic->markAsValidated();

    $commandTopic = ($this->makeCommandTopic)();
    $commandTopic->markAsValidated();

    $onClient = ($this->makeCommandClient)(
        'ON',
        'ON',
    );

    $offClient = Mockery::mock(MqttClientContract::class);

    $offClient->shouldReceive('connect')
        ->once()
        ->with(
            Mockery::type(ConnectionSettings::class),
            true,
        )
        ->andReturnNull();

    $offClient->shouldReceive('subscribe')
        ->once()
        ->andReturnNull();

    $offClient->shouldReceive('publish')
        ->once()
        ->with(
            'test/device/command',
            'OFF',
            1,
            true,
        )
        ->andReturnNull();

    $offClient->shouldReceive('registerLoopEventHandler')
        ->once()
        ->with(Mockery::type(Closure::class))
        ->andReturn($offClient);

    $offClient->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnNull();

    $offClient->shouldReceive('isConnected')
        ->once()
        ->andReturnTrue();

    $offClient->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    /** @var MqttClientFactory&MockInterface $factory */
    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->andReturn($onClient);

    $factory->shouldReceive('create')
        ->once()
        ->andReturn($offClient);

    $service = ($this->makeValidationService)($factory);

    expect(
        fn () => $service->validateCommandTopic(
            $commandTopic,
            $stateTopic,
        ),
    )->toThrow(MqttTopicTestTimeoutException::class);

    expect($commandTopic->refresh()->validated_at)->toBeNull();
});

it('rejects command validation without a usable state topic', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');

    $service = ($this->makeValidationService)($factory);

    $stateTopic = ($this->makeStateTopic)();
    $commandTopic = ($this->makeCommandTopic)();

    expect(
        fn () => $service->validateCommandTopic(
            $commandTopic,
            $stateTopic,
        ),
    )->toThrow(
        \InvalidArgumentException::class,
        'Command topic validation requires an enabled and validated state topic.',
    );
});

it('requires command and state topics to belong to the same logical device', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');

    $service = ($this->makeValidationService)($factory);

    $stateTopic = ($this->makeStateTopic)();
    $stateTopic->markAsValidated();

    $commandTopic = ($this->makeCommandTopic)(
        $this->otherLogicalDevice,
    );

    expect(
        fn () => $service->validateCommandTopic(
            $commandTopic,
            $stateTopic,
        ),
    )->toThrow(
        \InvalidArgumentException::class,
        'The command topic and state topic must belong to the same logical device.',
    );
});

it('requires both on and off command payloads', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');

    $service = ($this->makeValidationService)($factory);

    $stateTopic = ($this->makeStateTopic)();
    $stateTopic->markAsValidated();

    $commandTopic = ($this->makeCommandTopic)();
    $commandTopic->update([
        'payload_mapping' => [
            'on' => 'ON',
        ],
    ]);

    expect(
        fn () => $service->validateCommandTopic(
            $commandTopic,
            $stateTopic,
        ),
    )->toThrow(
        \InvalidArgumentException::class,
        'The MQTT payload for command "off" is not configured.',
    );
});

it('invalidates command topics when a state topic validation is invalidated', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->shouldNotReceive('create');

    $service = ($this->makeValidationService)($factory);

    $stateTopic = ($this->makeStateTopic)();
    $commandTopic = ($this->makeCommandTopic)();

    $stateTopic->markAsValidated();
    $commandTopic->markAsValidated();

    $service->invalidate($stateTopic);

    expect($stateTopic->refresh()->validated_at)->toBeNull()
        ->and($commandTopic->refresh()->validated_at)->toBeNull();
});
