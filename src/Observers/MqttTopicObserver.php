<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Observers;

use Danpopa\LaraIoT\Models\MqttTopic;

final class MqttTopicObserver
{
    /**
     * Changes to these fields make a previous MQTT
     * topic validation no longer trustworthy.
     *
     * @var list<string>
     */
    private const array VALIDATION_SENSITIVE_FIELDS = [
        'logical_device_id',
        'purpose',
        'topic',
        'payload_mapping',
        'qos',
        'retain',
    ];

    public function updating(
        MqttTopic $mqttTopic,
    ): void {
        if (
            ! $mqttTopic->isDirty(
                self::VALIDATION_SENSITIVE_FIELDS,
            )
        ) {
            return;
        }

        /*
         * The configuration has changed, therefore
         * any previous validation is no longer valid.
         *
         * We only change the in-memory attribute here.
         * It will be persisted by the update which is
         * already in progress.
         */
        $mqttTopic->validated_at = null;
    }

    public function updated(
        MqttTopic $mqttTopic,
    ): void {
        if (
            ! $mqttTopic->wasChanged(
                self::VALIDATION_SENSITIVE_FIELDS,
            )
        ) {
            return;
        }

        /*
         * If this topic was a state topic before the
         * change, any command topics which were
         * validated against that state configuration
         * must also be invalidated.
         */
        $originalPurpose = $mqttTopic->getRawOriginal(
            'purpose',
        );

        if ($originalPurpose !== 'state') {
            return;
        }

        $originalLogicalDeviceId =
            $mqttTopic->getRawOriginal(
                'logical_device_id',
            );

        if ($originalLogicalDeviceId === null) {
            return;
        }

        MqttTopic::query()
            ->where(
                'logical_device_id',
                $originalLogicalDeviceId,
            )
            ->where('purpose', 'command')
            ->whereNotNull('validated_at')
            ->update([
                'validated_at' => null,
            ]);
    }
}
