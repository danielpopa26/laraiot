<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Http\Controllers\Ui;

use Danpopa\LaraIoT\Http\Requests\Ui\DeviceTypeRequest;
use Danpopa\LaraIoT\Models\DeviceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DeviceTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'laraiot/settings/device-types/Index',
            [
                'deviceTypes' => DeviceType::query()
                    ->withCount('logicalDevices')
                    ->orderBy('name')
                    ->get(),
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'laraiot/settings/device-types/Create',
        );
    }

    public function store(
        DeviceTypeRequest $request,
    ): RedirectResponse {
        DeviceType::query()->create(
            $request->validated(),
        );

        return redirect()
            ->route('laraiot.device-types.index')
            ->with(
                'laraiot_message',
                'Device type created.',
            );
    }

    public function edit(
        DeviceType $deviceType,
    ): Response {
        $deviceType->loadCount('logicalDevices');

        return Inertia::render(
            'laraiot/settings/device-types/Edit',
            [
                'deviceType' => $deviceType,
            ],
        );
    }

    public function update(
        DeviceTypeRequest $request,
        DeviceType $deviceType,
    ): RedirectResponse {
        $deviceType->update(
            $request->validated(),
        );

        return redirect()
            ->route('laraiot.device-types.index')
            ->with(
                'laraiot_message',
                'Device type updated.',
            );
    }

    public function destroy(
        DeviceType $deviceType,
    ): RedirectResponse {
        if ($deviceType->logicalDevices()->exists()) {
            return back()->withErrors([
                'device_type' => 'To delete this device type, first delete all associated logical devices.',
            ]);
        }

        $deviceType->delete();

        return redirect()
            ->route('laraiot.device-types.index')
            ->with(
                'laraiot_message',
                'Device type deleted.',
            );
    }
}
