<?php

namespace Modules\Payment\Support;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\RefundFinalizedEvent;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Support\Money;
use Modules\Core\Utils\Locale;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;

/**
 * The single shape of refund-finalized dispatch (gap-B1 D10): every
 * finalize site fires through here so the payload contract and the
 * after-commit guarantee (D15) live in exactly one place.
 */
class FiresRefundFinalizedEvent
{
    public static function from(PaymentRefund $refund): void
    {
        $payment = Payment::query()->findOrFail($refund->{PaymentRefundSchema::PAYMENT_ID});
        $currency = app(CoreGatewayInterface::class)
            ->getCurrencyById((int) $payment->{PaymentSchema::CURRENCY_ID});

        DB::afterCommit(function () use ($refund, $payment, $currency): void {
            event(RefundFinalizedEvent::fill([
                'payment_id' => (int) $payment->{PaymentSchema::ID},
                'order_id' => (int) $refund->{PaymentRefundSchema::ORDER_ID},
                'refund_uuid' => (string) $refund->{PaymentRefundSchema::UUID},
                'amount_minor' => Money::fromDecimal((string) $refund->{PaymentRefundSchema::AMOUNT}),
                'currency_code' => (string) ($currency?->code ?? ''),
                'reason' => $refund->{PaymentRefundSchema::REASON},
                'locale' => Locale::fromRequest(),
            ]));
        });
    }
}
