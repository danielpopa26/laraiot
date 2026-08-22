<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\MqttTopicRequest;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Services\MqttTopicValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

final class MqttTopicController extends Controller
{
    public function create(
        LogicalDevice $logicalDevice,
    ): Response {
        return Inertia::render(
            'laraiot/devices/logical/mqtt-topics/Create',
            [
                'logicalDevice' => $logicalDevice,
            ],
        );
    }

    public function store(
        MqttTopicRequest $request,
        LogicalDevice $logicalDevice,
    ): RedirectResponse {
        $mqttTopic = $logicalDevice
            ->mqttTopics()
            ->create(
                $this->modelAttributes($request),
            );

        return redirect()
            ->route(
                'laraiot.mqtt-topics.edit',
                [
                    'logicalDevice' => $logicalDevice,
                    'mqttTopic' => $mqttTopic,
                ],
            )
            ->with(
                'laraiot_message',
                'MQTT topic saved. Validate it before use.',
            );
    }

    public function edit(
        LogicalDevice $logicalDevice,
        MqttTopic $mqttTopic,
    ): Response {
        $this->assertBelongsTo(
            $logicalDevice,
            $mqttTopic,
        );

        return Inertia::render(
            'laraiot/devices/logical/mqtt-topics/Edit',
            [
                'logicalDevice' => $logicalDevice,
                'mqttTopic' => $mqttTopic,
                'stateTopics' => $logicalDevice
                    ->mqttTopics()
                    ->where('purpose', 'state')
                    ->orderBy('topic')
                    ->get(),
            ],
        );
    }

    public function update(
        MqttTopicRequest $request,
        LogicalDevice $logicalDevice,
        MqttTopic $mqttTopic,
    ): RedirectResponse {
        $this->assertBelongsTo(
            $logicalDevice,
            $mqttTopic,
        );

        $mqttTopic->update(
            $this->modelAttributes($request),
        );

        return redirect()
            ->route(
                'laraiot.mqtt-topics.edit',
                [
                    'logicalDevice' => $logicalDevice,
                    'mqttTopic' => $mqttTopic,
                ],
            )
            ->with(
                'laraiot_message',
                'MQTT topic updated.',
            );
    }

    public function destroy(
        LogicalDevice $logicalDevice,
        MqttTopic $mqttTopic,
    ): RedirectResponse {
        $this->assertBelongsTo(
            $logicalDevice,
            $mqttTopic,
        );

        $mqttTopic->delete();

        return redirect()
            ->route(
                'laraiot.logical-devices.show',
                $logicalDevice,
            )
            ->with(
                'laraiot_message',
                'MQTT topic deleted.',
            );
    }

    public function validateTopic(
        Request $request,
        LogicalDevice $logicalDevice,
        MqttTopic $mqttTopic,
        MqttTopicValidationService $validationService,
    ): RedirectResponse {
        $this->assertBelongsTo(
            $logicalDevice,
            $mqttTopic,
        );

        try {
            if ($mqttTopic->purpose === 'state') {
                $validationService->validateStateTopic(
                    $mqttTopic,
                );
            } else {
                $validated = $request->validate([
                    'state_topic_id' => [
                        'required',
                        'integer',
                        'exists:laraiot_mqtt_topics,id',
                    ],
                ]);

                $stateTopic = MqttTopic::query()->findOrFail(
                    (int) $validated['state_topic_id'],
                );

                $validationService->validateCommandTopic(
                    $mqttTopic,
                    $stateTopic,
                );
            }
        } catch (ValidationException $exception) {
            /*
             * Keep Laravel's field-level validation errors
             * intact (for example state_topic_id).
             */
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'validation' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            return back()
                ->withErrors([
                    'validation' =>
                        $exception->getMessage(),
                ]);
        }

        return back()->with(
            'laraiot_validation',
            [
                'success' => true,
                'message' =>
                    'MQTT topic validated successfully.',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function modelAttributes(
        MqttTopicRequest $request,
    ): array {
        $validated = $request->validated();
        $purpose = (string) $validated['purpose'];

        $payloadMapping = $purpose === 'state'
            ? $this->statePayloadMapping($validated)
            : [
                'on' => $validated['command_on'],
                'off' => $validated['command_off'],
            ];

        return [
            'purpose' => $purpose,
            'topic' => trim(
                (string) $validated['topic'],
            ),
            'payload_mapping' => $payloadMapping,
            'qos' => (int) $validated['qos'],
            'retain' => $purpose === 'command'
                ? (bool) ($validated['retain'] ?? false)
                : false,
            'is_enabled' =>
                (bool) $validated['is_enabled'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function statePayloadMapping(
        array $validated,
    ): array {
        $format = (string) $validated['payload_format'];

        $mapping = [
            'format' => $format,
        ];

        if ($format === 'json') {
            $mapping['value_path'] = trim(
                (string) $validated['value_path'],
            );
        }

        $valueMap = [];

        foreach (
            (array) ($validated['value_map'] ?? [])
            as $entry
        ) {
            if (! is_array($entry)) {
                continue;
            }

            $source = trim(
                (string) ($entry['source'] ?? ''),
            );

            if ($source === '') {
                continue;
            }

            $valueMap[$source] =
                (string) ($entry['target'] ?? '');
        }

        if ($valueMap !== []) {
            $mapping['value_map'] = $valueMap;
        }

        return $mapping;
    }

    private function assertBelongsTo(
        LogicalDevice $logicalDevice,
        MqttTopic $mqttTopic,
    ): void {
        abort_unless(
            $mqttTopic->logical_device_id
                === $logicalDevice->getKey(),
            404,
        );
    }
}
