<?php

namespace Modules\Support\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SupportServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Support';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'support';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();

        parent::boot();
    }

    /**
     * Rate limiters for the customer ticket endpoints, keyed per
     * authenticated user (falling back to the IP). The limits are read
     * per-request from config so tests can override them at runtime.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('support-ticket-create', fn (Request $request) => Limit::perMinute((int) config('support.rate_limit.create'))->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('support-ticket-reply', fn (Request $request) => Limit::perMinute((int) config('support.rate_limit.reply'))->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Register translations under the module namespace ("support::…") so
     * that error messages resolve like the other modules.
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
}
