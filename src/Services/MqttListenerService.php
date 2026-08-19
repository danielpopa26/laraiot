<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Closure;
use Danpopa\LaraIoT\Models\MqttTopic;
use PhpMqtt\Client\Contracts\MqttClient;

final class MqttListenerService
{
    private ?MqttClient $client = null;

    /**
     * @var array<string, int>
     */
    private array $subscriptions = [];

    public function __construct(
        private readonly MqttConnectionService $connectionService,
        private readonly MqttMessageHandler $messageHandler,
    ) {}

    public function listen(): void
    {
        $client = $this->connectionService->connect(
            $this->listenerClientId(),
        );

        $this->client = $client;
        $this->subscriptions = [];

        $syncInterval = max(
            1,
            (int) config(
                'laraiot.mqtt.listener.sync_interval',
                5,
            ),
        );

        $lastSyncAt = 0.0;

        try {
            $this->syncSubscriptions($client);

            $client->registerLoopEventHandler(
                function (
                    MqttClient $mqtt,
                    float $elapsedTime,
                ) use (
                    &$lastSyncAt,
                    $syncInterval,
                ): void {
                    if (
                        ($elapsedTime - $lastSyncAt)
                        < $syncInterval
                    ) {
                        return;
                    }

                    $lastSyncAt = $elapsedTime;

                    $this->syncSubscriptions($mqtt);
                },
            );

            $client->loop(true);
        } finally {
            try {
                if ($client->isConnected()) {
                    $client->disconnect();
                }
            } finally {
                $this->client = null;
                $this->subscriptions = [];
            }
        }
    }

    public function stop(): void
    {
        $this->client?->interrupt();
    }

    private function syncSubscriptions(
        MqttClient $client,
    ): void {
        $desiredSubscriptions = $this->desiredSubscriptions();

        foreach (array_keys($this->subscriptions) as $topic) {
            if (array_key_exists($topic, $desiredSubscriptions)) {
                continue;
            }

            $client->unsubscribe($topic);

            unset($this->subscriptions[$topic]);
        }

        foreach ($desiredSubscriptions as $topic => $qos) {
            if (
                ($this->subscriptions[$topic] ?? null)
                === $qos
            ) {
                continue;
            }

            $client->subscribe(
                $topic,
                $this->messageCallback(),
                $qos,
            );

            $this->subscriptions[$topic] = $qos;
        }
    }

    /**
     * @return array<string, int>
     */
    private function desiredSubscriptions(): array
    {
        $mqttTopics = MqttTopic::query()
            ->where('purpose', 'state')
            ->where('is_enabled', true)
            ->whereNotNull('validated_at')
            ->where('topic', '!=', '')
            ->select('topic')
            ->selectRaw('MAX(qos) AS qos')
            ->groupBy('topic')
            ->get();

        $subscriptions = [];

        foreach ($mqttTopics as $mqttTopic) {
            $subscriptions[(string) $mqttTopic->topic]
                = (int) $mqttTopic->qos;
        }

        return $subscriptions;
    }

    private function messageCallback(): Closure
    {
        return function (
            string $topic,
            string $payload,
            bool $_retained,
            array $_matchedWildcards,
        ): void {
            $this->messageHandler->handle(
                $topic,
                $payload,
            );
        };
    }

    private function listenerClientId(): ?string
    {
        $clientId = config(
            'laraiot.mqtt.listener.client_id',
            'laraiot-listener',
        );

        if (! is_string($clientId)) {
            return null;
        }

        $clientId = trim($clientId);

        return $clientId !== ''
            ? $clientId
            : null;
    }
}
