<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * Applies the store's legal identity to config('store.*') — the seller
 * block invoices freeze at issue time (gap-B1 D4). Same pattern as
 * AppliesStoreTimezone: cached lookup, flushed by UpdateSettingAction;
 * a fresh install (settings table absent) falls back to config('app.name').
 */
class AppliesStoreIdentity
{
    public const string CACHE_KEY = 'setting.store_identity';

    /** @return array<string, ?string> name, legal_address, tax_id, contact */
    public function __invoke(): array
    {
        try {
            $values = Cache::remember(
                self::CACHE_KEY,
                now()->addHour(),
                fn () => Setting::query()
                    ->whereIn(SettingSchema::NAME, [
                        SettingNameEnum::STORE_LEGAL_NAME->value,
                        SettingNameEnum::STORE_LEGAL_ADDRESS->value,
                        SettingNameEnum::STORE_TAX_ID->value,
                        SettingNameEnum::STORE_CONTACT->value,
                    ])
                    ->pluck(SettingSchema::VALUE, SettingSchema::NAME)
                    ->all(),
            );
        } catch (Throwable) {
            $values = [];
        }

        $identity = [
            'legal_name' => $values[SettingNameEnum::STORE_LEGAL_NAME->value] ?? null,
            'legal_address' => $values[SettingNameEnum::STORE_LEGAL_ADDRESS->value] ?? null,
            'tax_id' => $values[SettingNameEnum::STORE_TAX_ID->value] ?? null,
            'contact' => $values[SettingNameEnum::STORE_CONTACT->value] ?? null,
        ];

        config([
            'store.legal_name' => $identity['legal_name'] ?: config('app.name'),
            'store.legal_address' => $identity['legal_address'],
            'store.tax_id' => $identity['tax_id'],
            'store.contact' => $identity['contact'],
        ]);

        return $identity;
    }
}
