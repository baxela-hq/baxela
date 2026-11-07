<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('exports payments, methods and refunds (export-only)', function () {
    $this->actingAs($this->superAdminUser());

    // The refunds table carries a real FK to order_orders.
    $order = Order::query()->create([
        'user_id' => 7, 'status' => 'completed', 'payment_status' => 'refunded',
        'total_amount' => '150.00', 'currency_id' => 1,
        'display_currency_code' => 'USD', 'display_fx_rate' => '1.00000000',
        'discount_amount' => '0.00',
    ]);

    PaymentMethod::query()->create(['method' => 'manual', 'is_active' => true, 'sort_order' => 1]);
    $payment = Payment::query()->create([
        'order_id' => $order->id, 'transaction_id' => 'tx-1', 'method' => 'manual',
        'amount' => '150.00', 'currency_id' => 1, 'status' => 'success',
    ]);
    PaymentRefund::query()->create([
        'uuid' => 'r-1', 'payment_id' => $payment->id, 'order_id' => $order->id,
        'amount' => '50.00', 'reason' => 'damaged', 'status' => 'success',
    ]);

    $file = $this->exportedPaymentDataFile();

    expect($file['module'])->toBe('payment')
        ->and(array_column($file['sections'], 'entity'))->toBe(['payment-methods', 'payments', 'refunds'])
        ->and($file['sections'][0]['rows'][0]['payload'][PaymentMethodSchema::METHOD])->toBe('manual')
        ->and($file['sections'][1]['rows'][0]['payload'][PaymentSchema::TRANSACTION_ID])->toBe('tx-1')
        ->and($file['sections'][1]['rows'][0]['payload']['status'])->toBe('success')
        ->and((float) $file['sections'][2]['rows'][0]['payload'][PaymentRefundSchema::AMOUNT])->toBe(50.00);
});

it('denies the data endpoints to guests', function () {
    $this->getJson($this->baseUrl('/admin/data/export'))->assertStatus(401);
});
