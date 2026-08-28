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
            ],
            [
                CurrencySchema::NAME => 'Iranian Rial',
                CurrencySchema::NATIVE_NAME => 'ریال ایران',
                CurrencySchema::CODE => 'IRR',
                CurrencySchema::SYMBOL => '﷼',
                CurrencySchema::IS_DEFAULT => false,
                CurrencySchema::IS_SYMBOL_RIGHT => true,
                CurrencySchema::DECIMAL_PLACES => 2,
            ],
        ];
        $currencies = array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $currencies);
        Currency::query()->insert($currencies);

        $countries = [
            [
                CountrySchema::NAME => 'United States',
                CountrySchema::NATIVE_NAME => 'United States',
                CountrySchema::CODE => 'US',
                CountrySchema::CODE3 => 'USA',
                CountrySchema::PHONE_CODE => '1',
                CountrySchema::EMOJI => '🇺🇸',
            ],
            [
                CountrySchema::NAME => 'Iran',
                CountrySchema::NATIVE_NAME => 'ایران',
                CountrySchema::CODE => 'IR',
                CountrySchema::CODE3 => 'IRN',
                CountrySchema::PHONE_CODE => '98',
                CountrySchema::EMOJI => '🦁',
            ],
        ];
        $countries = array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $countries);
        Country::query()->insert($countries);
    }
}
