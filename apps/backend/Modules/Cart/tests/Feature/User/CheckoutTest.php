<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Cart\Models\Cart;
use Modules\Core\Models\Currency;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Tests\Feature\HelperTrait;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(fn () => $this->defaultTaxClass());

function userCartWithItem(User $user, int $variantId, int $quantity = 2, int $price = 150): Cart
{
    $cart = Cart::query()->firstOrCreate(
        [CartSchema::USER_ID => $user->id],
        [CartSchema::TOKEN => null],
    );

    CartItem::query()->create([
        CartItemSchema::CART_ID => $cart->id,
        CartItemSchema::VARIANT_ID => $variantId,
        CartItemSchema::QUANTITY => $quantity,
        CartItemSchema::PRICE_SNAPSHOT => $price,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'Test Product',
    ]);

    return $cart;
}

it('checks out a cart: order created, stock taken, cart torn down, shipping quoted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10, price: 150);
    $address = $this->addressForUser($user);
    $method = $this->shippingMethodForCountry(price: 5.0);
    $cart = userCartWithItem($user, $variant->id);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'shipping_method_id' => $method->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    expect($order)->not->toBeNull()
        ->and((int) $order->{OrderSchema::USER_ID})->toBe($user->id)
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(305.0) // 150 × 2 + 5
        ->and($order->{OrderSchema::SHIPPING_METHOD_NAME})->toBe('Express')
        // stock reserved by the checkout
        ->and((int) Modules\Inventory\Models\InventoryStock::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(8)
        // the cart and its items are gone
        ->and(Cart::query()->where(CartSchema::USER_ID, $user->id)->exists())->toBeFalse()
        ->and(CartItem::query()->where(CartItemSchema::CART_ID, $cart->id)->count())->toBe(0);
});

it('rejects checkout of an empty cart', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $address = $this->addressForUser($user);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'shipping_method_id' => null,
    ])->assertStatus(400)->assertJsonPath('code', 'cart.checkout.empty');

    expect(Order::count())->toBe(0);
});

it('rejects checkout against an address the user does not own', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    userCartWithItem($user, $variant->id);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $this->addressForUser(User::factory()->create())->id,
        'shipping_method_id' => null,
    ])->assertStatus(400)->assertJsonPath('code', 'cart.checkout.invalid_address');

    expect(Order::count())->toBe(0);
});

it('rejects checkout when stock no longer covers the cart', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(3);
    $cart = userCartWithItem($user, $variant->id, quantity: 5);
    $address = $this->addressForUser($user);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'shipping_method_id' => null,
    ])->assertStatus(400)->assertJsonPath('code', 'cart.checkout.insufficient_stock');

    expect(Order::count())->toBe(0)
        // nothing was decremented and the cart survives for a retry
        ->and((int) Modules\Inventory\Models\InventoryStock::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(3)
        ->and($cart->items()->count())->toBe(1);
});

it('rejects checkout with a shipping method that does not quote for the address country', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $variant = $this->variantWithStock(10);
    userCartWithItem($user, $variant->id);
    $address = $this->addressForUser($user);

    $dormantMethod = \Modules\Shipping\Models\Method::factory()->create(['is_active' => false]);

    $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'shipping_method_id' => $dormantMethod->id,
    ])->assertStatus(400)->assertJsonPath('code', 'cart.checkout.invalid_shipping_method');

    expect(Order::count())->toBe(0);
});

it('snapshots the default currency onto the order at checkout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create(['is_default' => true]);
    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);
    $cart = userCartWithItem($user, $variant->id);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    expect($order->{OrderSchema::CURRENCY_ID})->toBe($currency->id);
});

it('snapshots the display currency and fx rate when a non-base currency was selected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Currency::factory()->create(['is_default' => true, 'code' => 'USD']);
    $euro = Currency::factory()->create([
        'code' => 'EUR',
        'exchange_rate' => '1.08500000',
        'is_active' => true,
        'is_default' => false,
    ]);
    $variant = $this->variantWithStock(10, price: 150);
    $address = $this->addressForUser($user);
    userCartWithItem($user, $variant->id);

    $response = $this->postJson($this->baseUrl('/user/checkout'), [
        'address_id' => $address->id,
        'display_currency' => 'eur',
    ])->assertOk();

    $order = Order::query()
        ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
        ->first();

    // Code is normalized, rate comes from the active record — never the client
    expect($order->{OrderSchema::DISPLAY_CURRENCY_CODE})->toBe('EUR')
        // SQLite returns the decimal unpadded — compare numerically
        ->and((float) $order->{OrderSchema::DISPLAY_FX_RATE})->toBe(1.085)
        // charging amounts stay in the base currency, untouched
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(300.0);
});

it('ignores unknown or inactive display currencies instead of blocking checkout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Currency::factory()->create(['is_default' => true, 'code' => 'USD']);
    Currency::factory()->create(['code' => 'GBP', 'is_active' => false, 'is_default' => false]);
    $variant = $this->variantWithStock(10);
    $address = $this->addressForUser($user);

    foreach (['XXX', 'gbp'] as $code) {
        // checkout tears the cart down — rebuild it per iteration
        userCartWithItem($user, $variant->id);

        $response = $this->postJson($this->baseUrl('/user/checkout'), [
            'address_id' => $address->id,
            'display_currency' => $code,
        ])->assertOk();

        $order = Order::query()
            ->where(OrderSchema::ORDER_CODE, $response->json('data.order_code'))
            ->first();

        expect($order->{OrderSchema::DISPLAY_CURRENCY_CODE})->toBeNull()
            ->and($order->{OrderSchema::DISPLAY_FX_RATE})->toBeNull();
    }
});
