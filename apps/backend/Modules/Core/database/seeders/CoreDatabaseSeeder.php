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
        // Every world language from the shared data file, inactive by
        // default — the admin activates the ones the store actually uses.
        // The curated map carries the shipped demo set (all six ship with
        // full admin/storefront translations).
        $activeLanguages = [
            'en' => [LanguageSchema::IS_DEFAULT => true, LanguageSchema::POSITION => 1],
            'fa' => [LanguageSchema::POSITION => 2],
            'de' => [LanguageSchema::POSITION => 3],
            'fr' => [LanguageSchema::POSITION => 4],
            'es' => [LanguageSchema::POSITION => 5],
            'ar' => [LanguageSchema::POSITION => 6],
        ];

        $languages = array_map(function (array $row) use ($activeLanguages) {
            return array_merge([
                LanguageSchema::NAME => $row['name'],
                LanguageSchema::NATIVE_NAME => $row['native_name'],
                LanguageSchema::LOCALE => $row['locale'],
                LanguageSchema::CODE => $row['code2'],
                LanguageSchema::CODE3 => $row['code3'],
                LanguageSchema::IS_RTL => $row['is_rtl'],
                LanguageSchema::IS_DEFAULT => false,
                LanguageSchema::IS_ACTIVE => false,
                LanguageSchema::POSITION => 99,
            ], $activeLanguages[$row['code2']] ?? [], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, require __DIR__.'/../data/languages.php');

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
