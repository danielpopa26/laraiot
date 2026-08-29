<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Ui;

final class UiInstallationPlan
{
    /**
     * Detector keys mapped to the Composer packages required by the LaraIoT UI.
     *
     * @var array<string, string>
     */
    private const array REQUIRED_COMPOSER_PACKAGES = [
        'inertia_laravel' => 'inertiajs/inertia-laravel',
    ];

    /**
     * Detector keys mapped to the Node packages required by the LaraIoT UI.
     *
     * @var array<string, string>
     */
    private const array REQUIRED_NODE_PACKAGES = [
        'vite' => 'vite',
        'laravel_vite_plugin' => 'laravel-vite-plugin',
        'vue' => 'vue',
        'inertia_vue3' => '@inertiajs/vue3',
        'vite_plugin_vue' => '@vitejs/plugin-vue',
        'tailwindcss' => 'tailwindcss',
        'tailwindcss_vite' => '@tailwindcss/vite',
        'lucide_vue_next' => 'lucide-vue-next',
        'fontsource_inter' => '@fontsource/inter',
        'fontsource_jetbrains_mono' => '@fontsource/jetbrains-mono',
    ];

    /**
     * @param  list<string>  $composerPackagesToAdd
     * @param  list<string>  $nodePackagesToAdd
     * @param  list<string>  $declaredButNotInstalled
     * @param  list<string>  $missingRequirements
     * @param  list<string>  $configurationChanges
     * @param  list<string>  $postInstallationActions
     * @param  list<string>  $alreadySatisfied
     */
    private function __construct(
        private readonly string $frontendStack,
        private readonly array $composerPackagesToAdd,
        private readonly array $nodePackagesToAdd,
        private readonly array $declaredButNotInstalled,
        private readonly array $missingRequirements,
        private readonly array $configurationChanges,
        private readonly array $postInstallationActions,
        private readonly array $alreadySatisfied,
        private readonly bool $composerInstallRequired,
        private readonly bool $npmInstallRequired,
        private readonly bool $frameworkConflict,
        private readonly ?string $frameworkConflictMessage,
    ) {}

    /**
     * Build an installation plan from UiEnvironmentDetector::detect().
     *
     * This method is intentionally side-effect free.
     *
     * @param  array<string, mixed>  $environment
     */
    public static function fromEnvironment(array $environment): self
    {
        $composer = self::arrayValue($environment, 'composer');
        $node = self::arrayValue($environment, 'node');
        $vite = self::arrayValue($environment, 'vite');
        $inertia = self::arrayValue($environment, 'inertia');

        $frontendStack = is_string($environment['frontend_stack'] ?? null)
            ? $environment['frontend_stack']
            : 'unknown';

        $composerPackagesToAdd = [];
        $nodePackagesToAdd = [];
        $declaredButNotInstalled = [];
        $missingRequirements = [];
        $alreadySatisfied = [];

        $composerInstallRequired = false;
        $npmInstallRequired = false;

        foreach (self::REQUIRED_COMPOSER_PACKAGES as $key => $packageName) {
            $status = self::arrayValue($composer, $key);

            if (self::isInstalled($status)) {
                $alreadySatisfied[] = $packageName;

                continue;
            }

            $missingRequirements[] = $packageName;
            $composerInstallRequired = true;

            if (self::isDeclared($status)) {
                $declaredButNotInstalled[] = $packageName;

                continue;
            }

            $composerPackagesToAdd[] = $packageName;
        }

        foreach (self::REQUIRED_NODE_PACKAGES as $key => $packageName) {
            $status = self::arrayValue($node, $key);

            if (self::isInstalled($status)) {
                $alreadySatisfied[] = $packageName;

                continue;
            }

            $missingRequirements[] = $packageName;
            $npmInstallRequired = true;

            if (self::isDeclared($status)) {
                $declaredButNotInstalled[] = $packageName;

                continue;
            }

            $nodePackagesToAdd[] = $packageName;
        }

        $configurationChanges = [];

        if (($vite['config_exists'] ?? false) !== true) {
            $configurationChanges[] = 'create-vite-config';
        }

        if (($vite['vue_plugin_configured'] ?? false) !== true) {
            $configurationChanges[] = 'configure-vite-vue-plugin';
        }

        if (($vite['tailwind_plugin_configured'] ?? false) !== true) {
            $configurationChanges[] = 'configure-vite-tailwind-plugin';
        }

        if (($inertia['vue_bootstrap_detected'] ?? false) !== true) {
            $configurationChanges[] = 'configure-inertia-vue-bootstrap';
        }

        if (($inertia['root_view_has_inertia_directive'] ?? false) !== true) {
            $configurationChanges[] = 'configure-inertia-root-view';
        }

        $frameworkConflict = self::isReactStack($frontendStack);

        $frameworkConflictMessage = $frameworkConflict
            ? 'An existing React-based frontend was detected. LaraIoT UI targets Vue 3, so adding Vue support requires explicit user approval.'
            : null;

        return new self(
            frontendStack: $frontendStack,
            composerPackagesToAdd: self::uniqueList(
                $composerPackagesToAdd,
            ),
            nodePackagesToAdd: self::uniqueList(
                $nodePackagesToAdd,
            ),
            declaredButNotInstalled: self::uniqueList(
                $declaredButNotInstalled,
            ),
            missingRequirements: self::uniqueList(
                $missingRequirements,
            ),
            configurationChanges: self::uniqueList(
                $configurationChanges,
            ),
            postInstallationActions: [
                'publish-laraiot-ui',
                'enable-laraiot-ui',
                'verify-laraiot-ui',
            ],
            alreadySatisfied: self::uniqueList(
                $alreadySatisfied,
            ),
            composerInstallRequired: $composerInstallRequired,
            npmInstallRequired: $npmInstallRequired,
            frameworkConflict: $frameworkConflict,
            frameworkConflictMessage: $frameworkConflictMessage,
        );
    }

