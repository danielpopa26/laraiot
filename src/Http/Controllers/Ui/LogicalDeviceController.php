<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\LogicalDeviceRequest;
use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Support\Ui\LogicalDevicePresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(
        Request $request,
    ): Response {
        $options = $this->formOptions();
        $requestedPhysicalDeviceId = $request->integer(
            'physical_device_id',
        );

        $selectedPhysicalDeviceId = null;

        if (
            $requestedPhysicalDeviceId > 0
            && $options['physicalDevices']->contains(
                'id',
                $requestedPhysicalDeviceId,
            )
        ) {
            $selectedPhysicalDeviceId = $requestedPhysicalDeviceId;
        }

        return Inertia::render(
            'laraiot/devices/logical/Create',
            [
                ...$options,
                'selectedPhysicalDeviceId' => $selectedPhysicalDeviceId,
            ],
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
        LogicalDevicePresenter $logicalDevicePresenter,
    ): Response {
        $logicalDevice->load([
            'physicalDevice:id,name,identifier,is_enabled',
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
                'deviceOverview' => $logicalDevicePresenter
                    ->present($logicalDevice),
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
                'delete' => 'To delete this logical device, first delete all associated MQTT topics.',
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
     *     physicalDevices: Collection<int, PhysicalDevice>,
     *     deviceTypes: Collection<int, DeviceType>
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
