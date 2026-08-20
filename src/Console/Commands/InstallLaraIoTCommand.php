<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class InstallLaraIoTCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laraiot:install
                            {--ui : Install and enable the optional LaraIoT Vue starter UI}
                            {--force : Overwrite existing LaraIoT UI files when used with --ui}';

    /**
     * The command description.
     */
    protected $description = 'Install LaraIoT core resources and optionally the Vue starter UI.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing LaraIoT...');

        $this->publishLaraIoTResources();

        if ((bool) $this->option('ui')) {
            $this->publishLaraIoTUi();
            $this->enableLaraIoTUi();
        } elseif ((bool) $this->option('force')) {
            $this->components->warn(
                'The --force option only applies to the optional UI and is ignored without --ui.',
            );
        }

        $this->installReverb();

        $this->newLine();

        $this->components->info('LaraIoT installed successfully.');

        if ((bool) $this->option('ui')) {
            $this->components->info('LaraIoT Vue starter UI installed and enabled.');
        } else {
            $this->components->info(
                'The optional Vue starter UI was not installed. Run "php artisan laraiot:install --ui" to install it.',
            );
        }

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

    private function publishLaraIoTUi(): void
    {
        $this->newLine();
        $this->components->info('Publishing LaraIoT Vue starter UI...');

        $this->call('vendor:publish', [
            '--tag' => 'laraiot-ui',
            '--force' => (bool) $this->option('force'),
        ]);
    }

    private function enableLaraIoTUi(): void
    {
        $environmentFile = app()->environmentFilePath();

        if (! is_file($environmentFile)) {
            $this->components->warn(
                'The application .env file was not found. Add LARAIOT_UI_ENABLED=true manually.',
            );

            return;
        }

        $contents = file_get_contents($environmentFile);

        if ($contents === false) {
            $this->components->warn(
                'The application .env file could not be read. Add LARAIOT_UI_ENABLED=true manually.',
            );

            return;
        }

        $key = 'LARAIOT_UI_ENABLED';
        $replacement = $key.'=true';
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            $updatedContents = preg_replace(
                $pattern,
                $replacement,
                $contents,
            );
        } else {
            $updatedContents = rtrim($contents)
                .PHP_EOL
                .$replacement
                .PHP_EOL;
        }

        if ($updatedContents === null
            || file_put_contents($environmentFile, $updatedContents) === false) {
            $this->components->warn(
                'LaraIoT UI files were published, but .env could not be updated. Add LARAIOT_UI_ENABLED=true manually.',
            );

            return;
        }

        $this->callSilent('config:clear');

        $this->components->info(
            'LARAIOT_UI_ENABLED=true was written to the application .env file.',
        );
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

        try {
            if ($application->has('install:broadcasting')) {
                $broadcastingExitCode = $this->call('install:broadcasting', [
                    '--reverb' => true,
                    '--without-node' => true,
                    '--no-interaction' => true,
                ]);

                if ($broadcastingExitCode !== self::SUCCESS) {
                    $this->components->warn(
                        'Laravel broadcasting could not be configured automatically for Reverb.',
                    );

                    return;
                }
            }

            $exitCode = $this->call('reverb:install');
        } catch (Throwable $exception) {
            $this->components->warn(
                'Laravel Reverb could not be configured automatically.',
            );
            $this->components->warn(
                'Run "php artisan reverb:install" manually in the host application.',
            );

            return;
        }

        if ($exitCode !== self::SUCCESS) {
            $this->components->warn(
                'Laravel Reverb could not be configured automatically. Run "php artisan reverb:install" manually.',
            );
        }
    }
}
