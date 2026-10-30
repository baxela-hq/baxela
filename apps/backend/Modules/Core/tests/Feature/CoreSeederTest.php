<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

uses(RefreshDatabase::class);

it('seeds every world currency with only the curated set active', function () {
    $this->seed(CoreDatabaseSeeder::class);

    $fileEntries = require __DIR__.'/../../database/data/currencies.php';

    expect(Currency::count())->toBe(count($fileEntries))
        ->and(Currency::query()->where(CurrencySchema::IS_ACTIVE, true)->pluck(CurrencySchema::CODE)->sort()->values()->all())
        ->toBe(['EUR', 'IRR', 'USD'])
        ->and(Currency::query()->where(CurrencySchema::IS_DEFAULT, true)->value(CurrencySchema::CODE))->toBe('USD');
});

it('keeps the seeder idempotent and preserves admin edits on re-runs', function () {
    $this->seed(CoreDatabaseSeeder::class);

    // an admin-managed change on an existing row…
    Currency::query()->where(CurrencySchema::CODE, 'EUR')->update([
        CurrencySchema::EXCHANGE_RATE => '1.23400000',
        CurrencySchema::IS_ACTIVE => true,
    ]);
    // …and an activation of a previously inactive currency
    Currency::query()->where(CurrencySchema::CODE, 'GBP')->update([
        CurrencySchema::IS_ACTIVE => true,
        CurrencySchema::EXCHANGE_RATE => '0.79000000',
    ]);

    $this->seed(CoreDatabaseSeeder::class);

    expect(Currency::count())->toBe(count(require __DIR__.'/../../database/data/currencies.php'))
        ->and(Currency::query()->where(CurrencySchema::CODE, 'EUR')->value(CurrencySchema::EXCHANGE_RATE))->toBe('1.23400000')
        ->and(Currency::query()->where(CurrencySchema::CODE, 'GBP')->value(CurrencySchema::EXCHANGE_RATE))->toBe('0.79000000');
});

it('gives freshly-seeded inactive currencies the identity rate', function () {
    $this->seed(CoreDatabaseSeeder::class);

    $gbp = Currency::query()->where(CurrencySchema::CODE, 'GBP')->first();

    expect($gbp->{CurrencySchema::EXCHANGE_RATE})->toBe('1.00000000')
        ->and($gbp->{CurrencySchema::IS_ACTIVE})->toBeFalse();
});
