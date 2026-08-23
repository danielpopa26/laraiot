<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Support\Reverb\ReverbEnvironmentDetector;

/**
 * @param  array<string, mixed>  $composerJson
 * @param  list<array{name: string, version: string}>  $lockedPackages
 */
function createReverbDetectorFixture(
    array $composerJson,
    array $lockedPackages,
): string {
    $basePath = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR
        .'laraiot-reverb-detector-'
        .bin2hex(random_bytes(8));

    mkdir($basePath, 0755, true);

    file_put_contents(
        $basePath.'/composer.json',
        json_encode(
            $composerJson,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
        ),
    );

    file_put_contents(
        $basePath.'/composer.lock',
        json_encode(
            [
                'packages' => $lockedPackages,
                'packages-dev' => [],
            ],
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
        ),
    );

    return $basePath;
}

function removeReverbDetectorFixture(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS,
        ),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

it('detects when a clean host may need Composer dependency resolution for Reverb', function (): void {
    $basePath = createReverbDetectorFixture(
        [
            'require' => [
                'php' => '^8.3',
            ],
        ],
        [
            [
                'name' => 'guzzlehttp/guzzle',
                'version' => '8.0.2',
            ],
            [
                'name' => 'guzzlehttp/psr7',
                'version' => '3.0.0',
            ],
            [
                'name' => 'guzzlehttp/promises',
                'version' => '3.0.1',
            ],
        ],
    );

    try {
        foreach (
            [
                'vendor/guzzlehttp/guzzle',
                'vendor/guzzlehttp/psr7',
                'vendor/guzzlehttp/promises',
            ] as $directory
        ) {
            mkdir($basePath.'/'.$directory, 0755, true);
        }

        $environment = (new ReverbEnvironmentDetector(
            $basePath,
        ))->detect();

        expect($environment['configured'])->toBeFalse()
            ->and($environment['dependency_resolution_may_be_required'])
            ->toBeTrue()
            ->and($environment['reverb']['installed'])->toBeFalse()
            ->and($environment['guzzle']['psr7']['version'])
            ->toBe('3.0.0')
            ->and($environment['guzzle']['guzzle']['version'])
            ->toBe('8.0.2');
    } finally {
        removeReverbDetectorFixture($basePath);
    }
});

it('detects an installed and configured Reverb environment', function (): void {
    $basePath = createReverbDetectorFixture(
        [
            'require' => [
                'laravel/reverb' => '^1.11',
            ],
        ],
        [
            [
                'name' => 'laravel/reverb',
                'version' => 'v1.11.1',
            ],
            [
                'name' => 'guzzlehttp/guzzle',
                'version' => '7.15.3',
            ],
            [
                'name' => 'guzzlehttp/psr7',
                'version' => '2.13.0',
            ],
            [
                'name' => 'guzzlehttp/promises',
                'version' => '2.5.2',
            ],
        ],
    );

    try {
        foreach (
            [
                'vendor/laravel/reverb',
                'vendor/guzzlehttp/guzzle',
                'vendor/guzzlehttp/psr7',
                'vendor/guzzlehttp/promises',
                'config',
                'routes',
            ] as $directory
        ) {
            mkdir($basePath.'/'.$directory, 0755, true);
        }

        file_put_contents(
            $basePath.'/config/broadcasting.php',
            '<?php return [];',
        );
        file_put_contents(
            $basePath.'/config/reverb.php',
            '<?php return [];',
        );
        file_put_contents(
            $basePath.'/routes/channels.php',
            '<?php',
        );
        file_put_contents(
            $basePath.'/.env',
            "BROADCAST_CONNECTION=reverb\nREVERB_APP_ID=12345\n",
        );

        $environment = (new ReverbEnvironmentDetector(
            $basePath,
        ))->detect();

        expect($environment['configured'])->toBeTrue()
            ->and($environment['dependency_resolution_may_be_required'])
            ->toBeFalse()
            ->and($environment['reverb']['installed'])->toBeTrue()
            ->and($environment['reverb']['version'])->toBe('v1.11.1')
            ->and($environment['configuration']['broadcast_connection'])
            ->toBe('reverb')
            ->and($environment['configuration']['reverb_app_id'])
            ->toBe('12345');
    } finally {
        removeReverbDetectorFixture($basePath);
    }
});

it('does not mark Reverb ready when the broadcast driver is not enabled', function (): void {
    $basePath = createReverbDetectorFixture(
        [
            'require' => [
                'laravel/reverb' => '^1.11',
            ],
        ],
        [
            [
                'name' => 'laravel/reverb',
                'version' => 'v1.11.1',
            ],
        ],
    );

    try {
        foreach (
            [
                'vendor/laravel/reverb',
                'config',
                'routes',
            ] as $directory
        ) {
            mkdir($basePath.'/'.$directory, 0755, true);
        }

        file_put_contents(
            $basePath.'/config/broadcasting.php',
            '<?php return [];',
        );
        file_put_contents(
            $basePath.'/config/reverb.php',
            '<?php return [];',
        );
        file_put_contents(
            $basePath.'/routes/channels.php',
            '<?php',
        );
        file_put_contents(
            $basePath.'/.env',
            "BROADCAST_CONNECTION=log\nREVERB_APP_ID=12345\n",
        );

        $environment = (new ReverbEnvironmentDetector(
            $basePath,
        ))->detect();

        expect($environment['configured'])->toBeFalse()
            ->and($environment['configuration']['broadcast_connection'])
            ->toBe('log');
    } finally {
        removeReverbDetectorFixture($basePath);
    }
});
