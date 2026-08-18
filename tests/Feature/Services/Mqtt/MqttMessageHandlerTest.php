<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Services\MqttMessageHandler;

beforeEach(function () {
    $deviceType = DeviceType::query()->create([
        'identifier' => 'test-relay',
        'name' => 'Test relay',
        'is_enabled' => true,
    ]);

    $this->physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'test-controller',
        'name' => 'Test controller',
    ]);

    $this->logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $this->physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'test-logical-relay',
        'name' => 'Test logical relay',
        'is_enabled' => true,
    ]);
});

it('persists a processed MQTT message', function () {
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

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/device/state', 'ON');

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(1)
        ->and($mqttTopic->last_payload)->toBe('ON')
        ->and($mqttTopic->last_value)->toBeTrue()
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull();
});

it('stores a payload processing error', function () {
    $previousValue = true;

    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/json',
        'payload_mapping' => [
            'format' => 'json',
            'value_path' => 'state',
        ],
        'qos' => 1,
        'is_enabled' => true,
        'last_value' => $previousValue,
    ]);

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/device/json', '{"state":');

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(1)
        ->and($mqttTopic->last_payload)->toBe('{"state":')
        ->and($mqttTopic->last_value)->toBe($previousValue)
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($mqttTopic->last_error)
        ->toBe('The received payload is not valid JSON.');
});

it('ignores disabled MQTT topics', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/disabled',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 0,
        'is_enabled' => false,
    ]);

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/device/disabled', 'ON');

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(0)
        ->and($mqttTopic->last_payload)->toBeNull()
        ->and($mqttTopic->last_value)->toBeNull()
        ->and($mqttTopic->last_received_at)->toBeNull();
});

it('updates every enabled record using the received MQTT topic', function () {
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

    $secondTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/shared/state',
        'payload_mapping' => [
            'format' => 'raw',
            'value_map' => [
                'ON' => 1,
            ],
        ],
        'qos' => 1,
        'is_enabled' => true,
    ]);

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/shared/state', 'ON');

    $firstTopic->refresh();
    $secondTopic->refresh();

    expect($handledTopics)->toBe(2)
        ->and($firstTopic->last_value)->toBe('ON')
        ->and($secondTopic->last_value)->toBe(1)
        ->and($firstTopic->last_received_at)->not->toBeNull()
        ->and($secondTopic->last_received_at)->not->toBeNull();
});

it('ignores MQTT messages for unknown topics', function () {
    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('unknown/device/state', 'ON');

    expect($handledTopics)->toBe(0);
});

it('ignores command MQTT topics', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'command',
        'topic' => 'cmnd/test-controller/POWER1',
        'payload_mapping' => [
            'on' => 'ON',
            'off' => 'OFF',
        ],
        'qos' => 0,
        'retain' => false,
        'is_enabled' => true,
    ]);

    $handledTopics = app(MqttMessageHandler::class)->handle(
        'cmnd/test-controller/POWER1',
        'ON',
    );

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(0)
        ->and($mqttTopic->last_payload)->toBeNull()
        ->and($mqttTopic->last_value)->toBeNull()
        ->and($mqttTopic->last_received_at)->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull();
});
