<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ActivityLog;
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

it('persists a processed MQTT message and records the first state activity', function () {
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

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/device/state', 'ON');

    $mqttTopic->refresh();
    $activity = ActivityLog::query()->sole();

    expect($handledTopics)->toBe(1)
        ->and($mqttTopic->last_payload)->toBe('ON')
        ->and($mqttTopic->last_value)->toBeTrue()
        ->and($this->logicalDevice->fresh()->last_value)->toBeTrue()
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull()
        ->and($activity->type)->toBe('state')
        ->and($activity->logical_device_id)
        ->toBe($this->logicalDevice->getKey())
        ->and($activity->mqtt_topic_id)->toBe($mqttTopic->getKey())
        ->and($activity->title)->toBe('Test logical relay state updated')
        ->and($activity->data['topic'])->toBe('test/device/state')
        ->and($activity->data['raw_payload'])->toBe('ON')
        ->and($activity->data['normalized_value'])->toBeTrue();
});

it('stores a payload processing error and records an error activity', function () {
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

    $mqttTopic->markAsValidated();

    $payload = '{"state":';

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/device/json', $payload);

    $mqttTopic->refresh();
    $activity = ActivityLog::query()->sole();

    expect($handledTopics)->toBe(1)
        ->and($mqttTopic->last_payload)->toBe($payload)
        ->and($mqttTopic->last_value)->toBe($previousValue)
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($mqttTopic->last_error)
        ->toBe('The received payload is not valid JSON.')
        ->and($activity->type)->toBe('error')
        ->and($activity->mqtt_topic_id)->toBe($mqttTopic->getKey())
        ->and($activity->title)->toBe('MQTT state processing failed')
        ->and($activity->data['raw_payload'])->toBe($payload)
        ->and($activity->data['error'])
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

    $mqttTopic->markAsValidated();

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/device/disabled', 'ON');

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(0)
        ->and($mqttTopic->last_payload)->toBeNull()
        ->and($mqttTopic->last_value)->toBeNull()
        ->and($mqttTopic->last_received_at)->toBeNull()
        ->and(ActivityLog::query()->count())->toBe(0);
});

it('updates every enabled validated state record using the received MQTT topic', function () {
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
            'value_map' => [
                'ON' => 1,
            ],
        ],
        'qos' => 1,
        'is_enabled' => true,
    ]);

    $secondTopic->markAsValidated();

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('test/shared/state', 'ON');

    $firstTopic->refresh();
    $secondTopic->refresh();

    expect($handledTopics)->toBe(2)
        ->and($firstTopic->last_value)->toBe('ON')
        ->and($secondTopic->last_value)->toBe(1)
        ->and($firstTopic->last_received_at)->not->toBeNull()
        ->and($secondTopic->last_received_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('type', 'state')->count())->toBe(2);
});

it('records a new state activity when the normalized value changes', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/change',
        'payload_mapping' => [
            'format' => 'raw',
            'value_map' => [
                'ON' => true,
                'OFF' => false,
            ],
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    $mqttTopic->markAsValidated();

    $handler = $this->app->make(MqttMessageHandler::class);

    $handler->handle('test/device/change', 'ON');
    $handler->handle('test/device/change', 'OFF');

    $activities = ActivityLog::query()
        ->where('type', 'state')
        ->oldest('id')
        ->get();

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->data['normalized_value'])->toBeTrue()
        ->and($activities[1]->data['normalized_value'])->toBeFalse()
        ->and($mqttTopic->fresh()->last_value)->toBeFalse()
        ->and($this->logicalDevice->fresh()->last_value)->toBeFalse();
});

