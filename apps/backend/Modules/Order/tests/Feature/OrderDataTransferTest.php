<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports orders with items, addresses and returns (export-only)', function () {
    $this->actingAs($this->superAdminUser());

    $order = Order::query()->create([
        'user_id' => 7, 'status' => 'pending', 'payment_status' => 'unpaid',
        'total_amount' => '150.00', 'currency_id' => 1,
        'display_currency_code' => 'USD', 'display_fx_rate' => '1.00000000',
        'shipping_method_name' => 'Express', 'shipping_cost' => '9.99',
        'discount_amount' => '0.00',
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id, 'variant_id' => 5, 'product_name_snapshot' => 'Tee',
        'product_slug_snapshot' => 'tee', 'price_snapshot' => '140.01', 'quantity' => 1,
    ]);
    OrderAddress::query()->create([
        'order_id' => $order->id, 'type' => 'shipping', 'full_name' => 'Jane',
        'phone' => '1', 'address_line' => 'St 1', 'city' => 'Berlin', 'postal_code' => '10', 'country_code' => 'DE',
    ]);
    OrderReturn::query()->create([
        'order_id' => $order->id, 'user_id' => 7, 'status' => 'requested', 'reason' => 'damaged',
    ]);

    $file = $this->exportedOrderDataFile();

    expect($file['module'])->toBe('order')
        ->and(array_column($file['sections'], 'entity'))->toBe(['orders', 'order-items', 'order-addresses', 'order-returns'])
        ->and($file['sections'][0]['rows'][0]['payload']['order_code'])->toBe($order->order_code)
        ->and($file['sections'][0]['rows'][0]['payload']['status'])->toBe('pending')
        ->and($file['sections'][1]['rows'][0]['payload'][OrderItemSchema::PRODUCT_NAME_SNAPSHOT])->toBe('Tee')
        ->and($file['sections'][2]['rows'][0]['payload']['country_code'])->toBe('DE')
        ->and($file['sections'][3]['rows'][0]['payload']['reason'])->toBe('damaged');
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
