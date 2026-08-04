<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Contracts\MqttPublisher;
use Danpopa\LaraIoT\Exceptions\InvalidMqttCommandException;
use Danpopa\LaraIoT\Models\MqttTopic;
use JsonException;

final class MqttCommandService
{
    public function __construct(
        private readonly MqttPublisher $publisher,
    ) {}

    /**
     * @param  string|array<array-key, mixed>  $payload
     *
     * @throws InvalidMqttCommandException
     * @throws JsonException
     */
    public function send(
        MqttTopic $mqttTopic,
        string|array $payload,
        ?string $clientId = null,
    ): void {
        if ($mqttTopic->purpose !== 'command') {
            throw new InvalidMqttCommandException(
                'MQTT commands can only be sent through command topics.',
            );
        }

        if (! $mqttTopic->is_enabled) {
            throw new InvalidMqttCommandException(
                'MQTT commands cannot be sent through a disabled topic.',
            );
        }

        $this->publisher->publish(
            topic: $mqttTopic->topic,
            payload: $this->preparePayload($payload),
            qos: $mqttTopic->qos,
            retain: $mqttTopic->retain,
            clientId: $clientId,
        );
    }

    /**
     * @param  string|array<array-key, mixed>  $payload
     *
     * @throws JsonException
     */
    private function preparePayload(string|array $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
