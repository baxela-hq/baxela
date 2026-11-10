<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Payment\RefundFinalizedEvent;
use Modules\Core\Models\Currency;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * gap-B1 Phase 3 / D10: every successful refund finalization — the
 * direct admin flow, the webhook confirm, and the ambiguity resolution
 * — dispatches RefundFinalizedEvent exactly once, after commit, with
 * the refund's identity on it. The credit-note automation (Phase 4)
 * keys its idempotency on that refund_uuid.
 */
function refundablePaidOrderWithManualPayment(): array
{
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'Euro', 'is_default' => true],
    );

    $order = Order::query()->create([
        OrderSchema::USER_ID => 1,
        OrderSchema::ORDER_CODE => 'RFE'.uniqid(),
        OrderSchema::STATUS => OrderStatusEnum::PROCESSING,
        OrderSchema::PAYMENT_STATUS => OrderPaymentStatusEnum::PAID,
        OrderSchema::PAID_AT => now(),
        OrderSchema::TOTAL_AMOUNT => 100.0,
        OrderSchema::CURRENCY_ID => $currency->id,
        OrderSchema::EXPIRES_AT => now()->addHour(),
    ]);

    $payment = Payment::query()->create([
        PaymentSchema::ORDER_ID => $order->id,
        PaymentSchema::METHOD => 'manual',
        PaymentSchema::STATUS => PaymentStatusEnum::SUCCESS,
        PaymentSchema::AMOUNT => 100.0,
        PaymentSchema::CURRENCY_ID => $currency->id,
    ]);

    return [$order, $payment];
}

it('fires RefundFinalizedEvent when the admin refund finalizes directly', function () {
    Event::fake([RefundFinalizedEvent::class]);
    [$order, $payment] = refundablePaidOrderWithManualPayment();
    $this->actingAs($this->superAdminUser());

    $this->postJson($this->baseUrl("/admin/payments/{$payment->id}/refunds"), [
        'amount' => '40.00',
        'reason' => 'partial return',
    ])->assertCreated();

    $refund = PaymentRefund::query()
        ->where(PaymentRefundSchema::PAYMENT_ID, $payment->id)
        ->first();

    expect($refund->{PaymentRefundSchema::STATUS})
        ->toBe(PaymentRefundStatusEnum::SUCCESS);

    Event::assertDispatchedTimes(RefundFinalizedEvent::class, 1);
    Event::assertDispatched(RefundFinalizedEvent::class, function ($event) use ($order, $refund): bool {
        return $event->refund_uuid === $refund->{PaymentRefundSchema::UUID}
            && $event->order_id === $order->id
            && $event->amount_minor === 4000
            && $event->currency_code === 'EUR'
            && $event->reason === 'partial return';
    });
});

it('does not fire for a resolution to failed, and fires once when resolved to success', function () {
    [$order, $payment] = refundablePaidOrderWithManualPayment();

    $failed = PaymentRefund::query()->create([
        PaymentRefundSchema::UUID => (string) Str::uuid(),
        PaymentRefundSchema::PAYMENT_ID => $payment->id,
        PaymentRefundSchema::ORDER_ID => $order->id,
        PaymentRefundSchema::AMOUNT => 10.0,
        PaymentRefundSchema::REASON => 'ambiguous',
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
    ]);

    $this->actingAs($this->superAdminUser());

    Event::fake([RefundFinalizedEvent::class]);

    // Resolving the ambiguity as failure frees the reservation — no
    // finalized refund, no event.
    $this->patchJson($this->baseUrl("/admin/refunds/{$failed->id}/resolve"), [
        'status' => 'failed',
    ])->assertOk();

    Event::assertNotDispatched(RefundFinalizedEvent::class);

    $succeeded = PaymentRefund::query()->create([
        PaymentRefundSchema::UUID => (string) Str::uuid(),
        PaymentRefundSchema::PAYMENT_ID => $payment->id,
        PaymentRefundSchema::ORDER_ID => $order->id,
        PaymentRefundSchema::AMOUNT => 20.0,
        PaymentRefundSchema::REASON => 'ambiguous ok',
        PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
    ]);

    $this->patchJson($this->baseUrl("/admin/refunds/{$succeeded->id}/resolve"), [
        'status' => 'success',
        'transaction_id' => 'ref-777',
    ])->assertOk();

    Event::assertDispatchedTimes(RefundFinalizedEvent::class, 1);
    Event::assertDispatched(RefundFinalizedEvent::class, fn ($event): bool => $event->refund_uuid === $succeeded->{PaymentRefundSchema::UUID}
        && $event->amount_minor === 2000);
});
