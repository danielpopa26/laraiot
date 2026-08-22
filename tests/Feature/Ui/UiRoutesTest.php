<?php

declare(strict_types=1);

use Illuminate\Routing\Route;

it('registers the complete optional UI route set in the test environment', function () {
    $routes = collect(
        app('router')->getRoutes()->getRoutes(),
    )
        ->filter(
            fn (Route $route): bool => str_starts_with(
                (string) $route->getName(),
                'laraiot.',
            ),
        )
        ->values();

    expect($routes)
        ->toHaveCount(30)
        ->and(
            route(
                'laraiot.dashboard',
                absolute: false,
            ),
        )
        ->toBe('/laraiot');
});

it('keeps the UI routes protected by authentication', function () {
    $dashboard = app('router')
        ->getRoutes()
        ->getByName('laraiot.dashboard');

    expect($dashboard)
        ->not->toBeNull()
        ->and($dashboard->middleware())
        ->toContain('web', 'auth');
});
