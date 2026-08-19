<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class LogicalDeviceStateUpdated implements ShouldBroadcastNow
{
    public const string CHANNEL = 'laraiot.devices';

    public const string EVENT_NAME = 'logical-device.state-updated';

    public function __construct(
        public readonly int $logicalDeviceId,
        public readonly int $mqttTopicId,
        public readonly mixed $value,
        public readonly string $receivedAt,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(self::CHANNEL),
        ];
    }

    public function broadcastAs(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'logical_device_id' => $this->logicalDeviceId,
            'mqtt_topic_id' => $this->mqttTopicId,
            'value' => $this->value,
            'received_at' => $this->receivedAt,
        ];
    }
}
