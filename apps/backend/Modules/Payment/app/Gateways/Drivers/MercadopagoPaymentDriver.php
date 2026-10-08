<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\MercadopagoClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class MercadopagoPaymentDriver implements PaymentDriverInterface
{
    public function __construct(protected MercadopagoClient $mercadopago) {}

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        $params = [
            // The echoed external reference is the whole bridge back from
            // Mercado Pago: the payment resource carries it, so it doubles
            // as the stored transaction_id the settle path matches on.
            'external_reference' => (string) $input->payment_id,
            'items' => [[
                'title' => config('app.name').' '.$input->order_code,
                'quantity' => 1,
                // Mercado Pago wants a decimal price in the account's
                // country currency; Pix/Boleto amounts derive on their
                // side.
                'unit_price' => (float) number_format($input->amount, $input->currency_decimal_places ?? 2, '.', ''),
                'currency_id' => strtoupper((string) $input->currency),
            ]],
            'back_urls' => [
                'success' => $this->url('success_url', $input->order_code),
                'pending' => $this->url('pending_url', $input->order_code),
                'failure' => $this->url('failure_url', $input->order_code),
            ],
        ];

        // Optional: when empty, the account-level webhook config applies.
        $notification = (string) config('payment.mercadopago.notification_url');

        if ($notification !== '') {
            $params['notification_url'] = $notification;
        }

        $preference = $this->mercadopago->createPreference($params);

        $url = config('payment.mercadopago.sandbox')
            ? (string) ($preference->sandbox_init_point ?? '')
            : (string) ($preference->init_point ?? '');

        if ($url === '') {
            // A preference without a hosted URL cannot be redirected to,
            // and a null payment_url would make the storefront treat the
            // method as manual and render its on-site success panel.
            throw new RuntimeException('Mercado Pago preference response is missing its redirect URL.');
        }

        return new PaymentGatewayResult(
            redirect_url: $url,
            transaction_id: (string) $input->payment_id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ((string) config('payment.mercadopago.webhook_secret') === '') {
            // Without the signing secret every notification would fail
            // verification; say so instead of reporting each one as invalid.
            throw PaymentException::gatewayUnconfigured();
        }

        $body = json_decode($request->getContent(), true);

        // Notifications are shadows: they only say which payment changed
        // (older style carries the id in the query string).
        $dataId = is_array($body) ? (string) ($body['data']['id'] ?? '') : '';

        if ($dataId === '') {
            // Direct bag access with the mangled key: dots in query keys
            // arrive as underscores, so Mercado Pago's literal "data.id"
            // becomes "data_id".
            $dataId = (string) $request->query->get('data_id', '');
        }

        if ($dataId === '') {
            throw PaymentException::webhookInvalid();
        }

        if (! $this->mercadopago->verifyWebhookSignature(
            $dataId,
            (string) $request->header('x-request-id'),
            (string) $request->header('x-signature'),
        )) {
            throw PaymentException::webhookInvalid();
        }

        // The signature proves the notification but not the outcome — the
        // payment resource must be fetched for its status and the echoed
        // reference. Fetch failures bubble so Mercado Pago retries the
        // whole delivery.
        $payment = $this->mercadopago->getPayment($dataId);

        $reference = (string) ($payment->external_reference ?? '');

        if ($reference === '') {
            throw PaymentException::webhookInvalid();
        }

        // Pix vouchers and Boleto slips sit in pending/in_process for
        // hours; non-terminal statuses are rejected so the payment stays
        // PENDING until the terminal notification settles it. Only
        // approved means the money is in the account.
        $status = match ($payment->status ?? null) {
            'approved' => PaymentStatusEnum::SUCCESS->value,
            'rejected', 'cancelled' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: $reference,
            status: $status,
        );
    }

    private function url(string $key, ?string $orderCode): string
    {
        return str_replace('{order_code}', (string) $orderCode, (string) config("payment.mercadopago.{$key}"));
    }
}
