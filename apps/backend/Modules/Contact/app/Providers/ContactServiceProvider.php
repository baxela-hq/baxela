<?php

namespace Modules\Contact\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ContactServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Contact';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'contact';

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
     * Per-IP rate limiter for the public contact submission endpoint. The
     * limit is read per-request from config so tests can override it at
     * runtime.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('contact-submit', fn (Request $request) => Limit::perMinute((int) config('contact.rate_limit.submit'))->by($request->ip()));
    }

    /**
     * Register translations under the module namespace ("contact::…") so
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
