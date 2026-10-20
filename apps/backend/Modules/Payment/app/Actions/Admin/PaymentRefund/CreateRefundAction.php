<?php

namespace Modules\Payment\Actions\Admin\PaymentRefund;

use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Support\Money;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Support\FinalizesRefund;
use Stripe\Exception\ApiConnectionException as StripeConnectionException;
use Throwable;

class CreateRefundAction
{
    public function __construct(protected PaymentDriverManager $driverManager) {}

    /**
     * Two-phase refund. Phase 1 (transactional): lock the payment row,
     * validate the remaining refundable balance and insert a durable
     * PENDING ledger row — its amount is reserved, so concurrent refunds
     * serialize against the lock and can never over-refund. Phase 2
     * (outside any transaction): call the gateway with the refund's uuid
     * as the idempotency key, then finalize the row — a definitive
     * rejection marks it FAILED, an ambiguous outcome (timeout) marks it
     * RECONCILIATION_REQUIRED with the amount still reserved.
     *
     * @param  array{amount: string, reason: string}  $data
     *
     * @throws PaymentException
     */
    public function handle(string $paymentId, array $data): PaymentRefund
    {
        [$refund, $payment] = DB::transaction(function () use ($paymentId, $data): array {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->{PaymentSchema::STATUS} !== PaymentStatusEnum::SUCCESS) {
                throw PaymentException::refundInvalidStatus();
            }

            $reservedMinor = FinalizesRefund::minor(PaymentRefund::query()
                ->where(PaymentRefundSchema::PAYMENT_ID, $payment->{PaymentSchema::ID})
                ->whereIn(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::reserving())
                ->sum(PaymentRefundSchema::AMOUNT));

            $remainingMinor = Money::fromDecimal((string) $payment->{PaymentSchema::AMOUNT}) - $reservedMinor;
            $requestedMinor = Money::fromDecimal((string) $data[PaymentRefundSchema::AMOUNT]);

            if ($requestedMinor > $remainingMinor) {
                throw PaymentException::refundExceedsRefundable();
            }

            $refund = PaymentRefund::query()->create([
                PaymentRefundSchema::UUID => (string) Str::uuid(),
                PaymentRefundSchema::PAYMENT_ID => $payment->{PaymentSchema::ID},
                PaymentRefundSchema::ORDER_ID => $payment->{PaymentSchema::ORDER_ID},
                PaymentRefundSchema::AMOUNT => $data[PaymentRefundSchema::AMOUNT],
                PaymentRefundSchema::REASON => $data[PaymentRefundSchema::REASON],
                PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::PENDING,
                PaymentRefundSchema::REFUNDED_BY => Auth::id(),
            ]);

            return [$refund, $payment];
        });

        $currency = app(CoreGatewayInterface::class)
            ->getCurrencyById((int) $payment->{PaymentSchema::CURRENCY_ID});

        try {
            $result = $this->driverManager
                ->forMethod($payment->{PaymentSchema::METHOD})
                ->refund(new PaymentRefundInput(
                    payment_id: (int) $payment->{PaymentSchema::ID},
                    order_id: (int) $payment->{PaymentSchema::ORDER_ID},
                    transaction_id: (string) $payment->{PaymentSchema::TRANSACTION_ID},
                    amount: (float) $data[PaymentRefundSchema::AMOUNT],
                    currency: $currency?->code,
                    currency_decimal_places: $currency === null ? null : (int) $currency->decimal_places,
                    idempotency_key: $refund->{PaymentRefundSchema::UUID},
                ));
        } catch (HttpConnectionException|StripeConnectionException $e) {
            // Ambiguous: the refund may exist at the gateway. Keep the row
            // (and its reserved amount) until an admin resolves it.
            report($e);
            $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED]);
            throw PaymentException::refundOutcomeUnknown($e);
        } catch (PaymentException $e) {
            $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED]);
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED]);
            throw PaymentException::refundFailed($e);
        }

        [$full] = FinalizesRefund::success($refund, $result->transaction_id);

        event(PaymentRefundedEvent::fill([
            PaymentRefundSchema::ID => (int) $refund->{PaymentRefundSchema::ID},
            PaymentSchema::ID => (int) $payment->{PaymentSchema::ID},
            PaymentSchema::ORDER_ID => (int) $payment->{PaymentSchema::ORDER_ID},
            PaymentSchema::AMOUNT => (float) $data[PaymentRefundSchema::AMOUNT],
            'full' => $full,
            PaymentRefundSchema::TRANSACTION_ID => (string) $result->transaction_id,
            PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS->value,
            'locale' => Locale::fromRequest(),
        ]));

        return $refund->refresh();
    }
}
