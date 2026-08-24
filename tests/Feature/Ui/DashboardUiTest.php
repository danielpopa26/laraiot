<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the LaraIoT dashboard with real summary values', function () {
    $tree = UiTestData::deviceTree();

    $topic = UiTestData::stateTopic(
        $tree['logicalDevice'],
    );

    ActivityLog::query()->create([
        'type' => 'state',
        'logical_device_id' => $tree['logicalDevice']->getKey(),
        'mqtt_topic_id' => $topic->getKey(),
        'title' => 'State updated',
        'description' => 'Test state update.',
        'data' => [
            'value' => 'on',
        ],
        'happened_at' => now(),
    ]);

    $response = $this->get(
        route('laraiot.dashboard'),
    );

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/Dashboard',
                    false,
                )
                ->where(
                    'statistics.physicalDevices',
                    1,
                )
                ->where(
                    'statistics.logicalDevices',
                    1,
                )
                ->where(
                    'statistics.mqttTopics',
                    1,
                )
                ->where('mode', 'polling')
                ->where('mqtt.connected', null)
                ->has('recentActivity', 1)
                ->where(
                    'recentActivity.0.message',
                    'State updated',
                )
                ->where(
                    'recentActivity.0.description',
                    'Test state update.',
                )
                ->has('physicalDevices', 1)
                ->where(
                    'physicalDevices.0.id',
                    $tree['physicalDevice']->getKey(),
                )
                ->has(
                    'physicalDevices.0.logical_devices',
                    1,
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.configuration.status',
                    'state_topic_unvalidated',
                )
                ->where(
                    'laraiot.baseUrl',
                    '/laraiot',
                ),
        );
});

it('renders zero dashboard counts for an empty installation', function () {
    MqttTopic::query()->delete();

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/Dashboard',
                    false,
                )
                ->where(
                    'statistics.physicalDevices',
                    0,
                )
                ->where(
                    'statistics.logicalDevices',
                    0,
                )
                ->where(
                    'statistics.mqttTopics',
                    0,
                )
                ->has('physicalDevices', 0),
        );
});

it('renders a physical device card before logical devices are attached', function () {
    $physicalDevice = PhysicalDevice::query()->create([
        'identifier' => 'controller-without-logical-devices',
        'name' => 'Empty Controller',
        'is_enabled' => true,
    ]);

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'physicalDevices.0.id',
                    $physicalDevice->getKey(),
                )
                ->has(
                    'physicalDevices.0.logical_devices',
                    0,
                ),
        );
});

it('reports logical devices which do not have MQTT topics', function () {
    $tree = UiTestData::deviceTree();

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'physicalDevices.0.logical_devices.0.id',
                    $tree['logicalDevice']->getKey(),
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.configuration.status',
                    'no_topics',
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.control.available',
                    false,
                ),
        );
});

it('exposes confirmed state and control readiness for a configured relay', function () {
    $tree = UiTestData::deviceTree();

    $tree['logicalDevice']->forceFill([
        'last_value' => 'off',
    ])->saveQuietly();

    $stateTopic = UiTestData::stateTopic(
        $tree['logicalDevice'],
        [
            'last_value' => 'off',
            'last_received_at' => now(),
        ],
    );
    $stateTopic->markAsValidated();

    $commandTopic = UiTestData::commandTopic(
        $tree['logicalDevice'],
    );
    $commandTopic->markAsValidated();

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'physicalDevices.0.logical_devices.0.configuration.status',
                    'ready',
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.control.available',
                    true,
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.control.current_state',
                    'off',
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.control.command_topic_id',
                    $commandTopic->getKey(),
                ),
        );
});

it('keeps a validated sensor available as read-only monitoring', function () {
    $tree = UiTestData::deviceTree();

    $tree['logicalDevice']->forceFill([
        'last_value' => 23.5,
        'unit' => '%',
    ])->saveQuietly();

    $stateTopic = UiTestData::stateTopic(
        $tree['logicalDevice'],
        [
            'last_value' => 23.5,
            'last_received_at' => now(),
        ],
    );
    $stateTopic->markAsValidated();

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'physicalDevices.0.logical_devices.0.last_value',
                    23.5,
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.configuration.status',
                    'read_only',
                )
                ->where(
                    'physicalDevices.0.logical_devices.0.control.available',
                    false,
                ),
        );
});
