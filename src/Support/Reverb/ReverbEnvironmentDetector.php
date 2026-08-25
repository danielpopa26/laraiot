<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Reverb;

use JsonException;

final class ReverbEnvironmentDetector
{
    public function __construct(
        private readonly ?string $basePath = null,
    ) {}

    /**
     * Inspect the host application without changing it.
     *
     * @return array<string, mixed>
     */
    public function detect(): array
    {
        $composerJson = $this->readJsonFile(
            $this->path('composer.json'),
        );

        $composerLock = $this->readJsonFile(
            $this->path('composer.lock'),
        );

        $reverb = $this->composerPackageStatus(
            'laravel/reverb',
            $composerJson,
            $composerLock,
        );

        $guzzle = $this->composerPackageStatus(
            'guzzlehttp/guzzle',
            $composerJson,
            $composerLock,
        );

        $psr7 = $this->composerPackageStatus(
            'guzzlehttp/psr7',
            $composerJson,
            $composerLock,
        );

        $promises = $this->composerPackageStatus(
            'guzzlehttp/promises',
            $composerJson,
            $composerLock,
        );

        $configuration = [
            'broadcasting_config_exists' => is_file(
                $this->path('config/broadcasting.php'),
            ),
            'reverb_config_exists' => is_file(
                $this->path('config/reverb.php'),
            ),
            'channels_route_exists' => is_file(
                $this->path('routes/channels.php'),
            ),
            'broadcast_connection' => $this->readEnvValue(
                'BROADCAST_CONNECTION',
            ),
            'reverb_app_id' => $this->readEnvValue(
                'REVERB_APP_ID',
            ),
        ];

        $configured = $reverb['installed'] === true
            && $configuration['broadcasting_config_exists'] === true
            && $configuration['reverb_config_exists'] === true
            && $configuration['channels_route_exists'] === true
            && $configuration['broadcast_connection'] === 'reverb'
            && is_string($configuration['reverb_app_id'])
            && $configuration['reverb_app_id'] !== '';

        return [
            'base_path' => $this->applicationBasePath(),
            'composer_json_exists' => is_file(
                $this->path('composer.json'),
            ),
            'composer_lock_exists' => is_file(
                $this->path('composer.lock'),
            ),
            'reverb' => $reverb,
            'guzzle' => [
                'guzzle' => $guzzle,
                'psr7' => $psr7,
                'promises' => $promises,
            ],
            'configuration' => $configuration,
            'configured' => $configured,
            'dependency_resolution_may_be_required' => $this->dependencyResolutionMayBeRequired(
                $guzzle,
                $psr7,
                $promises,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @param  array<string, mixed>  $composerLock
     * @return array{
     *     declared: bool,
     *     constraint: string|null,
     *     locked: bool,
     *     version: string|null,
     *     installed: bool
     * }
     */
    private function composerPackageStatus(
        string $package,
        array $composerJson,
        array $composerLock,
    ): array {
        $declaredPackages = array_merge(
            $this->stringMap($composerJson['require'] ?? []),
            $this->stringMap($composerJson['require-dev'] ?? []),
        );

        $lockedPackages = $this->lockedComposerPackages(
            $composerLock,
        );

        return [
            'declared' => array_key_exists(
                $package,
                $declaredPackages,
            ),
            'constraint' => $declaredPackages[$package] ?? null,
            'locked' => array_key_exists(
                $package,
                $lockedPackages,
            ),
            'version' => $lockedPackages[$package] ?? null,
            'installed' => is_dir(
                $this->path('vendor/'.$package),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $composerLock
     * @return array<string, string>
     */
    private function lockedComposerPackages(array $composerLock): array
    {
        $packages = [];

        foreach (['packages', 'packages-dev'] as $section) {
            $entries = $composerLock[$section] ?? [];

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $name = $entry['name'] ?? null;
                $version = $entry['version'] ?? null;

                if (! is_string($name) || ! is_string($version)) {
                    continue;
                }

                $packages[$name] = $version;
            }
        }

        return $packages;
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        try {
            $decoded = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function readEnvValue(string $key): ?string
    {
        $path = $this->path('.env');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        if (
            preg_match(
                '/^'.preg_quote($key, '/').'=(.*)$/m',
                $contents,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $value = trim((string) $matches[1]);

        if ($value === '') {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $guzzle
     * @param  array<string, mixed>  $psr7
     * @param  array<string, mixed>  $promises
     */
    private function dependencyResolutionMayBeRequired(
        array $guzzle,
        array $psr7,
        array $promises,
    ): bool {
        return $this->majorVersion($guzzle['version'] ?? null) >= 8
            || $this->majorVersion($psr7['version'] ?? null) >= 3
            || $this->majorVersion($promises['version'] ?? null) >= 3;
    }

    private function majorVersion(mixed $version): int
    {
        if (! is_string($version) || $version === '') {
            return 0;
        }

        $normalized = ltrim($version, 'vV');

        if (preg_match('/^(\d+)/', $normalized, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function path(string $relativePath): string
    {
        return $this->applicationBasePath()
            .DIRECTORY_SEPARATOR
            .str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $relativePath,
            );
    }

    private function applicationBasePath(): string
    {
        if ($this->basePath !== null) {
            return rtrim(
                $this->basePath,
                DIRECTORY_SEPARATOR,
            );
        }

        if (function_exists('base_path')) {
            return rtrim(
                base_path(),
                DIRECTORY_SEPARATOR,
            );
        }

        return rtrim(
            (string) getcwd(),
            DIRECTORY_SEPARATOR,
        );
    }
}
