<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttPublisher;
use Danpopa\LaraIoT\Exceptions\InvalidMqttCommandException;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Services\MqttCommandService;
use Mockery\MockInterface;

it('publishes a raw MQTT command using the topic settings', function (): void {
    $mqttTopic = (new MqttTopic)->forceFill([
        'purpose' => 'command',
        'topic' => 'cmnd/solar1/POWER',
        'payload_mapping' => [
            'on' => 'ON',
            'off' => 'OFF',
        ],
        'qos' => 1,
        'retain' => true,
        'is_enabled' => true,
        'validated_at' => now(),
    ]);

    $mqttTopic->exists = true;

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);

    $publisher
        ->shouldReceive('publish')
        ->once()
        ->with(
            'cmnd/solar1/POWER',
            'ON',
            1,
            true,
            'laraiot-test',
        );

    $service = new MqttCommandService($publisher);

    $service->send(
        $mqttTopic,
        'on',
        'laraiot-test',
    );
});

it('encodes configured array payloads as JSON', function (): void {
    $mqttTopic = (new MqttTopic)->forceFill([
        'purpose' => 'command',
        'topic' => 'devices/relay/command',
        'payload_mapping' => [
            'on' => [
                'state' => 'ON',
                'level' => 100,
            ],
            'off' => [
                'state' => 'OFF',
                'level' => 0,
            ],
        ],
        'qos' => 0,
        'retain' => false,
        'is_enabled' => true,
        'validated_at' => now(),
    ]);

    $mqttTopic->exists = true;

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);

    $publisher
        ->shouldReceive('publish')
        ->once()
        ->with(
            'devices/relay/command',
            '{"state":"ON","level":100}',
            0,
            false,
            null,
        );

    $service = new MqttCommandService($publisher);

    $service->send(
        $mqttTopic,
        'on',
    );
});

it('rejects topics that are not command topics', function (): void {
    $mqttTopic = (new MqttTopic)->forceFill([
        'purpose' => 'state',
        'topic' => 'tele/solar1/STATE',
        'qos' => 0,
        'retain' => false,
        'is_enabled' => true,
    ]);

    $mqttTopic->exists = true;

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);

    $publisher->shouldNotReceive('publish');

    $service = new MqttCommandService($publisher);

    expect(
        fn () => $service->send($mqttTopic, 'on'),
    )->toThrow(
        InvalidMqttCommandException::class,
        'MQTT commands can only be sent through command topics.',
    );
});

it('rejects disabled command topics', function (): void {
    $mqttTopic = (new MqttTopic)->forceFill([
        'purpose' => 'command',
        'topic' => 'cmnd/solar1/POWER',
        'payload_mapping' => [
            'on' => 'ON',
            'off' => 'OFF',
        ],
        'qos' => 0,
        'retain' => false,
        'is_enabled' => false,
    ]);

    $mqttTopic->exists = true;

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);

    $publisher->shouldNotReceive('publish');

    $service = new MqttCommandService($publisher);

    expect(
        fn () => $service->send($mqttTopic, 'on'),
    )->toThrow(
        InvalidMqttCommandException::class,
        'MQTT commands cannot be sent through a disabled topic.',
    );
});

it('rejects unsaved command topics', function (): void {
    $mqttTopic = (new MqttTopic)->forceFill([
        'purpose' => 'command',
        'topic' => 'cmnd/solar1/POWER',
        'payload_mapping' => [
            'on' => 'ON',
            'off' => 'OFF',
        ],
        'qos' => 0,
        'retain' => false,
        'is_enabled' => true,
        'validated_at' => now(),
    ]);

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);

    $publisher->shouldNotReceive('publish');

    $service = new MqttCommandService($publisher);

    expect(
        fn () => $service->send($mqttTopic, 'on'),
    )->toThrow(
        InvalidMqttCommandException::class,
        'MQTT commands can only be sent through a saved topic.',
    );
});

it('rejects unvalidated command topics', function (): void {
    $mqttTopic = (new MqttTopic)->forceFill([
        'purpose' => 'command',
        'topic' => 'cmnd/solar1/POWER',
        'payload_mapping' => [
            'on' => 'ON',
            'off' => 'OFF',
        ],
        'qos' => 0,
        'retain' => false,
        'is_enabled' => true,
    ]);

    $mqttTopic->exists = true;

    /** @var MqttPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(MqttPublisher::class);

    $publisher->shouldNotReceive('publish');

    $service = new MqttCommandService($publisher);

    expect(
        fn () => $service->send($mqttTopic, 'on'),
    )->toThrow(
        InvalidMqttCommandException::class,
        'MQTT commands cannot be sent through an unvalidated topic.',
    );
});
