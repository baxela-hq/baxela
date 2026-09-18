<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

function adminPaymentFor(Order $order, PaymentStatusEnum $status): Payment
{
    return Payment::factory()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => PaymentMethodEnum::MANUAL,
        PaymentSchema::STATUS => $status,
    ]);
}

it('denies a guest with 401', function () {
    $this->getJson($this->baseUrl('/admin/payments'))->assertStatus(401);
});

it('lists payments with ids and timestamps for the admin grid', function () {
    $this->actingAs($this->superAdminUser());
    $order = Order::factory()->create();
    $payment = adminPaymentFor($order, PaymentStatusEnum::PENDING);

    $response = $this->getJson($this->baseUrl('/admin/payments'))->assertOk();

    $row = collect($response->json('data'))->firstWhere(PaymentSchema::ID, $payment->id);

    expect($row)->not->toBeNull()
        ->and($row[PaymentSchema::ORDER_ID])->toBe($order->id)
        ->and($row[PaymentSchema::STATUS])->toBe(PaymentStatusEnum::PENDING->value)
        ->and($row[PaymentSchema::CREATED_AT])->not->toBeNull();
});

it('filters by status and method', function () {
    $this->actingAs($this->superAdminUser());
    $order = Order::factory()->create();
    adminPaymentFor($order, PaymentStatusEnum::PENDING);
    adminPaymentFor($order, PaymentStatusEnum::SUCCESS);

    $this->getJson($this->baseUrl('/admin/payments?filter[status]=pending'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.'.PaymentSchema::STATUS, PaymentStatusEnum::PENDING->value);

    $this->getJson($this->baseUrl('/admin/payments?filter[method]=paypal'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('sorts newest first by default and honours explicit sorts', function () {
    $this->actingAs($this->superAdminUser());
    $order = Order::factory()->create();
    $older = adminPaymentFor($order, PaymentStatusEnum::PENDING);
    $newer = adminPaymentFor($order, PaymentStatusEnum::PENDING);

    $this->getJson($this->baseUrl('/admin/payments'))
        ->assertOk()
        ->assertJsonPath('data.0.'.PaymentSchema::ID, $newer->id);

    $this->getJson($this->baseUrl('/admin/payments?sort=id'))
        ->assertOk()
        ->assertJsonPath('data.0.'.PaymentSchema::ID, $older->id);
});
