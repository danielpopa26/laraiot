<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;
use Danpopa\LaraIoT\Services\MqttHealthMonitor;
use Danpopa\LaraIoT\Support\Reverb\ReverbHealthMonitor;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
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
                ->where('mqtt.status', 'unknown')
                ->where('mqtt.label', 'Unknown')
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
                )
                ->where(
                    'laraiot.mqtt.status',
                    'unknown',
                ),
        );
});

it('shares a recent MQTT listener heartbeat with the dashboard and topbar', function () {
    $healthMonitor = new MqttHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
    );
    $healthMonitor->markConnected(3);

    $this->app->instance(
        MqttHealthMonitor::class,
        $healthMonitor,
    );

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('mqtt.connected', true)
                ->where('mqtt.status', 'connected')
                ->where('mqtt.label', 'Connected')
                ->where('mqtt.subscriptions', 3)
                ->where(
                    'laraiot.mqtt.connected',
                    true,
                )
                ->where(
                    'laraiot.mqtt.status',
                    'connected',
                ),
        );
});

it('falls back to polling when websocket mode is requested but Reverb is offline', function () {
    ApplicationSetting::current()->update([
        'application_mode' => ApplicationSetting::MODE_WEBSOCKET,
    ]);

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('requestedMode', 'websocket')
                ->where('mode', 'polling')
                ->where('fallbackActive', true)
                ->where('websocket.live', false)
                ->where('laraiot.requestedMode', 'websocket')
                ->where('laraiot.mode', 'polling')
                ->where('laraiot.fallbackActive', true),
        );
});

it('uses websocket mode when Reverb is live', function () {
    config()->set(
        'laraiot.websocket.connection',
        'reverb',
    );
    config()->set(
        'broadcasting.connections.reverb',
        [
            'driver' => 'reverb',
            'key' => 'laraiot-dashboard-key',
        ],
    );
    config()->set(
        'reverb.servers.reverb',
        [
            'host' => '127.0.0.1',
            'port' => 8080,
            'hostname' => 'localhost',
            'options' => [
                'tls' => [],
            ],
        ],
    );
    config()->set(
        'reverb.apps.apps',
        [
            [
                'key' => 'laraiot-dashboard-key',
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'scheme' => 'http',
                ],
            ],
        ],
    );

    $this->app->instance(
        ReverbHealthMonitor::class,
        new ReverbHealthMonitor(
            new CacheRepository(new ArrayStore),
            app(ConfigRepository::class),
            static fn (array $server): bool => true,
        ),
    );

    ApplicationSetting::current()->update([
        'application_mode' => ApplicationSetting::MODE_WEBSOCKET,
    ]);

    $this
        ->get(route('laraiot.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('requestedMode', 'websocket')
                ->where('mode', 'websocket')
                ->where('fallbackActive', false)
                ->where('websocket.live', true)
                ->where('laraiot.mode', 'websocket')
                ->where('laraiot.fallbackActive', false),
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
