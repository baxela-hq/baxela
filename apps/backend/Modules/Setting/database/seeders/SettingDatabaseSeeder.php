<?php

namespace Modules\Setting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Setting\Models\Setting;
use Modules\Setting\Models\Translation;
use Modules\Setting\Schemas\Module;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;
use Modules\Setting\Schemas\Translation\TranslationSchema;

class SettingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * IDEMPOTENT (review F17): re-running seeds missing rows and
     * preserves every existing value — administrator-managed settings
     * and machine-managed state (fx_sync_status, credentials) survive
     * a re-seed. Translations are seeded only for freshly created rows.
     */
    public function run(CoreGatewayInterface $coreGateway): void
    {
        $lang = $coreGateway->getDefaultLanguage();
        $languages = $coreGateway->getActiveLanguages();
        $currency = $coreGateway->getDefaultCurrency();

        $records = [
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::WEBSITE_TITLE,
                SettingSchema::IS_TRANSLATABLE => true,
            ],
            [
                // Store timezone — applied over Laravel's app.timezone at
                // boot when set (see AppliesStoreTimezone). UTC default.
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::TIMEZONE,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => 'UTC',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::SEO,
                SettingSchema::TYPE => SettingTypeEnum::TEXT,
                SettingSchema::NAME => SettingNameEnum::WEBSITE_DESCRIPTION,
                SettingSchema::IS_TRANSLATABLE => true,
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::INTEGER,
                SettingSchema::NAME => SettingNameEnum::LANGUAGE_ID,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => $lang->id,
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::INTEGER,
                SettingSchema::NAME => SettingNameEnum::CURRENCY_ID,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => $currency->id,
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::ANNOUNCEMENT,
                SettingSchema::TYPE => SettingTypeEnum::TEXT,
                SettingSchema::NAME => SettingNameEnum::ANNOUNCEMENT_TEXT,
                SettingSchema::IS_TRANSLATABLE => true,
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::ANNOUNCEMENT,
                SettingSchema::TYPE => SettingTypeEnum::BOOLEAN,
                SettingSchema::NAME => SettingNameEnum::ANNOUNCEMENT_BAR_ENABLED,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '1',
            ],
            // Storefront theme key — the storefront owns the theme registry
            // and falls back to its default for unknown values.
            [
                SettingSchema::GROUP => SettingGroupEnum::THEME,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::STOREFRONT_THEME,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => 'default',
            ],
            // FX rate auto-sync (Part E) — opt-in, manual rates stay
            // authoritative. fx_sync_status is machine-managed by the
            // sync engine and starts empty.
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::BOOLEAN,
                SettingSchema::NAME => SettingNameEnum::FX_AUTO_SYNC_ENABLED,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '0',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::FX_PROVIDER,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => 'openexchangerates',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::FX_API_KEY,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::INTEGER,
                SettingSchema::NAME => SettingNameEnum::FX_SYNC_INTERVAL_HOURS,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '24',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::TEXT,
                SettingSchema::NAME => SettingNameEnum::FX_SYNC_STATUS,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '',
            ],
            // Store legal identity (gap-B1 D4) — the invoice seller
            // snapshot source. Empty until the merchant fills them;
            // issuance falls back to the app name for the legal name.
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::STORE_LEGAL_NAME,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::TEXT,
                SettingSchema::NAME => SettingNameEnum::STORE_LEGAL_ADDRESS,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::STORE_TAX_ID,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '',
            ],
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::STORE_CONTACT,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::VALUE => '',
            ],
        ];

        foreach ($records as $record) {
            $setting = Setting::query()->firstOrCreate(
                [SettingSchema::NAME => $record[SettingSchema::NAME]],
                $record,
            );

            if (! $setting->wasRecentlyCreated || ! $record[SettingSchema::IS_TRANSLATABLE]) {
                continue;
            }

            $name = $record[SettingSchema::NAME]->value;
            foreach ($languages as $language) {
                $key = Module::NAME_LOWER.'::seeder.'.$name;
                $value = trans($key, [], $language->code) === $key
                    ? trans($key)
                    : trans($key, [], $language->code);
                Translation::query()->create([
                    TranslationSchema::SETTING_ID => $setting->{SettingSchema::ID},
                    TranslationSchema::LANGUAGE_ID => $language->id,
                    TranslationSchema::VALUE => $value,
                ]);
            }
        }
    }
}
