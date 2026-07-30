<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Exceptions\InvalidMqttPayloadException;
use Danpopa\LaraIoT\Models\MqttTopic;
use Illuminate\Support\Carbon;

final class MqttMessageHandler
{
    public function __construct(
        private readonly MqttPayloadProcessor $payloadProcessor,
    ) {}

    public function handle(string $topic, string $payload): int
    {
        $mqttTopics = MqttTopic::query()
            ->where('topic', $topic)
            ->where('is_enabled', true)
            ->get();

        $receivedAt = Carbon::now();

        foreach ($mqttTopics as $mqttTopic) {
            $this->handleForTopic(
                $mqttTopic,
                $payload,
                $receivedAt,
            );
        }

        return $mqttTopics->count();
    }

    private function handleForTopic(
        MqttTopic $mqttTopic,
        string $payload,
        Carbon $receivedAt,
    ): void {
        try {
            $processedValue = $this->payloadProcessor->process(
                $payload,
                $mqttTopic->payload_mapping ?? [],
            );

            $mqttTopic->update([
                'last_payload' => $payload,
                'last_value' => $processedValue,
                'last_received_at' => $receivedAt,
                'last_error' => null,
            ]);
        } catch (InvalidMqttPayloadException $exception) {
            $mqttTopic->update([
                'last_payload' => $payload,
                'last_received_at' => $receivedAt,
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
