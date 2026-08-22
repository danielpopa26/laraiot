<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Models\ActivityLog;
use Danpopa\LaraIoT\Tests\Support\UiTestData;
use Inertia\Testing\AssertableInertia as Assert;

it('renders and filters activity logs', function () {
    $first = UiTestData::deviceTree('first');
    $second = UiTestData::deviceTree('second');

    ActivityLog::query()->create([
        'type' => 'state',
        'logical_device_id' =>
            $first['logicalDevice']->getKey(),
        'title' => 'Humidity updated',
        'description' => 'Humidity is 42%.',
        'data' => [
            'value' => 42,
        ],
        'happened_at' => now()->subMinute(),
    ]);

    ActivityLog::query()->create([
        'type' => 'error',
        'logical_device_id' =>
            $second['logicalDevice']->getKey(),
        'title' => 'Payload failed',
        'description' => 'Invalid payload.',
        'data' => [
            'error' => true,
        ],
        'happened_at' => now(),
    ]);

    $this
        ->get(
            route(
                'laraiot.logs.index',
                [
                    'type' => 'state',
                    'logical_device_id' =>
                        $first['logicalDevice']->getKey(),
                    'search' => 'Humidity',
                ],
            ),
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'laraiot/logs/Index',
                    false,
                )
                ->has('logs.data', 1)
                ->where(
                    'logs.data.0.type',
                    'state',
                )
                ->where(
                    'logs.data.0.title',
                    'Humidity updated',
                )
                ->where(
                    'logs.data.0.logical_device.id',
                    $first['logicalDevice']->getKey(),
                )
                ->where(
                    'filters.search',
                    'Humidity',
                )
                ->where(
                    'filters.type',
                    'state',
                ),
        );
});

it('rejects an invalid activity log date range', function () {
    $this->get(
        route(
            'laraiot.logs.index',
            [
                'from' => '2026-08-22',
                'to' => '2026-08-21',
            ],
        ),
    )
        ->assertSessionHasErrors('to');
});
