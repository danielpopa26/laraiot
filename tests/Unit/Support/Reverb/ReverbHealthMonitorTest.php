<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Support\Reverb\ReverbHealthMonitor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set(
        'laraiot.websocket',
        [
            'connection' => 'reverb',
            'health' => [
                'cache_key' => 'laraiot:test:websocket:health',
                'cache_ttl' => 5,
                'timeout' => 1,
                'reconnect_interval' => 3,
            ],
        ],
    );
    config()->set(
        'broadcasting.connections.reverb',
        [
            'driver' => 'reverb',
            'key' => 'laraiot-test-key',
        ],
    );
    config()->set(
        'reverb.default',
        'reverb',
    );
    config()->set(
        'reverb.servers.reverb',
        [
            'host' => '0.0.0.0',
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
                'key' => 'laraiot-test-key',
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'scheme' => 'http',
                ],
            ],
        ],
    );

    Carbon::setTestNow(
        '2026-08-24 14:00:00',
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reports a successful Reverb handshake as live and selectable', function () {
    $receivedServer = null;
    $monitor = new ReverbHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
        static function (array $server) use (&$receivedServer): bool {
            $receivedServer = $server;

            return true;
        },
    );

    $snapshot = $monitor->snapshot();

    expect($snapshot['configured'])->toBeTrue()
        ->and($snapshot['live'])->toBeTrue()
        ->and($snapshot['selectable'])->toBeTrue()
        ->and($snapshot['status'])->toBe('live')
        ->and($snapshot['client'])->toBe([
            'key' => 'laraiot-test-key',
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
        ])
        ->and($receivedServer['host'])->toBe('127.0.0.1')
        ->and($receivedServer['app_key'])->toBe('laraiot-test-key');
});

it('reports configured Reverb as offline when the handshake fails', function () {
    $monitor = new ReverbHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
        static fn (array $server): bool => false,
    );

    $snapshot = $monitor->snapshot();

    expect($snapshot['configured'])->toBeTrue()
        ->and($snapshot['live'])->toBeFalse()
        ->and($snapshot['selectable'])->toBeFalse()
        ->and($snapshot['status'])->toBe('offline')
        ->and($snapshot['label'])->toBe('Server Offline');
});

it('reports incomplete Reverb configuration without running the probe', function () {
    config()->set(
        'reverb.apps.apps',
        [],
    );
    $probeCalls = 0;
    $monitor = new ReverbHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
        static function (array $server) use (&$probeCalls): bool {
            $probeCalls++;

            return true;
        },
    );

    $snapshot = $monitor->snapshot();

    expect($snapshot['configured'])->toBeFalse()
        ->and($snapshot['live'])->toBeFalse()
        ->and($snapshot['status'])->toBe('not_configured')
        ->and($probeCalls)->toBe(0);
});

it('caches normal checks and allows a forced health refresh', function () {
    $probeCalls = 0;
    $monitor = new ReverbHealthMonitor(
        new CacheRepository(new ArrayStore),
        app(ConfigRepository::class),
        static function (array $server) use (&$probeCalls): bool {
            $probeCalls++;

            return true;
        },
    );

    $monitor->snapshot();
    $monitor->snapshot();

    expect($probeCalls)->toBe(1);

    $monitor->snapshot(force: true);

    expect($probeCalls)->toBe(2);
});
