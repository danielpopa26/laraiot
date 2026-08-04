<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Contracts;

interface MqttPublisher
{
    /**
     * Publish a payload to an MQTT topic.
     */
    public function publish(
        string $topic,
        string $payload,
        int $qos = 0,
        bool $retain = false,
        ?string $clientId = null,
    ): void;
}
