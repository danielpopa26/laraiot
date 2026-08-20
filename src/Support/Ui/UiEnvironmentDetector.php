<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Ui;

use JsonException;

final class UiEnvironmentDetector
{
    public function __construct(
        private readonly ?string $basePath = null,
    ) {}

    /**
     * Inspect the host application without modifying any files.
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

        $packageJson = $this->readJsonFile(
            $this->path('package.json'),
        );

        $viteConfigPath = $this->firstExistingFile([
            'vite.config.ts',
            'vite.config.js',
            'vite.config.mts',
            'vite.config.mjs',
            'vite.config.cts',
            'vite.config.cjs',
        ]);

        $javascriptEntryPath = $this->firstExistingFile([
            'resources/js/app.ts',
            'resources/js/app.js',
            'resources/js/app.tsx',
            'resources/js/app.jsx',
        ]);

        $inertiaViewPath = $this->firstExistingFile([
            'resources/views/app.blade.php',
            'resources/views/inertia.blade.php',
        ]);

        $viteConfig = $this->readTextFile($viteConfigPath);
        $javascriptEntry = $this->readTextFile($javascriptEntryPath);
        $inertiaView = $this->readTextFile($inertiaViewPath);

        $composerPackages = [
            'laravel_framework' => $this->composerPackageStatus(
                'laravel/framework',
                $composerJson,
                $composerLock,
            ),
            'inertia_laravel' => $this->composerPackageStatus(
                'inertiajs/inertia-laravel',
                $composerJson,
                $composerLock,
            ),
        ];

        $nodePackages = [
            'vite' => $this->nodePackageStatus('vite', $packageJson),
            'laravel_vite_plugin' => $this->nodePackageStatus(
                'laravel-vite-plugin',
                $packageJson,
            ),
            'vue' => $this->nodePackageStatus('vue', $packageJson),
            'inertia_vue3' => $this->nodePackageStatus(
                '@inertiajs/vue3',
                $packageJson,
            ),
            'vite_plugin_vue' => $this->nodePackageStatus(
                '@vitejs/plugin-vue',
                $packageJson,
            ),
            'inertia_vite' => $this->nodePackageStatus(
                '@inertiajs/vite',
                $packageJson,
            ),
            'react' => $this->nodePackageStatus('react', $packageJson),
            'inertia_react' => $this->nodePackageStatus(
                '@inertiajs/react',
                $packageJson,
            ),
            'vite_plugin_react' => $this->nodePackageStatus(
                '@vitejs/plugin-react',
                $packageJson,
            ),
            'tailwindcss' => $this->nodePackageStatus(
                'tailwindcss',
                $packageJson,
            ),
            'tailwindcss_vite' => $this->nodePackageStatus(
                '@tailwindcss/vite',
                $packageJson,
            ),
            'lucide_vue_next' => $this->nodePackageStatus(
                'lucide-vue-next',
                $packageJson,
            ),
        ];

        $vite = [
            'config_exists' => $viteConfigPath !== null,
            'config_path' => $viteConfigPath,
            'vue_plugin_configured' => $this->containsAll(
                $viteConfig,
                [
                    '@vitejs/plugin-vue',
                    'vue(',
                ],
            ),
            'react_plugin_configured' => $this->containsAny(
                $viteConfig,
                [
                    '@vitejs/plugin-react',
                    '@vitejs/plugin-react-swc',
                ],
            ),
            'tailwind_plugin_configured' => $this->containsAll(
                $viteConfig,
                [
                    '@tailwindcss/vite',
                    'tailwindcss(',
                ],
            ),
            'inertia_plugin_configured' => $this->containsAll(
                $viteConfig,
                [
                    '@inertiajs/vite',
                    'inertia(',
                ],
            ),
        ];

        $inertia = [
            'entry_exists' => $javascriptEntryPath !== null,
            'entry_path' => $javascriptEntryPath,
            'bootstrap_detected' => str_contains(
                $javascriptEntry,
                'createInertiaApp',
            ),
            'vue_bootstrap_detected' => $this->containsAll(
                $javascriptEntry,
                [
                    'createInertiaApp',
                    '@inertiajs/vue3',
                ],
            ),
            'react_bootstrap_detected' => $this->containsAll(
                $javascriptEntry,
                [
                    'createInertiaApp',
                    '@inertiajs/react',
                ],
            ),
            'root_view_exists' => $inertiaViewPath !== null,
            'root_view_path' => $inertiaViewPath,
            'root_view_has_inertia_directive' => str_contains(
                $inertiaView,
                '@inertia',
            ),
        ];

        return [
            'base_path' => $this->applicationBasePath(),
            'composer_json_exists' => is_file($this->path('composer.json')),
            'composer_lock_exists' => is_file($this->path('composer.lock')),
            'package_json_exists' => is_file($this->path('package.json')),
            'composer' => $composerPackages,
            'node' => $nodePackages,
            'vite' => $vite,
            'inertia' => $inertia,
            'frontend_stack' => $this->detectFrontendStack(
                $nodePackages,
                $inertia,
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

        $lockedPackages = $this->lockedComposerPackages($composerLock);

        return [
            'declared' => array_key_exists($package, $declaredPackages),
            'constraint' => $declaredPackages[$package] ?? null,
            'locked' => array_key_exists($package, $lockedPackages),
            'version' => $lockedPackages[$package] ?? null,
            'installed' => is_dir(
                $this->path('vendor/'.$package),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $packageJson
     * @return array{
     *     declared: bool,
     *     constraint: string|null,
     *     installed: bool
     * }
     */
    private function nodePackageStatus(
        string $package,
        array $packageJson,
    ): array {
        $declaredPackages = array_merge(
            $this->stringMap($packageJson['dependencies'] ?? []),
            $this->stringMap($packageJson['devDependencies'] ?? []),
            $this->stringMap($packageJson['optionalDependencies'] ?? []),
        );

        return [
            'declared' => array_key_exists($package, $declaredPackages),
            'constraint' => $declaredPackages[$package] ?? null,
            'installed' => is_file(
                $this->path('node_modules/'.$package.'/package.json'),
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

    private function readTextFile(?string $path): string
    {
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    /**
     * @param  list<string>  $relativePaths
     */
    private function firstExistingFile(array $relativePaths): ?string
    {
        foreach ($relativePaths as $relativePath) {
            $path = $this->path($relativePath);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAll(string $haystack, array $needles): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $nodePackages
     * @param  array<string, mixed>  $inertia
     */
    private function detectFrontendStack(
        array $nodePackages,
        array $inertia,
    ): string {
        if (($inertia['vue_bootstrap_detected'] ?? false) === true) {
            return 'inertia-vue';
        }

        if (($inertia['react_bootstrap_detected'] ?? false) === true) {
            return 'inertia-react';
        }

        if ($this->nodePackageDeclared($nodePackages, 'inertia_vue3')) {
            return 'inertia-vue-partial';
        }

        if ($this->nodePackageDeclared($nodePackages, 'inertia_react')) {
            return 'inertia-react-partial';
        }

        if ($this->nodePackageDeclared($nodePackages, 'vue')) {
            return 'vue';
        }

        if ($this->nodePackageDeclared($nodePackages, 'react')) {
            return 'react';
        }

        return 'none';
    }

    /**
     * @param  array<string, mixed>  $nodePackages
     */
    private function nodePackageDeclared(
        array $nodePackages,
        string $key,
    ): bool {
        $status = $nodePackages[$key] ?? null;

        if (! is_array($status)) {
            return false;
        }

        return ($status['declared'] ?? false) === true;
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
