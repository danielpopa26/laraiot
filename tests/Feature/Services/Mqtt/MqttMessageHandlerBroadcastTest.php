<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Events\LogicalDeviceStateUpdated;
use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Services\MqttMessageHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $deviceType = DeviceType::query()->create([
        'identifier' => 'broadcast-test-relay',
        'name' => 'Broadcast test relay',
        'is_enabled' => true,
    ]);

    $physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'broadcast-test-controller',
        'name' => 'Broadcast test controller',
    ]);

    $this->logicalDevice = LogicalDevice::query()->create([
        'physical_device_id' => $physicalDevice->getKey(),
        'device_type_id' => $deviceType->getKey(),
        'identifier' => 'broadcast-test-logical-relay',
        'name' => 'Broadcast test logical relay',
        'is_enabled' => true,
    ]);
});

it('does not broadcast state updates in polling mode', function () {
    Event::fake([
        LogicalDeviceStateUpdated::class,
    ]);

    ApplicationSetting::current()->update([
        'application_mode' => ApplicationSetting::MODE_POLLING,
    ]);

    $mqttTopic = createBroadcastStateTopic(
        $this->logicalDevice,
    );

    app(MqttMessageHandler::class)->handle(
        $mqttTopic->topic,
        'ON',
    );

    Event::assertNotDispatched(
        LogicalDeviceStateUpdated::class,
    );
});

it('broadcasts a changed state in websocket mode', function () {
    Event::fake([
        LogicalDeviceStateUpdated::class,
    ]);

    ApplicationSetting::current()->update([
        'application_mode' => ApplicationSetting::MODE_WEBSOCKET,
    ]);

    $mqttTopic = createBroadcastStateTopic(
        $this->logicalDevice,
    );

    app(MqttMessageHandler::class)->handle(
        $mqttTopic->topic,
        'ON',
    );

    Event::assertDispatched(
        LogicalDeviceStateUpdated::class,
        function (
            LogicalDeviceStateUpdated $event,
        ) use ($mqttTopic): bool {
            return $event->logicalDeviceId
                    === (int) $this->logicalDevice->getKey()
                && $event->mqttTopicId
                    === (int) $mqttTopic->getKey()
                && $event->value === true
                && $event->receivedAt !== '';
        },
    );
});

it('records activity independently of the application mode', function (
    string $mode,
) {
    Event::fake([
        LogicalDeviceStateUpdated::class,
    ]);

    ApplicationSetting::current()->update([
        'application_mode' => $mode,
    ]);

    $mqttTopic = createBroadcastStateTopic(
        $this->logicalDevice,
    );

    app(MqttMessageHandler::class)->handle(
        $mqttTopic->topic,
        'ON',
    );

    $activity = ActivityLog::query()
        ->where('mqtt_topic_id', $mqttTopic->getKey())
        ->sole();

    expect($activity->type)->toBe('state')
        ->and($activity->logical_device_id)
        ->toBe($this->logicalDevice->getKey())
        ->and($activity->data['topic'])
        ->toBe($mqttTopic->topic)
        ->and($activity->data['raw_payload'])
        ->toBe('ON')
        ->and($activity->data['normalized_value'])
        ->toBeTrue();
})->with([
    'polling' => ApplicationSetting::MODE_POLLING,
    'websocket' => ApplicationSetting::MODE_WEBSOCKET,
]);

it('does not broadcast when the normalized state has not changed', function () {
    Event::fake([
        LogicalDeviceStateUpdated::class,
    ]);

    ApplicationSetting::current()->update([
        'application_mode' => ApplicationSetting::MODE_WEBSOCKET,
    ]);

    $mqttTopic = createBroadcastStateTopic(
        $this->logicalDevice,
    );

    $mqttTopic->forceFill([
        'last_value' => true,
        'last_received_at' => now()->subSecond(),
    ])->saveQuietly();

    app(MqttMessageHandler::class)->handle(
        $mqttTopic->topic,
        'ON',
    );

    Event::assertNotDispatched(
        LogicalDeviceStateUpdated::class,
    );
});

it('keeps MQTT persistence and activity when broadcasting fails', function () {
    ApplicationSetting::current()->update([
        'application_mode' => ApplicationSetting::MODE_WEBSOCKET,
    ]);

    $mqttTopic = createBroadcastStateTopic(
        $this->logicalDevice,
    );

    Log::spy();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(LogicalDeviceStateUpdated::class))
        ->andThrow(new RuntimeException('Broadcast failed'));

    app()->instance('events', $dispatcher);

    $handledTopics = app(MqttMessageHandler::class)->handle(
        $mqttTopic->topic,
        'ON',
    );

    $mqttTopic->refresh();

    expect($handledTopics)->toBe(1)
        ->and($mqttTopic->last_payload)->toBe('ON')
        ->and($mqttTopic->last_value)->toBeTrue()
        ->and($mqttTopic->last_received_at)->not->toBeNull()
        ->and($mqttTopic->last_error)->toBeNull()
        ->and(
            ActivityLog::query()
                ->where('mqtt_topic_id', $mqttTopic->getKey())
                ->where('type', 'state')
                ->exists(),
        )->toBeTrue();

    Log::shouldHaveReceived('error')
        ->once()
        ->with(
            'Logical device state could not be broadcast.',
            Mockery::type('array'),
        );
});

function createBroadcastStateTopic(
    LogicalDevice $logicalDevice,
): MqttTopic {
    $mqttTopic = MqttTopic::query()->create([
        'logical_device_id' => $logicalDevice->getKey(),
        'purpose' => 'state',
        'topic' => sprintf(
            'test/broadcast/%s/state',
            $logicalDevice->getKey(),
        ),
        'payload_mapping' => [
            'format' => 'raw',
            'value_map' => [
                'ON' => true,
                'OFF' => false,
            ],
        ],
        'qos' => 0,
        'retain' => false,
        'is_enabled' => true,
    ]);

    $mqttTopic->markAsValidated();

    return $mqttTopic;
}
