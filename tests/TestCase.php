<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Tests;

use Danpopa\LaraIoT\LaraIoTServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private ?string $testDatabasePath = null;

    /**
     * @param  Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaraIoTServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->testDatabasePath = sys_get_temp_dir()
            .'/laraiot-tests-'
            .getmypid()
            .'-'
            .bin2hex(random_bytes(8));

        (new Filesystem)->ensureDirectoryExists(
            $this->testDatabasePath.'/migrations',
        );

        $app->useDatabasePath($this->testDatabasePath);

        $app['config']->set('database.default', 'testing');

        $app['config']->set(
            'database.connections.testing.foreign_key_constraints',
            true,
        );
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            dirname(__DIR__).'/database/migrations',
        );
    }

    protected function tearDown(): void
    {
        $databasePath = $this->testDatabasePath;

        parent::tearDown();

        if ($databasePath !== null) {
            (new Filesystem)->deleteDirectory($databasePath);
        }
    }
}
