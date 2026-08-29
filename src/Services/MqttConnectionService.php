<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use Danpopa\LaraIoT\Exceptions\MqttConnectionException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;

final class MqttConnectionService
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly MqttClientFactory $clientFactory,
    ) {}

    public function connect(?string $clientId = null): MqttClient
    {
        $host = trim((string) $this->config->get(
            'laraiot.mqtt.host',
            '127.0.0.1',
        ));

        $port = (int) $this->config->get(
            'laraiot.mqtt.port',
            1883,
        );

        $cleanSession = (bool) $this->config->get(
            'laraiot.mqtt.clean_session',
            true,
        );

        $resolvedClientId = $this->resolveClientId($clientId);

        $this->validateConfiguration(
            $host,
            $port,
            $resolvedClientId,
            $cleanSession,
        );

        $keepAlive = max(
            1,
            (int) $this->config->get(
                'laraiot.mqtt.keep_alive',
                10,
            ),
        );

        $connectionTimeout = max(
            1,
            (int) $this->config->get(
                'laraiot.mqtt.connection_timeout',
                5,
            ),
        );

        try {
            $client = $this->clientFactory->create(
                $host,
                $port,
                $resolvedClientId,
            );

            $settings = (new ConnectionSettings)
                ->setUsername(
                    $this->nullableString(
                        $this->config->get(
                            'laraiot.mqtt.username',
                        ),
                    ),
                )
                ->setPassword(
                    $this->nullableString(
                        $this->config->get(
                            'laraiot.mqtt.password',
                        ),
                    ),
                )
                ->setConnectTimeout($connectionTimeout)
                ->setKeepAliveInterval($keepAlive)
                ->setUseTls(
                    (bool) $this->config->get(
                        'laraiot.mqtt.tls.enabled',
                        false,
                    ),
                )
                ->setTlsVerifyPeer(
                    (bool) $this->config->get(
                        'laraiot.mqtt.tls.verify_peer',
                        true,
                    ),
                )
                ->setTlsVerifyPeerName(
                    (bool) $this->config->get(
                        'laraiot.mqtt.tls.verify_peer_name',
                        true,
                    ),
                )
                ->setTlsSelfSignedAllowed(
                    (bool) $this->config->get(
                        'laraiot.mqtt.tls.allow_self_signed',
                        false,
                    ),
                );

            $client->connect(
                $settings,
                $cleanSession,
            );

            return $client;
        } catch (
            MqttClientException|InvalidArgumentException $exception
        ) {
            throw new MqttConnectionException(
                message: sprintf(
                    'Unable to connect to the MQTT broker at %s:%d.',
                    $host,
                    $port,
                ),
                previous: $exception,
            );
        }
    }

    private function resolveClientId(?string $clientId): ?string
    {
        $clientId ??= $this->config->get(
            'laraiot.mqtt.client_id',
        );

        if (! is_string($clientId)) {
            return null;
        }

        $clientId = trim($clientId);

        return $clientId !== ''
            ? $clientId
            : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function validateConfiguration(
        string $host,
        int $port,
        ?string $clientId,
        bool $cleanSession,
    ): void {
        if ($host === '') {
            throw new MqttConnectionException(
                'The MQTT broker host is not configured.',
            );
        }

        if ($port < 1 || $port > 65535) {
            throw new MqttConnectionException(
                'The MQTT broker port must be between 1 and 65535.',
            );
        }

        if (! $cleanSession && $clientId === null) {
            throw new MqttConnectionException(
                'A client ID is required when clean session is disabled.',
            );
        }
    }
}
