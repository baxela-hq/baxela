<?php

namespace Modules\Payment\Actions\Admin\PaymentRefund;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Utils\Locale;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Support\FinalizesRefund;

class ResolveRefundAction
{
    /**
     * Close a RECONCILIATION_REQUIRED ledger row: the gateway outcome was
     * ambiguous (timeout) and an operator has since verified what actually
     * happened. Confirming success runs the same order-accounting
     * finalization as the live flow; confirming failure frees the
     * reserved amount.
     *
     * @param  array{status: string, transaction_id?: string|null}  $data
     *
     * @throws PaymentException
     */
    public function handle(string $id, array $data): PaymentRefund
    {
        $status = PaymentRefundStatusEnum::from($data[PaymentRefundSchema::STATUS]);

        $refund = DB::transaction(function () use ($id, $status): PaymentRefund {
            $refund = PaymentRefund::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! $refund->{PaymentRefundSchema::STATUS}->canTransitionTo($status)) {
                throw PaymentException::refundInvalidStatus();
            }

            return $refund;
        });

        if ($status === PaymentRefundStatusEnum::SUCCESS) {
            [$full] = FinalizesRefund::success($refund, $data[PaymentRefundSchema::TRANSACTION_ID] ?? null);

            event(PaymentRefundedEvent::fill([
                PaymentRefundSchema::ID => (int) $refund->{PaymentRefundSchema::ID},
                PaymentSchema::ID => (int) $refund->{PaymentRefundSchema::PAYMENT_ID},
                PaymentSchema::ORDER_ID => (int) $refund->{PaymentRefundSchema::ORDER_ID},
                PaymentSchema::AMOUNT => (float) $refund->{PaymentRefundSchema::AMOUNT},
                'full' => $full,
                PaymentRefundSchema::TRANSACTION_ID => (string) $refund->refresh()->{PaymentRefundSchema::TRANSACTION_ID},
                PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS->value,
                'locale' => Locale::fromRequest(),
            ]));

            return $refund->refresh();
        }

        $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED]);

        return $refund->refresh();
    }
}
