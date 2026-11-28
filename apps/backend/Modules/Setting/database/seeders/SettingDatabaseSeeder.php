<?php

namespace Modules\Setting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Setting\Models\Setting;
use Modules\Setting\Models\Translation;
use Modules\Setting\Schemas\Module;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;
use Modules\Setting\Schemas\Translation\TranslationSchema;
use Modules\Setting\Support\PlatformSettings;

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
            // FX config/status and platform identity are installation-level
            // rows (tenant_id NULL), seeded below — never store rows.
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

        $this->seedPlatform([
            PlatformSettingsInterface::FX_AUTO_SYNC_ENABLED => ['0', SettingGroupEnum::GENERAL, SettingTypeEnum::BOOLEAN],
            PlatformSettingsInterface::FX_PROVIDER => ['openexchangerates', SettingGroupEnum::GENERAL, SettingTypeEnum::STRING],
            PlatformSettingsInterface::FX_API_KEY => ['', SettingGroupEnum::GENERAL, SettingTypeEnum::STRING],
            PlatformSettingsInterface::FX_SYNC_INTERVAL_HOURS => ['24', SettingGroupEnum::GENERAL, SettingTypeEnum::INTEGER],
            PlatformSettingsInterface::FX_SYNC_STATUS => ['', SettingGroupEnum::GENERAL, SettingTypeEnum::TEXT],
            PlatformSettingsInterface::PLATFORM_IDENTITY => [
                json_encode([
                    'name' => (string) config('app.name'),
                    'url' => (string) config('app.url'),
                ], JSON_THROW_ON_ERROR),
                SettingGroupEnum::GENERAL,
                SettingTypeEnum::TEXT,
            ],
        ]);
    }

    /**
     * Platform rows are written outside the tenant scope (tenant_id NULL).
     * Existing values — including the encrypted credential and the
     * machine-managed status — survive a re-seed.
     *
     * @param  array<string, array{0: string, 1: SettingGroupEnum, 2: SettingTypeEnum}>  $values
     */
    protected function seedPlatform(array $values): void
    {
        foreach ($values as $name => [$value, $group, $type]) {
            $attributes = [
                TenantColumn::TENANT_ID => null,
                SettingSchema::NAME => $name,
            ];

            $existing = DB::table(SettingSchema::TABLE)->where($attributes)->first();

            if ($existing !== null) {
                DB::table(SettingSchema::TABLE)->where(SettingSchema::ID, $existing->id)->update([
                    SettingSchema::GROUP => $group->value,
                    SettingSchema::TYPE => $type->value,
                    SettingSchema::IS_TRANSLATABLE => false,
                    SettingSchema::UPDATED_AT => now(),
                ]);

                continue;
            }

            DB::table(SettingSchema::TABLE)->insert([
                ...$attributes,
                SettingSchema::GROUP => $group->value,
                SettingSchema::TYPE => $type->value,
                SettingSchema::VALUE => $value,
                SettingSchema::IS_TRANSLATABLE => false,
                SettingSchema::CREATED_AT => now(),
                SettingSchema::UPDATED_AT => now(),
            ]);
        }

        app(PlatformSettings::class)->invalidate();
    }
}
