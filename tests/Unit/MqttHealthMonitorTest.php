<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Services\MqttHealthMonitor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set(
        'laraiot.mqtt.health.cache_key',
        'laraiot:test:mqtt:health',
    );
    config()->set(
        'laraiot.mqtt.health.stale_after',
        20,
    );

    $this->healthMonitor = new MqttHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
    );

    Carbon::setTestNow(
        '2026-08-24 12:00:00',
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reports unknown before the listener has provided health data', function () {
    $snapshot = $this->healthMonitor->snapshot();

    expect($snapshot['connected'])->toBeNull()
        ->and($snapshot['status'])->toBe('unknown')
        ->and($snapshot['label'])->toBe('Unknown');
});

it('reports a recent listener heartbeat as connected', function () {
    $this->healthMonitor->markConnected(3);

    $snapshot = $this->healthMonitor->snapshot();

    expect($snapshot['connected'])->toBeTrue()
        ->and($snapshot['status'])->toBe('connected')
        ->and($snapshot['label'])->toBe('Connected')
        ->and($snapshot['subscriptions'])->toBe(3)
        ->and($snapshot['heartbeat_at'])->not->toBeNull();
});

it('reports an expired listener heartbeat as offline', function () {
    $this->healthMonitor->markConnected(2);

    Carbon::setTestNow(
        '2026-08-24 12:00:21',
    );

    $snapshot = $this->healthMonitor->snapshot();

    expect($snapshot['connected'])->toBeFalse()
        ->and($snapshot['status'])->toBe('offline')
        ->and($snapshot['label'])->toBe('Listener Offline');
});

it('reports the latest MQTT connection failure', function () {
    $this->healthMonitor->markDisconnected(
        'Unable to connect to the MQTT broker.',
    );

    $snapshot = $this->healthMonitor->snapshot();

    expect($snapshot['connected'])->toBeFalse()
        ->and($snapshot['status'])->toBe('disconnected')
        ->and($snapshot['label'])->toBe('Disconnected')
        ->and($snapshot['error'])
        ->toBe('Unable to connect to the MQTT broker.');
});

it('preserves message activity when the listener stops', function () {
    $this->healthMonitor->markConnected(1);
    $this->healthMonitor->markMessageReceived(1);
    $this->healthMonitor->markStopped();

    $snapshot = $this->healthMonitor->snapshot();

    expect($snapshot['connected'])->toBeFalse()
        ->and($snapshot['status'])->toBe('offline')
        ->and($snapshot['last_message_at'])->not->toBeNull();
});
