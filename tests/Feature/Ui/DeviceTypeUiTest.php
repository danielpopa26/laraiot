<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Inertia\Testing\AssertableInertia as Assert;

it('renders device types with logical device counts', function () {
    UiTestData::deviceTree();

    $this
        ->get(route('laraiot.device-types.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/settings/device-types/Index',
                    false,
                )
                ->has('deviceTypes', 1)
                ->where(
                    'deviceTypes.0.logical_devices_count',
                    1,
                ),
        );
});

it('creates and updates a device type', function () {
    $this->post(
        route('laraiot.device-types.store'),
        [
            'identifier' => 'temperature',
            'name' => 'Temperature',
            'description' => 'Temperature sensor.',
            'is_enabled' => true,
        ],
    )
        ->assertRedirect(
            route('laraiot.device-types.index'),
        );

    $deviceType = DeviceType::query()
        ->where('identifier', 'temperature')
        ->firstOrFail();

    $this->put(
        route(
            'laraiot.device-types.update',
            $deviceType,
        ),
        [
            'identifier' => 'temperature',
            'name' => 'Temperature Sensor',
            'description' => 'Updated.',
            'is_enabled' => false,
        ],
    )
        ->assertRedirect(
            route('laraiot.device-types.index'),
        );

    expect($deviceType->refresh()->name)
        ->toBe('Temperature Sensor')
        ->and($deviceType->is_enabled)
        ->toBeFalse();
});

it('does not delete a device type used by logical devices', function () {
    $tree = UiTestData::deviceTree();

    $this->delete(
        route(
            'laraiot.device-types.destroy',
            $tree['deviceType'],
        ),
    )
        ->assertSessionHasErrors('device_type');

    $this->assertDatabaseHas(
        'laraiot_device_types',
        [
            'id' => $tree['deviceType']->getKey(),
        ],
    );
});

it('deletes an unused device type', function () {
    $deviceType = DeviceType::query()->create([
        'identifier' => 'unused-type',
        'name' => 'Unused Type',
        'is_enabled' => true,
    ]);

    $this->delete(
        route(
            'laraiot.device-types.destroy',
            $deviceType,
        ),
    )
        ->assertRedirect(
            route('laraiot.device-types.index'),
        );

    $this->assertDatabaseMissing(
        'laraiot_device_types',
        [
            'id' => $deviceType->getKey(),
        ],
    );
});
