<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Setting\Database\Seeders\SettingDatabaseSeeder;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Gap-B1 sliver regression: the store legal-identity settings must EXIST
 * as rows — the admin settings form always submits all four fields and
 * UpdateSettingAction resolves each by name with firstOrFail, so missing
 * rows made every settings PATCH fail. The idempotent seeder is the row
 * source (the FX-settings pattern).
 */
/** The seeder reads the default language/currency through the Core gateway. */
function identitySeedOnce(): void
{
    Language::factory()->create(['is_default' => true]);
    Currency::factory()->create(['is_default' => true]);

    test()->seed(SettingDatabaseSeeder::class);
}

it('seeds the four store identity settings', function () {
    identitySeedOnce();

    foreach ([
        SettingNameEnum::STORE_LEGAL_NAME,
        SettingNameEnum::STORE_LEGAL_ADDRESS,
        SettingNameEnum::STORE_TAX_ID,
        SettingNameEnum::STORE_CONTACT,
    ] as $name) {
        expect(Setting::query()->where(SettingSchema::NAME, $name->value)->exists())->toBeTrue();
    }
});

it('persists admin updates of the store identity settings', function () {
    identitySeedOnce();
    $this->actingAs($this->superAdminUser());

    $this->patchJson($this->baseUrl('/admin/settings'), [
        ['name' => 'store_legal_name', 'value' => 'Baxela Commerce GmbH', 'translations' => null],
        ['name' => 'store_legal_address', 'value' => 'Musterstrasse 1, 10115 Berlin', 'translations' => null],
        ['name' => 'store_tax_id', 'value' => 'DE123456789', 'translations' => null],
        ['name' => 'store_contact', 'value' => 'billing@baxela.test', 'translations' => null],
    ])->assertOk();

    expect(Setting::query()->where('name', 'store_legal_name')->value('value'))->toBe('Baxela Commerce GmbH')
        ->and(Setting::query()->where('name', 'store_tax_id')->value('value'))->toBe('DE123456789');
});

it('preserves configured store identity values when the seeder re-runs', function () {
    identitySeedOnce();

    Setting::query()->where('name', 'store_legal_name')
        ->update(['value' => 'Kept Name Ltd']);

    test()->seed(SettingDatabaseSeeder::class);

    expect(Setting::query()->where('name', 'store_legal_name')->value('value'))->toBe('Kept Name Ltd');
});
