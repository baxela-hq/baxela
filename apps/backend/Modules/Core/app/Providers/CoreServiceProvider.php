<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Actions\Tenancy\AuditTenancyAction;
use Modules\Core\Console\Commands\BaxelaDisableCommand;
use Modules\Core\Console\Commands\BaxelaEnableCommand;
use Modules\Core\Console\Commands\BaxelaInstallCommand;
use Modules\Core\Console\Commands\BaxelaListCommand;
use Modules\Core\Console\Commands\BaxelaRemoveCommand;
use Modules\Core\Console\Commands\BaxelaUpdateCommand;
use Modules\Core\Console\Commands\ErdGenerateCommand;
use Modules\Core\Console\Commands\QueueCheckBacklogCommand;
use Modules\Core\Console\Commands\SyncExchangeRatesCommand;
use Modules\Core\Console\Commands\TenancyAuditCommand;
use Modules\Core\Console\Commands\TransferClearRemapsCommand;
use Modules\Core\Console\Commands\TransferResetIdentityCommand;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Extensions\ExtensionManager;
use Modules\Core\Gateways\CoreGateway;
use Modules\Core\Http\Middleware\AssignRequestId;
use Modules\Core\Http\Middleware\RequestLogger;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Http\Middleware\SetSecurityHeaders;
use Modules\Core\Operations\AuditTenancyOperation;
use Modules\Core\Support\Database\Grammars\PostgresUnsignedGrammar;
use Modules\Core\Support\Erd\ModelRelationsScanner;
use Modules\Core\Support\Erd\ModuleTableResolver;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Core\Support\Transfer\TransferGuardRegistry;
use Modules\Core\Support\Transfer\TransferRemapStore;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CoreServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Core';

    protected string $nameLower = 'core';

    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CoreGatewayInterface::class => CoreGateway::class,
        TransferRemapInterface::class => TransferRemapStore::class,
    ];

    /**
     * Boot the application events.
     */
    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('api', AssignRequestId::class);
        $router->pushMiddlewareToGroup('api', SetSecurityHeaders::class);

        // Tenant identification, stage 2 (multi-tenancy §3.1): first in
        // the `api` group; its position against the credentials, auth and
        // binding middleware is fixed by the priority list in
        // bootstrap/app.php.
        $router->prependMiddlewareToGroup('api', ResolveTenant::class);

        // Every queued payload carries the tenant it was dispatched under
        // and, for a centrally-dispatched job, the registered operation it
        // must invoke itself (§6.1). The queue listeners read it back.
        Queue::createPayloadUsing(function (): array {
            $context = app(TenantContext::class);

            return [
                'tenant_id' => $context->tenantId(),
                'central_operation' => $context->centralOperation(),
            ];
        });

        if (config('core.log_requests')) {
            $router->pushMiddlewareToGroup('api', RequestLogger::class);
        }

        $this->registerCommands();
        $this->registerCentralOperations();
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
        // Unsigned-preserving pgsql schema grammar (postgres-migration
        // DD9b), registered in register() deliberately: a connection
        // created during any provider's boot() would otherwise cache with
        // the stock grammar before this resolver existed. The container
        // decorator fires on the manager's first resolution — before its
        // first ->connection() call — so the resolver is in place for
        // every (re)instantiation: purge, reconnect, fresh resolution.
        $this->app->extend('db', function ($db) {
            $db->extend('pgsql', function ($config, $name) {
                $connection = app(ConnectionFactory::class)->make($config, $name);
                $connection->setSchemaGrammar(new PostgresUnsignedGrammar($connection));

                return $connection;
            });

            return $db;
        });

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(SentryContextServiceProvider::class);

        $this->app->bind(ModelRelationsScanner::class, fn () => new ModelRelationsScanner(base_path('Modules')));
        $this->app->bind(ModuleTableResolver::class, fn () => new ModuleTableResolver(base_path('Modules')));

        // Extension runtime state (extension plan §4): singleton so the
        // per-request state cache and skip-reason map are shared.
        $this->app->singleton(ExtensionManager::class);

        // Transfer import guard lifecycle (C7/F32): one registry per
        // process holds each connection's single ownership hook and its
        // active run context, so repeated imports never re-register hooks.
        $this->app->singleton(TransferGuardRegistry::class);

        // Tenant state (multi-tenancy §2): one context per process. HTTP
        // serves one request per process (php-fpm) and identification
        // resets it; the queue listeners re-initialize it per job attempt.
        $this->app->singleton(TenantContext::class);

        // The central-operation registry (multi-tenancy §6.5): modules
        // register their operations into it at boot.
        $this->app->singleton(CentralOperationRegistry::class);
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
            SyncExchangeRatesCommand::class,
            TenancyAuditCommand::class,
            TransferClearRemapsCommand::class,
            TransferResetIdentityCommand::class,
        ]);
    }

    /**
     * Core's Category-1 central operation (multi-tenancy §6.5): the
     * installation-wide tenancy audit. The console command is its only
     * caller; no queued job may carry it.
     */
    protected function registerCentralOperations(): void
    {
        $registry = $this->app->make(CentralOperationRegistry::class);

        $registry->register(AuditTenancyOperation::class, [
            'handler' => [AuditTenancyAction::class, 'execute'],
            'callers' => [TenancyAuditCommand::class],
            'jobs' => [],
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

            // FX rate auto-sync (Part E): hourly tick; the engine applies
            // the enabled/retry-cooldown/interval gates itself, so the
            // admin-configured interval is real (review F19). Without-
            // Overlapping guards the scheduler path; the engine's
            // distributed lock additionally serializes manual syncs.
            $schedule->command('currencies:sync-rates')->hourly()->withoutOverlapping();
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
     *
     * @return list<string>
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
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
