<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
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
        // insertOrIgnore everywhere: re-running the seeder on an existing
        // database fills gaps but never duplicates rows or overwrites
        // admin-made changes (default flags, rates).
        Language::query()->insertOrIgnore($languages);

        // Seed every world currency from the shared data file, inactive by
        // default — the admin activates (and rates) the ones the store
        // actually uses. The curated map below carries the demo defaults
        // and is the ONLY place deciding what ships active.
        $active = [
            'USD' => [
                CurrencySchema::IS_DEFAULT => true,
                CurrencySchema::IS_ACTIVE => true,
            ],
            // Demo display rates — admin-managed, no external FX source
            'EUR' => [
                CurrencySchema::IS_ACTIVE => true,
                CurrencySchema::EXCHANGE_RATE => '0.92000000',
            ],
            'IRR' => [
                CurrencySchema::IS_ACTIVE => true,
                CurrencySchema::EXCHANGE_RATE => '600000.00000000',
            ],
        ];

        $currencies = array_map(function (array $row) use ($active) {
            return array_merge([
                CurrencySchema::NAME => $row['name'],
                CurrencySchema::NATIVE_NAME => $row['native_name'],
                CurrencySchema::CODE => $row['code'],
                CurrencySchema::SYMBOL => $row['symbol'],
                CurrencySchema::IS_SYMBOL_RIGHT => $row['is_symbol_right'],
                CurrencySchema::DECIMAL_PLACES => $row['decimal_place'],
                CurrencySchema::EXCHANGE_RATE => '1.00000000',
                CurrencySchema::IS_DEFAULT => false,
                CurrencySchema::IS_ACTIVE => false,
            ], $active[$row['code']] ?? [], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, require __DIR__.'/../data/currencies.php');

        // insertOrIgnore keeps the seeder idempotent and never overwrites
        // admin-managed rates on re-runs — missing world currencies fill in.
        Currency::query()->insertOrIgnore($currencies);


        $countries = require __DIR__.'/../data/countries.php';

        $countries = array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $countries);
        Country::query()->insertOrIgnore($countries);
    }
}
