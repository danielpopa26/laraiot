<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$prefix = trim(
    (string) config('laraiot.ui.prefix', 'laraiot'),
    '/',
);

$middleware = config('laraiot.ui.middleware', ['web']);

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('laraiot.')
    ->group(function () use ($prefix): void {
        Route::get('/', function () use ($prefix) {
            return Inertia::render('laraiot/Dashboard', [
                'laraiot' => [
                    'ui' => [
                        'baseUrl' => '/'.$prefix,
                    ],
                    'mode' => config(
                        'laraiot.mode',
                        'polling',
                    ),
                    'mqtt' => [
                        'connected' => false,
                    ],
                ],
            ]);
        })->name('dashboard');
    });
