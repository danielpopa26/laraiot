<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laraiot:install
                            {--force : Overwrite existing published files}';

    /**
     * The command description.
     */
    protected $description = 'Install the LaraIoT package resources.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing LaraIoT...');

        $this->call('vendor:publish', [
            '--tag' => 'laraiot-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'laraiot-migrations',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->newLine();

        $this->components->info('LaraIoT installed successfully.');
        $this->components->info(
            'Run "php artisan migrate" to create the LaraIoT database tables.',
        );

        return self::SUCCESS;
    }
}
