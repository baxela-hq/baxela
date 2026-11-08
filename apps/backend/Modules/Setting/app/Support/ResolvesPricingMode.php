<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\Gateways\Setting\PricingModeResolverInterface;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

class ResolvesPricingMode implements PricingModeResolverInterface
{
    public const string CACHE_KEY = 'setting.prices_include_tax';

    public function pricesIncludeTax(): bool
    {
        try {
            $value = Cache::remember(
                self::CACHE_KEY,
                now()->addHour(),
                fn () => Setting::query()
                    ->where(SettingSchema::NAME, SettingNameEnum::PRICES_INCLUDE_TAX->value)
                    ->value(SettingSchema::VALUE)
            );
        } catch (Throwable) {
            return false; // settings table not migrated yet — historical mode
        }

        return $value === '1';
    }
}
