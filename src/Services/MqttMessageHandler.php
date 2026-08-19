<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Exceptions\InvalidMqttPayloadException;
use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\MqttTopic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MqttMessageHandler
{
    public function __construct(
        private readonly MqttPayloadProcessor $payloadProcessor,
    ) {}

    public function handle(string $topic, string $payload): int
    {
        $mqttTopics = MqttTopic::query()
            ->with('logicalDevice:id,name,unit')
            ->where('topic', $topic)
            ->where('purpose', 'state')
            ->where('is_enabled', true)
            ->whereNotNull('validated_at')
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
            $processed = $this->payloadProcessor->process(
                $payload,
                $mqttTopic->payload_mapping ?? [],
            );

            $normalizedValue = $processed['normalized_value'];
            $isFirstValue = $mqttTopic->last_received_at === null;
            $stateChanged = $isFirstValue
                || $mqttTopic->last_value !== $normalizedValue;

            $mqttTopic->update([
                'last_payload' => $payload,
                'last_value' => $normalizedValue,
                'last_received_at' => $receivedAt,
                'last_error' => null,
            ]);

            if (! $stateChanged) {
                return;
            }

            $logicalDevice = $mqttTopic->logicalDevice;

            $this->recordActivity([
                'type' => 'state',
                'logical_device_id' => $mqttTopic->logical_device_id,
                'mqtt_topic_id' => $mqttTopic->getKey(),
                'title' => sprintf(
                    '%s state updated',
                    $logicalDevice?->name ?? 'MQTT topic',
                ),
                'description' => sprintf(
                    '%s → %s',
                    $mqttTopic->topic,
                    $this->formatActivityValue(
                        $normalizedValue,
                        $logicalDevice?->unit,
                    ),
                ),
                'data' => [
                    'topic' => $mqttTopic->topic,
                    'raw_payload' => $payload,
                    'normalized_value' => $normalizedValue,
                ],
                'happened_at' => $receivedAt,
            ]);
        } catch (InvalidMqttPayloadException $exception) {
            $mqttTopic->update([
                'last_payload' => $payload,
                'last_received_at' => $receivedAt,
                'last_error' => $exception->getMessage(),
            ]);

            $this->recordActivity([
                'type' => 'error',
                'logical_device_id' => $mqttTopic->logical_device_id,
                'mqtt_topic_id' => $mqttTopic->getKey(),
                'title' => 'MQTT state processing failed',
                'description' => $exception->getMessage(),
                'data' => [
                    'topic' => $mqttTopic->topic,
                    'raw_payload' => $payload,
                    'error' => $exception->getMessage(),
                ],
                'happened_at' => $receivedAt,
            ]);

            Log::warning('Invalid MQTT payload received.', [
                'mqtt_topic_id' => $mqttTopic->getKey(),
                'topic' => $mqttTopic->topic,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordActivity(
        array $attributes,
    ): ?ActivityLog {
        try {
            return ActivityLog::query()->create($attributes);
        } catch (Throwable $exception) {
            Log::error('Activity log could not be recorded.', [
                'type' => $attributes['type'] ?? null,
                'mqtt_topic_id' => $attributes['mqtt_topic_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function formatActivityValue(
        mixed $value,
        ?string $unit,
    ): string {
        $formattedValue = match (true) {
            $value === null || $value === '' => 'No value',
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_array($value) => json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            ) ?: 'Invalid value',
            default => (string) $value,
        };

        if ($unit && is_numeric($value)) {
            return $formattedValue.' '.$unit;
        }

        return $formattedValue;
    }
}
