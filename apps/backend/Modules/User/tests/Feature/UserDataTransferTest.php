<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\Address;
use Modules\User\Models\Profile;
use Modules\User\Models\WishlistItem;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports profiles, addresses and wishlist items (export-only)', function () {
    $this->actingAs($this->superAdminUser());

    $profile = Profile::query()->create(['user_id' => 7, 'full_name' => 'Jane Doe']);
    $address = Address::query()->create([
        'user_id' => 7, 'type' => 'shipping', 'full_name' => 'Jane Doe',
        'phone' => '123', 'address_line' => 'Street 1', 'city' => 'Berlin',
        'postal_code' => '10115', 'country_code' => 'DE', 'is_default' => true,
    ]);
    WishlistItem::query()->create(['user_id' => 7, 'product_id' => 42]);

    $file = $this->exportedUserDataFile();

    expect($file['module'])->toBe('user')
        ->and(array_column($file['sections'], 'entity'))->toBe(['profiles', 'addresses', 'wishlist-items'])
        ->and($file['sections'][0]['rows'][0]['payload']['full_name'])->toBe('Jane Doe')
        ->and($file['sections'][1]['rows'][0]['payload'][AddressSchema::COUNTRY_CODE])->toBe('DE')
        ->and($file['sections'][2]['rows'][0]['payload']['product_id'])->toBe(42);

    // The capabilities matrix flags everything export-only.
    $capability = collect(
        $this->getJson('api/v1/transfer/admin/capabilities')->assertOk()->json('data')
    )->firstWhere('module', 'user');
    foreach ($capability['sections'] as $section) {
        expect($section['exportable'])->toBeTrue()->and($section['importable'])->toBeFalse();
    }
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
