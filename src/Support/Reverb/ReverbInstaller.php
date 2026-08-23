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
            $this->configureReverb($interactive);
            $steps[] = 'configure-reverb';
        }

        $this->verifyInstallation();
        $steps[] = 'verify-reverb';

        return $steps;
    }

    private function configureReverb(bool $interactive): void
    {
        if ($this->runFreshArtisanReverbInstall($interactive)) {
            return;
        }

        /*
         * On a clean Laravel application, Reverb may initialise the
         * broadcasting infrastructure on the first pass and require a
         * second fresh Artisan process to finish configuring the driver.
         */
        if (
            is_file($this->path('routes/channels.php'))
            && $this->runFreshArtisanReverbInstall($interactive)
        ) {
            return;
        }

        throw new RuntimeException(
            'Laravel Reverb configuration failed. Run "php artisan reverb:install" manually to inspect the host-specific error.',
        );
    }

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
