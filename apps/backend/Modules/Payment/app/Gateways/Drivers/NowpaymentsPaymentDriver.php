<?php

namespace Modules\Payment\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\NowpaymentsClient;
use Modules\Payment\Schemas\Payment\PaymentStatusEnum;
use RuntimeException;

class NowpaymentsPaymentDriver implements PaymentDriverInterface
{
    public function __construct(protected NowpaymentsClient $nowpayments) {}

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        $params = [
            // The echoed order reference is the whole bridge back from
            // NowPayments: every IPN carries it back, so it doubles as the
            // stored transaction_id the settle path matches on.
            'order_id' => (string) $input->payment_id,
            'order_description' => config('app.name').' '.$input->order_code,
            // NowPayments wants a decimal price in the order currency; the
            // customer's crypto amount is derived on their side.
            'price_amount' => (float) number_format($input->amount, $input->currency_decimal_places ?? 2, '.', ''),
            'price_currency' => strtolower((string) $input->currency),
            'success_url' => $this->url('success_url', $input->order_code),
            'cancel_url' => $this->url('cancel_url', $input->order_code),
        ];

        // Optional: when empty, NowPayments falls back to the account-level
        // IPN callback setting.
        $callback = (string) config('payment.nowpayments.ipn_callback_url');

        if ($callback !== '') {
            $params['ipn_callback_url'] = $callback;
        }

        $invoice = $this->nowpayments->createInvoice($params);

        $url = (string) ($invoice->invoice_url ?? '');

        if ($url === '') {
            // An invoice without its hosted URL cannot be redirected to,
            // and a null payment_url would make the storefront treat the
            // method as manual and render its on-site success panel.
            throw new RuntimeException('NowPayments invoice response is missing its invoice_url.');
        }

        return new PaymentGatewayResult(
            redirect_url: $url,
            transaction_id: (string) $input->payment_id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ((string) config('payment.nowpayments.ipn_secret') === '') {
            // Without the IPN secret every notification would fail
            // verification; say so instead of reporting each one as invalid.
            throw PaymentException::gatewayUnconfigured();
        }

        $ipn = json_decode($request->getContent(), true);

        if (! is_array($ipn)) {
            throw PaymentException::webhookInvalid();
        }

        if (! $this->nowpayments->verifyWebhookSignature($ipn, (string) $request->header('x-nowpayments-sig'))) {
            throw PaymentException::webhookInvalid();
        }

        $reference = (string) ($ipn['order_id'] ?? '');

        if ($reference === '') {
            throw PaymentException::webhookInvalid();
        }

        // Crypto payments crawl through on-chain states for minutes to
        // hours (waiting → confirming → sending); non-terminal IPNs are
        // rejected so the payment stays PENDING until `finished` — the
        // state where the funds have reached the account wallet. A
        // `confirmed` (on-chain only) payment must not settle the order.
        $status = match ($ipn['payment_status'] ?? null) {
            'finished' => PaymentStatusEnum::SUCCESS->value,
            'failed', 'expired' => PaymentStatusEnum::FAILED->value,
            default => throw PaymentException::webhookInvalid(),
        };

        return new WebhookResult(
            transaction_id: $reference,
            status: $status,
        );
    }

    private function url(string $key, ?string $orderCode): string
    {
        return str_replace('{order_code}', (string) $orderCode, (string) config("payment.nowpayments.{$key}"));
    }
}
