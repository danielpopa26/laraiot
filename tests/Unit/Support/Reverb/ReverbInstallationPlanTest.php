<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Support\Reverb\ReverbInstallationPlan;

it('requires Composer and configuration when Reverb is absent', function (): void {
    $plan = ReverbInstallationPlan::fromEnvironment([
        'reverb' => [
            'installed' => false,
        ],
        'configured' => false,
        'dependency_resolution_may_be_required' => true,
    ]);

    expect($plan->packageInstalled())->toBeFalse()
        ->and($plan->configured())->toBeFalse()
        ->and($plan->requiresComposerInstall())->toBeTrue()
        ->and($plan->requiresConfiguration())->toBeTrue()
        ->and($plan->dependencyResolutionMayBeRequired())->toBeTrue()
        ->and($plan->requiresChanges())->toBeTrue()
        ->and($plan->packageRequirement())->toBe('laravel/reverb:^1.11');
});

it('requires only configuration when Reverb is already installed', function (): void {
    $plan = ReverbInstallationPlan::fromEnvironment([
        'reverb' => [
            'installed' => true,
        ],
        'configured' => false,
        'dependency_resolution_may_be_required' => false,
    ]);

    expect($plan->packageInstalled())->toBeTrue()
        ->and($plan->configured())->toBeFalse()
        ->and($plan->requiresComposerInstall())->toBeFalse()
        ->and($plan->requiresConfiguration())->toBeTrue()
        ->and($plan->requiresChanges())->toBeTrue();
});

it('requires no changes when Reverb is installed and configured', function (): void {
    $plan = ReverbInstallationPlan::fromEnvironment([
        'reverb' => [
            'installed' => true,
        ],
        'configured' => true,
        'dependency_resolution_may_be_required' => false,
    ]);

    expect($plan->packageInstalled())->toBeTrue()
        ->and($plan->configured())->toBeTrue()
        ->and($plan->requiresComposerInstall())->toBeFalse()
        ->and($plan->requiresConfiguration())->toBeFalse()
        ->and($plan->dependencyResolutionMayBeRequired())->toBeFalse()
        ->and($plan->requiresChanges())->toBeFalse();
});

it('exposes the plan as an array', function (): void {
    $plan = ReverbInstallationPlan::fromEnvironment([
        'reverb' => [
            'installed' => false,
        ],
        'configured' => false,
        'dependency_resolution_may_be_required' => true,
    ]);

    expect($plan->toArray())->toBe([
        'package_installed' => false,
        'configured' => false,
        'requires_composer_install' => true,
        'requires_configuration' => true,
        'dependency_resolution_may_be_required' => true,
        'requires_changes' => true,
        'package_requirement' => 'laravel/reverb:^1.11',
    ]);
});
