<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Tests\Support\UiTestData;

it('stores a state topic and builds its payload mapping', function () {
    $tree = UiTestData::deviceTree();

    $this->post(
        route(
            'laraiot.mqtt-topics.store',
            $tree['logicalDevice'],
        ),
        [
            'purpose' => 'state',
            'topic' => 'tele/greenhouse/SENSOR',
            'payload_format' => 'json',
            'value_path' => 'MS01.Humidity',
            'value_map' => [
                [
                    'source' => '0',
                    'target' => 'off',
                ],
                [
                    'source' => '1',
                    'target' => 'on',
                ],
            ],
            'command_on' => null,
            'command_off' => null,
            'qos' => 1,
            'retain' => false,
            'is_enabled' => true,
        ],
    )
        ->assertSessionHasNoErrors();

    $topic = MqttTopic::query()
        ->where(
            'topic',
            'tele/greenhouse/SENSOR',
        )
        ->firstOrFail();

    expect($topic->payload_mapping)->toBe([
        'format' => 'json',
        'value_path' => 'MS01.Humidity',
        'value_map' => [
            '0' => 'off',
            '1' => 'on',
        ],
    ]);
});

it('stores command payloads as normalized on and off mappings', function () {
    $tree = UiTestData::deviceTree();

    $this->post(
        route(
            'laraiot.mqtt-topics.store',
            $tree['logicalDevice'],
        ),
        [
            'purpose' => 'command',
            'topic' => 'cmnd/greenhouse/POWER',
            'payload_format' => null,
            'value_path' => null,
            'value_map' => [],
            'command_on' => 'ON',
            'command_off' => 'OFF',
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
        ],
    )
        ->assertSessionHasNoErrors();

    $topic = MqttTopic::query()
        ->where(
            'topic',
            'cmnd/greenhouse/POWER',
        )
        ->firstOrFail();

    expect($topic->payload_mapping)->toBe([
        'on' => 'ON',
        'off' => 'OFF',
    ]);
});

it('rejects wildcard MQTT topics', function () {
    $tree = UiTestData::deviceTree();

    $this->post(
        route(
            'laraiot.mqtt-topics.store',
            $tree['logicalDevice'],
        ),
        [
            'purpose' => 'state',
            'topic' => 'tele/+/SENSOR',
            'payload_format' => 'raw',
            'value_path' => null,
            'value_map' => [],
            'command_on' => null,
            'command_off' => null,
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
        ],
    )
        ->assertSessionHasErrors('topic');

    expect(MqttTopic::query()->count())->toBe(0);
});

it('invalidates a previously validated topic after relevant configuration changes', function () {
    $tree = UiTestData::deviceTree();
    $topic = UiTestData::stateTopic(
        $tree['logicalDevice'],
    );

    $topic->markAsValidated();

    expect(
        $topic->refresh()->validated_at,
    )->not->toBeNull();

    $this->put(
        route(
            'laraiot.mqtt-topics.update',
            [
                'logicalDevice' =>
                    $tree['logicalDevice'],
                'mqttTopic' => $topic,
            ],
        ),
        [
            'purpose' => 'state',
            'topic' => 'test/changed/state',
            'payload_format' => 'raw',
            'value_path' => null,
            'value_map' => [],
            'command_on' => null,
            'command_off' => null,
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
        ],
    )
        ->assertSessionHasNoErrors();

    expect(
        $topic->refresh()->validated_at,
    )->toBeNull();
});

it('rejects a nested MQTT topic belonging to another logical device', function () {
    $first = UiTestData::deviceTree('first');
    $second = UiTestData::deviceTree('second');

    $topic = UiTestData::stateTopic(
        $second['logicalDevice'],
    );

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(
            route(
                'laraiot.mqtt-topics.edit',
                [
                    'logicalDevice' =>
                        $first['logicalDevice'],
                    'mqttTopic' => $topic,
                ],
            ),
        )
        ->assertNotFound();
});

it('requires a state topic id before validating a command topic', function () {
    $tree = UiTestData::deviceTree();

    $commandTopic = UiTestData::commandTopic(
        $tree['logicalDevice'],
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.validate',
            [
                'logicalDevice' =>
                    $tree['logicalDevice'],
                'mqttTopic' => $commandTopic,
            ],
        ),
        [],
    )
        ->assertSessionHasErrors(
            'state_topic_id',
        );
});

it('rejects validation of a disabled state topic without contacting the broker', function () {
    $tree = UiTestData::deviceTree();

    $stateTopic = UiTestData::stateTopic(
        $tree['logicalDevice'],
        [
            'is_enabled' => false,
        ],
    );

    $this->post(
        route(
            'laraiot.mqtt-topics.validate',
            [
                'logicalDevice' =>
                    $tree['logicalDevice'],
                'mqttTopic' => $stateTopic,
            ],
        ),
    )
        ->assertSessionHasErrors('validation');

    expect(
        $stateTopic->refresh()->validated_at,
    )->toBeNull();
});
