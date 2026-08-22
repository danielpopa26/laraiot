<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Ui;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Process\Process;

final class UiInstaller
{
    /**
     * Node packages used at runtime by the LaraIoT Vue UI.
     *
     * @var list<string>
     */
    private const array NODE_RUNTIME_PACKAGES = [
        'vue',
        '@inertiajs/vue3',
        'lucide-vue-next',
    ];

    /**
     * Node packages used only by the frontend build toolchain.
     *
     * @var list<string>
     */
    private const array NODE_DEV_PACKAGES = [
        'vite',
        'laravel-vite-plugin',
        '@vitejs/plugin-vue',
        'tailwindcss',
        '@tailwindcss/vite',
    ];

    /**
     * Composer requirements supported by the bundled LaraIoT UI.
     *
     * @var array<string, string>
     */
    private const array COMPOSER_REQUIREMENTS = [
        'inertiajs/inertia-laravel' => '^3.0',
    ];

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * Execute an approved UI installation plan.
     *
     * @return list<string> Executed installation steps.
     */
    public function install(
        UiInstallationPlan $plan,
        bool $force = false,
    ): array {
        if ($plan->hasFrameworkConflict()) {
            throw new RuntimeException(
                $plan->frameworkConflictMessage()
                ?? 'The host frontend conflicts with the LaraIoT Vue UI.',
            );
        }

        $steps = [];

        $this->installComposerDependencies($plan, $steps);
        $this->installNodeDependencies($plan, $steps);
        $this->applyConfigurationChanges($plan, $steps);

        $this->ensureTailwindEntryFile($steps);
        $this->publishUi($force);
        $steps[] = 'publish-laraiot-ui';

        $this->enableUi();
        $steps[] = 'enable-laraiot-ui';

        $this->verifyInstallation();
        $steps[] = 'verify-laraiot-ui';

        return $steps;
    }

    /**
     * @param  list<string>  $steps
     */
    private function installComposerDependencies(
        UiInstallationPlan $plan,
        array &$steps,
    ): void {
        $packages = $plan->composerPackagesToAdd();

        if ($packages !== []) {
            $arguments = [
                'composer',
                'require',
                '--no-interaction',
                '--with-all-dependencies',
            ];

            foreach ($packages as $package) {
                $constraint = self::COMPOSER_REQUIREMENTS[$package] ?? null;

                $arguments[] = $constraint === null
                    ? $package
                    : $package.':'.$constraint;
            }

            $this->runProcess($arguments);

            $steps[] = 'composer-require';

            return;
        }

        if ($plan->requiresComposerInstall()) {
            $this->runProcess([
                'composer',
                'install',
                '--no-interaction',
            ]);

            $steps[] = 'composer-install';
        }
    }

    /**
     * @param  list<string>  $steps
     */
    private function installNodeDependencies(
        UiInstallationPlan $plan,
        array &$steps,
    ): void {
        $packagesToAdd = $plan->nodePackagesToAdd();

        $runtimePackages = array_values(
            array_intersect(
                $packagesToAdd,
                self::NODE_RUNTIME_PACKAGES,
            ),
        );

        $devPackages = array_values(
            array_intersect(
                $packagesToAdd,
                self::NODE_DEV_PACKAGES,
            ),
        );

        if ($runtimePackages !== []) {
            $this->runProcess([
                'npm',
                'install',
                '--no-audit',
                '--no-fund',
                ...$runtimePackages,
            ]);

            $steps[] = 'npm-install-runtime';
        }

        if ($devPackages !== []) {
            $this->runProcess([
                'npm',
                'install',
                '--save-dev',
                '--no-audit',
                '--no-fund',
                ...$devPackages,
            ]);

            $steps[] = 'npm-install-dev';
        }

        if (
            $runtimePackages === []
            && $devPackages === []
            && $plan->requiresNpmInstall()
        ) {
            $this->runProcess([
                'npm',
                'install',
                '--no-audit',
                '--no-fund',
            ]);

            $steps[] = 'npm-install';
        }
    }

