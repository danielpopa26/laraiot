<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Tests;

use Danpopa\LaraIoT\LaraIoTServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Inertia\ServiceProvider as InertiaServiceProvider;
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
            InertiaServiceProvider::class,
            LaraIoTServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set(
            'app.key',
            'base64:'.base64_encode(
                str_repeat('a', 32),
            ),
        );

        $app['config']->set(
            'session.driver',
            'array',
        );

        $this->testDatabasePath = sys_get_temp_dir()
            .'/laraiot-tests-'
            .getmypid()
            .'-'
            .bin2hex(random_bytes(8));

        (new Filesystem)->ensureDirectoryExists(
            $this->testDatabasePath.'/migrations',
        );

        $app->useDatabasePath(
            $this->testDatabasePath,
        );

        $app['config']->set(
            'database.default',
            'testing',
        );

        /*
         * RefreshDatabase starts its transaction before our
         * setUp() method continues. SQLite cannot effectively
         * change PRAGMA foreign_keys while a transaction is
         * active, so foreign-key enforcement must be enabled
         * on the connection configuration before the connection
         * is created.
         */
        $app['config']->set(
            'database.connections.testing.foreign_key_constraints',
            true,
        );

        /*
         * Enable the optional UI in the package test environment.
         * The package config file itself still defaults to false.
         */
        $app['config']->set(
            'laraiot.ui',
            [
                'enabled' => true,
                'prefix' => 'laraiot',
                'middleware' => [
                    'web',
                    'auth',
                ],
            ],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Simulate the Inertia root view provided by the host
         * Laravel application after LaraIoT UI installation.
         */
        $this->app['view']->addLocation(
            __DIR__.'/Fixtures/views',
        );

        /*
         * Keep "auth" attached to LaraIoT routes, but bypass its
         * execution during package tests so they do not depend on
         * the host application's User/auth implementation.
         */
        $this->withoutMiddleware(
            Authenticate::class,
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
            (new Filesystem)->deleteDirectory(
                $databasePath,
            );
        }
    }
}
