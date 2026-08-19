<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Events\LogicalDeviceStateUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

it('defines the logical device state broadcast contract', function () {
    $event = new LogicalDeviceStateUpdated(
        logicalDeviceId: 12,
        mqttTopicId: 34,
        value: true,
        receivedAt: '2026-08-19T21:35:00+03:00',
    );

    expect($event)
        ->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($event->broadcastAs())
        ->toBe('logical-device.state-updated')
        ->and($event->broadcastWith())
        ->toBe([
            'logical_device_id' => 12,
            'mqtt_topic_id' => 34,
            'value' => true,
            'received_at' => '2026-08-19T21:35:00+03:00',
        ]);

    $channels = $event->broadcastOn();

    expect($channels)
        ->toHaveCount(1)
        ->and($channels[0])
        ->toBeInstanceOf(PrivateChannel::class)
        ->and((string) $channels[0])
        ->toBe('private-laraiot.devices');
});
