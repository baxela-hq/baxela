<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\RazorpayClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class RazorpayPaymentDriver implements PaymentDriverInterface
{
    public function __construct(protected RazorpayClient $razorpay) {}

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

        $reference = is_array($event)
            ? (string) ($event['payload']['payment_link']['entity']['reference_id'] ?? '')
            : '';

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
