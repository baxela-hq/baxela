<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFormat;
use Modules\User\Models\Address;
use Modules\User\Models\Profile;
use Modules\User\Models\WishlistItem;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Tests\Feature\HelperTrait;
use Modules\User\Transfer\UserTransfer;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports the user directory, addresses and wishlist items', function () {
    $this->actingAs($this->superAdminUser());

    // The identity owner: auth_users. The profile row is optional.
    $user = User::factory()->create(['email' => 'jane@example.com']);
    Profile::query()->create(['user_id' => $user->id, 'full_name' => 'Jane Doe']);
    Address::query()->create([
        'user_id' => $user->id, 'type' => 'shipping', 'full_name' => 'Jane Doe',
        'phone' => '123', 'address_line' => 'Street 1', 'city' => 'Berlin',
        'postal_code' => '10115', 'country_code' => 'DE', 'region_code' => 'BE',
        'vat_id' => null, 'is_default' => true,
    ]);
    WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => 42]);

    // A user WITHOUT a profile row: has_profile must disambiguate (C6).
    $bare = User::factory()->create(['email' => 'bare@example.com']);

    $file = $this->exportedUserDataFile();

    expect($file['module'])->toBe('user')
        ->and(array_column($file['sections'], 'entity'))->toBe(['profiles', 'addresses', 'wishlist-items']);

    $directory = collect($file['sections'][0]['rows'])->keyBy(fn ($row) => $row['payload']['email']);
    expect($directory->get('jane@example.com')['payload']['full_name'])->toBe('Jane Doe')
        ->and($directory->get('jane@example.com')['payload']['has_profile'])->toBeTrue()
        ->and($directory->get('jane@example.com')['source_id'])->toBe($user->id)
        ->and($directory->get('bare@example.com')['payload']['has_profile'])->toBeFalse()
        ->and($directory->get('bare@example.com')['payload']['full_name'])->toBeNull()
        ->and($directory->get('bare@example.com')['source_id'])->toBe($bare->id);

    expect($file['sections'][1]['rows'][0]['payload'][AddressSchema::COUNTRY_CODE])->toBe('DE')
        ->and($file['sections'][1]['rows'][0]['payload'][AddressSchema::REGION_CODE])->toBe('BE')
        ->and($file['sections'][1]['rows'][0]['payload'][AddressSchema::USER_ID])->toBe($user->id)
        ->and($file['sections'][2]['rows'][0]['payload']['product_id'])->toBe(42);
});

it('round-trips users, attaching to local accounts by email (C6)', function () {
    $this->actingAs($this->superAdminUser());

    // Source store: Jane with profile, address, wishlist; Bare without.
    // The wishlist points at a real catalog product (cross-module refs
    // resolve against the destination catalog).
    $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
    $jane = User::factory()->create(['email' => 'jane@example.com']);
    Profile::query()->create(['user_id' => $jane->id, 'full_name' => 'Jane Doe', 'bio' => 'hi']);
    Address::query()->create([
        'user_id' => $jane->id, 'type' => 'shipping', 'full_name' => 'Jane Doe',
        'phone' => '123', 'address_line' => 'Street 1', 'city' => 'Berlin',
        'country_code' => 'DE', 'is_default' => true,
    ]);
    WishlistItem::query()->create(['user_id' => $jane->id, 'product_id' => $product->id]);
    $bare = User::factory()->create(['email' => 'bare@example.com']);

    $file = $this->exportedUserDataFile();
    $sourceIds = collect($file['sections'][0]['rows'])->keyBy(fn ($row) => $row['payload']['email']);

    // The destination "store": wipe the data planes but keep the auth
    // users and the catalog — Jane survives here; Bare does not exist in
    // this store; the product survives (its history is the destination's
    // own), so Jane's wishlist ref resolves via the raw tier of an
    // identified file (C2).
    $janeLocal = User::query()->where('email', 'jane@example.com')->first();
    Profile::query()->delete();
    Address::query()->delete();
    WishlistItem::query()->delete();
    User::query()->whereKey($bare->id)->delete();

    $summary = app(ModuleTransferRunner::class)->run(
        'transfer-user.json',
        json_encode($file),
        TransferStrategyEnum::UPDATE,
        false,
        'user',
        app(UserTransfer::class),
    );

    expect($summary['status'])->toBe('completed');

    // Jane attached to the surviving local account; her data restored.
    expect(Profile::query()->where('user_id', $janeLocal->id)->value('full_name'))->toBe('Jane Doe')
        ->and(Address::query()->where('user_id', $janeLocal->id)->value('city'))->toBe('Berlin')
        ->and(WishlistItem::query()->where('user_id', $janeLocal->id)->value('product_id'))->toBe($product->id);

    // Bare had no local account: an INERT placeholder was created (C6).
    $bareLocal = User::query()->where('email', 'bare@example.com')->first();
    expect($bareLocal)->not->toBeNull()
        ->and($bareLocal->is_active)->toBeFalse()
        ->and($bareLocal->email_verified_at)->toBeNull()
        ->and($bareLocal->hasRole('super-admin'))->toBeFalse();

    // The ledger: users, addresses, wishlist items all mapped per source.
    $sourceJane = (int) $sourceIds->get('jane@example.com')['source_id'];
    expect(TransferRemap::query()->where('module', 'user')->where('entity', 'users')->where('source_id', $sourceJane)->value('local_id'))
        ->toBe((int) $janeLocal->id);
});

it('fails legacy rows without emails instead of guessing identity (C6)', function () {
    $this->actingAs($this->superAdminUser());

    // A legacy v1 file: user refs only, no email key anywhere.
    $payload = [
        TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
        TransferFormat::KEY_MODULE => 'user',
        TransferFormat::KEY_VERSION => TransferFormat::VERSION,
        TransferFormat::KEY_EXPORTED_AT => now()->toIso8601String(),
        TransferFormat::KEY_SOURCE_INSTALLATION => (string) Str::uuid(),
        TransferFormat::KEY_SECTIONS => [
            [TransferFormat::KEY_ENTITY => 'profiles', TransferFormat::KEY_ROWS => [
                ['source_id' => 7, 'payload' => ['has_profile' => true, 'full_name' => 'Mystery']],
            ]],
        ],
    ];

    $summary = app(ModuleTransferRunner::class)->run(
        'transfer-user.json',
        json_encode($payload),
        TransferStrategyEnum::UPDATE,
        false,
        'user',
        app(UserTransfer::class),
    );

    expect($summary['failed_count'])->toBe(1)
        ->and(collect($summary['errors'])->first())->toContain('user_unresolvable_no_email')
        // Only the acting admin exists — no account was guessed into being.
        ->and(User::query()->count())->toBe(1);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
