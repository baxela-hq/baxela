<?php

use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;

it('maps every payment transition', function (OrderPaymentStatusEnum $from, array $expected) {
    expect($from->transitions())->toBe($expected);
})->with([
    'unpaid' => [OrderPaymentStatusEnum::UNPAID, [OrderPaymentStatusEnum::PAID]],
    'paid' => [OrderPaymentStatusEnum::PAID, [OrderPaymentStatusEnum::REFUNDED]],
    'refunded is terminal' => [OrderPaymentStatusEnum::REFUNDED, []],
]);

it('allows same-status and legal transitions, rejects the rest', function (OrderPaymentStatusEnum $from, OrderPaymentStatusEnum $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'unpaid to paid' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::PAID, true],
    'unpaid straight to refunded is illegal' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::REFUNDED, false],
    'paid to unpaid is illegal' => [OrderPaymentStatusEnum::PAID, OrderPaymentStatusEnum::UNPAID, false],
    'paid to refunded' => [OrderPaymentStatusEnum::PAID, OrderPaymentStatusEnum::REFUNDED, true],
    'refunded back to paid is illegal' => [OrderPaymentStatusEnum::REFUNDED, OrderPaymentStatusEnum::PAID, false],
    'same status is idempotent' => [OrderPaymentStatusEnum::UNPAID, OrderPaymentStatusEnum::UNPAID, true],
]);
