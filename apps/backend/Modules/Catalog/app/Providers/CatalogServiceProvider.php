<?php

namespace Modules\Catalog\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Catalog\Console\Commands\ReconcileCatalogSearchCommand;
use Modules\Catalog\Gateways\CatalogGateway;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Search\MeilisearchSearchClient;
use Modules\Catalog\Search\NullSearchClient;
use Modules\Catalog\Search\SearchClientInterface;
use Modules\Catalog\Search\SearchCursorManager;
use Modules\Catalog\Search\SearchGenerationGuard;
use Modules\Catalog\Search\SearchIndexSettings;
use Modules\Catalog\Search\SearchMutationGate;
use Modules\Catalog\Search\SearchOutbox;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CatalogServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Catalog';

    protected string $nameLower = 'catalog';

    /** @var array<class-string, class-string> */
    public array $bindings = [
        CatalogGatewayInterface::class => CatalogGateway::class,
    ];

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        Relation::morphMap([
            FeaturedItemSchema::TYPE_PRODUCT => Product::class,
            FeaturedItemSchema::TYPE_CATEGORY => Category::class,
            FeaturedItemSchema::TYPE_BRAND => Brand::class,
        ]);

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

        $this->app->bind(SearchClientInterface::class, function ($app) {
            $host = (string) config('catalog.search.host');

            if ($host === '') {
                return new NullSearchClient;
            }

            return new MeilisearchSearchClient(
                $host,
                (string) config('catalog.search.master_key'),
                (int) config('catalog.search.request_timeout'),
                (int) config('catalog.search.task_await'),
                new SearchIndexSettings,
            );
        });

        $this->app->singleton(SearchGenerationGuard::class, fn () => new SearchGenerationGuard(
            (string) config('catalog.search.uid_base', 'catalog_products'),
        ));

        $this->app->bind(SearchMutationGate::class, fn ($app) => new SearchMutationGate(
            $app->make(SearchGenerationGuard::class),
            (int) config('catalog.search.drain_timeout', 30),
            (int) config('catalog.search.worker_stale_after', 60),
        ));

        $this->app->bind(SearchOutbox::class, fn ($app) => new SearchOutbox(
            $app->make(SearchGenerationGuard::class),
        ));

        $this->app->singleton(SearchCursorManager::class);

        // Savepoint-aware lock bookkeeping (postgres-migration §3.4.1b C2/C4):
        // mirror the connection's transaction stack so cursor-lock records
        // restore to the pre-savepoint snapshot on rollback and clear on
        // outer commit/rollback.
        $this->app->booted(function (): void {
            Event::listen(TransactionCommitted::class, fn () => SearchCursorManager::commitFrame());
            Event::listen(TransactionRolledBack::class, fn () => SearchCursorManager::rollbackFrame());
        });
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\Catalog\Console\Commands\ReconcileCatalogSearchCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            // The durable search reconciler (postgres-migration §3.4):
            // drift-repair cadence; the convergence SLO is ~2x this
            // interval, nightly reindex remains the absolute backstop.
            $minutes = max(1, intdiv((int) config('catalog.search.reconcile_interval', 300), 60));

            $schedule
                ->command('catalog:search:reconcile')
                ->cron(sprintf('*/%d * * * *', $minutes))
                ->withoutOverlapping();
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
    /** @return list<string> */
    public function provides(): array
    {
        return [];
    }

    /** @return list<string> */
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
