<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Models\MqttTopic;
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
                ),
        );
});
