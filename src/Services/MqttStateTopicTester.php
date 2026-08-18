<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Exceptions\MqttTopicTestTimeoutException;
use InvalidArgumentException;
use PhpMqtt\Client\Contracts\MqttClient;
use Throwable;

final class MqttStateTopicTester
{
    public function __construct(
        private readonly MqttConnectionService $connectionService,
        private readonly MqttPayloadProcessor $payloadProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $payloadConfiguration
     * @return array<string, mixed>
     */
    public function test(
        string $topic,
        int $qos,
        array $payloadConfiguration,
    ): array {
        $topic = trim($topic);

        $this->validateTopic($topic);
        $this->validateQos($qos);

        $timeout = max(
            1,
            (int) config(
                'laraiot.mqtt.testing.timeout',
                10,
            ),
        );

        $clientIdPrefix = config(
            'laraiot.mqtt.testing.client_id_prefix',
            'laraiot-test',
        );

        if (
            ! is_string($clientIdPrefix)
            || trim($clientIdPrefix) === ''
        ) {
            $clientIdPrefix = 'laraiot-test';
        }

        $clientId = sprintf(
            '%s-%s',
            trim($clientIdPrefix),
            bin2hex(random_bytes(6)),
        );

        $client = $this->connectionService->connect(
            $clientId,
        );

        $received = null;

        try {
            $client->subscribe(
                $topic,
                function (
                    string $receivedTopic,
                    string $message,
                    bool $retained,
                    array $matchedWildcards,
                ) use (
                    &$received,
                    $client,
                ): void {
                    $received = [
                        'topic' => $receivedTopic,
                        'raw_payload' => $message,
                        'retained' => $retained,
                    ];

                    $client->interrupt();
                },
                $qos,
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

        if ($received === null) {
            throw new MqttTopicTestTimeoutException(
                sprintf(
                    'No message was received on topic "%s" within %d seconds.',
                    $topic,
                    $timeout,
                ),
            );
        }

        $processed = $this->payloadProcessor->process(
            $received['raw_payload'],
            $payloadConfiguration,
        );

        return [
            ...$received,
            ...$processed,
        ];
    }

    private function validateTopic(string $topic): void
    {
        if ($topic === '') {
            throw new InvalidArgumentException(
                'The MQTT topic must not be empty.',
            );
        }

        if (
            str_contains($topic, '+')
            || str_contains($topic, '#')
        ) {
            throw new InvalidArgumentException(
                'The MQTT topic test requires an exact topic without wildcard characters.',
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

    private function disconnectSafely(
        MqttClient $client,
    ): void {
        try {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        } catch (Throwable) {
            // A test or processing exception is more useful
            // than a disconnect exception.
        }
    }
}
