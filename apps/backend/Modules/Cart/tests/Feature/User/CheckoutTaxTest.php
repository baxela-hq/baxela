<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Core\Models\Currency;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('charges and snapshots taxes at checkout against hand-computed values', function () {
    Currency::factory()->create(['is_default' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $default = $this->defaultTaxClass();
    $shipping = $this->taxClass(isShipping: true);
    $this->taxRate($default, '8.5');
    $this->taxRate($shipping, '8.5');

    // $100.00 × 2 + $5.00 shipping, 8.5% on both
    $variant = $this->variantWithStock(10, price: 100);
    $address = $this->addressForUser($user, 'US');
    $method = $this->shippingMethodForCountry(price: 5.0);
    $this->cartWithItem($user, $variant->id, quantity: 2, price: 100);

    $response = $this->checkoutCart($user, $address, $method)->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // line tax: 8.5% of 200.00 = 17.00; shipping tax: 8.5% of 5.00 = 0.43
    // total: 200.00 − 0 + 5.00 + 17.43 = 222.43
    expect((float) $order->{OrderSchema::TAX_AMOUNT})->toBe(17.43)
        ->and((float) $order->{OrderSchema::SHIPPING_TAX_AMOUNT})->toBe(0.43)
        ->and((float) $order->{OrderSchema::SHIPPING_TAX_RATE})->toBe(8.5)
        ->and($order->{OrderSchema::SHIPPING_TAX_CLASS_ID})->toBe($shipping->id)
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(222.43);

    $item = OrderItem::query()
        ->where(OrderItemSchema::VARIANT_ID, $variant->id)
        ->where(OrderItemSchema::ORDER_ID, $order->id)
        ->first();

    expect((float) $item->{OrderItemSchema::TAX_AMOUNT})->toBe(17.0)
        ->and((float) $item->{OrderItemSchema::TAX_RATE})->toBe(8.5)
        ->and($item->{OrderItemSchema::TAX_CLASS_ID})->toBe($default->id);
});

it('applies mixed classes per line and taxes no shipping without a method', function () {
    Currency::factory()->create(['is_default' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $default = $this->defaultTaxClass();
    $reduced = $this->taxClass();
    $this->taxRate($default, '8.5');
    $this->taxRate($reduced, '4.0');

    $full = $this->variantWithStock(10, price: 100);
    $reducedLine = $this->variantWithStock(10, price: 50);
    // the reduced product explicitly carries its class
    $reducedLine->product->{ProductSchema::TAX_CLASS_ID} = $reduced->id;
    $reducedLine->product->save();

    $address = $this->addressForUser($user, 'US');
    $this->cartWithItem($user, $full->id, quantity: 1, price: 100);
    $this->cartWithItem($user, $reducedLine->id, quantity: 1, price: 50);

    $response = $this->checkoutCart($user, $address)->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // 8.5% of 100.00 = 8.50 + 4% of 50.00 = 2.00 → 10.50 total
    expect((float) $order->{OrderSchema::TAX_AMOUNT})->toBe(10.5)
        ->and((float) $order->{OrderSchema::SHIPPING_TAX_AMOUNT})->toBe(0.0)
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(160.5);

    $rates = OrderItem::query()
        ->where(OrderItemSchema::ORDER_ID, $order->id)
        ->pluck(OrderItemSchema::TAX_RATE, OrderItemSchema::VARIANT_ID);

    expect((float) $rates[$full->id])->toBe(8.5)
        ->and((float) $rates[$reducedLine->id])->toBe(4.0);
});

it('keeps historical order taxes immutable when rates change later', function () {
    Currency::factory()->create(['is_default' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $default = $this->defaultTaxClass();
    $shippingClass = $this->taxClass(isShipping: true);
    $rate = $this->taxRate($default, '8.5');
    $this->taxRate($shippingClass, '8.5');

    $variant = $this->variantWithStock(10, price: 100);
    $address = $this->addressForUser($user, 'US');
    $method = $this->shippingMethodForCountry(price: 5.0);
    $this->cartWithItem($user, $variant->id, quantity: 2, price: 100);

    $response = $this->checkoutCart($user, $address, $method)->assertOk();
    $order = Order::query()->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))->first();

    // the admin edits the rate afterwards
    $rate->update([TaxRateSchema::RATE => '20.0']);
    $this->taxRate($default, '0.0', 'DE');

    $fresh = $order->fresh();
    expect((float) $fresh->{OrderSchema::TAX_AMOUNT})->toBe(17.43)
        ->and((float) $fresh->{OrderSchema::TOTAL_AMOUNT})->toBe(222.43)
        ->and((float) $fresh->items()->first()->{OrderItemSchema::TAX_RATE})->toBe(8.5);
});

it('refuses checkout loudly when a product references a deleted class', function () {
    Currency::factory()->create(['is_default' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->defaultTaxClass();

    $variant = $this->variantWithStock(10, price: 100);
    // simulate a dangling reference (the CRUD guards prevent creating one;
    // raw writes can still strand one)
    $variant->product->{ProductSchema::TAX_CLASS_ID} = 424242;
    $variant->product->save();

    $address = $this->addressForUser($user, 'US');
    $this->cartWithItem($user, $variant->id, quantity: 1, price: 100);

    $this->checkoutCart($user, $address)
        ->assertStatus(500)
        ->assertJsonPath('code', 'tax.configuration.invalid_class');

    expect(Order::count())->toBe(0)
        ->and(Cart::query()->where(CartSchema::USER_ID, $user->id)->exists())->toBeTrue();
});
