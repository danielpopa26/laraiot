<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Contracts\MqttPublisher as MqttPublisherContract;
use Danpopa\LaraIoT\Services\MqttCommandService;
use Danpopa\LaraIoT\Services\MqttPublisher;

it('resolves the MQTT publisher contract as a singleton', function (): void {
    $publisher = app(MqttPublisherContract::class);

    expect($publisher)
        ->toBeInstanceOf(MqttPublisher::class)
        ->and(app(MqttPublisherContract::class))
        ->toBe($publisher);
});

it('resolves the MQTT command service from the container', function (): void {
    $service = app(MqttCommandService::class);

    expect($service)
        ->toBeInstanceOf(MqttCommandService::class);
});
