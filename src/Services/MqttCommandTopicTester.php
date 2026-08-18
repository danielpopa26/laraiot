<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Exceptions\MqttTopicTestTimeoutException;
use Danpopa\LaraIoT\Models\MqttTopic;
use InvalidArgumentException;
use JsonException;
use PhpMqtt\Client\Contracts\MqttClient;
use Throwable;

final class MqttCommandTopicTester
{
    public function __construct(
        private readonly MqttConnectionService $connectionService,
        private readonly MqttPayloadProcessor $payloadProcessor,
    ) {}

    /**
     * @param  array{
     *     on: string|array<array-key, mixed>,
     *     off: string|array<array-key, mixed>
     * }  $commandPayloads
     * @return array{
     *     on: array<string, mixed>,
     *     off: array<string, mixed>
     * }
     *
     * @throws JsonException
     */
    public function test(
        MqttTopic $stateTopic,
        string $commandTopic,
        array $commandPayloads,
        int $qos = 0,
        bool $retain = false,
    ): array {
        $this->validateStateTopic($stateTopic);

        $commandTopic = trim($commandTopic);

        $this->validateCommandTopic($commandTopic);
        $this->validateQos($qos);
        $this->validateCommandPayloads($commandPayloads);

        $onResult = $this->testAction(
            stateTopic: $stateTopic,
            commandTopic: $commandTopic,
            commandPayload: $this->preparePayload(
                $commandPayloads['on'],
            ),
            expectedState: 'on',
            qos: $qos,
            retain: $retain,
        );

        $offResult = $this->testAction(
            stateTopic: $stateTopic,
            commandTopic: $commandTopic,
            commandPayload: $this->preparePayload(
                $commandPayloads['off'],
            ),
            expectedState: 'off',
            qos: $qos,
            retain: $retain,
        );

        return [
            'on' => $onResult,
            'off' => $offResult,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function testAction(
        MqttTopic $stateTopic,
        string $commandTopic,
        string $commandPayload,
        string $expectedState,
        int $qos,
        bool $retain,
    ): array {
        $timeout = max(
            1,
            (int) config(
                'laraiot.mqtt.testing.timeout',
                10,
            ),
        );

        $client = $this->connectionService->connect(
            $this->makeClientId($expectedState),
        );

        $confirmed = null;
        $lastObserved = null;

        try {
            $client->subscribe(
                $stateTopic->topic,
                function (
                    string $receivedTopic,
                    string $message,
                    bool $retained,
                    array $matchedWildcards,
                ) use (
                    &$confirmed,
                    &$lastObserved,
                    $client,
                    $stateTopic,
                    $expectedState,
                    $commandPayload,
                ): void {
                    /*
                     * Ignore a retained state delivered immediately
                     * after subscribing. We need a state produced
                     * after the test command.
                     */
                    if ($retained) {
                        return;
                    }

                    $processed = $this->payloadProcessor->process(
                        $message,
                        $stateTopic->payload_mapping ?? [],
                    );

                    $lastObserved = [
                        'topic' => $receivedTopic,
                        'raw_payload' => $message,
                        'normalized_value' => $processed['normalized_value'],
                    ];

                    if (
                        $processed['normalized_value']
                        !== $expectedState
                    ) {
                        return;
                    }

                    $confirmed = [
                        'command_payload' => $commandPayload,
                        'state_topic' => $receivedTopic,
                        'state_raw_payload' => $message,
                        'normalized_value' => $processed['normalized_value'],
                    ];

                    $client->interrupt();
                },
                $stateTopic->qos,
            );

            /*
             * Subscribe first, then publish. This prevents losing
             * a very fast state response from the equipment.
             */
            $client->publish(
                $commandTopic,
                $commandPayload,
                $qos,
                $retain,
            );

            $client->registerLoopEventHandler(
                function (
                    MqttClient $mqtt,
                    float $elapsedTime,
                ) use ($timeout): void {
                    if ($elapsedTime >= $timeout) {
                        $mqtt->interrupt();
                    }
                },
            );

            $client->loop(true);
        } finally {
            $this->disconnectSafely($client);
        }

        if ($confirmed === null) {
            $message = sprintf(
                'The MQTT command "%s" was not confirmed on state topic "%s" within %d seconds.',
                $expectedState,
                $stateTopic->topic,
                $timeout,
            );

            if ($lastObserved !== null) {
                $message .= sprintf(
                    ' Last normalized state: %s.',
                    $this->printableValue(
                        $lastObserved['normalized_value'],
                    ),
                );
            }

            throw new MqttTopicTestTimeoutException(
                $message,
            );
        }

        return $confirmed;
    }

    private function validateStateTopic(
        MqttTopic $stateTopic,
    ): void {
        if (! $stateTopic->exists) {
            throw new InvalidArgumentException(
                'The state MQTT topic must be saved before testing a command topic.',
            );
        }

        if ($stateTopic->purpose !== 'state') {
            throw new InvalidArgumentException(
                'Command topic testing requires a state MQTT topic.',
            );
        }

        if (! $stateTopic->is_enabled) {
            throw new InvalidArgumentException(
                'Command topic testing requires an enabled state MQTT topic.',
            );
        }

        if (
            $stateTopic->payload_mapping === null
            || $stateTopic->payload_mapping === []
        ) {
            throw new InvalidArgumentException(
                'The state MQTT topic payload mapping is not configured.',
            );
        }
    }

    private function validateCommandTopic(
        string $topic,
    ): void {
        if ($topic === '') {
            throw new InvalidArgumentException(
                'The MQTT command topic must not be empty.',
            );
        }

        if (
            str_contains($topic, '+')
            || str_contains($topic, '#')
        ) {
            throw new InvalidArgumentException(
                'The MQTT command topic must not contain wildcard characters.',
            );
        }
    }

    private function validateQos(int $qos): void
    {
        if (! in_array($qos, [0, 1, 2], true)) {
            throw new InvalidArgumentException(
                'The MQTT QoS level must be 0, 1, or 2.',
            );
        }
    }

    /**
     * @param  array{
     *     on: string|array<array-key, mixed>,
     *     off: string|array<array-key, mixed>
     * }  $payloads
     */
    private function validateCommandPayloads(
        array $payloads,
    ): void {
        foreach (['on', 'off'] as $action) {
            if (! array_key_exists($action, $payloads)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The MQTT payload for command "%s" is not configured.',
                        $action,
                    ),
                );
            }

            $payload = $payloads[$action];

            if (
                is_string($payload)
                && $payload === ''
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The MQTT payload for command "%s" must not be empty.',
                        $action,
                    ),
                );
            }
        }
    }

    /**
     * @param  string|array<array-key, mixed>  $payload
     *
     * @throws JsonException
     */
    private function preparePayload(
        string|array $payload,
    ): string {
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
        );
    }

    private function makeClientId(
        string $action,
    ): string {
        $prefix = config(
            'laraiot.mqtt.testing.client_id_prefix',
            'laraiot-test',
        );

        if (
            ! is_string($prefix)
            || trim($prefix) === ''
        ) {
            $prefix = 'laraiot-test';
        }

        return sprintf(
            '%s-command-%s-%s',
            trim($prefix),
            $action,
            bin2hex(random_bytes(6)),
        );
    }

    private function disconnectSafely(
        MqttClient $client,
    ): void {
        try {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        } catch (Throwable) {
            // The test result is more useful than a disconnect error.
        }
    }

    private function printableValue(
        mixed $value,
    ): string {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value === true => 'true',
            $value === false => 'false',
            $value === null => 'null',
            default => get_debug_type($value),
        };
    }
}
