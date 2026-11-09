<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Console\Commands\BaxelaDisableCommand;
use Modules\Core\Console\Commands\BaxelaEnableCommand;
use Modules\Core\Console\Commands\BaxelaInstallCommand;
use Modules\Core\Console\Commands\BaxelaListCommand;
use Modules\Core\Console\Commands\BaxelaRemoveCommand;
use Modules\Core\Console\Commands\BaxelaUpdateCommand;
use Modules\Core\Console\Commands\ErdGenerateCommand;
use Modules\Core\Console\Commands\QueueCheckBacklogCommand;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Gateways\CoreGateway;
use Modules\Core\Http\Middleware\AssignRequestId;
use Modules\Core\Http\Middleware\RequestLogger;
use Modules\Core\Support\Erd\ModelRelationsScanner;
use Modules\Core\Support\Erd\ModuleTableResolver;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CoreServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Core';

    protected string $nameLower = 'core';

    public array $bindings = [
        CoreGatewayInterface::class => CoreGateway::class,
    ];

    /**
     * Boot the application events.
     */
    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('api', AssignRequestId::class);

        if (config('core.log_requests')) {
            $router->pushMiddlewareToGroup('api', RequestLogger::class);
        }

        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(ModelRelationsScanner::class, fn () => new ModelRelationsScanner(base_path('Modules')));
        $this->app->bind(ModuleTableResolver::class, fn () => new ModuleTableResolver(base_path('Modules')));

        // Extension runtime state (extension plan §4): singleton so the
        // per-request state cache and skip-reason map are shared.
        $this->app->singleton(ExtensionManager::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            BaxelaDisableCommand::class,
            BaxelaEnableCommand::class,
            BaxelaInstallCommand::class,
            BaxelaListCommand::class,
            BaxelaRemoveCommand::class,
            BaxelaUpdateCommand::class,
            ErdGenerateCommand::class,
            QueueCheckBacklogCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Queue-depth monitor (gap-B3 D6): withoutOverlapping guards the
            // check-then-act workflow; the cache-state key dedups crossings.
            $schedule->command('core:queue-check-backlog')->everyFiveMinutes()->withoutOverlapping();

            // Prune failed jobs past the configured retention (gap-B3 D7) —
            // permanent deletion, and what keeps exception search cheap.
            $retentionHours = max(1, (int) config('core.queue.failed_jobs.retention_days', 30)) * 24;
            $schedule->command('queue:prune-failed --hours='.$retentionHours)->daily();
        });
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

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\'.$this->name.'\\View\\Components', $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
