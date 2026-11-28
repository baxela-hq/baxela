<?php

namespace Modules\Setting\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Support\SupportedLocales;
use Modules\Core\Support\Tenancy\TenantContext;

/**
 * Records the store runtime a queue attempt actually saw. Used by the
 * §6.7 item 3 test: two stores served by one process must not share
 * timezone, locale, legal identity or supported-locale state.
 */
class RecordsStoreRuntimeJob implements ShouldQueue
{
    /** @var list<array<string, mixed>> */
    public static array $observed = [];

    public function __construct(public readonly string $label) {}

    public function handle(): void
    {
        $context = app(TenantContext::class);

        self::$observed[] = [
            'label' => $this->label,
            'tenant_id' => $context->tenantId(),
            'mode' => $context->mode(),
            'app_timezone' => config('app.timezone'),
            'php_timezone' => date_default_timezone_get(),
            'locale' => app()->getLocale(),
            'legal_name' => config('store.legal_name'),
            'supported_locales' => SupportedLocales::current(),
        ];
    }

    public static function flush(): void
    {
        self::$observed = [];
    }
}
