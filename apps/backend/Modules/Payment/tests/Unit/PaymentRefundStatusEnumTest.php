<?php

use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

/**
 * The full refund transition matrix (gap-B4 plan D2a): every permitted
 * transition and the important forbidden ones. The ledger's integrity —
 * reservations held while uncertain, accounting applied once, reversals
 * terminal — rests on these guards.
 */
it('permits exactly the documented transitions', function (PaymentRefundStatusEnum $from, array $permitted) {
    foreach (PaymentRefundStatusEnum::cases() as $to) {
        expect($from->canTransitionTo($to))->toBe(in_array($to, $permitted, true));
    }
})->with([
    'pending' => [PaymentRefundStatusEnum::PENDING, [
        PaymentRefundStatusEnum::PENDING,
        PaymentRefundStatusEnum::PROCESSING,
        PaymentRefundStatusEnum::SUCCESS,
        PaymentRefundStatusEnum::FAILED,
        PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
        PaymentRefundStatusEnum::REVERSED,
    ]],
    'processing' => [PaymentRefundStatusEnum::PROCESSING, [
        PaymentRefundStatusEnum::PROCESSING,
        PaymentRefundStatusEnum::SUCCESS,
        PaymentRefundStatusEnum::FAILED,
        PaymentRefundStatusEnum::REVERSED,
    ]],
    'reconciliation_required' => [PaymentRefundStatusEnum::RECONCILIATION_REQUIRED, [
        PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
        PaymentRefundStatusEnum::SUCCESS,
        PaymentRefundStatusEnum::FAILED,
        PaymentRefundStatusEnum::REVERSED,
    ]],
    'success' => [PaymentRefundStatusEnum::SUCCESS, [
        PaymentRefundStatusEnum::SUCCESS,
        PaymentRefundStatusEnum::REVERSED,
    ]],
    'failed is terminal' => [PaymentRefundStatusEnum::FAILED, [PaymentRefundStatusEnum::FAILED]],
    'reversed is terminal' => [PaymentRefundStatusEnum::REVERSED, [PaymentRefundStatusEnum::REVERSED]],
]);

it('holds reservations while the outcome is uncertain and releases them when terminal', function () {
    expect(PaymentRefundStatusEnum::reserving())->toBe([
        PaymentRefundStatusEnum::PENDING,
        PaymentRefundStatusEnum::PROCESSING,
        PaymentRefundStatusEnum::SUCCESS,
        PaymentRefundStatusEnum::RECONCILIATION_REQUIRED,
    ])
        ->and(PaymentRefundStatusEnum::FAILED)->not->toBeIn(PaymentRefundStatusEnum::reserving())
        ->and(PaymentRefundStatusEnum::REVERSED)->not->toBeIn(PaymentRefundStatusEnum::reserving());
});

it('never lets a late success resurrect a reversed or failed refund', function (PaymentRefundStatusEnum $terminal) {
    expect($terminal->canTransitionTo(PaymentRefundStatusEnum::SUCCESS))->toBeFalse()
        ->and($terminal->canTransitionTo(PaymentRefundStatusEnum::PROCESSING))->toBeFalse()
        ->and($terminal->canTransitionTo(PaymentRefundStatusEnum::RECONCILIATION_REQUIRED))->toBeFalse();
})->with([
    'reversed' => PaymentRefundStatusEnum::REVERSED,
    'failed' => PaymentRefundStatusEnum::FAILED,
]);
