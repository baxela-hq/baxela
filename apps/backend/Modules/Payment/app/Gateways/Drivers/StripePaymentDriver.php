<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\StripeCheckout;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripePaymentDriver implements PaymentDriverInterface
{
    public function __construct(protected StripeCheckout $stripe) {}

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        $session = $this->stripe->createSession([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower((string) $input->currency),
                    // Stripe wants integer minor units (cents), built from
                    // the order currency's decimal places.
                    'unit_amount' => $this->minorUnits($input->amount, $input->currency_decimal_places ?? 2),
                    'product_data' => [
                        'name' => config('app.name').' '.$input->order_code,
                    ],
                ],
            ]],
            'client_reference_id' => (string) $input->payment_id,
            'metadata' => [
                'payment_id' => (string) $input->payment_id,
                'order_id' => (string) $input->order_id,
            ],
            'success_url' => $this->url('success_url', $input->order_code),
            'cancel_url' => $this->url('cancel_url', $input->order_code),
        ]);

        // The session id doubles as the stored transaction_id: every webhook
        // event for this checkout carries it at data.object.id.
        return new PaymentGatewayResult(
            redirect_url: $session->url,
            transaction_id: $session->id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('payment.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            throw PaymentException::webhookInvalid();
        }

        $session = $event->data?->object;

        if (is_null($session) || ! isset($session->id)) {
            throw PaymentException::webhookInvalid();
        }

        // Async methods (e.g. bank debits) also emit checkout.session.completed
        // while still unpaid — rejecting keeps the payment PENDING until their
        // terminal async_* event arrives.
        $status = match ($event->type) {
            'checkout.session.completed' => $session->payment_status === 'paid'
                ? PaymentStatusEnum::SUCCESS->value
                : throw PaymentException::webhookInvalid(),
            'checkout.session.async_payment_succeeded' => PaymentStatusEnum::SUCCESS->value,
            'checkout.session.async_payment_failed', 'checkout.session.expired' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: (string) $session->id,
            status: $status,
        );
    }

    private function minorUnits(float $amount, int $decimalPlaces): int
    {
        return (int) round($amount * (10 ** $decimalPlaces));
    }

    private function url(string $key, ?string $orderCode): string
    {
        return str_replace('{order_code}', (string) $orderCode, (string) config("payment.stripe.{$key}"));
    }
}
