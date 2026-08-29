<?php

declare(strict_types=1);

it('keeps Laravel Reverb optional for package consumers', function (): void {
    $rootPath = dirname(__DIR__, 4);
    $composer = json_decode(
        (string) file_get_contents($rootPath.'/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['require'])->not->toHaveKey('laravel/reverb')
        ->and($composer['require-dev']['laravel/reverb'] ?? null)
        ->toBe('^1.11')
        ->and($composer['suggest']['laravel/reverb'] ?? null)
        ->toBe('Required only when LaraIoT WebSocket mode is enabled.');
});
