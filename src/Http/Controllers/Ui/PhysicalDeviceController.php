<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\PhysicalDeviceRequest;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PhysicalDeviceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'laraiot/devices/physical/Index',
            [
                'physicalDevices' => PhysicalDevice::query()
                    ->withCount('logicalDevices')
                    ->orderBy('name')
                    ->get(),
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'laraiot/devices/physical/Create',
        );
    }

    public function store(
        PhysicalDeviceRequest $request,
    ): RedirectResponse {
        $physicalDevice = PhysicalDevice::query()->create(
            $request->validated(),
        );

        return redirect()
            ->route(
                'laraiot.physical-devices.show',
                $physicalDevice,
            )
            ->with(
                'laraiot_message',
                'Physical device created.',
            );
    }

    public function show(
        PhysicalDevice $physicalDevice,
    ): Response {
        $physicalDevice->load([
            'logicalDevices' => fn ($query) => $query
                ->with('deviceType:id,name')
                ->orderBy('name'),
        ]);

        return Inertia::render(
            'laraiot/devices/physical/Show',
            [
                'physicalDevice' => $physicalDevice,
                'logicalDevices' => $physicalDevice->logicalDevices,
            ],
        );
    }

    public function edit(
        PhysicalDevice $physicalDevice,
    ): Response {
        return Inertia::render(
            'laraiot/devices/physical/Edit',
            [
                'physicalDevice' => $physicalDevice,
            ],
        );
    }

    public function update(
        PhysicalDeviceRequest $request,
        PhysicalDevice $physicalDevice,
    ): RedirectResponse {
        $physicalDevice->update(
            $request->validated(),
        );

        return redirect()
            ->route(
                'laraiot.physical-devices.show',
                $physicalDevice,
            )
            ->with(
                'laraiot_message',
                'Physical device updated.',
            );
    }

    public function destroy(
        PhysicalDevice $physicalDevice,
    ): RedirectResponse {
        if ($physicalDevice->logicalDevices()->exists()) {
            return back()->withErrors([
                'delete' => 'This physical device contains one or more logical devices.',
            ]);
        }

        $physicalDevice->delete();

        return redirect()
            ->route('laraiot.physical-devices.index')
            ->with(
                'laraiot_message',
                'Physical device deleted.',
            );
    }
}
