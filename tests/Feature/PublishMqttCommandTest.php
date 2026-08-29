<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

it('registers the MQTT publish command', function (): void {
    $exitCode = Artisan::call('list', [
        'namespace' => 'laraiot',
        '--raw' => true,
    ]);

    expect($exitCode)
        ->toBe(Command::SUCCESS)
        ->and(Artisan::output())
        ->toContain('laraiot:publish');
});
