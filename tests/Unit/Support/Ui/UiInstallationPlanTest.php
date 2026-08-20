<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Support\Ui\UiInstallationPlan;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function completeInertiaVueEnvironment(array $overrides = []): array
{
    $environment = [
        'frontend_stack' => 'inertia-vue',

        'composer' => [
            'laravel_framework' => [
                'declared' => true,
                'constraint' => '^13.0',
                'locked' => true,
                'version' => 'v13.26.1',
                'installed' => true,
            ],
            'inertia_laravel' => [
                'declared' => true,
                'constraint' => '^3.0',
                'locked' => true,
                'version' => 'v3.0.0',
                'installed' => true,
            ],
        ],

        'node' => [
            'vite' => [
                'declared' => true,
                'constraint' => '^8.0.0',
                'installed' => true,
            ],
            'laravel_vite_plugin' => [
                'declared' => true,
                'constraint' => '^2.0.0',
                'installed' => true,
            ],
            'vue' => [
                'declared' => true,
                'constraint' => '^3.5.0',
                'installed' => true,
            ],
            'inertia_vue3' => [
                'declared' => true,
                'constraint' => '^2.0.0',
                'installed' => true,
            ],
            'vite_plugin_vue' => [
                'declared' => true,
                'constraint' => '^6.0.0',
                'installed' => true,
            ],
            'tailwindcss' => [
                'declared' => true,
                'constraint' => '^4.0.0',
                'installed' => true,
            ],
            'tailwindcss_vite' => [
                'declared' => true,
                'constraint' => '^4.0.0',
                'installed' => true,
            ],
            'lucide_vue_next' => [
                'declared' => true,
                'constraint' => '^0.468.0',
                'installed' => true,
            ],
            'react' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'inertia_react' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'vite_plugin_react' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
        ],

        'vite' => [
            'config_exists' => true,
            'config_path' => '/app/vite.config.js',
            'vue_plugin_configured' => true,
            'react_plugin_configured' => false,
            'tailwind_plugin_configured' => true,
            'inertia_plugin_configured' => false,
        ],

        'inertia' => [
            'entry_exists' => true,
            'entry_path' => '/app/resources/js/app.js',
            'bootstrap_detected' => true,
            'vue_bootstrap_detected' => true,
            'react_bootstrap_detected' => false,
            'root_view_exists' => true,
            'root_view_path' => '/app/resources/views/app.blade.php',
            'root_view_has_inertia_directive' => true,
        ],
    ];

    return array_replace_recursive($environment, $overrides);
}

it('builds the installation plan for a clean Laravel application', function (): void {
    $environment = completeInertiaVueEnvironment([
        'frontend_stack' => 'none',

        'composer' => [
            'inertia_laravel' => [
                'declared' => false,
                'constraint' => null,
                'locked' => false,
                'version' => null,
                'installed' => false,
            ],
        ],

        'node' => [
            'vite' => [
                'declared' => true,
                'constraint' => '^8.0.0',
                'installed' => false,
            ],
            'laravel_vite_plugin' => [
                'declared' => true,
                'constraint' => '^2.0.0',
                'installed' => false,
            ],
            'vue' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'inertia_vue3' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'vite_plugin_vue' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'tailwindcss' => [
                'declared' => true,
                'constraint' => '^4.0.0',
                'installed' => false,
            ],
            'tailwindcss_vite' => [
                'declared' => true,
                'constraint' => '^4.0.0',
                'installed' => false,
            ],
            'lucide_vue_next' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
        ],

        'vite' => [
            'vue_plugin_configured' => false,
            'tailwind_plugin_configured' => true,
        ],

        'inertia' => [
            'bootstrap_detected' => false,
            'vue_bootstrap_detected' => false,
            'root_view_exists' => false,
            'root_view_path' => null,
            'root_view_has_inertia_directive' => false,
        ],
    ]);

    $plan = UiInstallationPlan::fromEnvironment($environment);

    expect($plan->frontendStack())->toBe('none')
        ->and($plan->composerPackagesToAdd())->toBe([
            'inertiajs/inertia-laravel',
        ])
        ->and($plan->nodePackagesToAdd())->toBe([
            'vue',
            '@inertiajs/vue3',
            '@vitejs/plugin-vue',
            'lucide-vue-next',
        ])
        ->and($plan->declaredButNotInstalled())->toBe([
            'vite',
            'laravel-vite-plugin',
            'tailwindcss',
            '@tailwindcss/vite',
        ])
        ->and($plan->alreadySatisfied())->toBe([])
        ->and($plan->configurationChanges())->toBe([
            'configure-vite-vue-plugin',
            'configure-inertia-vue-bootstrap',
            'configure-inertia-root-view',
        ])
        ->and($plan->requiresComposerInstall())->toBeTrue()
        ->and($plan->requiresNpmInstall())->toBeTrue()
        ->and($plan->requiresChanges())->toBeTrue()
        ->and($plan->hasFrameworkConflict())->toBeFalse()
        ->and($plan->canPublishUiImmediately())->toBeFalse();

    expect($plan->missingRequirements())->toContain(
        'inertiajs/inertia-laravel',
        'vite',
        'laravel-vite-plugin',
        'vue',
        '@inertiajs/vue3',
        '@vitejs/plugin-vue',
        'tailwindcss',
        '@tailwindcss/vite',
        'lucide-vue-next',
    );
});

