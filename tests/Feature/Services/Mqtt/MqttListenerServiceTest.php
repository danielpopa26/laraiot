<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Services\MqttHealthMonitor;
use Danpopa\LaraIoT\Services\MqttListenerService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

beforeEach(function () {
    config()->set(
        'laraiot.mqtt.listener.client_id',
        'laraiot-listener-test',
    );

    config()->set(
        'laraiot.mqtt.listener.sync_interval',
        5,
    );

    $this->mqttHealthMonitor = new MqttHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
    );

    $this->app->instance(
        MqttHealthMonitor::class,
        $this->mqttHealthMonitor,
    );

    $deviceType = DeviceType::query()->create([
        'identifier' => 'listener-test-relay',
        'name' => 'Listener test relay',
        'is_enabled' => true,
    ]);

    $this->physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'listener-test-controller',
        'name' => 'Listener test controller',
    ]);

    $this->logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $this->physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'listener-test-logical-relay',
        'name' => 'Listener test logical relay',
        'is_enabled' => true,
    ]);
});

it('subscribes once to each enabled validated state MQTT topic using its highest QoS', function () {
    $firstTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/shared/state',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    $firstTopic->markAsValidated();

    $secondTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/shared/state',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 1,
        'is_enabled' => true,
    ]);

    $secondTopic->markAsValidated();

    $disabledTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/disabled/state',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 2,
        'is_enabled' => false,
    ]);

    $disabledTopic->markAsValidated();

    MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/unvalidated/state',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 2,
        'is_enabled' => true,
    ]);

    $commandTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'command',
        'topic' => 'test/device/command',
        'payload_mapping' => [
            'on' => 'ON',
            'off' => 'OFF',
        ],
        'qos' => 2,
        'retain' => false,
        'is_enabled' => true,
    ]);

    $commandTopic->markAsValidated();

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
            'test/shared/state',
            Mockery::type(Closure::class),
            1,
        )
        ->andReturnNull();

    $client->shouldReceive('registerLoopEventHandler')
        ->once()
        ->with(Mockery::type(Closure::class))
        ->andReturn($client);

    $client->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnUsing(function (): void {
            $snapshot = $this->mqttHealthMonitor->snapshot();

            expect($snapshot['connected'])->toBeTrue()
                ->and($snapshot['status'])->toBe('connected')
                ->and($snapshot['subscriptions'])->toBe(1);
        });

    $client->shouldReceive('isConnected')
        ->once()
        ->andReturnTrue();

    $client->shouldReceive('disconnect')
        ->once()
        ->andReturnNull();

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->with(
            '127.0.0.1',
            1883,
            'laraiot-listener-test',
        )
        ->andReturn($client);

    $this->app->instance(
        MqttClientFactory::class,
        $factory,
    );

    $this->app
        ->make(MqttListenerService::class)
        ->listen();

    expect($this->mqttHealthMonitor->snapshot()['status'])
        ->toBe('offline');
});

it('forwards received MQTT messages to the message handler', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/state',
        'payload_mapping' => [
            'format' => 'raw',
            'value_map' => [
                'ON' => true,
                'OFF' => false,
            ],
        ],
        'qos' => 1,
        'is_enabled' => true,
    ]);

    $mqttTopic->markAsValidated();

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
        ->andReturnUsing(function () use (
            &$messageCallback,
        ): void {
            expect($messageCallback)->not->toBeNull();

            $messageCallback(
                'test/device/state',
                'ON',
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

    $factory = Mockery::mock(MqttClientFactory::class);

    $factory->shouldReceive('create')
        ->once()
        ->andReturn($client);

    $this->app->instance(
        MqttClientFactory::class,
        $factory,
    );

    $this->app
        ->make(MqttListenerService::class)
        ->listen();

    $mqttTopic->refresh();

    expect($mqttTopic->last_payload)->toBe('ON')
        ->and($mqttTopic->last_value)->toBeTrue()
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull()
        ->and(
            $this->mqttHealthMonitor
                ->snapshot()['last_message_at'],
        )->not->toBeNull();
});

it('synchronizes MQTT subscriptions while the listener is running', function () {
    $firstTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/first/state',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    $firstTopic->markAsValidated();

    $loopHandler = null;
    $logicalDeviceId = $this->logicalDevice->getKey();

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
            'test/first/state',
            Mockery::type(Closure::class),
            0,
        )
        ->andReturnNull();

    $client->shouldReceive('subscribe')
        ->once()
        ->with(
            'test/second/state',
            Mockery::type(Closure::class),
            2,
        )
        ->andReturnNull();

    $client->shouldReceive('unsubscribe')
        ->once()
        ->with('test/first/state')
        ->andReturnNull();

    $client->shouldReceive('registerLoopEventHandler')
        ->once()
        ->withArgs(function (
            Closure $callback,
        ) use (&$loopHandler): bool {
            $loopHandler = $callback;

            return true;
        })
        ->andReturn($client);

    $client->shouldReceive('loop')
        ->once()
        ->with(true)
        ->andReturnUsing(function () use (
            &$loopHandler,
            $client,
            $firstTopic,
            $logicalDeviceId,
        ): void {
            $firstTopic->update([
                'is_enabled' => false,
            ]);

            $secondTopic = MqttTopic::query()->create([
                'logical_device_id' => $logicalDeviceId,
                'purpose' => 'state',
                'topic' => 'test/second/state',
                'payload_mapping' => [
                    'format' => 'raw',
                ],
                'qos' => 2,
                'is_enabled' => true,
            ]);

            $secondTopic->markAsValidated();

            expect($loopHandler)->not->toBeNull();

            $loopHandler(
                $client,
                5.0,
            );
        });

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

    $this->app->instance(
        MqttClientFactory::class,
        $factory,
    );

    $this->app
        ->make(MqttListenerService::class)
        ->listen();
});
