<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Contracts\MqttPublisher as MqttPublisherContract;
use Danpopa\LaraIoT\Exceptions\MqttPublishException;
use InvalidArgumentException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Throwable;

final class MqttPublisher implements MqttPublisherContract
{
    public function __construct(
        private readonly MqttConnectionService $connectionService,
    ) {}

    public function publish(
        string $topic,
        string $payload,
        int $qos = 0,
        bool $retain = false,
        ?string $clientId = null,
    ): void {
        $this->validateTopic($topic);
        $this->validateQos($qos);

        $client = $this->connectionService->connect($clientId);
        $failure = null;

        try {
            $client->publish(
                $topic,
                $payload,
                $qos,
                $retain,
            );
        } catch (MqttClientException $exception) {
            $failure = new MqttPublishException(
                sprintf(
                    'Unable to publish the MQTT message to topic "%s".',
                    $topic,
                ),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            try {
                $client->disconnect();
            } catch (MqttClientException $exception) {
                if ($failure === null) {
                    $failure = new MqttPublishException(
                        sprintf(
                            'The MQTT message was published to topic "%s", but the client could not disconnect safely.',
                            $topic,
                        ),
                        previous: $exception,
                    );
                }
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    private function validateTopic(string $topic): void
    {
        if (trim($topic) === '') {
            throw new InvalidArgumentException(
                'The MQTT topic must not be empty.',
            );
        }

        if (trim($topic) !== $topic) {
            throw new InvalidArgumentException(
                'The MQTT topic must not contain leading or trailing whitespace.',
            );
        }

        if (str_contains($topic, '+') || str_contains($topic, '#')) {
            throw new InvalidArgumentException(
                'The MQTT publish topic must not contain wildcard characters.',
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
}
