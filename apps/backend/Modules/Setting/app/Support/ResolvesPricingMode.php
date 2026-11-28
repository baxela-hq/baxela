<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\Gateways\Setting\PricingModeResolverInterface;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

class ResolvesPricingMode implements PricingModeResolverInterface
{
    public const string CACHE_KEY = 'setting.prices_include_tax';

    public static function cacheKey(): string
    {
        return app(TenantContext::class)->cachePrefix().self::CACHE_KEY;
    }

    public function pricesIncludeTax(): bool
    {
        try {
            $value = Cache::remember(
                self::cacheKey(),
                now()->addHour(),
                fn () => Setting::query()
                    ->where(SettingSchema::NAME, SettingNameEnum::PRICES_INCLUDE_TAX->value)
                    ->value(SettingSchema::VALUE)
            );
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            return false; // settings table not migrated yet — historical mode
        }

        return $value === '1';
    }
}
