<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ApplicationSetting;
use Danpopa\LaraIoT\Support\Reverb\ReverbHealthMonitor;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set(
        'laraiot.websocket.connection',
        'reverb',
    );
    config()->set(
        'broadcasting.default',
        'reverb',
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

    $this->bindReverbHealth = function (bool $live): void {
        $this->app->instance(
            ReverbHealthMonitor::class,
            new ReverbHealthMonitor(
                new CacheRepository(new ArrayStore),
                app(ConfigRepository::class),
                static fn (array $server): bool => $live,
            ),
        );
    };

    ($this->bindReverbHealth)(true);
});

it('renders all application settings options', function () {
    $this
        ->get(route('laraiot.settings.edit'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/settings/Application',
                    false,
                )
                ->where(
                    'settings.application_mode',
                    'polling',
                )
                ->where(
                    'settings.polling_interval',
                    10,
                )
                ->where('websocket.status', 'live')
                ->where('websocket.selectable', true)
                ->where('laraiot.websocket.status', 'live')
                ->has('timezones')
                ->has('dateFormats', 5)
                ->has('timeFormats', 4)
                ->where(
                    'pollingIntervalLimits.min',
                    ApplicationSetting::MIN_POLLING_INTERVAL,
                )
                ->where(
                    'pollingIntervalLimits.max',
                    ApplicationSetting::MAX_POLLING_INTERVAL,
                ),
        );
});

it('does not enable websocket mode while Reverb is offline', function () {
    ($this->bindReverbHealth)(false);

    $this->put(
        route('laraiot.settings.update'),
        [
            'application_mode' => 'websocket',
            'polling_interval' => 10,
            'timezone' => 'UTC',
            'date_format' => 'd M Y',
            'time_format' => 'H:i:s',
        ],
    )
        ->assertSessionHasErrors('application_mode');

    expect(ApplicationSetting::current()->application_mode)
        ->toBe(ApplicationSetting::MODE_POLLING);
});

it('updates application settings through the UI endpoint', function () {
    $this->put(
        route('laraiot.settings.update'),
        [
            'application_mode' => 'websocket',
            'polling_interval' => 5,
            'timezone' => 'Europe/Bucharest',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
        ],
    )
        ->assertSessionHasNoErrors();

    $settings = ApplicationSetting::current();

    expect($settings->application_mode)
        ->toBe('websocket')
        ->and($settings->polling_interval)
        ->toBe(5)
        ->and($settings->timezone)
        ->toBe('Europe/Bucharest')
        ->and($settings->date_format)
        ->toBe('d/m/Y')
        ->and($settings->time_format)
        ->toBe('H:i');
});

it('rejects invalid application settings', function () {
    $this->put(
        route('laraiot.settings.update'),
        [
            'application_mode' => 'invalid',
            'polling_interval' => 0,
            'timezone' => 'Invalid/Timezone',
            'date_format' => 'invalid',
            'time_format' => 'invalid',
        ],
    )
        ->assertSessionHasErrors([
            'application_mode',
            'polling_interval',
            'timezone',
            'date_format',
            'time_format',
        ]);
});
