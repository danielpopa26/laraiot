<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\LogicalDeviceCommandRequest;
use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Services\MqttCommandService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Throwable;

final class LogicalDeviceCommandController extends Controller
{
    public function __invoke(
        LogicalDeviceCommandRequest $request,
        LogicalDevice $logicalDevice,
        MqttTopic $mqttTopic,
        MqttCommandService $commandService,
    ): RedirectResponse {
        abort_unless(
            (int) $mqttTopic->logical_device_id
                === (int) $logicalDevice->getKey(),
            404,
        );

        $logicalDevice->loadMissing(
            'physicalDevice:id,is_enabled',
        );

        if (! $logicalDevice->is_enabled) {
            return back()->withErrors([
                'command' => 'Commands cannot be sent to a disabled logical device.',
            ]);
        }

        if (
            $logicalDevice->physicalDevice === null
            || ! $logicalDevice->physicalDevice->is_enabled
        ) {
            return back()->withErrors([
                'command' => 'Commands cannot be sent while the physical device is disabled.',
            ]);
        }

        $command = (string) $request->validated('command');

        try {
            $commandService->send(
                mqttTopic: $mqttTopic,
                command: $command,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'command' => $exception->getMessage(),
            ]);
        }

        $actor = $request->user();

        ActivityLog::query()->create([
            'type' => 'command',
            'logical_device_id' => $logicalDevice->getKey(),
            'mqtt_topic_id' => $mqttTopic->getKey(),
            'actor_type' => $actor instanceof Model
                ? $actor::class
                : null,
            'actor_id' => $actor instanceof Model
                ? $actor->getKey()
                : null,
            'title' => sprintf(
                '%s command published',
                $logicalDevice->name,
            ),
            'description' => sprintf(
                '%s ← %s',
                $mqttTopic->topic,
                strtoupper($command),
            ),
            'data' => [
                'topic' => $mqttTopic->topic,
                'command' => $command,
            ],
            'happened_at' => now(),
        ]);

        return back()->with(
            'laraiot_message',
            sprintf(
                'Command "%s" published for %s.',
                $command,
                $logicalDevice->name,
            ),
        );
    }
}
