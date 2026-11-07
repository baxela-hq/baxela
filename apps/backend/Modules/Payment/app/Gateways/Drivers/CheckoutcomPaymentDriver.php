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
use Modules\Payment\Gateways\CheckoutcomClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class CheckoutcomPaymentDriver implements RefundableDriver
{
    public function __construct(protected CheckoutcomClient $checkoutcom) {}

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        $link = $this->checkoutcom->createPaymentLink([
            // The echoed reference is the whole bridge back from
            // Checkout.com: every webhook event carries it back at
            // data.reference, so it doubles as the stored transaction_id
            // the settle path matches on.
            'reference' => (string) $input->payment_id,
            // Checkout.com wants integer minor units (cents), built from
            // the order currency's decimal places.
            'amount' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
            'currency' => strtoupper((string) $input->currency),
            'return_url' => $this->url($input->order_code),
        ]);

        $url = (string) ($link->_links->{'payment-link'}->href ?? '');

        if ($url === '') {
            // A link without its hosted URL cannot be redirected to, and a
            // null payment_url would make the storefront treat the method
            // as manual and render its on-site success panel.
            throw new RuntimeException('Checkout.com payment link response is missing its hosted URL.');
        }

        return new PaymentGatewayResult(
            redirect_url: $url,
            transaction_id: (string) $input->payment_id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ((string) config('payment.checkoutcom.webhook_secret') === '') {
            // Without the signing secret every event would fail
            // verification; say so instead of reporting each one as invalid.
            throw PaymentException::gatewayUnconfigured();
        }

        // Verified over the exact delivered bytes, never a re-encoding.
        if (! $this->checkoutcom->verifyWebhookSignature($request->getContent(), (string) $request->header('cko-signature'))) {
            throw PaymentException::webhookInvalid();
        }

        $event = json_decode($request->getContent(), true);

        if (! is_array($event)) {
            throw PaymentException::webhookInvalid();
        }

        $type = $event['type'] ?? null;

        // Refund lifecycle: the outcome routes to the refund ledger. The
        // refund request's reference (the ledger row's uuid) echoes back
        // at data.reference. Verify per event against sandbox payloads
        // which fields the refund events carry — the settle path falls
        // back to the stored gateway refund id when the uuid is absent.
        if ($type === 'payment_refunded' || $type === 'payment_refund_declined') {
            $data = is_array($event['data'] ?? null) ? $event['data'] : [];

            $reference = (string) ($data['reference'] ?? '');

            if ($reference === '') {
                throw PaymentException::webhookInvalid();
            }

            return new WebhookResult(
                transaction_id: '',
                status: PaymentStatusEnum::FAILED->value, // unused on the refund branch
                refund_outcome: new RefundWebhookOutcome(
                    reference: $reference,
                    gateway_refund_id: (string) ($data['action_id'] ?? ''),
                    gateway_payment_id: isset($data['payment_id']) ? (string) $data['payment_id'] : null,
                    status: $type === 'payment_refunded' ? 'success' : 'failed',
                    amount_minor: isset($data['amount']) ? (int) $data['amount'] : null,
                    currency: isset($data['currency']) ? (string) $data['currency'] : null,
                ),
            );
        }

        $reference = (string) ($event['data']['reference'] ?? '');

        if ($reference === '') {
            throw PaymentException::webhookInvalid();
        }

        // Auto-capture accounts only: only payment_captured means the money
        // moved — payment_approved is authorised-but-uncaptured and must
        // not settle the order (see payment-flow.md).
        $status = match ($type) {
            'payment_captured' => PaymentStatusEnum::SUCCESS->value,
            'payment_declined', 'payment_capture_declined', 'payment_canceled', 'payment_expired' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: $reference,
            status: $status,
            // data.id is what Checkout.com's refund API keys on — persist
            // it at settle time.
            gateway_transaction_id: (string) ($event['data']['id'] ?? ''),
        );
    }

    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        // Refunds key on the Checkout.com payment id, persisted from the
        // payment_captured webhook. Payments settled before the column
        // shipped have nothing to refund through the API — refuse instead
        // of guessing.
        $paymentId = (string) ($input->gateway_transaction_id ?? '');

        if ($paymentId === '') {
            throw PaymentException::refundNotSupported();
        }

        $refund = $this->checkoutcom->createRefund($paymentId, [
            // Echoed back on the refund webhook events: the whole bridge
            // back to this ledger row.
            'reference' => (string) $input->idempotency_key,
            // Checkout.com wants integer minor units (cents), built from
            // the payment currency's decimal places.
            'amount' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
        ], $input->idempotency_key);

        // Acceptance is asynchronous: payment_refunded /
        // payment_refund_declined confirm the outcome, so this is never
        // treated as completed money movement.
        $refundId = (string) ($refund->action_id ?? $refund->id ?? '');

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
        return str_replace('{order_code}', (string) $orderCode, (string) config('payment.checkoutcom.return_url'));
    }
}