    public function frontendStack(): string
    {
        return $this->frontendStack;
    }

    /**
     * @return list<string>
     */
    public function composerPackagesToAdd(): array
    {
        return $this->composerPackagesToAdd;
    }

    /**
     * @return list<string>
     */
    public function nodePackagesToAdd(): array
    {
        return $this->nodePackagesToAdd;
    }

    /**
     * Packages already declared by the host application, but not currently
     * present in vendor/ or node_modules/.
     *
     * @return list<string>
     */
    public function declaredButNotInstalled(): array
    {
        return $this->declaredButNotInstalled;
    }

    /**
     * All LaraIoT UI package requirements which are not installed yet.
     *
     * @return list<string>
     */
    public function missingRequirements(): array
    {
        return $this->missingRequirements;
    }

    /**
     * @return list<string>
     */
    public function configurationChanges(): array
    {
        return $this->configurationChanges;
    }

    /**
     * @return list<string>
     */
    public function postInstallationActions(): array
    {
        return $this->postInstallationActions;
    }

    /**
     * Packages already installed and available to LaraIoT UI.
     *
     * @return list<string>
     */
    public function alreadySatisfied(): array
    {
        return $this->alreadySatisfied;
    }

    public function requiresComposerInstall(): bool
    {
        return $this->composerInstallRequired;
    }

    public function requiresNpmInstall(): bool
    {
        return $this->npmInstallRequired;
    }

    public function hasFrameworkConflict(): bool
    {
        return $this->frameworkConflict;
    }

    public function frameworkConflictMessage(): ?string
    {
        return $this->frameworkConflictMessage;
    }

    public function requiresChanges(): bool
    {
        return $this->composerInstallRequired
            || $this->npmInstallRequired
            || $this->configurationChanges !== [];
    }

    public function canPublishUiImmediately(): bool
    {
        return ! $this->frameworkConflict
            && ! $this->requiresChanges();
    }

    /**
     * @return array{
     *     frontend_stack: string,
     *     composer_packages_to_add: list<string>,
     *     node_packages_to_add: list<string>,
     *     declared_but_not_installed: list<string>,
     *     missing_requirements: list<string>,
     *     configuration_changes: list<string>,
     *     post_installation_actions: list<string>,
     *     already_satisfied: list<string>,
     *     requires_composer_install: bool,
     *     requires_npm_install: bool,
     *     has_framework_conflict: bool,
     *     framework_conflict_message: string|null,
     *     requires_changes: bool,
     *     can_publish_ui_immediately: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'frontend_stack' => $this->frontendStack(),
            'composer_packages_to_add' => $this->composerPackagesToAdd(),
            'node_packages_to_add' => $this->nodePackagesToAdd(),
            'declared_but_not_installed' => $this->declaredButNotInstalled(),
            'missing_requirements' => $this->missingRequirements(),
            'configuration_changes' => $this->configurationChanges(),
            'post_installation_actions' => $this->postInstallationActions(),
            'already_satisfied' => $this->alreadySatisfied(),
            'requires_composer_install' => $this->requiresComposerInstall(),
            'requires_npm_install' => $this->requiresNpmInstall(),
            'has_framework_conflict' => $this->hasFrameworkConflict(),
            'framework_conflict_message' => $this->frameworkConflictMessage(),
            'requires_changes' => $this->requiresChanges(),
            'can_publish_ui_immediately' => $this->canPublishUiImmediately(),
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

    /**
     * @param  array<string, mixed>  $status
     */
    private static function isInstalled(array $status): bool
    {
        return ($status['installed'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private static function isDeclared(array $status): bool
    {
        return ($status['declared'] ?? false) === true;
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private static function uniqueList(array $items): array
    {
        return array_values(array_unique($items));
    }

    private static function isReactStack(string $frontendStack): bool
    {
        return in_array(
            $frontendStack,
            [
                'react',
                'inertia-react',
                'inertia-react-partial',
            ],
            true,
        );
    }
}
