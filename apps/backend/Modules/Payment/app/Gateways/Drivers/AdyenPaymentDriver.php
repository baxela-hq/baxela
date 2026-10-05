<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\AdyenCheckout;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class AdyenPaymentDriver implements PaymentDriverInterface
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

        $status = match ($item['eventCode'] ?? null) {
            'AUTHORISATION' => $success
                ? PaymentStatusEnum::SUCCESS->value
                : PaymentStatusEnum::FAILED->value,
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
        return str_replace('{order_code}', (string) $orderCode, (string) config('payment.adyen.return_url'));
    }
}
