<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * Applies the store's legal identity to config('store.*') — the seller
 * block invoices freeze at issue time (gap-B1 D4). The lookup is cached
 * per store, so a fresh install (settings table absent) falls back to
 * config('app.name') but one store's identity never reaches another.
 */
class AppliesStoreIdentity
{
    public const string CACHE_KEY = 'setting.store_identity';

    public static function cacheKey(): string
    {
        return app(TenantContext::class)->cachePrefix().self::CACHE_KEY;
    }

    /** @return array<string, ?string> name, legal_address, tax_id, contact */
    public function __invoke(): array
    {
        try {
            $identity = Cache::remember(
                self::cacheKey(),
                now()->addHour(),
                function (): array {
                    $values = Setting::query()
                        ->whereIn(SettingSchema::NAME, [
                            SettingNameEnum::STORE_LEGAL_NAME->value,
                            SettingNameEnum::STORE_LEGAL_ADDRESS->value,
                            SettingNameEnum::STORE_TAX_ID->value,
                            SettingNameEnum::STORE_CONTACT->value,
                        ])
                        ->get()
                        ->mapWithKeys(static fn (Setting $setting): array => [
                            $setting->{SettingSchema::NAME}->value => $setting->{SettingSchema::VALUE},
                        ])
                        ->all();

                    return [
                        'legal_name' => $values[SettingNameEnum::STORE_LEGAL_NAME->value] ?? null,
                        'legal_address' => $values[SettingNameEnum::STORE_LEGAL_ADDRESS->value] ?? null,
                        'tax_id' => $values[SettingNameEnum::STORE_TAX_ID->value] ?? null,
                        'contact' => $values[SettingNameEnum::STORE_CONTACT->value] ?? null,
                    ];
                },
            );
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            $identity = [
                'legal_name' => null,
                'legal_address' => null,
                'tax_id' => null,
                'contact' => null,
            ];
        }

        config([
            'store.legal_name' => $identity['legal_name'] ?: config('app.name'),
            'store.legal_address' => $identity['legal_address'],
            'store.tax_id' => $identity['tax_id'],
            'store.contact' => $identity['contact'],
        ]);

        return $identity;
    }
}
