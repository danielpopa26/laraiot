<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;

beforeEach(function () {
    $deviceType = DeviceType::query()->create([
        'identifier' => 'observer-test-relay',
        'name' => 'Observer test relay',
        'is_enabled' => true,
    ]);

    $this->physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'observer-test-controller',
        'name' => 'Observer test controller',
    ]);

    $this->logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $this->physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'observer-test-logical-device',
        'name' => 'Observer test logical device',
        'is_enabled' => true,
    ]);

    $this->otherLogicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $this->physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'observer-test-other-logical-device',
        'name' => 'Observer test other logical device',
        'is_enabled' => true,
    ]);

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
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
        ]);
    };

    $this->makeCommandTopic = function (
        ?LogicalDevice $logicalDevice = null,
        string $topic = 'test/device/command',
    ): MqttTopic {
        $logicalDevice ??= $this->logicalDevice;

        return MqttTopic::query()->create([
            'logical_device_id' => $logicalDevice->getKey(),
            'purpose' => 'command',
            'topic' => $topic,
            'payload_mapping' => [
                'on' => 'ON',
                'off' => 'OFF',
            ],
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
        ]);
    };
});

it('invalidates a topic when MQTT validation-sensitive configuration changes', function (
    string $field,
    mixed $value,
) {
    $mqttTopic = ($this->makeCommandTopic)();
    $mqttTopic->markAsValidated();

    $mqttTopic->update([
        $field => $value,
    ]);

    expect($mqttTopic->refresh()->validated_at)->toBeNull();
})->with([
    'topic' => [
        'topic',
        'test/device/command-v2',
    ],
    'payload mapping' => [
        'payload_mapping',
        [
            'on' => '1',
            'off' => '0',
        ],
    ],
    'qos' => [
        'qos',
        1,
    ],
    'retain' => [
        'retain',
        true,
    ],
    'purpose' => [
        'purpose',
        'state',
    ],
]);

it('invalidates a topic when it is moved to another logical device', function () {
    $mqttTopic = ($this->makeCommandTopic)();
    $mqttTopic->markAsValidated();

    $mqttTopic->update([
        'logical_device_id' => $this->otherLogicalDevice->getKey(),
    ]);

    expect($mqttTopic->refresh()->validated_at)->toBeNull();
});

it('keeps validation when only the enabled flag changes', function () {
    $mqttTopic = ($this->makeStateTopic)();
    $mqttTopic->markAsValidated();

    $validatedAt = $mqttTopic->validated_at?->toDateTimeString();

    $mqttTopic->update([
        'is_enabled' => false,
    ]);

    $mqttTopic->refresh();

    expect($mqttTopic->validated_at)->not->toBeNull()
        ->and($mqttTopic->validated_at?->toDateTimeString())
        ->toBe($validatedAt);
});

it('keeps validation when runtime state fields change', function () {
    $mqttTopic = ($this->makeStateTopic)();
    $mqttTopic->markAsValidated();

    $validatedAt = $mqttTopic->validated_at?->toDateTimeString();

    $mqttTopic->update([
        'last_payload' => 'ON',
        'last_value' => 'on',
        'last_received_at' => now(),
        'last_error' => null,
    ]);

    $mqttTopic->refresh();

    expect($mqttTopic->validated_at)->not->toBeNull()
        ->and($mqttTopic->validated_at?->toDateTimeString())
        ->toBe($validatedAt);
});

it('invalidates validated command topics when a state topic configuration changes', function () {
    $stateTopic = ($this->makeStateTopic)();
    $commandTopic = ($this->makeCommandTopic)();
    $secondCommandTopic = ($this->makeCommandTopic)(
        $this->logicalDevice,
        'test/device/second-command',
    );

    $stateTopic->markAsValidated();
    $commandTopic->markAsValidated();
    $secondCommandTopic->markAsValidated();

    $stateTopic->update([
        'topic' => 'test/device/state-v2',
    ]);

    expect($stateTopic->refresh()->validated_at)->toBeNull()
        ->and($commandTopic->refresh()->validated_at)->toBeNull()
        ->and($secondCommandTopic->refresh()->validated_at)->toBeNull();
});

it('does not invalidate command topics belonging to another logical device', function () {
    $stateTopic = ($this->makeStateTopic)();
    $sameDeviceCommand = ($this->makeCommandTopic)();
    $otherDeviceCommand = ($this->makeCommandTopic)(
        $this->otherLogicalDevice,
        'test/other-device/command',
    );

    $stateTopic->markAsValidated();
    $sameDeviceCommand->markAsValidated();
    $otherDeviceCommand->markAsValidated();

    $stateTopic->update([
        'payload_mapping' => [
            'format' => 'raw',
            'value_map' => [
                '1' => 'on',
                '0' => 'off',
            ],
        ],
    ]);

    expect($sameDeviceCommand->refresh()->validated_at)->toBeNull()
        ->and($otherDeviceCommand->refresh()->validated_at)->not->toBeNull();
});

it('invalidates command topics on the original logical device when a state topic is moved', function () {
    $stateTopic = ($this->makeStateTopic)();
    $originalDeviceCommand = ($this->makeCommandTopic)();
    $destinationDeviceCommand = ($this->makeCommandTopic)(
        $this->otherLogicalDevice,
        'test/destination-device/command',
    );

    $stateTopic->markAsValidated();
    $originalDeviceCommand->markAsValidated();
    $destinationDeviceCommand->markAsValidated();

    $stateTopic->update([
        'logical_device_id' => $this->otherLogicalDevice->getKey(),
    ]);

    expect($stateTopic->refresh()->validated_at)->toBeNull()
        ->and($originalDeviceCommand->refresh()->validated_at)->toBeNull()
        ->and($destinationDeviceCommand->refresh()->validated_at)->not->toBeNull();
});
