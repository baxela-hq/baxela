<?php

namespace Modules\Payment\Actions\Admin\PaymentRefund;

use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Payment\PaymentRefundedEvent;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Support\Money;
use Modules\Core\Utils\Auth;
use Modules\Core\Utils\Locale;
use Modules\Payment\Exceptions\ErrorCodeEnum;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundStatusEnum;
use Modules\Payment\Support\FinalizesRefund;
use Modules\Payment\Support\FiresRefundFinalizedEvent;
use RuntimeException;
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
     * rejection marks it FAILED, an ambiguous outcome (timeout, or a
     * response too malformed to establish acceptance) marks it
     * RECONCILIATION_REQUIRED with the amount still reserved, and an
     * accepted-but-async gateway marks it PROCESSING — the definitive
     * outcome arrives via the gateway's refund webhook and a 2xx accept
     * is never treated as completed money movement.
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
                ->forName($payment->{PaymentSchema::METHOD})
                ->refund(new PaymentRefundInput(
                    payment_id: (int) $payment->{PaymentSchema::ID},
                    order_id: (int) $payment->{PaymentSchema::ORDER_ID},
                    transaction_id: (string) $payment->{PaymentSchema::TRANSACTION_ID},
                    amount: (float) $data[PaymentRefundSchema::AMOUNT],
                    currency: $currency?->code,
                    currency_decimal_places: $currency === null ? null : (int) $currency->decimal_places,
                    idempotency_key: $refund->{PaymentRefundSchema::UUID},
                    gateway_transaction_id: $payment->{PaymentSchema::GATEWAY_TRANSACTION_ID},
                ));
        } catch (HttpConnectionException|StripeConnectionException $e) {
            // Ambiguous: the refund may exist at the gateway. Keep the row
            // (and its reserved amount) until an admin resolves it.
            report($e);
            $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED]);
            throw PaymentException::refundOutcomeUnknown($e);
        } catch (PaymentException $e) {
            // A driver signalling an ambiguous-but-possibly-accepted
            // outcome (e.g. a malformed 2xx) is uncertainty, not rejection
            // — reconcile it like a timeout instead of freeing the amount.
            if ($e->getMessage() === ErrorCodeEnum::REFUND_OUTCOME_UNKNOWN->value) {
                report($e);
                $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::RECONCILIATION_REQUIRED]);
                throw $e;
            }

            $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED]);
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $refund->update([PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::FAILED]);
            throw PaymentException::refundFailed($e);
        }

        if ($result->accepted_pending) {
            // The gateway accepted the refund; its definitive outcome
            // arrives via webhook. The update is conditional on the row
            // still being pending, so a webhook that already confirmed or
            // reversed the refund (beats the HTTP response home) is never
            // overwritten. A 2xx without a parseable refund id is still an
            // acceptance — processing it is, and the echoed uuid carries
            // correlation; say so loudly.
            $updated = PaymentRefund::query()
                ->whereKey($refund->{PaymentRefundSchema::ID})
                ->where(PaymentRefundSchema::STATUS, PaymentRefundStatusEnum::PENDING)
                ->update([
                    PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::PROCESSING,
                    PaymentRefundSchema::TRANSACTION_ID => $result->transaction_id,
                ]);

            if (! $updated) {
                Log::info('refund webhook settled the row before the accept response', [
                    'refund' => $refund->{PaymentRefundSchema::UUID},
                ]);
            } elseif (is_null($result->transaction_id)) {
                report(new RuntimeException(
                    "gateway accepted refund {$refund->{PaymentRefundSchema::UUID}} without returning a refund id",
                ));
            }

            return $refund->refresh();
        }

        [$full] = FinalizesRefund::success($refund, $result->transaction_id);

        event(PaymentRefundedEvent::fill([
            'id' => (int) $refund->{PaymentRefundSchema::ID},
            'payment_id' => (int) $payment->{PaymentSchema::ID},
            'order_id' => (int) $payment->{PaymentSchema::ORDER_ID},
            PaymentRefundSchema::AMOUNT => (float) $data[PaymentRefundSchema::AMOUNT],
            'full' => $full,
            PaymentRefundSchema::TRANSACTION_ID => (string) $result->transaction_id,
            PaymentRefundSchema::STATUS => PaymentRefundStatusEnum::SUCCESS->value,
            'locale' => Locale::fromRequest(),
        ]));

        // Per-refund accounting signal (gap-B1 D10): fires after commit
        // through the shared dispatcher.
        FiresRefundFinalizedEvent::from($refund->refresh());

        return $refund->refresh();
    }
}
