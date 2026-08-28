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
     */
    public function run(CoreGatewayInterface $coreGateway): void
    {
        // $this->call([]);

        $lang = $coreGateway->getDefaultLanguage();
        $currency = $coreGateway->getDefaultCurrency();
        Translation::query()->delete();
        Setting::query()->delete();
        $records = [
            [
                SettingSchema::GROUP => SettingGroupEnum::GENERAL,
                SettingSchema::TYPE => SettingTypeEnum::STRING,
                SettingSchema::NAME => SettingNameEnum::WEBSITE_TITLE,
                SettingSchema::IS_TRANSLATABLE => true,
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
        ];
        foreach ($records as $record) {
            $setting = Setting::query()->create($record);
            $setting = $setting->refresh();
            if ($record[SettingSchema::IS_TRANSLATABLE]) {
                $name = $record[SettingSchema::NAME]->value;
                Translation::query()->create([
                    TranslationSchema::SETTING_ID => $setting->{SettingSchema::ID},
                    TranslationSchema::LANGUAGE_ID => $lang->id,
                    TranslationSchema::VALUE => __(Module::NAME_LOWER.'::seeder.'.$name),
                ]);
            }
        }
    }
}
