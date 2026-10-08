<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\CheckoutcomClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class CheckoutcomPaymentDriver implements PaymentDriverInterface
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

        $reference = is_array($event) ? (string) ($event['data']['reference'] ?? '') : '';

        if ($reference === '') {
            throw PaymentException::webhookInvalid();
        }

        // Auto-capture accounts only: only payment_captured means the money
        // moved — payment_approved is authorised-but-uncaptured and must
        // not settle the order (see payment-flow.md).
        $status = match ($event['type'] ?? null) {
            'payment_captured' => PaymentStatusEnum::SUCCESS->value,
            'payment_declined', 'payment_capture_declined', 'payment_canceled', 'payment_expired' => PaymentStatusEnum::FAILED->value,
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
        return str_replace('{order_code}', (string) $orderCode, (string) config('payment.checkoutcom.return_url'));
    }
}
