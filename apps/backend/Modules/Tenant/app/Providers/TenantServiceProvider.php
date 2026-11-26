<?php

namespace Modules\Tenant\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Tenant\Actions\Admin\Tenant\CreateTenantAction;
use Modules\Tenant\Actions\Admin\Tenant\DeleteTenantAction;
use Modules\Tenant\Actions\Admin\Tenant\SetTenantStatusAction;
use Modules\Tenant\Actions\Admin\Tenant\UpdateTenantAction;
use Modules\Tenant\Actions\Admin\TenantDomain\AttachTenantDomainAction;
use Modules\Tenant\Actions\Admin\TenantDomain\ReleaseTenantDomainAction;
use Modules\Tenant\Gateways\TenantGateway;
use Modules\Tenant\Operations\AttachTenantDomainOperation;
use Modules\Tenant\Operations\CreateTenantOperation;
use Modules\Tenant\Operations\DeleteTenantOperation;
use Modules\Tenant\Operations\ReleaseTenantDomainOperation;
use Modules\Tenant\Operations\SetTenantStatusOperation;
use Modules\Tenant\Operations\UpdateTenantOperation;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TenantServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Tenant';

    protected string $nameLower = 'tenant';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCentralOperations();
        $this->registerRateLimiters();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // The gateway takes the environment (the built-in default tenant
        // exists outside production only), so it is bound through a closure.
        $this->app->bind(TenantGatewayInterface::class, fn ($app) => new TenantGateway($app->environment()));

        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * The module's central operations (multi-tenancy §6.5): platform
     * administration of stores and their domains. Each Action that offers
     * an operation is its only authorized caller; no queued class carries
     * one.
     */
    protected function registerCentralOperations(): void
    {
        $registry = $this->app->make(CentralOperationRegistry::class);

        $operations = [
            CreateTenantOperation::class => [CreateTenantAction::class, 'execute'],
            UpdateTenantOperation::class => [UpdateTenantAction::class, 'execute'],
            SetTenantStatusOperation::class => [SetTenantStatusAction::class, 'execute'],
            DeleteTenantOperation::class => [DeleteTenantAction::class, 'execute'],
            AttachTenantDomainOperation::class => [AttachTenantDomainAction::class, 'execute'],
            ReleaseTenantDomainOperation::class => [ReleaseTenantDomainAction::class, 'execute'],
        ];

        foreach ($operations as $operation => [$handler, $method]) {
            $registry->register($operation, [
                'handler' => [$handler, $method],
                'callers' => [$handler],
                'jobs' => [],
            ]);
        }
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('tenant-onboarding', fn (Request $request) => Limit::perHour(5)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $config = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
                    $segments = explode('.', $this->nameLower.'.'.$config_key);

                    // Remove duplicated adjacent segments
                    $normalized = [];
                    foreach ($segments as $segment) {
                        if (end($normalized) !== $segment) {
                            $normalized[] = $segment;
                        }
                    }

                    $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

                    $this->publishes([$file->getPathname() => config_path($config)], 'config');
                    $this->merge_config_from($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Merge config from the given path recursively.
     */
    protected function merge_config_from(string $path, string $key): void
    {
        $existing = config($key, []);
        $module_config = require $path;

        config([$key => array_replace_recursive($existing, $module_config)]);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [];
    }
}
