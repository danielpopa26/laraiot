<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Reverb;

final class ReverbInstallationPlan
{
    public const string PACKAGE = 'laravel/reverb';

    public const string PACKAGE_CONSTRAINT = '^1.11';

    private function __construct(
        private readonly bool $packageInstalled,
        private readonly bool $configured,
        private readonly bool $composerInstallRequired,
        private readonly bool $configurationRequired,
        private readonly bool $dependencyResolutionMayBeRequired,
    ) {}

    /**
     * @param  array<string, mixed>  $environment
     */
    public static function fromEnvironment(array $environment): self
    {
        $reverb = self::arrayValue($environment, 'reverb');

        $packageInstalled = ($reverb['installed'] ?? false) === true;
        $configured = ($environment['configured'] ?? false) === true;

        return new self(
            packageInstalled: $packageInstalled,
            configured: $configured,
            composerInstallRequired: ! $packageInstalled,
            configurationRequired: ! $configured,
            dependencyResolutionMayBeRequired:
                ($environment['dependency_resolution_may_be_required'] ?? false)
                    === true,
        );
    }

    public function packageInstalled(): bool
    {
        return $this->packageInstalled;
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    public function requiresComposerInstall(): bool
    {
        return $this->composerInstallRequired;
    }

    public function requiresConfiguration(): bool
    {
        return $this->configurationRequired;
    }

    public function dependencyResolutionMayBeRequired(): bool
    {
        return $this->dependencyResolutionMayBeRequired;
    }

    public function requiresChanges(): bool
    {
        return $this->composerInstallRequired
            || $this->configurationRequired;
    }

    public function packageRequirement(): string
    {
        return self::PACKAGE.':'.self::PACKAGE_CONSTRAINT;
    }

    /**
     * @return array{
     *     package_installed: bool,
     *     configured: bool,
     *     requires_composer_install: bool,
     *     requires_configuration: bool,
     *     dependency_resolution_may_be_required: bool,
     *     requires_changes: bool,
     *     package_requirement: string
     * }
     */
    public function toArray(): array
    {
        return [
            'package_installed' => $this->packageInstalled(),
            'configured' => $this->configured(),
            'requires_composer_install' => $this->requiresComposerInstall(),
            'requires_configuration' => $this->requiresConfiguration(),
            'dependency_resolution_may_be_required' =>
                $this->dependencyResolutionMayBeRequired(),
            'requires_changes' => $this->requiresChanges(),
            'package_requirement' => $this->packageRequirement(),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function arrayValue(
        array $value,
        string $key,
    ): array {
        $item = $value[$key] ?? null;

        return is_array($item) ? $item : [];
    }
}
