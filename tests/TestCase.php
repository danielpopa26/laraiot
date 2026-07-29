<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Tests;

use Danpopa\LaraIoT\LaraIoTServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaraIoTServiceProvider::class,
        ];
    }
}
