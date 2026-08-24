<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Inertia\Testing\AssertableInertia as Assert;

it('only offers enabled physical devices and device types on create', function () {
    $enabled = UiTestData::deviceTree('enabled');

    PhysicalDevice::query()->create([
        'identifier' => 'disabled-physical',
        'name' => 'Disabled Physical',
        'is_enabled' => false,
    ]);

    DeviceType::query()->create([
        'identifier' => 'disabled-type',
        'name' => 'Disabled Type',
        'is_enabled' => false,
    ]);

    $this
        ->get(route('laraiot.logical-devices.create'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/devices/logical/Create',
                    false,
                )
                ->has('physicalDevices', 1)
                ->where(
                    'physicalDevices.0.id',
                    $enabled['physicalDevice']->getKey(),
                )
                ->has('deviceTypes', 1)
                ->where(
                    'deviceTypes.0.id',
                    $enabled['deviceType']->getKey(),
                ),
        );
});

it('creates and updates a logical device', function () {
    $tree = UiTestData::deviceTree();

    $this->post(
        route('laraiot.logical-devices.store'),
        [
            'physical_device_id' => $tree['physicalDevice']->getKey(),
            'device_type_id' => $tree['deviceType']->getKey(),
            'identifier' => 'humidity-sensor',
            'name' => 'Humidity Sensor',
            'unit' => '%',
            'is_enabled' => true,
        ],
    );

    $logicalDevice = LogicalDevice::query()
        ->where('identifier', 'humidity-sensor')
        ->firstOrFail();

    $this->put(
        route(
            'laraiot.logical-devices.update',
            $logicalDevice,
        ),
        [
            'physical_device_id' => $tree['physicalDevice']->getKey(),
            'device_type_id' => $tree['deviceType']->getKey(),
            'identifier' => 'humidity-sensor',
            'name' => 'Soil Humidity',
            'unit' => '%',
            'is_enabled' => false,
        ],
    )
        ->assertRedirect(
            route(
                'laraiot.logical-devices.show',
                $logicalDevice,
            ),
        );

    expect($logicalDevice->refresh()->name)
        ->toBe('Soil Humidity')
        ->and($logicalDevice->is_enabled)
        ->toBeFalse();
});

it('renders a logical device together with its MQTT topics', function () {
    $tree = UiTestData::deviceTree();

    $tree['logicalDevice']->forceFill([
        'last_value' => 42.5,
    ])->saveQuietly();

    UiTestData::stateTopic(
        $tree['logicalDevice'],
    );

    $this
        ->get(
            route(
                'laraiot.logical-devices.show',
                $tree['logicalDevice'],
            ),
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/devices/logical/Show',
                    false,
                )
                ->where(
                    'logicalDevice.id',
                    $tree['logicalDevice']->getKey(),
                )
                ->where(
                    'logicalDevice.last_value',
                    42.5,
                )
                ->has('mqttTopics', 1),
        );
});

it('does not delete a logical device which has MQTT topics', function () {
    $tree = UiTestData::deviceTree();

    $topic = UiTestData::stateTopic(
        $tree['logicalDevice'],
    );

    $this->delete(
        route(
            'laraiot.logical-devices.destroy',
            $tree['logicalDevice'],
        ),
    )
        ->assertSessionHasErrors('delete');

    $this->assertDatabaseHas(
        'laraiot_logical_devices',
        [
            'id' => $tree['logicalDevice']->getKey(),
        ],
    );

    $this->assertDatabaseHas(
        'laraiot_mqtt_topics',
        [
            'id' => $topic->getKey(),
        ],
    );
});
