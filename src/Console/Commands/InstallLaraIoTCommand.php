<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Danpopa\LaraIoT\Support\Ui\UiEnvironmentDetector;
use Danpopa\LaraIoT\Support\Ui\UiInstallationPlan;
use Danpopa\LaraIoT\Support\Ui\UiInstaller;
use Illuminate\Console\Command;
use Throwable;

class InstallLaraIoTCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laraiot:install
                            {--ui : Install the optional LaraIoT Vue UI}
                            {--force : Overwrite existing LaraIoT UI files when UI installation is enabled}';

    /**
     * The command description.
     */
    protected $description = 'Install LaraIoT core resources and optionally the Vue UI.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing LaraIoT...');

        $this->publishLaraIoTResources();

        $uiStatus = 'not-requested';

        if ((bool) $this->option('ui')) {
            $uiStatus = $this->installUi();

            if ($uiStatus === 'failed') {
                $this->newLine();

                $this->components->error(
                    'LaraIoT installation stopped because the requested Vue UI could not be installed.',
                );

                return self::FAILURE;
            }
        } elseif ((bool) $this->option('force')) {
            $this->components->warn(
                'The --force option only applies to the optional UI and is ignored without --ui.',
            );
        }

        $this->installReverb();

        $this->newLine();
        $this->components->info('LaraIoT installed successfully.');

        match ($uiStatus) {
            'installed' => $this->components->info(
                'LaraIoT Vue UI installed and enabled successfully.',
            ),
            'skipped' => $this->components->info(
                'LaraIoT Vue UI installation was skipped. The core remains available.',
            ),
            'conflict' => $this->components->warn(
                'LaraIoT Vue UI was not installed because the host application uses a different frontend stack. The LaraIoT core remains available.',
            ),
            'failed' => $this->components->error(
                'LaraIoT Vue UI installation failed. The LaraIoT core remains available.',
            ),
            default => $this->components->info(
                'The optional Vue UI was not requested. Run "php artisan laraiot:install --ui" to install it.',
            ),
        };

        $this->components->info(
            'Run "php artisan migrate" to create the LaraIoT database tables.',
        );

        $this->components->info(
            'WebSocket mode uses Laravel Reverb. Start it with "php artisan reverb:start" when websocket mode is enabled.',
        );

        return self::SUCCESS;
    }

    private function publishLaraIoTResources(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'laraiot-config',
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'laraiot-migrations',
        ]);
    }

    private function installUi(): string
    {
        $this->newLine();
        $this->components->info('Inspecting LaraIoT UI environment...');

        $environment = (new UiEnvironmentDetector(base_path()))->detect();

        $this->displayUiEnvironment($environment);

        $plan = UiInstallationPlan::fromEnvironment($environment);

        $this->newLine();
        $this->components->info('LaraIoT UI installation plan');

        $this->displayUiInstallationPlan($plan);

        if ($plan->hasFrameworkConflict()) {
            $this->newLine();

            $message = $plan->frameworkConflictMessage();

            if ($message !== null) {
                $this->components->warn($message);
            }

            $this->components->warn(
                'The bundled LaraIoT UI supports Vue 3 + Inertia. Use the LaraIoT core without the bundled UI when the host application uses another frontend technology.',
            );

            return 'conflict';
        }

        if ($plan->requiresChanges()) {
            $this->newLine();

            $approved = $this->confirm(
                'The changes listed above are required for the LaraIoT Vue UI. Install and configure them now?',
                false,
            );

            if (! $approved) {
                $this->components->info(
                    'No frontend changes were made.',
                );

                return 'skipped';
            }
        } else {
            $this->newLine();
            $this->components->info(
                'The host application already satisfies the LaraIoT UI requirements.',
            );
        }

        $this->newLine();
        $this->components->info('Installing LaraIoT Vue UI...');

        try {
            $steps = (new UiInstaller(base_path()))->install(
                $plan,
                (bool) $this->option('force'),
            );
        } catch (Throwable $exception) {
            $this->components->error(
                'LaraIoT Vue UI could not be installed.',
            );

            $this->components->error(
                $exception->getMessage(),
            );

            return 'failed';
        }

        $this->displayExecutedUiSteps($steps);

        return 'installed';
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function displayUiEnvironment(array $environment): void
    {
        $composer = $environment['composer'] ?? [];
        $node = $environment['node'] ?? [];
        $vite = $environment['vite'] ?? [];
        $inertia = $environment['inertia'] ?? [];

        $composer = is_array($composer) ? $composer : [];
        $node = is_array($node) ? $node : [];
        $vite = is_array($vite) ? $vite : [];
        $inertia = is_array($inertia) ? $inertia : [];

        $this->table(
            ['Requirement', 'Status', 'Details'],
            [
                [
                    'Laravel framework',
                    $this->composerStatus(
                        $composer['laravel_framework'] ?? null,
                    ),
                    $this->composerDetails(
                        $composer['laravel_framework'] ?? null,
                    ),
                ],
                [
                    'Inertia Laravel',
                    $this->composerStatus(
                        $composer['inertia_laravel'] ?? null,
                    ),
                    $this->composerDetails(
                        $composer['inertia_laravel'] ?? null,
                    ),
                ],
                [
                    'Vite',
                    $this->nodeStatus($node['vite'] ?? null),
                    $this->nodeDetails($node['vite'] ?? null),
                ],
                [
                    'Vue 3',
                    $this->nodeStatus($node['vue'] ?? null),
                    $this->nodeDetails($node['vue'] ?? null),
                ],
                [
                    'Inertia Vue 3',
                    $this->nodeStatus(
                        $node['inertia_vue3'] ?? null,
                    ),
                    $this->nodeDetails(
                        $node['inertia_vue3'] ?? null,
                    ),
                ],
                [
                    'Vite Vue plugin',
                    $this->nodeStatus(
                        $node['vite_plugin_vue'] ?? null,
                    ),
                    $this->nodeDetails(
                        $node['vite_plugin_vue'] ?? null,
                    ),
                ],
                [
                    'Vue plugin configured',
                    $this->booleanStatus(
                        $vite['vue_plugin_configured'] ?? false,
                    ),
                    $this->pathDetails(
                        $vite['config_path'] ?? null,
                    ),
                ],
                [
                    'Inertia bootstrap',
                    $this->booleanStatus(
                        $inertia['bootstrap_detected'] ?? false,
                    ),
                    $this->pathDetails(
                        $inertia['entry_path'] ?? null,
                    ),
                ],
                [
                    'Tailwind CSS',
                    $this->nodeStatus(
                        $node['tailwindcss'] ?? null,
                    ),
                    $this->nodeDetails(
                        $node['tailwindcss'] ?? null,
                    ),
                ],
                [
                    'Tailwind Vite plugin',
                    $this->nodeStatus(
                        $node['tailwindcss_vite'] ?? null,
                    ),
                    $this->nodeDetails(
                        $node['tailwindcss_vite'] ?? null,
                    ),
                ],
                [
                    'Lucide Vue',
                    $this->nodeStatus(
                        $node['lucide_vue_next'] ?? null,
                    ),
                    $this->nodeDetails(
                        $node['lucide_vue_next'] ?? null,
                    ),
                ],
            ],
        );

        $this->line(
            'Detected frontend stack: '
            .(string) ($environment['frontend_stack'] ?? 'unknown'),
        );
    }

    private function displayUiInstallationPlan(
        UiInstallationPlan $plan,
    ): void {
        $this->line(
            'Frontend stack: '.$plan->frontendStack(),
        );

        $this->displayPlanSection(
            'Composer packages to add',
            $plan->composerPackagesToAdd(),
        );

        $this->displayPlanSection(
            'Node packages to add',
            $plan->nodePackagesToAdd(),
        );

        $this->displayPlanSection(
            'Declared but not installed',
            $plan->declaredButNotInstalled(),
        );

        $this->displayPlanSection(
            'Missing requirements',
            $plan->missingRequirements(),
        );

        $this->displayPlanSection(
            'Already installed',
            $plan->alreadySatisfied(),
        );

        $this->displayPlanSection(
            'Configuration changes',
            $plan->configurationChanges(),
        );

        $this->table(
            ['Plan flag', 'Value'],
            [
                [
                    'Composer install required',
                    $this->yesNo($plan->requiresComposerInstall()),
                ],
                [
                    'NPM install required',
                    $this->yesNo($plan->requiresNpmInstall()),
                ],
                [
                    'Framework conflict',
                    $this->yesNo($plan->hasFrameworkConflict()),
                ],
                [
                    'Changes required',
                    $this->yesNo($plan->requiresChanges()),
                ],
                [
                    'Can publish UI immediately',
                    $this->yesNo($plan->canPublishUiImmediately()),
                ],
            ],
        );
    }

    /**
     * @param  list<string>  $items
     */
    private function displayPlanSection(
        string $title,
        array $items,
    ): void {
        $this->newLine();
        $this->line($title.':');

        if ($items === []) {
            $this->line('  - none');

            return;
        }

        foreach ($items as $item) {
            $this->line('  - '.$item);
        }
    }

    /**
     * @param  list<string>  $steps
     */
    private function displayExecutedUiSteps(array $steps): void
    {
        $this->newLine();
        $this->components->info('LaraIoT UI installation steps completed:');

        foreach ($steps as $step) {
            $this->line('  - '.$step);
        }
    }

    private function composerStatus(mixed $status): string
    {
        if (! is_array($status)) {
            return 'missing';
        }

        if (($status['installed'] ?? false) === true) {
            return 'installed';
        }

        if (($status['locked'] ?? false) === true) {
            return 'locked';
        }

        if (($status['declared'] ?? false) === true) {
            return 'declared only';
        }

        return 'missing';
    }

    private function composerDetails(mixed $status): string
    {
        if (! is_array($status)) {
            return '-';
        }

        $version = $status['version'] ?? null;
        $constraint = $status['constraint'] ?? null;

        if (is_string($version) && $version !== '') {
            return $version;
        }

        if (is_string($constraint) && $constraint !== '') {
            return $constraint;
        }

        return '-';
    }

    private function nodeStatus(mixed $status): string
    {
        if (! is_array($status)) {
            return 'missing';
        }

        if (($status['installed'] ?? false) === true) {
            return 'installed';
        }

        if (($status['declared'] ?? false) === true) {
            return 'declared only';
        }

        return 'missing';
    }

    private function nodeDetails(mixed $status): string
    {
        if (! is_array($status)) {
            return '-';
        }

        $constraint = $status['constraint'] ?? null;

        return is_string($constraint) && $constraint !== ''
            ? $constraint
            : '-';
    }

    private function booleanStatus(mixed $value): string
    {
        return $value === true ? 'detected' : 'not detected';
    }

    private function pathDetails(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            return '-';
        }

        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR);

        if (str_starts_with($path, $basePath.DIRECTORY_SEPARATOR)) {
            return substr(
                $path,
                strlen($basePath) + 1,
            );
        }

        return $path;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function installReverb(): void
    {
        $application = $this->getApplication();

        if ($application === null || ! $application->has('reverb:install')) {
            $this->components->warn(
                'Laravel Reverb is not available. Ensure laravel/reverb is installed.',
            );

            return;
        }

        $this->newLine();
        $this->components->info('Configuring Laravel Reverb...');

        if ($this->runReverbInstall()) {
            return;
        }

        /*
        * On a clean Laravel installation, the first Reverb installation
        * attempt may initialise broadcasting and create routes/channels.php
        * before returning unsuccessfully. Once broadcasting exists, a
        * second Reverb installation attempt can complete normally.
        */
        if (! is_file(base_path('routes/channels.php'))) {
            $this->components->warn(
                'Laravel Reverb could not be configured automatically.',
            );
            $this->components->warn(
                'Run "php artisan reverb:install" manually in the host application.',
            );

            return;
        }

        $this->components->info(
            'Broadcasting was initialized. Retrying Laravel Reverb configuration...',
        );

        if ($this->runReverbInstall()) {
            $this->components->info(
                'Laravel Reverb configured successfully.',
            );

            return;
        }

        $this->components->warn(
            'Laravel Reverb could not be configured automatically after retry.',
        );
        $this->components->warn(
            'Run "php artisan reverb:install" manually in the host application.',
        );
    }

    /**
     * Run the Laravel Reverb installer.
     *
     * The command mutates the host application, therefore subsequent calls
     * may legitimately return a different result.
     *
     * @phpstan-impure
     */
    private function runReverbInstall(): bool
    {
        try {
            return $this->call('reverb:install') === self::SUCCESS;
        } catch (Throwable) {
            return false;
        }
    }
}
