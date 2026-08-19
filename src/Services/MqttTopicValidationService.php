<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Models\MqttTopic;
use InvalidArgumentException;

final class MqttTopicValidationService
{
    public function __construct(
        private readonly MqttStateTopicTester $stateTopicTester,
        private readonly MqttCommandTopicTester $commandTopicTester,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validateStateTopic(
        MqttTopic $stateTopic,
    ): array {
        $this->assertSavedTopic($stateTopic);

        if ($stateTopic->purpose !== 'state') {
            throw new InvalidArgumentException(
                'Only MQTT state topics can be validated as state topics.',
            );
        }

        if (! $stateTopic->is_enabled) {
            throw new InvalidArgumentException(
                'A disabled MQTT state topic cannot be validated.',
            );
        }

        if (
            $stateTopic->payload_mapping === null
            || $stateTopic->payload_mapping === []
        ) {
            throw new InvalidArgumentException(
                'The MQTT state topic payload mapping is not configured.',
            );
        }

        /*
         * A validation attempt always starts from an
         * unvalidated state. If the test fails, the
         * topic remains unusable.
         */
        $this->invalidate($stateTopic);

        $result = $this->stateTopicTester->test(
            topic: $stateTopic->topic,
            qos: $stateTopic->qos,
            payloadConfiguration:
                $stateTopic->payload_mapping,
        );

        $stateTopic->markAsValidated();

        return $result;
    }

    /**
     * @return array{
     *     on: array<string, mixed>,
     *     off: array<string, mixed>
     * }
     */
    public function validateCommandTopic(
        MqttTopic $commandTopic,
        MqttTopic $stateTopic,
    ): array {
        $this->assertSavedTopic($commandTopic);
        $this->assertSavedTopic($stateTopic);

        if ($commandTopic->purpose !== 'command') {
            throw new InvalidArgumentException(
                'Only MQTT command topics can be validated as command topics.',
            );
        }

        if (! $commandTopic->is_enabled) {
            throw new InvalidArgumentException(
                'A disabled MQTT command topic cannot be validated.',
            );
        }

        if ($stateTopic->purpose !== 'state') {
            throw new InvalidArgumentException(
                'Command topic validation requires an MQTT state topic.',
            );
        }

        if (! $stateTopic->isUsable()) {
            throw new InvalidArgumentException(
                'Command topic validation requires an enabled and validated state topic.',
            );
        }

        if (
            $commandTopic->logical_device_id
            !== $stateTopic->logical_device_id
        ) {
            throw new InvalidArgumentException(
                'The command topic and state topic must belong to the same logical device.',
            );
        }

        $payloadMapping =
            $commandTopic->payload_mapping ?? [];

        foreach (['on', 'off'] as $command) {
            if (! array_key_exists(
                $command,
                $payloadMapping,
            )) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The MQTT payload for command "%s" is not configured.',
                        $command,
                    ),
                );
            }
        }

        /*
         * The command topic becomes validated only
         * after both ON and OFF have been confirmed.
         */
        $commandTopic->invalidateValidation();

        $result = $this->commandTopicTester->test(
            stateTopic: $stateTopic,
            commandTopic: $commandTopic->topic,
            commandPayloads: [
                'on' => $payloadMapping['on'],
                'off' => $payloadMapping['off'],
            ],
            qos: $commandTopic->qos,
            retain: $commandTopic->retain,
        );

        $commandTopic->markAsValidated();

        return $result;
    }

    public function invalidate(
        MqttTopic $mqttTopic,
    ): void {
        $mqttTopic->invalidateValidation();

        if ($mqttTopic->purpose !== 'state') {
            return;
        }

        /*
         * Command topics were functionally validated
         * against the state topic. Therefore, when the
         * state validation becomes invalid, command
         * validations for the same logical device must
         * also become invalid.
         */
        MqttTopic::query()
            ->where(
                'logical_device_id',
                $mqttTopic->logical_device_id,
            )
            ->where('purpose', 'command')
            ->whereNotNull('validated_at')
            ->update([
                'validated_at' => null,
            ]);
    }

    private function assertSavedTopic(
        MqttTopic $mqttTopic,
    ): void {
        if (! $mqttTopic->exists) {
            throw new InvalidArgumentException(
                'The MQTT topic must be saved before it can be validated.',
            );
        }
    }
}