    /**
     * @param  list<string>  $steps
     */
    private function applyConfigurationChanges(
        UiInstallationPlan $plan,
        array &$steps,
    ): void {
        foreach ($plan->configurationChanges() as $change) {
            match ($change) {
                'create-vite-config' => $this->createViteConfig(),
                'configure-vite-vue-plugin' => $this->configureViteVuePlugin(),
                'configure-vite-tailwind-plugin' => $this->configureViteTailwindPlugin(),
                'configure-inertia-vue-bootstrap' => $this->configureInertiaVueBootstrap(),
                'configure-inertia-root-view' => $this->configureInertiaRootView(),
                default => throw new RuntimeException(
                    'Unsupported LaraIoT UI configuration change: '.$change,
                ),
            };

            $steps[] = $change;
        }
    }

    private function createViteConfig(): void
    {
        $path = $this->path('vite.config.js');

        if (is_file($path)) {
            return;
        }

        $contents = <<<'JS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
});
JS;

        $this->writeFile($path, $contents."\n");
    }

    private function configureViteVuePlugin(): void
    {
        $path = $this->existingViteConfigPath();

        if ($path === null) {
            $this->createViteConfig();

            return;
        }

        $contents = $this->readFile($path);

        if (! str_contains($contents, '@vitejs/plugin-vue')) {
            $contents = "import vue from '@vitejs/plugin-vue';\n".$contents;
        }

        if (! str_contains($contents, 'vue(')) {
            $contents = $this->insertVitePlugin(
                $contents,
                'vue(),',
            );
        }

        $this->writeFile($path, $contents);
    }

    private function configureViteTailwindPlugin(): void
    {
        $path = $this->existingViteConfigPath();

        if ($path === null) {
            $this->createViteConfig();

            return;
        }

        $contents = $this->readFile($path);

        if (! str_contains($contents, '@tailwindcss/vite')) {
            $contents = "import tailwindcss from '@tailwindcss/vite';\n".$contents;
        }

        if (! str_contains($contents, 'tailwindcss(')) {
            $contents = $this->insertVitePlugin(
                $contents,
                'tailwindcss(),',
            );
        }

        $this->writeFile($path, $contents);
    }

    private function configureInertiaVueBootstrap(): void
    {
        $path = $this->path('resources/js/app.js');

        if (is_file($path)) {
            $current = $this->readFile($path);

            if (
                str_contains($current, 'createInertiaApp')
                && str_contains($current, '@inertiajs/vue3')
            ) {
                return;
            }

            $this->backupFile($path);
        }

        $contents = <<<'JS'

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

const pages = import.meta.glob('./pages/**/*.vue');

createInertiaApp({
    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page();
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .mount(el);
    },
});
JS;

        $this->writeFile($path, $contents."\n");
    }

    private function configureInertiaRootView(): void
    {
        $path = $this->path('resources/views/app.blade.php');

        if (is_file($path)) {
            $current = $this->readFile($path);

            if (
                str_contains($current, '@inertia')
                && str_contains($current, '@inertiaHead')
            ) {
                return;
            }

            $this->backupFile($path);
        }

        $contents = <<<'BLADE'
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>

    <body>
        @inertia
    </body>
</html>
BLADE;

        $this->writeFile($path, $contents."\n");
    }

    /**
     * @param  list<string>  $steps
     */
    private function ensureTailwindEntryFile(array &$steps): void
    {
        $path = $this->path('resources/css/app.css');

        if (is_file($path)) {
            return;
        }

        $this->writeFile(
            $path,
            "@import 'tailwindcss';\n",
        );

        $steps[] = 'create-tailwind-entry';
    }

    private function publishUi(bool $force): void
    {
        $arguments = [
            '--tag' => 'laraiot-ui',
        ];

        if ($force) {
            $arguments['--force'] = true;
        }

        $exitCode = Artisan::call(
            'vendor:publish',
            $arguments,
        );

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'LaraIoT UI resources could not be published.',
            );
        }
    }

    private function enableUi(): void
    {
        $envPath = $this->path('.env');

        if (! is_file($envPath)) {
            throw new RuntimeException(
                'The host application .env file could not be found.',
            );
        }

        $contents = $this->readFile($envPath);

        if (preg_match('/^LARAIOT_UI_ENABLED=.*/m', $contents) === 1) {
            $contents = (string) preg_replace(
                '/^LARAIOT_UI_ENABLED=.*/m',
                'LARAIOT_UI_ENABLED=true',
                $contents,
            );
        } else {
            $contents = rtrim($contents).PHP_EOL
                .'LARAIOT_UI_ENABLED=true'
                .PHP_EOL;
        }

        $this->writeFile($envPath, $contents);

        Artisan::call('config:clear');
    }

    private function verifyInstallation(): void
    {
        $environment = (new UiEnvironmentDetector(
            $this->basePath,
        ))->detect();

        $plan = UiInstallationPlan::fromEnvironment($environment);

        if ($plan->hasFrameworkConflict() || $plan->requiresChanges()) {
            throw new RuntimeException(
                'LaraIoT UI verification failed after installation.',
            );
        }

        $requiredFiles = [
            'resources/js/components/laraiot/AppSidebar.vue',
            'resources/js/composables/laraiot/useLaraIoTUrl.js',
            'resources/js/layouts/laraiot/LaraIoTLayout.vue',
            'resources/js/pages/laraiot/Dashboard.vue',
        ];

        foreach ($requiredFiles as $requiredFile) {
            if (! is_file($this->path($requiredFile))) {
                throw new RuntimeException(
                    'LaraIoT UI verification failed: missing '
                    .$requiredFile.'.',
                );
            }
        }

        $envContents = $this->readFile(
            $this->path('.env'),
        );

        if (
            preg_match(
                '/^LARAIOT_UI_ENABLED=true$/m',
                $envContents,
            ) !== 1
        ) {
            throw new RuntimeException(
                'LaraIoT UI verification failed: LARAIOT_UI_ENABLED is not true.',
            );
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runProcess(array $arguments): void
    {
        $process = new Process(
            $arguments,
            $this->basePath,
            null,
            null,
            300.0,
        );

        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $output = trim(
            $process->getErrorOutput()
            .PHP_EOL
            .$process->getOutput(),
        );

        throw new RuntimeException(
            'Command failed: '
            .implode(' ', $arguments)
            .($output === '' ? '' : PHP_EOL.$output),
        );
    }

    private function insertVitePlugin(
        string $contents,
        string $plugin,
    ): string {
        $pattern = '/plugins\s*:\s*\[/';

        if (preg_match($pattern, $contents) !== 1) {
            throw new RuntimeException(
                'Unable to update vite.config.js automatically. The plugins array was not found.',
            );
        }

        $updated = preg_replace(
            $pattern,
            "plugins: [\n        ".$plugin,
            $contents,
            1,
        );

        if (! is_string($updated)) {
            throw new RuntimeException(
                'Unable to update vite.config.js automatically.',
            );
        }

        return $updated;
    }

    private function existingViteConfigPath(): ?string
    {
        foreach (
            [
                'vite.config.ts',
                'vite.config.js',
                'vite.config.mts',
                'vite.config.mjs',
                'vite.config.cts',
                'vite.config.cjs',
            ] as $relativePath
        ) {
            $path = $this->path($relativePath);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function backupFile(string $path): void
    {
        $backupPath = $path.'.laraiot.bak';

        if (is_file($backupPath)) {
            return;
        }

        if (! copy($path, $backupPath)) {
            throw new RuntimeException(
                'Unable to create backup file: '.$backupPath,
            );
        }
    }

    private function readFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                'Unable to read file: '.$path,
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read file: '.$path,
            );
        }

        return $contents;
    }

    private function writeFile(
        string $path,
        string $contents,
    ): void {
        $directory = dirname($path);

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create directory: '.$directory,
            );
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(
                'Unable to write file: '.$path,
            );
        }
    }

    private function path(string $relativePath): string
    {
        return rtrim(
            $this->basePath,
            DIRECTORY_SEPARATOR,
        )
            .DIRECTORY_SEPARATOR
            .str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $relativePath,
            );
    }
}
