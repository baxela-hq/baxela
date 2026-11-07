<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\RefundWebhookOutcome;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\RefundableDriver;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\AdyenCheckout;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class AdyenPaymentDriver implements RefundableDriver
{
    public function __construct(protected AdyenCheckout $adyen) {}

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        if ((string) config('payment.adyen.merchant_account') === '') {
            // AdyenCheckout guards the api key; this is the payload half of
            // the credential pair.
            throw PaymentException::gatewayUnconfigured();
        }

        $link = $this->adyen->createPaymentLink([
            // Adyen webhooks never carry the link id — only this reference,
            // as merchantReference — so it doubles as the stored
            // transaction_id the settle path matches on.
            'reference' => (string) $input->payment_id,
            'merchantAccount' => (string) config('payment.adyen.merchant_account'),
            'amount' => [
                'currency' => strtoupper((string) $input->currency),
                // Adyen wants integer minor units (cents), built from
                // the order currency's decimal places.
                'value' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
            ],
            'returnUrl' => $this->url($input->order_code),
        ]);

        $url = (string) ($link->url ?? '');

        if ($url === '') {
            // A link without a URL cannot be redirected to, and a null
            // payment_url would make the storefront treat the method as
            // manual and render its on-site success panel.
            throw new RuntimeException('Adyen payment link response is missing its url.');
        }

        return new PaymentGatewayResult(
            redirect_url: $url,
            transaction_id: (string) $input->payment_id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ((string) config('payment.adyen.hmac_key') === '') {
            // Without the signing key every notification would fail
            // verification; say so instead of reporting each one as invalid.
            throw PaymentException::gatewayUnconfigured();
        }

        $body = json_decode($request->getContent(), true);

        $items = is_array($body) ? ($body['notificationItems'] ?? null) : null;

        if (! is_array($items) || $items === []) {
            throw PaymentException::webhookInvalid();
        }

        // WebhookResult settles exactly one payment per delivery, so a
        // multi-item batch cannot be half-processed; reject it and let
        // Adyen retry or an operator resend the items individually.
        if (count($items) > 1) {
            throw PaymentException::webhookInvalid();
        }

        $item = $items[0]['NotificationRequestItem'] ?? null;

        if (! is_array($item)) {
            throw PaymentException::webhookInvalid();
        }

        if (! $this->adyen->verifyWebhookSignature($item)) {
            throw PaymentException::webhookInvalid();
        }

        $reference = (string) ($item['merchantReference'] ?? '');

        if ($reference === '') {
            throw PaymentException::webhookInvalid();
        }

        // Auto-capture accounts only: on manual-capture accounts an
        // authorised payment is not captured money and must not settle
        // the order (see payment-flow.md).
        $success = ($item['success'] ?? false) === true || ($item['success'] ?? false) === 'true';

        $eventCode = $item['eventCode'] ?? null;

        // Refund lifecycle: the outcome routes to the refund ledger. The
        // refund request's reference (the ledger row's uuid) echoes back
        // as merchantReference; the refund's own pspReference identifies
        // the refund and originalReference the original payment. Verify
        // per event against sandbox payloads that the uuid is actually
        // echoed — the settle path falls back to the stored gateway
        // refund id when it is not.
        if (in_array($eventCode, ['REFUND', 'REFUND_FAILED', 'REFUNDED_REVERSED'], true)) {
            $amount = is_array($item['amount'] ?? null) ? $item['amount'] : null;

            return new WebhookResult(
                transaction_id: '',
                status: PaymentStatusEnum::FAILED->value, // unused on the refund branch
                refund_outcome: new RefundWebhookOutcome(
                    reference: $reference,
                    gateway_refund_id: (string) ($item['pspReference'] ?? ''),
                    gateway_payment_id: (string) ($item['originalReference'] ?? ''),
                    status: match ($eventCode) {
                        'REFUND' => $success ? 'success' : 'failed',
                        'REFUND_FAILED' => 'failed',
                        'REFUNDED_REVERSED' => 'reversed',
                    },
                    amount_minor: $amount !== null && isset($amount['value']) ? (int) $amount['value'] : null,
                    currency: $amount !== null && isset($amount['currency']) ? (string) $amount['currency'] : null,
                ),
            );
        }

        $status = match ($eventCode) {
            'AUTHORISATION' => $success
                ? PaymentStatusEnum::SUCCESS->value
                : PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: $reference,
            status: $status,
            // The pspReference is what Adyen's refund API keys on — persist
            // it at settle time (it never appears on the payment link).
            gateway_transaction_id: (string) ($item['pspReference'] ?? ''),
        );
    }

    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        // Refunds key on the payment's pspReference, persisted from the
        // AUTHORISATION webhook. Payments settled before the column
        // shipped (or an account whose webhook never carried it) have
        // nothing to refund through the API — refuse instead of guessing.
        $pspReference = (string) ($input->gateway_transaction_id ?? '');

        if ($pspReference === '') {
            throw PaymentException::refundNotSupported();
        }

        $refund = $this->adyen->createRefund($pspReference, [
            // Echoed back as the REFUND webhook's merchantReference: the
            // whole bridge back to this ledger row.
            'reference' => (string) $input->idempotency_key,
            'merchantAccount' => (string) config('payment.adyen.merchant_account'),
            'amount' => [
                'currency' => strtoupper((string) $input->currency),
                // Adyen wants integer minor units (cents), built from the
                // payment currency's decimal places.
                'value' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
            ],
        ], $input->idempotency_key);

        // Acceptance is asynchronous: the definitive outcome arrives via
        // the REFUND webhook, so this is never treated as completed money
        // movement. A response without a parseable refund id is still an
        // acceptance — reconciliation correlates through the echoed uuid.
        $refundId = (string) ($refund->id ?? $refund->pspReference ?? '');

        return new PaymentRefundResult(
            transaction_id: $refundId !== '' ? $refundId : null,
            accepted_pending: true,
        );
    }

    private function minorUnits(float $amount, int $decimalPlaces): int
    {
        return (int) round($amount * (10 ** $decimalPlaces));
    }

    private function url(?string $orderCode): string
    {
        return str_replace('{order_code}', (string) $orderCode, (string) config('payment.adyen.return_url'));
    }
}
