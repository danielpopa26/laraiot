<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ApplicationSetting;
use Inertia\Testing\AssertableInertia as Assert;

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
