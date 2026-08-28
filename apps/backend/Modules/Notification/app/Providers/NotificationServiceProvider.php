<?php

namespace Modules\Notification\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Services\Notification\Contracts\BuilderRepositoryInterface;
use Modules\Notification\Services\Notification\Contracts\ChannelRepositoryInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;
use Modules\Notification\Services\Notification\NotificationService;
use Modules\Notification\Services\Notification\Repositories\BuilderRepository;
use Modules\Notification\Services\Notification\Repositories\ChannelRepository;
use Modules\Notification\Services\Notification\Repositories\TemplateRepository;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NotificationServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Notification';

    protected string $nameLower = 'notification';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
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

        $this->app->singleton(NotificationDispatcherInterface::class, NotificationService::class);

        $this->app->singleton(ChannelRepositoryInterface::class, function ($app) {
            $configChannels = config(Module::NAME_LOWER.'.notifications.channels', []); // Load from config

            return new ChannelRepository($app, $configChannels);
        });

        $this->app->singleton(BuilderRepositoryInterface::class, function ($app) {
            $configChannels = config(Module::NAME_LOWER.'.notifications.builders', []); // Load from config

            return new BuilderRepository($app, $configChannels);
        });

        $this->app->singleton(TemplateRepositoryInterface::class, function ($app) {
            $configChannels = config(Module::NAME_LOWER.'.notifications.templates', []); // Load from config

            return new TemplateRepository($app, $configChannels);
        });

        //        // Bind NotificationDispatcher to our implementation
        //        $this->app->singleton(NotificationDispatcherInterface::class, NotificationService::class);
        //
        //        // Bind ChannelResolver
        //        $this->app->singleton(ChannelResolverInterface::class, ChannelResolver::class);

        //        // Bind TemplateEngine to Blade implementation
        //        $this->app->singleton(TemplateEngineInterface::class, function ($app) {
        //            return new BladeTemplateEngine($app->get(ViewFactory::class));
        //        });

        //        // Bind ChannelRepository
        //        // The channel map needs to be configured (e.g., in config/notifications.php)
        //        $this->app->singleton(ChannelRepositoryInterface::class, function ($app) {
        //            $configChannels = config('notifications.channels', []); // Load from config
        //            return new ChannelRepository($app, $configChannels);
        //        });

        // Bind concrete channel implementations if they are not resolved dynamically
        // Or let ChannelRepository resolve them based on a map.
        // For example, if ChannelRepository expects concrete classes:
        // $this->app->singleton(EmailChannel::class, EmailChannel::class);
        // $this->app->singleton(SmsChannel::class, SmsChannel::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
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
