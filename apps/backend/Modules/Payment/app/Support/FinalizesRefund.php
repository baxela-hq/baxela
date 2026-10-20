<?php

namespace Modules\Payment\Support;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Support\Money;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;

/**
 * Completes a refund the gateway (or an admin resolution) confirmed as
 * successful: flips the ledger row and maintains the order-level
 * accounting in one transaction. Shared by the create and resolve flows
 * so both keep the same invariants.
 */
class FinalizesRefund
{
    /**
     * @return array{0: bool, 1: int, 2: int} [full refund?, refunded total minor, paid total minor]
     */
    public static function success(PaymentRefund $refund, ?string $transactionId): array
    {
        return DB::transaction(function () use ($refund, $transactionId): array {
            $refund = PaymentRefund::query()
                ->whereKey($refund->{PaymentRefundSchema::ID})
                ->lockForUpdate()
                ->first();

            $refund->update([
                PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS,
                PaymentRefundSchema::TRANSACTION_ID => $transactionId,
            ]);

            return self::applyOrderAccounting($refund);
        });
    }

    /**
     * Order-level accounting: the payment status derives from aggregate
     * totals across ALL the order's payments, never from the single
     * payment being refunded.
     *
     * @return array{0: bool, 1: int, 2: int}
     */
    private static function applyOrderAccounting(PaymentRefund $refund): array
    {
        $paidTotalMinor = self::minor(Payment::query()
            ->where(PaymentSchema::ORDER_ID, $refund->{PaymentRefundSchema::ORDER_ID})
            ->where(PaymentSchema::STATUS, PaymentStatusEnum::SUCCESS)
            ->sum(PaymentSchema::AMOUNT));

        $refundedTotalMinor = self::minor(PaymentRefund::query()
            ->where(PaymentRefundSchema::ORDER_ID, $refund->{PaymentRefundSchema::ORDER_ID})
            ->where(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::SUCCESS)
            ->sum(PaymentRefundSchema::AMOUNT));

        app(OrderGatewayInterface::class)->applyRefund(
            (int) $refund->{PaymentRefundSchema::ORDER_ID},
            $refundedTotalMinor,
            $paidTotalMinor,
        );

        $full = $paidTotalMinor > 0 && $refundedTotalMinor >= $paidTotalMinor;

        return [$full, $refundedTotalMinor, $paidTotalMinor];
    }

    /**
     * Normalize a decimal column sum to exact integer minor units.
     */
    public static function minor(float|int|string|null $sum): int
    {
        return Money::fromDecimal(number_format((float) ($sum ?? 0), 2, '.', ''));
    }
}
