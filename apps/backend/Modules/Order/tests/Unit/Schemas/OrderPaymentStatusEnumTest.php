<?php

use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;

it('maps every payment transition', function (OrderPaymentStatusEnum $from, array $expected) {
    expect($from->transitions())->toBe($expected);
})->with([
    'unpaid' => [OrderPaymentStatusEnum::UNPAID, [OrderPaymentStatusEnum::PAID]],
    'paid' => [OrderPaymentStatusEnum::PAID, [OrderPaymentStatusEnum::PARTIALLY_REFUNDED, OrderPaymentStatusEnum::REFUNDED]],
    // The downward legs exist for gateway-initiated refund reversals
    // (OrderGateway::reverseRefund) — money returned to the merchant
    // must restore the pre-refund status.
    'partially refunded can finish or return to paid' => [OrderPaymentStatusEnum::PARTIALLY_REFUNDED, [OrderPaymentStatusEnum::REFUNDED, OrderPaymentStatusEnum::PAID]],
    'refunded can be walked back by a gateway reversal' => [OrderPaymentStatusEnum::REFUNDED, [OrderPaymentStatusEnum::PARTIALLY_REFUNDED, OrderPaymentStatusEnum::PAID]],
]);

it('allows same-status and legal transitions, rejects the rest', function (OrderPaymentStatusEnum $from, OrderPaymentStatusEnum $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'unpaid to paid' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::PAID, true],
    'unpaid straight to refunded is illegal' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::REFUNDED, false],
    'paid to unpaid is illegal' => [OrderPaymentStatusEnum::PAID, OrderPaymentStatusEnum::UNPAID, false],
    'paid to refunded' => [OrderPaymentStatusEnum::PAID, OrderPaymentStatusEnum::REFUNDED, true],
    'paid to partially refunded' => [OrderPaymentStatusEnum::PAID, OrderPaymentStatusEnum::PARTIALLY_REFUNDED, true],
    'partially refunded to refunded' => [OrderPaymentStatusEnum::PARTIALLY_REFUNDED, OrderPaymentStatusEnum::REFUNDED, true],
    'partially refunded back to paid (gateway reversal)' => [OrderPaymentStatusEnum::PARTIALLY_REFUNDED, OrderPaymentStatusEnum::PAID, true],
    'unpaid straight to partially refunded is illegal' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::PARTIALLY_REFUNDED, false],
    'refunded back to paid via gateway reversal' => [OrderPaymentStatusEnum::REFUNDED, OrderPaymentStatusEnum::PAID, true],
    'refunded back to unpaid is still illegal' => [OrderPaymentStatusEnum::REFUNDED, OrderPaymentStatusEnum::UNPAID, false],
    'same status is idempotent' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::UNPAID, true],
]);
