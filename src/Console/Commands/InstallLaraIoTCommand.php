<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Illuminate\Console\Command;

class InstallLaraIoTCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laraiot:install
                            {--force : Overwrite existing published LaraIoT files}';

    /**
     * The command description.
     */
    protected $description = 'Install LaraIoT resources and configure Laravel Reverb.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing LaraIoT...');

        $this->publishLaraIoTResources();
        $this->installReverb();

        $this->newLine();

        $this->components->info('LaraIoT installed successfully.');
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
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'laraiot-migrations',
            '--force' => (bool) $this->option('force'),
        ]);
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

        $exitCode = $this->call('reverb:install', [
            '--no-interaction' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->components->warn(
                'Laravel Reverb could not be configured automatically. Run "php artisan reverb:install" manually.',
            );
        }
    }
}
