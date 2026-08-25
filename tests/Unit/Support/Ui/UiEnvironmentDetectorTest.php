<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Support\Ui\UiEnvironmentDetector;
use Danpopa\LaraIoT\Support\Ui\UiInstallationPlan;
use Illuminate\Filesystem\Filesystem;

it('detects the Laravel 13 Inertia 3 root view and TypeScript entry', function (): void {
    $basePath = sys_get_temp_dir()
        .'/laraiot-ui-detector-'
        .bin2hex(random_bytes(8));

    $filesystem = new Filesystem;

    $filesystem->ensureDirectoryExists(
        $basePath.'/resources/js',
    );
    $filesystem->ensureDirectoryExists(
        $basePath.'/resources/views',
    );

    try {
        $filesystem->put(
            $basePath.'/resources/js/app.ts',
            <<<'TYPESCRIPT'
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({});
TYPESCRIPT,
        );

        $filesystem->put(
            $basePath.'/resources/views/app.blade.php',
            <<<'BLADE'
<x-inertia::head>
    <title>Laravel</title>
</x-inertia::head>
<x-inertia::app />
BLADE,
        );

        $environment = (new UiEnvironmentDetector($basePath))->detect();
        $inertia = $environment['inertia'];

        expect($inertia)->toBeArray()
            ->and($inertia['entry_path'])->toBe(
                $basePath.'/resources/js/app.ts',
            )
            ->and($inertia['root_view_has_inertia_directive'])->toBeTrue()
            ->and($environment['frontend_stack'])->toBe('inertia-vue');

        $plan = UiInstallationPlan::fromEnvironment($environment);

        expect($plan->configurationChanges())
            ->not->toContain('configure-inertia-root-view');
    } finally {
        $filesystem->deleteDirectory($basePath);
    }
});
