<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Exceptions\InvalidMqttPayloadException;
use Danpopa\LaraIoT\Services\MqttPayloadProcessor;

it('processes a raw MQTT payload', function () {
    $result = $this->app
        ->make(MqttPayloadProcessor::class)
        ->process('ON', [
            'format' => 'raw',
        ]);

    expect($result)
        ->toBe([
            'configured_format' => 'raw',
            'detected_format' => 'raw',
            'extracted_value' => 'ON',
            'normalized_value' => 'ON',
        ]);
});

it('maps a raw MQTT payload to a normalized value', function () {
    $result = $this->app
        ->make(MqttPayloadProcessor::class)
        ->process('ON', [
            'format' => 'raw',
            'value_map' => [
                'ON' => true,
                'OFF' => false,
            ],
        ]);

    expect($result['extracted_value'])->toBe('ON')
        ->and($result['normalized_value'])->toBeTrue();
});

it('extracts a nested value from a JSON MQTT payload', function () {
    $result = $this->app
        ->make(MqttPayloadProcessor::class)
        ->process(
            '{"ENERGY":{"Power":125.5}}',
            [
                'format' => 'json',
                'value_path' => 'ENERGY.Power',
            ],
        );

    expect($result)
        ->toBe([
            'configured_format' => 'json',
            'detected_format' => 'json',
            'extracted_value' => 125.5,
            'normalized_value' => 125.5,
        ]);
});

it('maps an extracted JSON value to a normalized value', function () {
    $result = $this->app
        ->make(MqttPayloadProcessor::class)
        ->process(
            '{"state":"ON"}',
            [
                'format' => 'json',
                'value_path' => 'state',
                'value_map' => [
                    'ON' => true,
                    'OFF' => false,
                ],
            ],
        );

    expect($result['extracted_value'])->toBe('ON')
        ->and($result['normalized_value'])->toBeTrue();
});

it('rejects an invalid JSON MQTT payload', function () {
    expect(
        fn () => $this->app
            ->make(MqttPayloadProcessor::class)
            ->process(
                '{"state":',
                [
                    'format' => 'json',
                    'value_path' => 'state',
                ],
            ),
    )->toThrow(
        InvalidMqttPayloadException::class,
        'The received payload is not valid JSON.',
    );
});

it('requires a value path for a JSON MQTT payload', function () {
    expect(
        fn () => $this->app
            ->make(MqttPayloadProcessor::class)
            ->process(
                '{"state":"ON"}',
                [
                    'format' => 'json',
                ],
            ),
    )->toThrow(
        InvalidMqttPayloadException::class,
        'A value path is required for a JSON payload.',
    );
});

it('rejects a missing JSON value path', function () {
    expect(
        fn () => $this->app
            ->make(MqttPayloadProcessor::class)
            ->process(
                '{"state":"ON"}',
                [
                    'format' => 'json',
                    'value_path' => 'device.state',
                ],
            ),
    )->toThrow(
        InvalidMqttPayloadException::class,
        'The value path "device.state" was not found in the received JSON payload.',
    );
});

it('detects JSON received by a topic configured as raw', function () {
    expect(
        fn () => $this->app
            ->make(MqttPayloadProcessor::class)
            ->process(
                '{"state":"ON"}',
                [
                    'format' => 'raw',
                ],
            ),
    )->toThrow(
        InvalidMqttPayloadException::class,
        'The received payload appears to be JSON, but the topic is configured as RAW.',
    );
});

it('rejects an unsupported MQTT payload format', function () {
    expect(
        fn () => $this->app
            ->make(MqttPayloadProcessor::class)
            ->process('ON', [
                'format' => 'xml',
            ]),
    )->toThrow(
        InvalidMqttPayloadException::class,
        'The payload format "xml" is not supported.',
    );
});

it('requires an MQTT payload format', function () {
    expect(
        fn () => $this->app
            ->make(MqttPayloadProcessor::class)
            ->process('ON', []),
    )->toThrow(
        InvalidMqttPayloadException::class,
        'The payload format is not configured.',
    );
});
