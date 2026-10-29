<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->superAdmin = User::factory()->superAdmin()->create());

it('lists active currencies default-first with their rates', function () {
    Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    Currency::factory()->create(['code' => 'EUR', 'exchange_rate' => '1.08500000']);
    Currency::factory()->create(['code' => 'GBP', 'is_active' => false]);

    $this->getJson('api/v1/core/public/currencies')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'USD')
        ->assertJsonPath('data.1.code', 'EUR')
        ->assertJsonPath('data.1.exchange_rate', '1.08500000');
});

it('creates a currency as super-admin and derives its permission from the route', function () {
    $this->actingAs($this->superAdmin);

    $this->postJson('api/v1/core/admin/currencies', [
        'code' => 'jpy',
        'name' => 'Japanese Yen',
        'decimal_places' => 0,
        'symbol' => '¥',
        'is_symbol_right' => true,
        'exchange_rate' => '149.50000000',
        'is_active' => true,
    ])->assertCreated()->assertJsonPath('data.code', 'JPY');

    $currency = Currency::query()->where(CurrencySchema::CODE, 'JPY')->first();
    expect($currency)->not->toBeNull()
        ->and($currency->{CurrencySchema::EXCHANGE_RATE})->toBe('149.50000000');

    // the route-name-derived permission exists after a sync
    $this->artisan('auth:sync-permissions');
    expect(Permission::query()->where('name', 'core.admin.currencies.create')->exists())->toBeTrue();
});

it('rejects a duplicate currency code and a malformed rate', function () {
    $this->actingAs($this->superAdmin);
    Currency::factory()->create(['code' => 'EUR']);

    $this->postJson('api/v1/core/admin/currencies', [
        'code' => 'EUR',
        'name' => 'Euro again',
        'decimal_places' => 2,
        'exchange_rate' => '1.00000000',
    ])->assertStatus(422);

    $this->postJson('api/v1/core/admin/currencies', [
        'code' => 'CHF',
        'name' => 'Swiss Franc',
        'decimal_places' => 2,
        'exchange_rate' => '-0.9',
    ])->assertStatus(422);

    expect(Currency::query()->where(CurrencySchema::CODE, 'CHF')->exists())->toBeFalse();
});

it('updates a rate, but the base currency stays pinned to 1 and active', function () {
    $this->actingAs($this->superAdmin);

    $base = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $euro = Currency::factory()->create(['code' => 'EUR', 'is_default' => false]);

    $this->patchJson("api/v1/core/admin/currencies/{$euro->id}", [
        'exchange_rate' => '1.09000000',
    ])->assertOk()->assertJsonPath('data.exchange_rate', '1.09000000');

    $this->patchJson("api/v1/core/admin/currencies/{$base->id}", [
        'exchange_rate' => '2.00000000',
        'is_active' => false,
    ])->assertOk();

    $base->refresh();
    // SQLite returns decimals unpadded — compare numerically
    expect((float) $base->{CurrencySchema::EXCHANGE_RATE})->toBe(1.0)
        ->and($base->{CurrencySchema::IS_ACTIVE})->toBeTrue();
});

it('denies currency management without permissions', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $this->postJson('api/v1/core/admin/currencies', [
        'code' => 'JPY',
        'name' => 'Japanese Yen',
        'decimal_places' => 0,
        'exchange_rate' => '149.50000000',
    ])->assertStatus(403);

    $currency = Currency::factory()->create();
    $this->patchJson("api/v1/core/admin/currencies/{$currency->id}", [
        'exchange_rate' => '2.00000000',
    ])->assertStatus(403);
});