it('adds only the missing Inertia pieces to an existing Vue application', function (): void {
    $environment = completeInertiaVueEnvironment([
        'frontend_stack' => 'vue',

        'composer' => [
            'inertia_laravel' => [
                'declared' => false,
                'constraint' => null,
                'locked' => false,
                'version' => null,
                'installed' => false,
            ],
        ],

        'node' => [
            'inertia_vue3' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
        ],

        'inertia' => [
            'bootstrap_detected' => false,
            'vue_bootstrap_detected' => false,
            'root_view_exists' => false,
            'root_view_path' => null,
            'root_view_has_inertia_directive' => false,
        ],
    ]);

    $plan = UiInstallationPlan::fromEnvironment($environment);

    expect($plan->frontendStack())->toBe('vue')
        ->and($plan->composerPackagesToAdd())->toBe([
            'inertiajs/inertia-laravel',
        ])
        ->and($plan->nodePackagesToAdd())->toBe([
            '@inertiajs/vue3',
        ])
        ->and($plan->declaredButNotInstalled())->toBe([])
        ->and($plan->configurationChanges())->toBe([
            'configure-inertia-vue-bootstrap',
            'configure-inertia-root-view',
        ])
        ->and($plan->requiresComposerInstall())->toBeTrue()
        ->and($plan->requiresNpmInstall())->toBeTrue()
        ->and($plan->requiresChanges())->toBeTrue()
        ->and($plan->hasFrameworkConflict())->toBeFalse()
        ->and($plan->canPublishUiImmediately())->toBeFalse();

    expect($plan->alreadySatisfied())->toContain(
        'vite',
        'laravel-vite-plugin',
        'vue',
        '@vitejs/plugin-vue',
        'tailwindcss',
        '@tailwindcss/vite',
        'lucide-vue-next',
    );
});

it('requires no changes for a complete Inertia Vue application', function (): void {
    $plan = UiInstallationPlan::fromEnvironment(
        completeInertiaVueEnvironment(),
    );

    expect($plan->frontendStack())->toBe('inertia-vue')
        ->and($plan->composerPackagesToAdd())->toBe([])
        ->and($plan->nodePackagesToAdd())->toBe([])
        ->and($plan->declaredButNotInstalled())->toBe([])
        ->and($plan->missingRequirements())->toBe([])
        ->and($plan->configurationChanges())->toBe([])
        ->and($plan->requiresComposerInstall())->toBeFalse()
        ->and($plan->requiresNpmInstall())->toBeFalse()
        ->and($plan->requiresChanges())->toBeFalse()
        ->and($plan->hasFrameworkConflict())->toBeFalse()
        ->and($plan->frameworkConflictMessage())->toBeNull()
        ->and($plan->canPublishUiImmediately())->toBeTrue();

    expect($plan->postInstallationActions())->toBe([
        'publish-laraiot-ui',
        'enable-laraiot-ui',
        'verify-laraiot-ui',
    ]);
});

it('flags an existing Inertia React application as a framework conflict', function (): void {
    $environment = completeInertiaVueEnvironment([
        'frontend_stack' => 'inertia-react',

        'node' => [
            'vue' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'inertia_vue3' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'vite_plugin_vue' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'lucide_vue_next' => [
                'declared' => false,
                'constraint' => null,
                'installed' => false,
            ],
            'react' => [
                'declared' => true,
                'constraint' => '^19.0.0',
                'installed' => true,
            ],
            'inertia_react' => [
                'declared' => true,
                'constraint' => '^2.0.0',
                'installed' => true,
            ],
            'vite_plugin_react' => [
                'declared' => true,
                'constraint' => '^4.0.0',
                'installed' => true,
            ],
        ],

        'vite' => [
            'vue_plugin_configured' => false,
            'react_plugin_configured' => true,
        ],

        'inertia' => [
            'bootstrap_detected' => true,
            'vue_bootstrap_detected' => false,
            'react_bootstrap_detected' => true,
            'root_view_has_inertia_directive' => true,
        ],
    ]);

    $plan = UiInstallationPlan::fromEnvironment($environment);

    expect($plan->frontendStack())->toBe('inertia-react')
        ->and($plan->hasFrameworkConflict())->toBeTrue()
        ->and($plan->frameworkConflictMessage())->not->toBeNull()
        ->and($plan->requiresChanges())->toBeTrue()
        ->and($plan->canPublishUiImmediately())->toBeFalse()
        ->and($plan->nodePackagesToAdd())->toBe([
            'vue',
            '@inertiajs/vue3',
            '@vitejs/plugin-vue',
            'lucide-vue-next',
        ])
        ->and($plan->declaredButNotInstalled())->toBe([])
        ->and($plan->configurationChanges())->toBe([
            'configure-vite-vue-plugin',
            'configure-inertia-vue-bootstrap',
        ]);

    expect($plan->frameworkConflictMessage())
        ->toContain('React')
        ->toContain('explicit user approval');
});
