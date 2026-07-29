<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Illuminate\Console\Command;

class LaraIoTCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laraiot:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package laraiot.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('LaraIoT placeholder command executed.');

        return self::SUCCESS;
    }
}
