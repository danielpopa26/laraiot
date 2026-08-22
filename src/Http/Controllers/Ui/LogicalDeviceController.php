<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\LogicalDeviceRequest;
use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class LogicalDeviceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'laraiot/devices/logical/Index',
            [
                'logicalDevices' => LogicalDevice::query()
                    ->with([
                        'physicalDevice:id,name',
                        'deviceType:id,name',
                    ])
                    ->withCount('mqttTopics')
                    ->orderBy('name')
                    ->get(),
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'laraiot/devices/logical/Create',
            $this->formOptions(),
        );
    }

    public function store(
        LogicalDeviceRequest $request,
    ): RedirectResponse {
        $logicalDevice = LogicalDevice::query()->create(
            $request->validated(),
        );

        return redirect()
            ->route(
                'laraiot.logical-devices.show',
                $logicalDevice,
            )
            ->with(
                'laraiot_message',
                'Logical device created.',
            );
    }

    public function show(
        LogicalDevice $logicalDevice,
    ): Response {
        $logicalDevice->load([
            'physicalDevice:id,name,identifier',
            'deviceType:id,name,identifier',
            'mqttTopics' => fn ($query) => $query
                ->orderBy('purpose')
                ->orderBy('topic'),
        ]);

        return Inertia::render(
            'laraiot/devices/logical/Show',
            [
                'logicalDevice' => $logicalDevice,
                'mqttTopics' => $logicalDevice->mqttTopics,
            ],
        );
    }

    public function edit(
        LogicalDevice $logicalDevice,
    ): Response {
        return Inertia::render(
            'laraiot/devices/logical/Edit',
            [
                'logicalDevice' => $logicalDevice,
                ...$this->formOptions(
                    includeDisabled: true,
                ),
            ],
        );
    }

    public function update(
        LogicalDeviceRequest $request,
        LogicalDevice $logicalDevice,
    ): RedirectResponse {
        $logicalDevice->update(
            $request->validated(),
        );

        return redirect()
            ->route(
                'laraiot.logical-devices.show',
                $logicalDevice,
            )
            ->with(
                'laraiot_message',
                'Logical device updated.',
            );
    }

    public function destroy(
        LogicalDevice $logicalDevice,
    ): RedirectResponse {
        if ($logicalDevice->mqttTopics()->exists()) {
            return back()->withErrors([
                'delete' =>
                    'This logical device has one or more MQTT topics assigned.',
            ]);
        }

        $logicalDevice->delete();

        return redirect()
            ->route('laraiot.logical-devices.index')
            ->with(
                'laraiot_message',
                'Logical device deleted.',
            );
    }

    /**
     * @return array{
     *     physicalDevices: mixed,
     *     deviceTypes: mixed
     * }
     */
    private function formOptions(
        bool $includeDisabled = false,
    ): array {
        $physicalDevices = PhysicalDevice::query()
            ->when(
                ! $includeDisabled,
                fn ($query) => $query
                    ->where('is_enabled', true),
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'identifier',
                'is_enabled',
            ]);

        $deviceTypes = DeviceType::query()
            ->when(
                ! $includeDisabled,
                fn ($query) => $query
                    ->where('is_enabled', true),
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'identifier',
                'is_enabled',
            ]);

        return [
            'physicalDevices' => $physicalDevices,
            'deviceTypes' => $deviceTypes,
        ];
    }
}