it('does not duplicate state activity when the normalized value is unchanged', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/repeated',
        'payload_mapping' => [
            'format' => 'json',
            'value_path' => 'state',
            'value_map' => [
                'ON' => true,
                'OFF' => false,
            ],
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    $mqttTopic->markAsValidated();

    $handler = $this->app->make(MqttMessageHandler::class);

    $handler->handle(
        'test/device/repeated',
        '{"state":"ON","sequence":1}',
    );

    $secondPayload = '{"state":"ON","sequence":2}';

    $handler->handle(
        'test/device/repeated',
        $secondPayload,
    );

    $mqttTopic->refresh();

    expect(ActivityLog::query()->where('type', 'state')->count())->toBe(1)
        ->and($mqttTopic->last_payload)->toBe($secondPayload)
        ->and($mqttTopic->last_value)->toBeTrue()
        ->and($this->logicalDevice->fresh()->last_value)->toBeTrue()
        ->and($mqttTopic->last_received_at)->not->toBeNull();
});

it('does not duplicate numeric JSON state activity when only telemetry metadata changes', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'tele/test-device/SENSOR',
        'payload_mapping' => [
            'format' => 'json',
            'value_path' => 'MS01.Humidity',
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    $mqttTopic->markAsValidated();

    $handler = $this->app->make(MqttMessageHandler::class);

    $handler->handle(
        'tele/test-device/SENSOR',
        '{"Time":"2026-08-24T08:23:32","MS01":{"Humidity":22.2,"Raw":14670}}',
    );

    $secondPayload = '{"Time":"2026-08-24T08:23:42","MS01":{"Humidity":22.2,"Raw":14677}}';

    $handler->handle(
        'tele/test-device/SENSOR',
        $secondPayload,
    );

    $mqttTopic->refresh();

    expect(ActivityLog::query()->where('type', 'state')->count())->toBe(1)
        ->and($mqttTopic->last_payload)->toBe($secondPayload)
        ->and($mqttTopic->last_value)->toBe(22.2)
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($this->logicalDevice->fresh()->last_value)->toBe(22.2);
});

it('backfills the logical device value without duplicating unchanged topic activity', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/backfill',
        'payload_mapping' => [
            'format' => 'json',
            'value_path' => 'value',
        ],
        'qos' => 0,
        'is_enabled' => true,
        'last_payload' => '{"value":22.2}',
        'last_value' => 22.2,
        'last_received_at' => now()->subSecond(),
    ]);

    $mqttTopic->markAsValidated();

    $this->app
        ->make(MqttMessageHandler::class)
        ->handle(
            'test/device/backfill',
            '{"value":22.2}',
        );

    expect(ActivityLog::query()->where('type', 'state')->count())->toBe(0)
        ->and($this->logicalDevice->fresh()->last_value)->toBe(22.2);
});

it('ignores MQTT messages for unknown topics', function () {
    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle('unknown/device/state', 'ON');

    expect($handledTopics)->toBe(0)
        ->and(ActivityLog::query()->count())->toBe(0);
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

    $mqttTopic->markAsValidated();

    $handledTopics = app(MqttMessageHandler::class)->handle(
        'cmnd/test-controller/POWER1',
        'ON',
    );

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(0)
        ->and($mqttTopic->last_payload)->toBeNull()
        ->and($mqttTopic->last_value)->toBeNull()
        ->and($mqttTopic->last_received_at)->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull()
        ->and(ActivityLog::query()->count())->toBe(0);
});

it('ignores unvalidated MQTT state topics', function () {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $this->logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'test/device/unvalidated',
        'payload_mapping' => [
            'format' => 'raw',
        ],
        'qos' => 0,
        'is_enabled' => true,
    ]);

    $handledTopics = $this->app
        ->make(MqttMessageHandler::class)
        ->handle(
            'test/device/unvalidated',
            'ON',
        );

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(0)
        ->and($mqttTopic->validated_at)->toBeNull()
        ->and($mqttTopic->last_payload)->toBeNull()
        ->and($mqttTopic->last_value)->toBeNull()
        ->and($mqttTopic->last_received_at)->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull()
        ->and(ActivityLog::query()->count())->toBe(0);
});
