<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Country\CountrySchema;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        $languages = [
            [
                LanguageSchema::LOCALE => 'en-US',
                LanguageSchema::NAME => 'English',
                LanguageSchema::NATIVE_NAME => 'English',
                LanguageSchema::CODE => 'en',
                LanguageSchema::CODE3 => 'eng',
                LanguageSchema::IS_RTL => false,
                LanguageSchema::IS_ACTIVE => true,
                LanguageSchema::IS_DEFAULT => true,
                LanguageSchema::POSITION => 1,
            ],
            [
                LanguageSchema::LOCALE => 'fa-IR',
                LanguageSchema::NAME => 'Persian',
                LanguageSchema::NATIVE_NAME => 'پارسی',
                LanguageSchema::CODE => 'fa',
                LanguageSchema::CODE3 => 'fas',
                LanguageSchema::IS_RTL => true,
                LanguageSchema::IS_ACTIVE => true,
                LanguageSchema::IS_DEFAULT => false,
                LanguageSchema::POSITION => 2,
            ],
            [
                LanguageSchema::LOCALE => 'de-DE',
                LanguageSchema::NAME => 'German',
                LanguageSchema::NATIVE_NAME => 'Deutsch',
                LanguageSchema::CODE => 'de',
                LanguageSchema::CODE3 => 'deu',
                LanguageSchema::IS_RTL => false,
                LanguageSchema::IS_ACTIVE => true,
                LanguageSchema::IS_DEFAULT => false,
                LanguageSchema::POSITION => 3,
            ],
            [
                LanguageSchema::LOCALE => 'fr-FR',
                LanguageSchema::NAME => 'French',
                LanguageSchema::NATIVE_NAME => 'Français',
                LanguageSchema::CODE => 'fr',
                LanguageSchema::CODE3 => 'fra',
                LanguageSchema::IS_RTL => false,
                LanguageSchema::IS_ACTIVE => true,
                LanguageSchema::IS_DEFAULT => false,
                LanguageSchema::POSITION => 4,
            ],
            [
                LanguageSchema::LOCALE => 'es-ES',
                LanguageSchema::NAME => 'Spanish',
                LanguageSchema::NATIVE_NAME => 'Español',
                LanguageSchema::CODE => 'es',
                LanguageSchema::CODE3 => 'spa',
                LanguageSchema::IS_RTL => false,
                LanguageSchema::IS_ACTIVE => true,
                LanguageSchema::IS_DEFAULT => false,
                LanguageSchema::POSITION => 5,
            ],
            [
                LanguageSchema::LOCALE => 'ar-SA',
                LanguageSchema::NAME => 'Arabic',
                LanguageSchema::NATIVE_NAME => 'العربية',
                LanguageSchema::CODE => 'ar',
                LanguageSchema::CODE3 => 'ara',
                LanguageSchema::IS_RTL => true,
                LanguageSchema::IS_ACTIVE => true,
                LanguageSchema::IS_DEFAULT => false,
                LanguageSchema::POSITION => 6,
            ],
        ];
        $languages = array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $languages);
        Language::query()->insert($languages);

        $currencies = [
            [
                CurrencySchema::NAME => 'United States Dollar',
                CurrencySchema::NATIVE_NAME => 'United States dollar',
                CurrencySchema::CODE => 'USD',
                CurrencySchema::SYMBOL => '$',
                CurrencySchema::IS_DEFAULT => true,
                CurrencySchema::IS_SYMBOL_RIGHT => false,
                CurrencySchema::DECIMAL_PLACES => 2,
                CurrencySchema::EXCHANGE_RATE => '1.00000000',
                CurrencySchema::IS_ACTIVE => true,
            ],
            [
                CurrencySchema::NAME => 'Iranian Rial',
                CurrencySchema::NATIVE_NAME => 'ریال ایران',
                CurrencySchema::CODE => 'IRR',
                CurrencySchema::SYMBOL => '﷼',
                CurrencySchema::IS_DEFAULT => false,
                CurrencySchema::IS_SYMBOL_RIGHT => true,
                CurrencySchema::DECIMAL_PLACES => 2,
                // Demo display rate — admin-managed, no external FX source
                CurrencySchema::EXCHANGE_RATE => '600000.00000000',
                CurrencySchema::IS_ACTIVE => true,
            ],
        ];
        $currencies = array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $currencies);
        Currency::query()->insert($currencies);

        $countries = require __DIR__.'/../data/countries.php';

        $countries = array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $countries);
        Country::query()->insert($countries);
    }
}
