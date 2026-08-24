<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Support\Ui\LogicalDevicePresenter;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(
        LogicalDevicePresenter $logicalDevicePresenter,
    ): Response {
        $settings = ApplicationSetting::current();

        $physicalDevices = PhysicalDevice::query()
            ->with([
                'logicalDevices' => fn ($query) => $query
                    ->with([
                        'physicalDevice:id,name,identifier,is_enabled',
                        'deviceType:id,name,identifier',
                        'mqttTopics' => fn ($topicQuery) => $topicQuery
                            ->orderBy('purpose')
                            ->orderBy('id'),
                    ])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(
                fn (PhysicalDevice $physicalDevice): array => [
                    'id' => $physicalDevice->getKey(),
                    'name' => $physicalDevice->name,
                    'identifier' => $physicalDevice->identifier,
                    'ip_address' => $physicalDevice->ip_address,
                    'mac_address' => $physicalDevice->mac_address,
                    'description' => $physicalDevice->description,
                    'is_enabled' => $physicalDevice->is_enabled,
                    'logical_devices' => $physicalDevice
                        ->logicalDevices
                        ->map(
                            fn (LogicalDevice $logicalDevice): array => $logicalDevicePresenter
                                ->present($logicalDevice),
                        )
                        ->values(),
                ],
            )
            ->values();

        $recentActivity = ActivityLog::query()
            ->with('logicalDevice:id,name')
            ->latest('happened_at')
            ->limit(10)
            ->get()
            ->map(
                fn (ActivityLog $activity): array => [
                    'id' => $activity->getKey(),
                    'message' => $activity->title,
                    'description' => $activity->description,
                    'device' => $activity->logicalDevice?->name,
                    'status' => $this->activityStatus(
                        $activity->type,
                    ),
                    'created_at' => $activity->happened_at
                        ?->copy()
                        ->setTimezone($settings->timezone)
                        ->format(
                            $settings->date_format
                                .' '
                                .$settings->time_format,
                        ),
                ],
            )
            ->values();

        return Inertia::render(
            'laraiot/Dashboard',
            [
                'statistics' => [
                    'physicalDevices' => PhysicalDevice::query()->count(),
                    'logicalDevices' => LogicalDevice::query()->count(),
                    'mqttTopics' => MqttTopic::query()->count(),
                ],
                'physicalDevices' => $physicalDevices,
                'recentActivity' => $recentActivity,
                'mqtt' => [
                    /*
                     * LaraIoT currently has no persistent MQTT
                     * health state. Do not report a false
                     * connected/disconnected value.
                     */
                    'connected' => null,
                ],
                'mode' => $settings->application_mode,
            ],
        );
    }

    private function activityStatus(string $type): string
    {
        return match ($type) {
            'error' => 'danger',
            'state' => 'success',
            'command' => 'info',
            default => 'neutral',
        };
    }
}
