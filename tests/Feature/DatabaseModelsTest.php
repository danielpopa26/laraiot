<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('runs the LaraIoT package migrations', function () {
    expect(Schema::hasTable('laraiot_device_types'))->toBeTrue()
        ->and(Schema::hasTable('laraiot_physical_devices'))->toBeTrue()
        ->and(Schema::hasTable('laraiot_logical_devices'))->toBeTrue()
        ->and(Schema::hasTable('laraiot_mqtt_topics'))->toBeTrue()
        ->and(Schema::hasTable('laraiot_activity_logs'))->toBeTrue()
        ->and(Schema::hasTable('laraiot_settings'))->toBeTrue()
        ->and(Schema::hasColumns('laraiot_mqtt_topics', [
            'logical_device_id',
            'last_payload',
            'last_value',
            'last_received_at',
            'last_error',
        ]))->toBeTrue()
        ->and(
            Schema::hasColumn(
                'laraiot_mqtt_topics',
                'physical_device_id',
            ),
        )->toBeFalse()
        ->and(Schema::hasColumns('laraiot_activity_logs', [
            'actor_type',
            'actor_id',
        ]))->toBeTrue();
});

it('persists model casts and resolves relationships', function () {
    $deviceType = DeviceType::query()->create([
        'identifier' => 'relay',
        'name' => 'Relay',
        'is_enabled' => true,
    ]);

    $physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'device-01',
        'name' => 'Physical device 01',
        'ip_address' => '192.168.2.100',
        'is_enabled' => true,
    ]);

    $logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'relay-01',
        'name' => 'Relay 01',
        'last_value' => ['state' => 'on'],
        'is_enabled' => true,
    ]);

    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => 'laraiot/device-01/relay-01/state',
        'payload_mapping' => ['path' => 'state'],
        'qos' => 1,
        'retain' => false,
        'is_enabled' => true,
        'last_value' => ['state' => 'on'],
    ]);

    $activityLog = ActivityLog::query()->create([
        'type' => 'state_changed',
        'logical_device_id' => $logicalDevice->getKey(),
        'mqtt_topic_id' => $mqttTopic->getKey(),
        'actor_type' => PhysicalDevice::class,
        'actor_id' => $physicalDevice->getKey(),
        'title' => 'Relay state changed',
        'data' => ['state' => 'on'],
        'happened_at' => now(),
    ]);

    $logicalDevice->refresh();
    $mqttTopic->refresh();
    $activityLog->refresh();

    expect($logicalDevice->getAttribute('last_value'))
        ->toBe(['state' => 'on'])
        ->and($mqttTopic->getAttribute('payload_mapping'))
        ->toBe(['path' => 'state'])
        ->and($mqttTopic->getAttribute('qos'))->toBe(1)
        ->and($activityLog->getAttribute('data'))
        ->toBe(['state' => 'on'])
        ->and(
            $physicalDevice->logicalDevices()
                ->firstOrFail()
                ->is($logicalDevice),
        )->toBeTrue()
        ->and(
            $deviceType->logicalDevices()
                ->firstOrFail()
                ->is($logicalDevice),
        )->toBeTrue()
        ->and(
            $logicalDevice->mqttTopics()
                ->firstOrFail()
                ->is($mqttTopic),
        )->toBeTrue()
        ->and(
            $mqttTopic->logicalDevice()
                ->firstOrFail()
                ->is($logicalDevice),
        )->toBeTrue()
        ->and(
            $mqttTopic->activityLogs()
                ->firstOrFail()
                ->is($activityLog),
        )->toBeTrue()
        ->and(
            $activityLog->actor()
                ->firstOrFail()
                ->is($physicalDevice),
        )->toBeTrue();
});

it('deletes MQTT topics with their logical device', function () {
    $deviceType = DeviceType::query()->create([
        'identifier' => 'cascade-relay',
        'name' => 'Cascade relay',
        'is_enabled' => true,
    ]);

    $physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'cascade-controller',
        'name' => 'Cascade controller',
        'is_enabled' => true,
    ]);

    $logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'cascade-logical-relay',
        'name' => 'Cascade logical relay',
        'is_enabled' => true,
    ]);

    $mqttTopic = $logicalDevice->mqttTopics()->create([
        'purpose' => 'state',
        'topic' => 'test/cascade/state',
        'is_enabled' => true,
    ]);

    $logicalDevice->delete();

    expect(
        MqttTopic::query()
            ->whereKey($mqttTopic->getKey())
            ->exists(),
    )->toBeFalse();
});

it('returns the current singleton application settings', function () {
    $settings = ApplicationSetting::current();
    $sameSettings = ApplicationSetting::current();

    expect($sameSettings->is($settings))->toBeTrue()
        ->and($settings->getKey())
        ->toBe(ApplicationSetting::SINGLETON_ID)
        ->and(ApplicationSetting::query()->count())->toBe(1)
        ->and($settings->getAttribute('application_mode'))
        ->toBe(ApplicationSetting::MODE_POLLING)
        ->and($settings->getAttribute('polling_interval'))->toBe(10)
        ->and($settings->getAttribute('timezone'))->toBe('UTC')
        ->and($settings->getAttribute('date_format'))->toBe('d M Y')
        ->and($settings->getAttribute('time_format'))->toBe('H:i:s')
        ->and(ApplicationSetting::defaults())->toBe([
            'application_mode' => ApplicationSetting::MODE_POLLING,
            'polling_interval' => 10,
            'timezone' => 'UTC',
            'date_format' => 'd M Y',
            'time_format' => 'H:i:s',
        ]);
});

it('detects whether the current application settings use websocket mode', function () {
    $settings = ApplicationSetting::current();

    expect($settings->usesWebsocket())->toBeFalse();

    $settings->update([
        'application_mode' => ApplicationSetting::MODE_WEBSOCKET,
    ]);

    $settings->refresh();

    expect($settings->usesWebsocket())->toBeTrue()
        ->and($settings->getAttribute('application_mode'))
        ->toBe(ApplicationSetting::MODE_WEBSOCKET)
        ->and(ApplicationSetting::query()->count())->toBe(1);
});
