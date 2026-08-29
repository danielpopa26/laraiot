<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Danpopa\LaraIoT\Support\Reverb\ReverbEnvironmentDetector;
use Danpopa\LaraIoT\Support\Reverb\ReverbInstallationPlan;
use Danpopa\LaraIoT\Support\Reverb\ReverbInstaller;
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
    protected $description = 'Install LaraIoT core resources and optionally configure the Vue UI and WebSocket support.';

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

        $reverbStatus = $this->installOptionalReverb();

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
            default => $this->components->info(
                'The optional Vue UI was not requested. Run "php artisan laraiot:install --ui" to install it.',
            ),
        };

        match ($reverbStatus) {
            'installed' => $this->components->info(
                'Laravel Reverb was installed and configured for optional WebSocket mode.',
            ),
            'ready' => $this->components->info(
                'Laravel Reverb is already installed and configured.',
            ),
            'failed' => $this->components->warn(
                'Laravel Reverb could not be prepared. LaraIoT remains available in Polling mode.',
            ),
            default => $this->components->info(
                'Laravel Reverb was not configured. LaraIoT remains available in Polling mode.',
            ),
        };

        $this->components->info(
            'Run "php artisan migrate" to create the LaraIoT database tables.',
        );

        if (in_array($reverbStatus, ['installed', 'ready'], true)) {
            $this->components->info(
                'Start WebSocket mode with "php artisan reverb:start" when it is enabled.',
            );
        } else {
            $this->components->info(
                'Re-run "php artisan laraiot:install" later if you want to add WebSocket support.',
            );
        }

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

    private function installOptionalReverb(): string
    {
        $this->newLine();
        $this->components->info(
            'Inspecting LaraIoT WebSocket environment...',
        );

        $environment = (new ReverbEnvironmentDetector(
            base_path(),
        ))->detect();

        $this->displayReverbEnvironment($environment);

        $plan = ReverbInstallationPlan::fromEnvironment(
            $environment,
        );

        $this->newLine();
        $this->components->info('LaraIoT WebSocket installation plan');

        $this->displayReverbInstallationPlan($plan);

        if (! $plan->requiresChanges()) {
            $this->components->info(
                'Laravel Reverb already satisfies the LaraIoT WebSocket requirements.',
            );

            return 'ready';
        }

        if ($plan->dependencyResolutionMayBeRequired()) {
            $this->newLine();
            $this->components->warn(
                'Installing Reverb may require Composer to adjust existing Guzzle / PSR-7 dependency versions.',
            );
            $this->components->warn(
                'LaraIoT will allow those dependency changes only for the optional Reverb installation.',
            );
        }

        $this->newLine();

        $question = $plan->requiresComposerInstall()
            ? 'Install and configure Laravel Reverb for optional WebSocket mode now?'
            : 'Laravel Reverb is installed but not fully configured. Configure it now?';

        $approved = $this->confirm(
            $question,
            false,
        );

        if (! $approved) {
            $this->components->info(
                'No WebSocket infrastructure changes were made.',
            );

            return 'skipped';
        }

        $this->newLine();
        $this->components->info(
            'Preparing Laravel Reverb WebSocket support...',
        );

        try {
            $steps = (new ReverbInstaller(base_path()))->install(
                $plan,
                $this->input->isInteractive(),
            );
        } catch (Throwable $exception) {
            $this->components->error(
                'Laravel Reverb could not be installed or configured.',
            );
            $this->components->error(
                $exception->getMessage(),
            );

            return 'failed';
        }

        $this->displayExecutedReverbSteps($steps);

        return 'installed';
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function displayReverbEnvironment(array $environment): void
    {
        $reverb = $environment['reverb'] ?? [];
        $guzzle = $environment['guzzle'] ?? [];
        $configuration = $environment['configuration'] ?? [];

        $reverb = is_array($reverb) ? $reverb : [];
        $guzzle = is_array($guzzle) ? $guzzle : [];
        $configuration = is_array($configuration)
            ? $configuration
            : [];

        $this->table(
            ['Requirement', 'Status', 'Details'],
            [
                [
                    'Laravel Reverb',
                    $this->composerStatus($reverb),
                    $this->composerDetails($reverb),
                ],
                [
                    'Guzzle',
                    $this->composerStatus(
                        $guzzle['guzzle'] ?? null,
                    ),
                    $this->composerDetails(
                        $guzzle['guzzle'] ?? null,
                    ),
                ],
                [
                    'Guzzle PSR-7',
                    $this->composerStatus(
                        $guzzle['psr7'] ?? null,
                    ),
                    $this->composerDetails(
                        $guzzle['psr7'] ?? null,
                    ),
                ],
                [
                    'Guzzle Promises',
                    $this->composerStatus(
                        $guzzle['promises'] ?? null,
                    ),
                    $this->composerDetails(
                        $guzzle['promises'] ?? null,
                    ),
                ],
                [
                    'Broadcasting config',
                    $this->booleanStatus(
                        $configuration['broadcasting_config_exists'] ?? false,
                    ),
                    'config/broadcasting.php',
                ],
                [
                    'Reverb config',
                    $this->booleanStatus(
                        $configuration['reverb_config_exists'] ?? false,
                    ),
                    'config/reverb.php',
                ],
                [
                    'Broadcast channels route',
                    $this->booleanStatus(
                        $configuration['channels_route_exists'] ?? false,
                    ),
                    'routes/channels.php',
                ],
                [
                    'WebSocket support ready',
                    $this->booleanStatus(
                        $environment['configured'] ?? false,
                    ),
                    '-',
                ],
            ],
        );
    }

    private function displayReverbInstallationPlan(
        ReverbInstallationPlan $plan,
    ): void {
        $this->table(
            ['Plan flag', 'Value'],
            [
                [
                    'Reverb package installed',
                    $this->yesNo($plan->packageInstalled()),
                ],
                [
                    'Composer install required',
                    $this->yesNo($plan->requiresComposerInstall()),
                ],
                [
                    'Reverb configuration required',
                    $this->yesNo($plan->requiresConfiguration()),
                ],
                [
                    'Dependency changes may be required',
                    $this->yesNo(
                        $plan->dependencyResolutionMayBeRequired(),
                    ),
                ],
                [
                    'WebSocket support ready',
                    $this->yesNo($plan->configured()),
                ],
            ],
        );

        if ($plan->requiresComposerInstall()) {
            $this->line(
                'Composer package: '.$plan->packageRequirement(),
            );
        }
    }

    /**
     * @param  list<string>  $steps
     */
    private function displayExecutedReverbSteps(array $steps): void
    {
        $this->newLine();
        $this->components->info(
            'LaraIoT WebSocket installation steps completed:',
        );

        foreach ($steps as $step) {
            $this->line('  - '.$step);
        }
    }
}
