<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\RefundWebhookOutcome;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\RefundableDriver;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\RazorpayClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class RazorpayPaymentDriver implements ConfigurableDriver, RefundableDriver
{
    public function __construct(protected RazorpayClient $razorpay) {}

    public function credentialKeys(): array
    {
        // The key pair drives the API; the webhook secret verifies the
        // x-razorpay-signature notifications that settle payments.
        return ['key_id', 'key_secret', 'webhook_secret'];
    }

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        $link = $this->razorpay->createPaymentLink([
            // The echoed reference is the whole bridge back from Razorpay:
            // the payment_link.paid webhook carries it back on the entity,
            // so it doubles as the stored transaction_id the settle path
            // matches on.
            'reference_id' => (string) $input->payment_id,
            // Razorpay wants integer minor units (paise), built from the
            // order currency's decimal places.
            'amount' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
            'currency' => strtoupper((string) $input->currency),
            // Where Razorpay returns the customer after the hosted page.
            'callback_url' => $this->url($input->order_code),
        ]);

        $url = (string) ($link->short_url ?? '');

        if ($url === '') {
            // A link without its short URL cannot be redirected to, and a
            // null payment_url would make the storefront treat the method
            // as manual and render its on-site success panel.
            throw new RuntimeException('Razorpay payment link response is missing its short_url.');
        }

        return new PaymentGatewayResult(
            redirect_url: $url,
            transaction_id: (string) $input->payment_id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ((string) config('payment.razorpay.webhook_secret') === '') {
            // Without the signing secret every event would fail
            // verification; say so instead of reporting each one as invalid.
            throw PaymentException::gatewayUnconfigured();
        }

        // Verified over the exact delivered bytes, never a re-encoding.
        if (! $this->razorpay->verifyWebhookSignature($request->getContent(), (string) $request->header('x-razorpay-signature'))) {
            throw PaymentException::webhookInvalid();
        }

        $event = json_decode($request->getContent(), true);

        if (! is_array($event)) {
            throw PaymentException::webhookInvalid();
        }

        // Refund lifecycle: the outcome routes to the refund ledger. The
        // ledger uuid rides in the refund's notes (attached at creation
        // and echoed back); the refund entity's own id is the documented
        // fallback correlation key. Delivery is unordered — a
        // refund.processed may resolve a row the create-call timeout left
        // in reconciliation_required.
        if (in_array($event['event'] ?? null, ['refund.processed', 'refund.failed'], true)) {
            $entity = is_array($event['payload']['refund']['entity'] ?? null)
                ? $event['payload']['refund']['entity']
                : [];

            $uuid = (string) ($entity['notes']['baxela_refund_uuid'] ?? '');
            $refundId = (string) ($entity['id'] ?? '');

            if ($uuid === '' && $refundId === '') {
                throw PaymentException::webhookInvalid();
            }

            return new WebhookResult(
                transaction_id: '',
                status: PaymentStatusEnum::FAILED->value, // unused on the refund branch
                refund_outcome: new RefundWebhookOutcome(
                    reference: $uuid !== '' ? $uuid : null,
                    status: $event['event'] === 'refund.processed' ? 'success' : 'failed',
                    gateway_refund_id: $refundId !== '' ? $refundId : null,
                    gateway_payment_id: (string) ($entity['payment_id'] ?? ''),
                    amount_minor: isset($entity['amount']) ? (int) $entity['amount'] : null,
                ),
            );
        }

        $reference = (string) ($event['payload']['payment_link']['entity']['reference_id'] ?? '');

        if ($reference === '') {
            throw PaymentException::webhookInvalid();
        }

        $status = match ($event['event'] ?? null) {
            // The terminal link event: the amount is fully paid. Failed
            // attempts keep the link payable, so they are not terminal —
            // the payment stays PENDING until paid or cancelled.
            'payment_link.paid' => PaymentStatusEnum::SUCCESS->value,
            'payment_link.cancelled' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: $reference,
            status: $status,
            // The captured pay_ id is what Razorpay's refund API keys on —
            // persist it at settle time (the link's reference_id we store
            // cannot be resolved back to a payment reliably).
            gateway_transaction_id: (string) ($event['payload']['payment']['entity']['id'] ?? ''),
        );
    }

    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        // Refunds key on the captured pay_ id, persisted from the
        // payment_link.paid webhook. Payments settled before the column
        // shipped have nothing to refund through the API — refuse instead
        // of guessing which payment on the link to refund (a link with
        // several captures is never refunded arbitrarily).
        $payId = (string) ($input->gateway_transaction_id ?? '');

        if ($payId === '') {
            throw PaymentException::refundNotSupported();
        }

        $refund = $this->razorpay->createRefund($payId, [
            // Razorpay wants integer minor units (paise), built from the
            // payment currency's decimal places.
            'amount' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
            // Echoed back on the refund entity in webhook deliveries: the
            // whole bridge back to this ledger row.
            'notes' => ['baxela_refund_uuid' => (string) $input->idempotency_key],
        ], (string) $input->idempotency_key);

        // Normal refunds go pending → processed (5–7 working days);
        // instant refunds arrive processed. Only a processed refund is
        // definitive — everything else awaits the refund.processed
        // webhook (enable refund events on the dashboard webhook).
        $processed = ($refund->status ?? null) === 'processed';

        return new PaymentRefundResult(
            transaction_id: isset($refund->id) ? (string) $refund->id : null,
            accepted_pending: ! $processed,
        );
    }

    private function minorUnits(float $amount, int $decimalPlaces): int
    {
        return (int) round($amount * (10 ** $decimalPlaces));
    }

    private function url(?string $orderCode): string
    {
        return str_replace('{order_code}', (string) $orderCode, (string) config('payment.razorpay.return_url'));
    }
}
