<?php

declare(strict_types=1);

use Danpopa\LaraIoT\LaraIoT;

it('resolves the singleton', function () {
    expect(app(LaraIoT::class))->toBeInstanceOf(LaraIoT::class);
});

it('returns the same instance from the container', function () {
    expect(app(LaraIoT::class))->toBe(app(LaraIoT::class));
});

it('merges the package config', function () {
    expect(config('laraiot.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('laraiot::messages.placeholder'))->toBe('LaraIoT placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('laraiot::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('laraiot:placeholder')
        ->expectsOutputToContain('LaraIoT placeholder command executed.')
        ->assertSuccessful();
});
