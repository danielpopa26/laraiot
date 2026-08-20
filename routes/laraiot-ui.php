<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(
    (array) config('laraiot.ui.middleware', ['web']),
)
    ->prefix(
        trim(
            (string) config('laraiot.ui.prefix', 'laraiot'),
            '/',
        ),
    )
    ->as('laraiot.')
    ->group(function (): void {
        Route::get('/', function () {
            return Inertia::render('LaraIoT/Dashboard', [
                'laraiot' => [
                    'mode' => (string) config(
                        'laraiot.mode',
                        'polling',
                    ),
                    'prefix' => trim(
                        (string) config(
                            'laraiot.ui.prefix',
                            'laraiot',
                        ),
                        '/',
                    ),
                ],
            ]);
        })->name('dashboard');
    });
