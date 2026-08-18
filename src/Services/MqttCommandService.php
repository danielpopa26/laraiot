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
     * @throws InvalidMqttCommandException
     * @throws JsonException
     */
    public function send(
        MqttTopic $mqttTopic,
        string $command,
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

        $command = strtolower(trim($command));

        if ($command === '') {
            throw new InvalidMqttCommandException(
                'The MQTT command must not be empty.',
            );
        }

        $payload = $this->resolvePayload(
            $mqttTopic,
            $command,
        );

        $this->publisher->publish(
            topic: $mqttTopic->topic,
            payload: $payload,
            qos: $mqttTopic->qos,
            retain: $mqttTopic->retain,
            clientId: $clientId,
        );
    }

    /**
     * @throws InvalidMqttCommandException
     * @throws JsonException
     */
    private function resolvePayload(
        MqttTopic $mqttTopic,
        string $command,
    ): string {
        $mapping = $mqttTopic->payload_mapping ?? [];

        if (! array_key_exists($command, $mapping)) {
            throw new InvalidMqttCommandException(
                sprintf(
                    'The MQTT command "%s" is not configured for this topic.',
                    $command,
                ),
            );
        }

        $payload = $mapping[$command];

        if (is_string($payload)) {
            return $payload;
        }

        if (is_array($payload)) {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            );
        }

        throw new InvalidMqttCommandException(
            sprintf(
                'The MQTT payload configured for command "%s" must be a string or an array.',
                $command,
            ),
        );
    }
}
