<?php

namespace Modules\Payment\Tests\Feature\Webhook;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\RefundWebhookOutcome;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\PaymentDriverInterface;
use Modules\Payment\Exceptions\PaymentException;

/**
 * Minimal gateway stand-in: trusts a shared-secret header as its signature
 * check and maps the payload to a WebhookResult. A payload carrying a
 * `refund` object stands in for a refund-lifecycle event and routes to
 * the refund ledger.
 */
class TestWebhookDriver implements PaymentDriverInterface
{
    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        return new PaymentGatewayResult;
    }

    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        throw PaymentException::refundNotSupported();
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        if ($request->header('X-Test-Signature') !== 'secret') {
            throw PaymentException::webhookInvalid();
        }

        $refund = $request->input('refund');

        if (is_array($refund)) {
            return new WebhookResult(
                transaction_id: '',
                status: 'failed', // unused on the refund branch
                refund_outcome: new RefundWebhookOutcome(
                    reference: (string) ($refund['reference'] ?? ''),
                    gateway_refund_id: isset($refund['gateway_refund_id']) ? (string) $refund['gateway_refund_id'] : null,
                    gateway_payment_id: isset($refund['gateway_payment_id']) ? (string) $refund['gateway_payment_id'] : null,
                    status: (string) ($refund['status'] ?? ''),
                    amount_minor: isset($refund['amount_minor']) ? (int) $refund['amount_minor'] : null,
                    currency: isset($refund['currency']) ? (string) $refund['currency'] : null,
                ),
            );
        }

        return new WebhookResult(
            transaction_id: (string) $request->input('transaction_id'),
            status: (string) $request->input('status'),
            gateway_transaction_id: $request->filled('gateway_transaction_id')
                ? (string) $request->input('gateway_transaction_id')
                : null,
        );
    }
}
