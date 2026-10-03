<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;

class PaypalPaymentDriver implements PaymentDriverInterface
{
    public function __construct(protected PaypalClient $paypal) {}

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        $order = $this->paypal->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $input->order_code,
                'custom_id' => (string) $input->payment_id,
                'amount' => [
                    'currency_code' => (string) $input->currency,
                    // PayPal wants a decimal string, not minor units.
                    'value' => number_format($input->amount, $input->currency_decimal_places ?? 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $this->url('return_url', $input->order_code),
                'cancel_url' => $this->url('cancel_url', $input->order_code),
            ],
        ]);

        // The PayPal order id doubles as the stored transaction_id: every
        // webhook event for this checkout resolves back to it.
        return new PaymentGatewayResult(
            redirect_url: $this->approveLink($order),
            transaction_id: (string) $order->id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ((string) config('payment.paypal.webhook_id') === '') {
            // Without the webhook id the signature API cannot verify
            // anything; say so instead of reporting each event as invalid.
            throw PaymentException::gatewayUnconfigured();
        }

        $event = json_decode($request->getContent(), true);

        if (! is_array($event) || ! is_array($event['resource'] ?? null)) {
            throw PaymentException::webhookInvalid();
        }

        if (! $this->paypal->verifyWebhookSignature($event, $this->transmission($request))) {
            throw PaymentException::webhookInvalid();
        }

        $type = (string) ($event['event_type'] ?? '');

        // Order events carry the PayPal order id at resource.id; capture
        // events carry it at resource.supplementary_data.related_ids.order_id.
        $paypalOrderId = match (true) {
            str_starts_with($type, 'CHECKOUT.') => $event['resource']['id'] ?? null,
            str_starts_with($type, 'PAYMENT.CAPTURE.') => $event['resource']['supplementary_data']['related_ids']['order_id'] ?? null,
            default => null,
        };

        if (! is_string($paypalOrderId) || $paypalOrderId === '') {
            throw PaymentException::webhookInvalid();
        }

        // Redirect flows never auto-capture: approval is the capture
        // trigger, and this webhook is the guaranteed one — PayPal retries
        // non-2xx deliveries up to 25 times over 3 days, and the settle
        // path in HandleWebhookAction is idempotent.
        $status = match ($type) {
            'CHECKOUT.ORDER.APPROVED' => $this->capture($paypalOrderId),
            'PAYMENT.CAPTURE.COMPLETED' => PaymentStatusEnum::SUCCESS->value,
            'PAYMENT.CAPTURE.DENIED', 'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: $paypalOrderId,
            status: $status,
        );
    }

    private function capture(string $paypalOrderId): string
    {
        try {
            $capture = $this->paypal->captureOrder($paypalOrderId);
        } catch (RequestException $exception) {
            // A replayed APPROVED after settlement: PayPal 422s the
            // duplicate capture with ORDER_ALREADY_CAPTURED. Ack it as
            // success so the idempotent settle path no-ops the retry
            // instead of erroring until PayPal's retry cap; any other
            // failure rethrows so PayPal retries the webhook.
            $alreadyCaptured = collect((array) $exception->response->json('details'))
                ->contains(fn ($detail): bool => is_array($detail) && ($detail['issue'] ?? null) === 'ORDER_ALREADY_CAPTURED');

            if (! $alreadyCaptured) {
                throw $exception;
            }

            return PaymentStatusEnum::SUCCESS->value;
        }

        // The capture result is embedded under the order's purchase units.
        return match ($capture->purchase_units[0]->payments->captures[0]->status ?? null) {
            'COMPLETED' => PaymentStatusEnum::SUCCESS->value,
            'DENIED', 'DECLINED' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };
    }

    /**
     * A created order always carries an approve link for redirect flows;
     * its absence means a malformed response and fails loudly.
     */
    private function approveLink(object $order): string
    {
        $approve = collect((array) $order->links)
            ->first(fn (object $link): bool => $link->rel === 'approve');

        return (string) $approve->href;
    }

    /**
     * @return array<string, string>
     */
    private function transmission(Request $request): array
    {
        return [
            'auth_algo' => (string) $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => (string) $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => (string) $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => (string) $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => (string) $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];
    }

    private function url(string $key, ?string $orderCode): string
    {
        return str_replace('{order_code}', (string) $orderCode, (string) config("payment.paypal.{$key}"));
    }
}
