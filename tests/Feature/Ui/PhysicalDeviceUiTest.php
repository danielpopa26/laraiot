<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Inertia\Testing\AssertableInertia as Assert;

it('renders physical devices in alphabetical order', function () {
    PhysicalDevice::query()->create([
        'identifier' => 'z-controller',
        'name' => 'Zulu Controller',
        'is_enabled' => true,
    ]);

    PhysicalDevice::query()->create([
        'identifier' => 'a-controller',
        'name' => 'Alpha Controller',
        'is_enabled' => true,
    ]);

    $this
        ->get(route('laraiot.physical-devices.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/devices/physical/Index',
                    false,
                )
                ->has('physicalDevices', 2)
                ->where(
                    'physicalDevices.0.name',
                    'Alpha Controller',
                )
                ->where(
                    'physicalDevices.1.name',
                    'Zulu Controller',
                ),
        );
});

it('creates a physical device from the UI endpoint', function () {
    $response = $this->post(
        route('laraiot.physical-devices.store'),
        [
            'identifier' => 'greenhouse-controller',
            'name' => 'Greenhouse Controller',
            'ip_address' => '192.168.2.50',
            'mac_address' => 'AA:BB:CC:DD:EE:50',
            'description' => 'Greenhouse controller.',
            'is_enabled' => true,
        ],
    );

    $device = PhysicalDevice::query()
        ->where(
            'identifier',
            'greenhouse-controller',
        )
        ->firstOrFail();

    $response->assertRedirect(
        route(
            'laraiot.physical-devices.show',
            $device,
        ),
    );
});

it('updates an existing physical device', function () {
    $tree = UiTestData::deviceTree();

    $this->put(
        route(
            'laraiot.physical-devices.update',
            $tree['physicalDevice'],
        ),
        [
            'identifier' => $tree['physicalDevice']->identifier,
            'name' => 'Updated Controller',
            'ip_address' => '192.168.2.101',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'description' => 'Updated.',
            'is_enabled' => false,
        ],
    );

    expect(
        $tree['physicalDevice']->refresh()->name,
    )->toBe('Updated Controller')
        ->and(
            $tree['physicalDevice']->is_enabled,
        )
        ->toBeFalse();
});

it('rejects duplicate physical device identifiers', function () {
    $tree = UiTestData::deviceTree();

    $this->post(
        route('laraiot.physical-devices.store'),
        [
            'identifier' => $tree['physicalDevice']->identifier,
            'name' => 'Duplicate',
            'ip_address' => null,
            'mac_address' => null,
            'description' => null,
            'is_enabled' => true,
        ],
    )
        ->assertSessionHasErrors('identifier');

    expect(
        PhysicalDevice::query()->count(),
    )->toBe(1);
});

it('does not delete a physical device which contains logical devices', function () {
    $tree = UiTestData::deviceTree();

    $this->delete(
        route(
            'laraiot.physical-devices.destroy',
            $tree['physicalDevice'],
        ),
    )
        ->assertSessionHasErrors('delete');

    $this->assertDatabaseHas(
        'laraiot_physical_devices',
        [
            'id' => $tree['physicalDevice']->getKey(),
        ],
    );

    $this->assertDatabaseHas(
        'laraiot_logical_devices',
        [
            'id' => $tree['logicalDevice']->getKey(),
        ],
    );
});

it('deletes a physical device without logical devices', function () {
    $physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'unused-controller',
        'name' => 'Unused Controller',
        'is_enabled' => true,
    ]);

    $this->delete(
        route('laraiot.physical-devices.destroy', $physicalDevice),
    )->assertRedirect(route('laraiot.physical-devices.index'));

    $this->assertDatabaseMissing('laraiot_physical_devices', [
        'id' => $physicalDevice->getKey(),
    ]);
});
