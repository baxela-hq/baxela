<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\User\Models\Address;
use Modules\User\Schemas\Address\AddressSchema;

uses(RefreshDatabase::class);

it('keeps a customer address book global while staff see only their store order snapshots', function () {
    $context = app(TenantContext::class);

    $makeTenant = fn (string $slug): Tenant => Tenant::query()->create([
        TenantSchema::NAME => ucfirst($slug),
        TenantSchema::SLUG => $slug,
        TenantSchema::EMAIL => $slug.'@example.test',
        TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
    ]);

    $acme = $makeTenant('acme');
    $other = $makeTenant('other');

    $customer = User::factory()->active()->create();
    $stranger = User::factory()->active()->create();

    // The address book is a user-owned profile table (rev 4.3, B4): no
    // tenant column, and the same customer can read it in any store.
    $ownAddress = Address::factory()->create([AddressSchema::USER_ID => $customer->getKey()]);
    $strangerAddress = Address::factory()->create([AddressSchema::USER_ID => $stranger->getKey()]);

    $snapshot = fn (int $tenantId): OrderAddress => $context->runForTenant($tenantId, function () use ($customer): OrderAddress {
        $order = Order::factory()->create([OrderSchema::USER_ID => $customer->getKey()]);

        return OrderAddress::factory()->create([OrderAddressSchema::ORDER_ID => $order->getKey()]);
    });

    $acmeSnapshot = $snapshot((int) $acme->getKey());
    $otherSnapshot = $snapshot((int) $other->getKey());

    foreach ([(int) $acme->getKey(), (int) $other->getKey()] as $tenantId) {
        $context->runForTenant($tenantId, function () use ($customer, $ownAddress, $strangerAddress): void {
            $addressIds = Address::query()->where(AddressSchema::USER_ID, $customer->getKey())->pluck('id')->all();

            expect($addressIds)->toBe([$ownAddress->getKey()])
                ->and(in_array($strangerAddress->getKey(), $addressIds, true))->toBeFalse();
        });
    }

    $context->runForTenant((int) $acme->getKey(), function () use ($acmeSnapshot, $otherSnapshot): void {
        expect(OrderAddress::query()->pluck('id')->all())->toBe([$acmeSnapshot->getKey()])
            ->and(OrderAddress::query()->find($otherSnapshot->getKey()))->toBeNull();
    });

    $context->runForTenant((int) $other->getKey(), function () use ($acmeSnapshot, $otherSnapshot): void {
        expect(OrderAddress::query()->pluck('id')->all())->toBe([$otherSnapshot->getKey()])
            ->and(OrderAddress::query()->find($acmeSnapshot->getKey()))->toBeNull();
    });
});
