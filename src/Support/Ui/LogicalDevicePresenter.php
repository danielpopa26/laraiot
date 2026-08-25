<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Ui;

use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

final class LogicalDevicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(
        LogicalDevice $logicalDevice,
    ): array {
        $logicalDevice->loadMissing([
            'physicalDevice:id,name,identifier,is_enabled',
            'deviceType:id,name,identifier',
            'mqttTopics' => function (Relation $relation): void {
                $relation->getQuery()
                    ->orderBy('purpose')
                    ->orderBy('id');
            },
        ]);

        /** @var Collection<int, MqttTopic> $topics */
        $topics = $logicalDevice->mqttTopics;

        $stateTopic = $this->preferredTopic(
            $topics->where('purpose', 'state'),
        );

        $commandTopic = $this->preferredTopic(
            $topics->where('purpose', 'command'),
        );

        $currentState = $this->binaryState(
            $logicalDevice->last_value,
        );

        $configuration = $this->configuration(
            logicalDevice: $logicalDevice,
            topics: $topics,
            stateTopic: $stateTopic,
            commandTopic: $commandTopic,
            currentState: $currentState,
        );

        return [
            'id' => $logicalDevice->getKey(),
            'name' => $logicalDevice->name,
            'identifier' => $logicalDevice->identifier,
            'unit' => $logicalDevice->unit,
            'last_value' => $logicalDevice->last_value,
            'is_enabled' => $logicalDevice->is_enabled,
            'device_type' => $logicalDevice->deviceType
                ? [
                    'id' => $logicalDevice->deviceType->getKey(),
                    'name' => $logicalDevice->deviceType->name,
                    'identifier' => $logicalDevice->deviceType->identifier,
                ]
                : null,
            'physical_device' => $logicalDevice->physicalDevice
                ? [
                    'id' => $logicalDevice->physicalDevice->getKey(),
                    'name' => $logicalDevice->physicalDevice->name,
                    'identifier' => $logicalDevice->physicalDevice->identifier,
                    'is_enabled' => $logicalDevice->physicalDevice->is_enabled,
                ]
                : null,
            'topics' => [
                'total' => $topics->count(),
                'state' => $topics
                    ->where('purpose', 'state')
                    ->count(),
                'command' => $topics
                    ->where('purpose', 'command')
                    ->count(),
                'validated' => $topics
                    ->filter(
                        fn (MqttTopic $topic): bool => $topic
                            ->isValidated(),
                    )
                    ->count(),
            ],
            'state_topic' => $this->topicSummary(
                $stateTopic,
            ),
            'command_topic' => $this->topicSummary(
                $commandTopic,
            ),
            'configuration' => $configuration,
            'control' => [
                'available' => $configuration['status']
                    === 'ready',
                'command_topic_id' => $commandTopic?->getKey(),
                'current_state' => $currentState,
                'reason' => $configuration['status']
                    === 'ready'
                    ? null
                    : $configuration['message'],
                'confirmation_timeout' => max(
                    1,
                    (int) config(
                        'laraiot.mqtt.testing.command_timeout',
                        12,
                    ),
                ),
            ],
        ];
    }

    /**
     * @param  Collection<int, MqttTopic>  $topics
     */
    private function preferredTopic(
        Collection $topics,
    ): ?MqttTopic {
        return $topics->first(
            fn (MqttTopic $topic): bool => $topic
                ->isUsable(),
        ) ?? $topics->first(
            fn (MqttTopic $topic): bool => $topic
                ->is_enabled,
        ) ?? $topics->first();
    }

    /**
     * @param  Collection<int, MqttTopic>  $topics
     * @return array{
     *     status: string,
     *     label: string,
     *     message: string,
     *     tone: string
     * }
     */
    private function configuration(
        LogicalDevice $logicalDevice,
        Collection $topics,
        ?MqttTopic $stateTopic,
        ?MqttTopic $commandTopic,
        ?string $currentState,
    ): array {
        if (
            $logicalDevice->physicalDevice !== null
            && ! $logicalDevice->physicalDevice->is_enabled
        ) {
            return $this->configurationState(
                'disabled',
                'Physical device disabled',
                'The physical device is disabled. Monitoring and control are unavailable.',
                'neutral',
            );
        }

        if (! $logicalDevice->is_enabled) {
            return $this->configurationState(
                'disabled',
                'Logical device disabled',
                'The logical device is disabled. Monitoring and control are unavailable.',
                'neutral',
            );
        }

        if ($topics->isEmpty()) {
            return $this->configurationState(
                'no_topics',
                'No MQTT topics',
                'No MQTT topics are configured for this logical device.',
                'warning',
            );
        }

        if ($stateTopic === null) {
            return $this->configurationState(
                'state_topic_missing',
                'State topic missing',
                'No MQTT state topic is configured.',
                'warning',
            );
        }

        if (! $stateTopic->is_enabled) {
            return $this->configurationState(
                'state_topic_disabled',
                'State topic disabled',
                'The MQTT state topic is disabled.',
                'warning',
            );
        }

        if (! $stateTopic->isValidated()) {
            return $this->configurationState(
                'state_topic_unvalidated',
                'State topic not validated',
                'Validate the MQTT state topic before using its values.',
                'warning',
            );
        }

        if ($stateTopic->last_received_at === null) {
            return $this->configurationState(
                'waiting_for_state',
                'Waiting for state',
                'Waiting for the first MQTT value from the validated state topic.',
                'info',
            );
        }

        if ($commandTopic === null) {
            return $this->configurationState(
                'read_only',
                'Monitoring ready',
                'State monitoring is ready. No command topic is configured.',
                'info',
            );
        }

        if (! $commandTopic->is_enabled) {
            return $this->configurationState(
                'command_topic_disabled',
                'Command topic disabled',
                'State monitoring is available, but the command topic is disabled.',
                'warning',
            );
        }

        if (! $commandTopic->isValidated()) {
            return $this->configurationState(
                'command_topic_unvalidated',
                'Command topic not validated',
                'State monitoring is available, but control requires a validated command topic.',
                'warning',
            );
        }

        if ($currentState === null) {
            return $this->configurationState(
                'state_unknown',
                'State cannot be controlled',
                'The current state must be normalized to "on" or "off" before control is enabled.',
                'warning',
            );
        }

        return $this->configurationState(
            'ready',
            'Ready',
            'State monitoring and MQTT command control are ready.',
            'success',
        );
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     message: string,
     *     tone: string
     * }
     */
    private function configurationState(
        string $status,
        string $label,
        string $message,
        string $tone,
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'message' => $message,
            'tone' => $tone,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function topicSummary(
        ?MqttTopic $mqttTopic,
    ): ?array {
        if ($mqttTopic === null) {
            return null;
        }

        return [
            'id' => $mqttTopic->getKey(),
            'topic' => $mqttTopic->topic,
            'purpose' => $mqttTopic->purpose,
            'is_enabled' => $mqttTopic->is_enabled,
            'is_validated' => $mqttTopic->isValidated(),
            'is_usable' => $mqttTopic->isUsable(),
            'last_received_at' => $mqttTopic->last_received_at,
            'last_error' => $mqttTopic->last_error,
        ];
    }

    private function binaryState(
        mixed $value,
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $state = strtolower(trim($value));

        return in_array($state, ['on', 'off'], true)
            ? $state
            : null;
    }
}
