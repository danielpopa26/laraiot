<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Reverb;

use RuntimeException;
use Symfony\Component\Process\Process;

final class ReverbInstaller
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * Execute an approved Reverb installation plan.
     *
     * @return list<string>
     */
    public function install(
        ReverbInstallationPlan $plan,
        bool $interactive = true,
    ): array {
        $steps = [];

        if ($plan->requiresComposerInstall()) {
            $this->runProcess([
                'composer',
                'require',
                '--no-interaction',
                '--with-all-dependencies',
                $plan->packageRequirement(),
            ]);

            $steps[] = 'composer-require-reverb';

            if (! is_dir($this->path('vendor/laravel/reverb'))) {
                throw new RuntimeException(
                    'Laravel Reverb was not found after Composer installation.',
                );
            }
        }

        if ($plan->requiresConfiguration()) {
            $this->prepareBroadcastingInfrastructure();
            $steps[] = 'prepare-broadcasting';

            $this->configureReverb($interactive);
            $steps[] = 'configure-reverb';
        }

        $this->verifyInstallation();
        $steps[] = 'verify-reverb';

        return $steps;
    }

    /**
     * Prepare the Laravel broadcasting files before invoking reverb:install.
     *
     * Laravel Reverb 1.11 may call install:broadcasting with
     * --no-interaction when routes/channels.php is missing. On a fresh
     * Laravel 13 application that nested command can still require a driver
     * selection and emit a NonInteractiveValidationException. Creating the
     * framework-level broadcasting files first avoids that nested path while
     * leaving Reverb responsible for its own configuration and environment
     * variables.
     */
    private function prepareBroadcastingInfrastructure(): void
    {
        $broadcastingConfig = $this->path('config/broadcasting.php');

        if (! is_file($broadcastingConfig)) {
            $this->runProcess([
                PHP_BINARY,
                'artisan',
                'config:publish',
                'broadcasting',
                '--no-interaction',
            ]);

            if (! is_file($broadcastingConfig)) {
                throw new RuntimeException(
                    'Laravel broadcasting configuration could not be published.',
                );
            }
        }

        $channelsPath = $this->path('routes/channels.php');

        if (! is_file($channelsPath)) {
            $this->writeFile(
                $channelsPath,
                <<<'PHPFILE'
<?php

use Illuminate\Support\Facades\Broadcast;
PHPFILE
                .PHP_EOL,
            );
        }

        $this->registerBroadcastChannelsRoute();
    }

    /**
     * Register routes/channels.php in bootstrap/app.php when the host
     * application has not enabled broadcasting routes yet.
     */
    private function registerBroadcastChannelsRoute(): void
    {
        $path = $this->path('bootstrap/app.php');

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                'Unable to read Laravel bootstrap file: '.$path,
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read Laravel bootstrap file: '.$path,
            );
        }

        if (preg_match('/^\s*channels\s*:/m', $contents) === 1) {
            return;
        }

        $commandsNeedle = "commands: __DIR__.'/../routes/console.php',";
        $channelsLine = "channels: __DIR__.'/../routes/channels.php',";

        if (str_contains($contents, $commandsNeedle)) {
            $contents = str_replace(
                $commandsNeedle,
                $commandsNeedle.PHP_EOL.'        '.$channelsLine,
                $contents,
            );
        } elseif (str_contains($contents, '->withRouting(')) {
            $contents = preg_replace(
                '/->withRouting\(\s*/',
                '->withRouting('.PHP_EOL.'        '.$channelsLine.PHP_EOL.'        ',
                $contents,
                1,
            );

            if (! is_string($contents)) {
                throw new RuntimeException(
                    'Unable to register Laravel broadcast channel routes.',
                );
            }
        } else {
            throw new RuntimeException(
                'Unable to register routes/channels.php in bootstrap/app.php automatically.',
            );
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(
                'Unable to update Laravel bootstrap file: '.$path,
            );
        }
    }

    private function configureReverb(bool $interactive): void
    {
        if ($this->runFreshArtisanReverbInstall($interactive)) {
            return;
        }

        /*
         * Keep one defensive retry for host-specific cases where the first
         * fresh process mutates configuration before returning unsuccessfully.
         */
        if ($this->runFreshArtisanReverbInstall($interactive)) {
            return;
        }

        throw new RuntimeException(
            'Laravel Reverb configuration failed. Run "php artisan reverb:install" manually to inspect the host-specific error.',
        );
    }

    /**
     * @phpstan-impure
     */
    private function runFreshArtisanReverbInstall(
        bool $interactive,
    ): bool {
        $arguments = [
            PHP_BINARY,
            'artisan',
            'reverb:install',
        ];

        if (! $interactive || ! Process::isTtySupported()) {
            $arguments[] = '--no-interaction';
        }

        $process = new Process(
            $arguments,
            $this->basePath,
            null,
            null,
            $interactive ? null : 300.0,
        );

        if ($interactive && Process::isTtySupported()) {
            $process->setTty(true);
        }

        $process->run();

        return $process->isSuccessful();
    }

    private function verifyInstallation(): void
    {
        $environment = (new ReverbEnvironmentDetector(
            $this->basePath,
        ))->detect();

        if (($environment['configured'] ?? false) === true) {
            return;
        }

        throw new RuntimeException(
            'Laravel Reverb verification failed after installation.',
        );
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
