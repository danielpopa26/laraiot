<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttPublisher;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Mockery\MockInterface;

it('publishes a validated relay command and records the activity', function () {
    $tree = UiTestData::deviceTree();
    $commandTopic = UiTestData::commandTopic(
        $tree['logicalDevice'],
    );
    $commandTopic->markAsValidated();

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);
    $publisher
        ->shouldReceive('publish')
        ->once()
        ->with(
            $commandTopic->topic,
            'ON',
            0,
            false,
            null,
        );

    $this->app->instance(
        MqttPublisher::class,
        $publisher,
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.command',
            [
                'logicalDevice' => $tree['logicalDevice'],
                'mqttTopic' => $commandTopic,
            ],
        ),
        [
            'command' => 'ON',
        ],
    )
        ->assertSessionHasNoErrors()
        ->assertSessionHas('laraiot_message');

    $this->assertDatabaseHas(
        'laraiot_activity_logs',
        [
            'type' => 'command',
            'logical_device_id' => $tree['logicalDevice']
                ->getKey(),
            'mqtt_topic_id' => $commandTopic->getKey(),
            'title' => $tree['logicalDevice']->name
                .' command published',
            'description' => $commandTopic->topic.' ← ON',
        ],
    );
});

it('rejects invalid relay commands without publishing', function () {
    $tree = UiTestData::deviceTree();
    $commandTopic = UiTestData::commandTopic(
        $tree['logicalDevice'],
    );
    $commandTopic->markAsValidated();

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(
        MqttPublisher::class,
        $publisher,
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.command',
            [
                'logicalDevice' => $tree['logicalDevice'],
                'mqttTopic' => $commandTopic,
            ],
        ),
        [
            'command' => 'toggle',
        ],
    )
        ->assertSessionHasErrors('command');
});

it('rejects commands sent through an unvalidated topic', function () {
    $tree = UiTestData::deviceTree();
    $commandTopic = UiTestData::commandTopic(
        $tree['logicalDevice'],
    );

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(
        MqttPublisher::class,
        $publisher,
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.command',
            [
                'logicalDevice' => $tree['logicalDevice'],
                'mqttTopic' => $commandTopic,
            ],
        ),
        [
            'command' => 'off',
        ],
    )
        ->assertSessionHasErrors('command');
});

it('rejects commands for a disabled logical device', function () {
    $tree = UiTestData::deviceTree();
    $tree['logicalDevice']->forceFill([
        'is_enabled' => false,
    ])->saveQuietly();

    $commandTopic = UiTestData::commandTopic(
        $tree['logicalDevice'],
    );
    $commandTopic->markAsValidated();

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(
        MqttPublisher::class,
        $publisher,
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.command',
            [
                'logicalDevice' => $tree['logicalDevice'],
                'mqttTopic' => $commandTopic,
            ],
        ),
        [
            'command' => 'on',
        ],
    )
        ->assertSessionHasErrors('command');
});

it('rejects a command topic belonging to another logical device', function () {
    $first = UiTestData::deviceTree('first-command');
    $second = UiTestData::deviceTree('second-command');

    $commandTopic = UiTestData::commandTopic(
        $second['logicalDevice'],
    );
    $commandTopic->markAsValidated();

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(
        MqttPublisher::class,
        $publisher,
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.command',
            [
                'logicalDevice' => $first['logicalDevice'],
                'mqttTopic' => $commandTopic,
            ],
        ),
        [
            'command' => 'on',
        ],
    )
        ->assertNotFound();
});
